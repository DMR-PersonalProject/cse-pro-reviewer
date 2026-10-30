# Tasks — CSE Reviewer (MVP)

Status: **approved** (M1 started 2026-10-30) · Implements `plan.md`.

**How to read this**
- `[ ]` means not started and `[x]` means done. A task is done only when its tests pass, `pint --test` is clean, and the box is ticked here.
- **Size:** S < half a day, M 1–3 days, L > 3 days. There are no L tasks; anything that large has been split.
- **Deps:** task IDs that must be done first. Within a milestone, the tasks run in the order listed.
- **Tests:** written first and failing before the code (constitution §3). AC-x refers to `spec.md`.
- Each milestone ends with **What to try** (your manual check) and a **STOP**. After the stop I summarize in under 10 lines and wait.
- **Git** (full rules in `CLAUDE.md` → Git workflow): each milestone runs on its own branch (`m1-foundation`, `m2-question-bank`, …). One Conventional Commit per task or small group, task ID in the scope, e.g. `feat(M2-04): add question model and factory`. Tests and Pint green before every commit. On `approved: start M(n+1)` the finished branch is merged into `main` (merge commit), tagged `m<n>-done`, and the next branch is created. No remote pushes without your say-so.
- **`CLAUDE.md` is local-only** (gitignored since 2026-10-30): never stage it and never rely on it being in the repo. Anything that must survive a fresh clone (versions, decisions that change the build) also goes into `specs/` or `SUGGESTIONS.md`, which stay tracked. Every milestone STOP includes a reminder to back up `CLAUDE.md` manually. Careful: checking out a commit older than `9654f68` (which still tracks it) silently overwrites the local file; back it up first.

---

## M1 — Foundation, roles and theming

- [x] **M1-01 Verify the toolchain** · S · Deps: —
  You install Herd (steps in `CLAUDE.md`). I check `php -v` (≥ 8.3), `composer -V`, `laravel --version`, `node -v` and `npm -v`, and record the versions in `CLAUDE.md`.
  *Tests:* none (environment). The outcome is the recorded version output.
- [x] **M1-02 git init** · S · Deps: — (done 2026-10-30, before M1 approval, at your request)
  `git init` on `main`; commits `docs: add project specs and memory files` then `chore: add .gitignore`. `.gitignore` covers `.env`, `vendor/`, `node_modules/`, `database/*.sqlite`, storage logs and compiled files, `content/inbox/`, `content/staged/` and uploaded media.
  *Tests:* `git status` is clean after the commit; `git check-ignore content/inbox/x.pdf` matches.
- [x] **M1-03 Create the Laravel project** · M · Deps: M1-01, M1-02 (Laravel 13, pinned in M1-04; http://cse.test returns 200)
  Commit on `m1-foundation` as its own commit: `chore: scaffold Laravel project` (before M1-04).
  Run `laravel new` in a temporary sibling folder (the installer needs an empty folder): no starter kit, Pest, SQLite. Then move the files into the repo root **without overwriting** `CLAUDE.md`, `SUGGESTIONS.md` or `specs/` (any generated AI-guideline files are reviewed and merged by hand). Set `APP_TIMEZONE=UTC`, add `config/cse.php` with `display_timezone = Asia/Manila`, and check `herd link cse` → http://cse.test.
  *Tests:* the default Pest suite passes (`php artisan test`); a test that the `cse.display_timezone` config is `Asia/Manila`.
- [x] **M1-04 Pin compatible versions** · S · Deps: M1-03
  Pick the latest Livewire and Filament majors that support the installed Laravel (suggestion #5), install `livewire/livewire`, `filament/filament`, `pestphp/pest-plugin-livewire`, Tailwind and the typography plugin, and record the exact versions in plan §1.3 (tracked) and `CLAUDE.md` (local copy).
  *Tests:* the app boots; `GET /admin/login` returns 200.
- [x] **M1-05 Tooling and arch baseline** · S · Deps: M1-04
  `pint.json` (Laravel preset); composer scripts `test`, `lint` (`pint --test`), `fix`; the first `tests/Arch` rules: no `env()` outside `config/`, no `dd`/`dump`/`ray`, strict types in `app/Domain`.
  *Tests:* the arch suite passes; `composer lint` exits 0.
- [x] **M1-06 Users schema and model** · M · Deps: M1-05
  Change the default users migration to add `username`, `ign`, `ign_normalized`, `full_name`, nullable `email` and `password`, `role` (default `user`), `is_active`, `pin_set_at`. Add the `Role` enum, casts (`password` hashed), `isAdmin()`, `hasPin()`, a mutator that lowercases `username` and sets `ign_normalized`, and factory states `admin()`, `withPin()`, `inactive()`.
  *Tests:* duplicate usernames that differ only in case are rejected by the DB (AC-2 at DB level); IGN uniqueness also ignores case; two users with null email are allowed; `hasPin()` is false for null password.
- [x] **M1-07 Settings store** · M · Deps: M1-05
  `settings` migration, a `SettingKey` enum (key, type, default, validation rules) for all FR-C1 settings, a `Settings` service (typed getters, cache, cache cleared on write), and `SettingsSeeder`.
  *Tests:* defaults are returned when no row exists; set then get returns the right type; out-of-range values are rejected (for example a similarity of 0.3, or 0 lockout attempts); the cache is cleared on update; the item split defaults add up to 170.
- [x] **M1-08 Audit log** · S · Deps: M1-06
  `audit_logs` migration and model, plus an `Audit::record($action, $subject, $reason, $meta)` helper.
  *Tests:* a call writes one row with the acting user and the subject's type and ID.
- [x] **M1-09 Registration** · M · Deps: M1-06
  The `RegisterUser` action plus the `Auth\Register` component at `/start` (username, IGN, full name, optional PIN with confirmation) and a privacy summary.
  *Tests:* AC-1, AC-2; validation (length, character set, PIN must be 4–6 digits); the PIN is stored hashed and `pin_set_at` is set; the user is signed in and redirected to the dashboard.
- [x] **M1-10 Sign-in and throttle** · M · Deps: M1-07, M1-09
  `SignInWithUsername`, `LoginThrottle` (limits from settings) and the `Auth\SignIn` component at `/login`. Admins are refused on this path. Inactive users are refused.
  *Tests:* AC-3, including after changing `lockout_max_attempts`; a user without a PIN signs in with username only; an admin username is refused here; an inactive user is refused; the session is regenerated.
- [x] **M1-11 Profile and PIN management** · S · Deps: M1-10
  `SetPin`, `RemovePin` (needs the current PIN) and the `Profile\Edit` component.
  *Tests:* set, change and remove a PIN; removing it with the wrong PIN fails; the IGN can't be edited by a user.
- [x] **M1-12 Admin access and roles** · M · Deps: M1-08, M1-10
  A Filament panel at `/admin` (`canAccessPanel` = active admin), indigo primary color, dark mode on; `EnsureUserIsActive` middleware; base `UserPolicy`; the `cse:make-admin` command (prompts, no defaults).
  *Tests:* AC-4; an admin reaches the dashboard; an inactive admin is refused; the command creates an admin with a hashed password and refuses a duplicate email; a deactivated user mid-session is logged out on the next request.
- [x] **M1-13 Layout and theming** · M · Deps: M1-04
  An app layout with skip link, header and nav; CSS tokens for light and dark; the Tailwind dark variant by class; server-side `dark` class from the cookie; the blocking inline head script for `system`; a `theme-toggle` component (light/dark/system) that writes the cookie and localStorage using the same key as Filament.
  *Tests:* with the cookie `theme=dark`, `<html>` has `class="dark"`; with `theme=light` it doesn't; the inline script appears before the stylesheet link; the toggle has an accessible name and pressed state. Manual: no flash (What to try).
- [x] **M1-14 Base pages** · S · Deps: M1-09, M1-13
  A dashboard stub (with the PIN nudge when there's no PIN), `/privacy` with the spec §8 text, and a 403/404 page in the app layout.
  *Tests:* guests are redirected from `/` to `/start`; the PIN nudge shows only without a PIN; `/privacy` lists what's stored and what's public.
- [x] **M1-15 (optional) Set up GitHub remote and push** · S · Deps: your say-so (done 2026-10-30: https://github.com/DMR-PersonalProject/cse-pro-reviewer, private; `main` + `m1-done` pushed)
  Only when you ask: create a **private** repo `cse-pro-reviewer` with `gh repo create cse-pro-reviewer --private --source . --remote origin` (or manual steps if `gh` isn't available), push `main` and all tags. From then on, push only after each milestone approval.
  *Tests:* `git remote -v` shows `origin`; `gh repo view cse-pro-reviewer --json visibility` reports `PRIVATE`.
  *Note:* `CLAUDE.md` is gitignored, so a fresh clone won't have it. You'd recreate it from `specs/` (constitution, plan, tasks) and the decisions in `clarifications.md`/`SUGGESTIONS.md`, or from your manual backup. Before pushing, decide about the old `CLAUDE.md` copy still in commit `d90dcc1` (history is not rewritten unless you say so). Optional: add `docs/CLAUDE.example.md`, a copy with no private details, as a template in the repo. Only created if you ask.

**What to try (M1)**
- [ ] Open http://cse.test, register with a username, IGN and full name and no PIN; you land on the dashboard and see the PIN nudge.
- [ ] Set a PIN on the profile, sign out, and sign in with the wrong PIN 6 times; you get throttled.
- [ ] Run `php artisan cse:make-admin`, sign in at `/admin`, and check that a normal user gets a 403 there.
- [ ] Switch light, dark and system; reload each one; there's no white flash in dark mode; `/admin` follows the same theme.
- [ ] Tab through the start page: the skip link is visible and focus is always visible.
- [ ] `composer test` and `composer lint` are green.

**⏸ STOP — wait for `approved: start M2`.** Reminder at this stop: back up your local `CLAUDE.md` manually (it is gitignored).

---

## M2 — Question bank and admin

- [x] **M2-01 Taxonomy** · S · Deps: M1 done
  `subtests` and `topics` migrations and models, plus `TaxonomySeeder` (4 subtests, 17 topics, fixed codes and positions).
  *Tests:* 4 subtests and 17 topics with the expected codes; the seeder is idempotent.
- [x] **M2-02 Content enums** · S · Deps: M2-01
  `QuestionStatus`, `SourceVisibility`, `SourceType`, `Difficulty`, `Language`, `NeedsReviewReason` (each with a label).
  *Tests:* the allowed status transitions are listed on the enum (`canTransitionTo`) and unit-tested against FR-Q2.
- [x] **M2-03 Content schema and models** · M · Deps: M2-02
  Migrations, models, factories and relations for `sources`, `passages`, `questions`, `choices`, `tags`/`question_tag` and `media` (polymorphic), with the indexes from plan §3.2.
  *Tests:* a new source defaults to `private`; a question's choices are ordered by `position`; a passage's questions are ordered by `passage_position`; deleting a question deletes its choices; factory states `published()`, `private()`, `inPassage()`.
- [x] **M2-04 Markdown rendering** · S · Deps: M2-03
  A safe Markdown renderer (`html_input: strip`, no unsafe links) and a Blade component used by stems, passages and explanations. Supports tables.
  *Tests:* `<script>` is stripped; `javascript:` links are removed; a Markdown table renders as `<table>`.
- [x] **M2-05 Media validation and storage** · M · Deps: M2-03
  `MediaValidator` (real MIME via `finfo`, PNG/JPG/WebP only, ≤ 2 MB, alt text required) and an `AttachMedia` action (random name, `public` disk, `media/`).
  *Tests:* PNG, JPG and WebP accepted; SVG rejected; a `.png` file that is really SVG or HTML rejected; 2.1 MB rejected; missing alt text rejected (the editor side of AC-21a).
- [x] **M2-06 Normalizer and duplicate detector** · M · Deps: M1-07, M2-03
  `StemNormalizer` and `DuplicateDetector` (same subtest, ±30% length prefilter, `similar_text`, threshold from settings).
  *Tests:* normalization cases; a 95% match is flagged and a 70% match isn't at the default 0.90; changing the threshold setting changes the result; other subtests are ignored.
- [x] **M2-07 Publish and archive** · M · Deps: M1-08, M2-05
  The `PublishQuestion` (gates from FR-Q2), `ArchiveQuestion` and `UnarchiveQuestion` actions, with audit entries.
  *Tests:* AC-24; publishing is blocked with no correct choice, with media missing alt text, or with a passage that has no body and no media; archive followed by publish works; each action writes an audit row.
- [x] **M2-08 SourceResource** · S · Deps: M2-03, M1-12
  A Filament CRUD with a visibility toggle and a count of items.
  *Tests:* an admin can list, create and edit; a user gets a 403; a new source defaults to private.
- [x] **M2-09 QuestionResource: form and table** · M · Deps: M2-04, M2-05, M2-06
  Form fields: subtest, topic, passage, stem, a choices repeater (2–5, exactly one correct), explanation, difficulty, language, tags, media upload with alt text, provenance, `lock_choice_order`. Table filters: subtest, topic, status, source, visibility, needs-review, possible duplicate. A duplicate warning appears on save.
  *Tests:* create and edit via Filament Livewire tests; saving with 0 or 2 correct choices fails; topic options depend on the subtest; the duplicate warning appears for a near-identical stem.
- [x] **M2-10 QuestionResource: actions** · S · Deps: M2-07, M2-09
  Publish, archive and unarchive (single and bulk), and a needs-review panel that lists reasons with a "Resolve" action per reason.
  *Tests:* a bulk publish skips blocked items and reports why; resolving the last reason lets the item be published.
- [x] **M2-11 Hard delete with typed confirmation** · S · Deps: M2-10
  `DeleteQuestion` and `DeleteSource` (the source together with its items). The confirmation modal requires typing the item ID or the source name, and an audit entry is written. The purge option arrives in M4-17, once snapshots exist.
  *Tests:* the wrong confirmation text refuses; the right text deletes the item, its choices and its media rows; deleting a source removes all its items; an audit row is written.
- [x] **M2-12 PassageResource** · M · Deps: M2-09
  Passage CRUD with a relation manager for its questions (ordered, drag to reorder).
  *Tests:* create a passage with 3 questions; reordering updates `passage_position`; the questions inherit the passage's subtest and topic.
- [x] **M2-13 UserResource** · M · Deps: M1-12, M1-08
  A list (username, IGN, full name, role, active, has PIN) with actions: change IGN, change role, deactivate/activate, reset PIN, delete (typed confirmation, cascades). Every action is audited.
  *Tests:* each action works and is audited; an admin can't demote or deactivate themselves if they are the last active admin.
- [x] **M2-14 Exam settings page** · M · Deps: M1-07
  A Filament page for every FR-C1 setting, with the **"unverified"** badge on the item split, a live total, validation and an audit entry on save.
  *Tests:* invalid values are rejected; the total is shown; the "unverified" label is rendered; saving writes the audit entry and clears the cache.
- [x] **M2-15 Audit log viewer and review queue widget** · S · Deps: M2-10
  A read-only `AuditLogResource`, and a dashboard widget showing counts of drafts, needs-review items and possible duplicates.
  *Tests:* the resource is read-only (no create or edit routes); the widget counts match the factory data.
- [x] **M2-16 Sample content seeder** · M · Deps: M2-12, M2-05
  `SampleContentSeeder`: at least 2 **original** items for each of the 17 topics, including a reading passage with 3 sub-items, a data-interpretation item with a Markdown table, an abstract-reasoning item with a PNG figure (alt text included), and Filipino word-meaning items. All published, under the public source "Original — seed".
  *Tests:* each topic has ≥ 2 published public items; the passage has 3 items; every media file exists and has alt text; every item has a correct choice and an explanation.
- [ ] **M2-17 Current PIN to change a PIN (suggestion #17)** · S · Deps: M1-11
  `SetPin` requires the current PIN when the user already has one; setting a first PIN doesn't. `Profile\Edit` shows a "Current PIN" field on the change form. FR-I2 is updated to match.
  *Tests:* changing a PIN with the right current PIN works; a wrong or empty current PIN is refused and the PIN is unchanged; setting a first PIN needs no current PIN.

**What to try (M2)**
- [ ] Run `php artisan migrate:fresh --seed`, open `/admin`, and browse questions with each filter.
- [ ] Create a question with 4 choices and an image; try uploading an SVG and an oversized PNG (both rejected).
- [ ] Try to publish an item with no correct answer (blocked, with the reason shown).
- [ ] Create a near-duplicate of a seeded stem (warning shown).
- [ ] Edit the item split in settings: "unverified" is visible and the total updates.
- [ ] Hard-delete a test question: typing the wrong ID is refused; then check the audit log.
- [ ] Switch the admin panel to dark mode and back.

**⏸ STOP — wait for `approved: start M3`.** Reminder at this stop: back up your local `CLAUDE.md` manually (it is gitignored).

---

## M3 — Content import pipeline

- [ ] **M3-01 Content folder and docs** · S · Deps: M2 done
  Create `content/{inbox,staged,staged/media,schema}`, `content/README.md` (naming, what to send, what Claude does with each type of file, the never-guess rule, privacy and copyright note), `schema/question-import.schema.json`, `schema/example.json` and `schema/example.csv`.
  *Tests:* none yet; M3-03 and M3-04 validate both example files.
- [ ] **M3-02 Import tables** · S · Deps: M3-01 (also adds `questions.import_batch_id`, deferred from M2-03)
  Migrations and models for `import_batches` and `import_rows`.
  *Tests:* rows cascade with their batch; `decision` defaults to `pending`.
- [ ] **M3-03 Parser** · M · Deps: M3-02
  `ImportParser` with JSON (schema v1, passages with `ref`) and CSV (header check, `|` tags) variants that produce normalized row arrays.
  *Tests:* both example files parse; malformed JSON, a wrong `schema_version` and a missing CSV header give a file-level error; row numbers are kept.
- [ ] **M3-04 Row validator** · M · Deps: M3-03, M2-05
  `RowValidator` covering every error in plan §8.3, including media type and size checked through `MediaValidator`.
  *Tests:* AC-21 (1 choice), AC-21a (SVG and > 2 MB), unknown topic, an answer that doesn't match a choice, an unknown `passage_ref`, a duplicate `ref`; the example files have 0 errors.
- [ ] **M3-05 Row flagger** · M · Deps: M3-04, M2-06
  `RowFlagger`: missing key, missing explanation, low confidence (< 0.8), media missing, missing alt text, possible duplicate (against the bank **and** within the batch), and flags set by the extractor.
  *Tests:* AC-20 (no correct choice is set); AC-22; a duplicate within the batch; each flag maps to a `NeedsReviewReason`.
- [ ] **M3-06 Create batch** · S · Deps: M3-05
  `CreateImportBatch`: parse, validate, flag, and store the batch and rows with counts.
  *Tests:* the counts are correct; nothing is written to `questions` at this stage.
- [ ] **M3-07 Approve and reject rows** · M · Deps: M3-06, M2-07
  `ApproveImportRows` (one transaction per call: source defaulting to private, passages, questions, choices, tags, media, provenance; status `draft` or `needs_review`) and `RejectImportRows`.
  *Tests:* AC-23; rows with errors can't be approved; an approved row links to its question; a missing answer key leads to `needs_review` with no `is_correct` choice; nothing is ever `published` by an import; a rollback on failure leaves no partial records.
- [ ] **M3-08 `cse:import` command** · M · Deps: M3-07
  `php artisan cse:import {path} {--dry-run} {--approve-valid}`. Media is read from `content/staged/`. It prints a summary table of errors and flags for each row.
  *Tests:* a dry run writes nothing; a normal run creates a pending batch; `--approve-valid` creates draft items only; media files are copied and validated; the exit code is non-zero on a file-level error.
- [ ] **M3-09 ImportBatchResource and ImportWizard** · M · Deps: M3-07
  A Filament page: upload JSON or CSV, then a preview table (row number, stem excerpt, errors, flags, duplicate link), then select rows and approve or reject. Media rows get the flag "attach in editor". The batch list shows status and counts.
  *Tests:* upload through a Livewire test; error rows can't be selected; approve and reject update the counts; a user gets a 403.

**What to try (M3)**
- [ ] Read `content/README.md` and tell me whether the naming rules suit how you gather files.
- [ ] Run `php artisan cse:import content/schema/example.json --dry-run`, then without `--dry-run`.
- [ ] In `/admin`, upload `example.csv`, check the errors on a broken row, approve the valid rows, and confirm they arrive as draft or needs-review under a **private** source.
- [ ] Optional: drop a small real file in `content/inbox/`, ask me to extract it, and walk the whole flow.

**⏸ STOP — wait for `approved: start M4`.** Reminder at this stop: back up your local `CLAUDE.md` manually (it is gitignored).

---

## M4 — Individual tests

- [ ] **M4-01 Attempt schema** · M · Deps: M3 done
  Migrations, models, factories and enums for `attempts`, `attempt_answers` (`question_id` **nullOnDelete**), `attempt_results` and `attempt_subtest_scores`; `AttemptPolicy`.
  *Tests:* deleting a question nulls `question_id` and keeps the snapshot; the policy lets the owner update an in-progress attempt only; an admin can view.
- [ ] **M4-02 Item pool** · M · Deps: M4-01
  `ItemPool`: published items only, private sources only for admins, subtest and topic filters, and the fresh/seen split over the last N attempts (from settings).
  *Tests:* draft, needs-review and archived items are excluded; a private item is visible to an admin but not a user; a repeat is avoided when alternatives exist and used, oldest first, when they don't.
- [ ] **M4-03 Assembler (individual)** · M · Deps: M4-02
  `TestAssembler` for one subtest: selection units, an even topic split with shortfall fill, passage fitting, a seeded shuffle, and a shortfall report.
  *Tests:* AC-5 (20 distinct items from the topic); AC-9 (passage items together and in order); the same seed gives the same order; the shortfall is reported correctly.
- [ ] **M4-04 Choice shuffler and snapshot builder** · S · Deps: M4-03
  `ChoiceShuffler` (honors `lock_choice_order`, assigns A–E) and `SnapshotBuilder`.
  *Tests:* AC-11; the snapshot holds the stem, passage, choices in shown order, explanation and source visibility; `correct_key` matches the shuffled position.
- [ ] **M4-05 Clock and scorer** · S · Deps: M4-01
  `AttemptClock` (remaining time, grace, guard) and `Scorer` v1 (GR, subtest and topic percentages, pass check in integer hundredths).
  *Tests:* AC-13 (136/170 → 80.00 pass, 135/170 → 79.41 fail); an unanswered item counts as wrong; `remainingMs` is never negative; grace boundaries at exactly +10 s and +11 s.
- [ ] **M4-06 Start attempt** · M · Deps: M4-04, M4-05
  `StartAttempt`: duration ⌈190 × n ÷ 170⌉, `config` snapshot, `has_private_items`, `is_partial`, `is_standard`, all answer rows in one transaction, and the one-active-attempt guard (user row lock).
  *Tests:* AC-5 (23-minute deadline); a second start while one is in progress is refused with a "resume or abandon" result; settings changed after the start don't affect the attempt (FR-C2); `is_standard` is true only for a full-length, unfiltered, timed test.
- [ ] **M4-07 Answer actions** · S · Deps: M4-06
  `SaveAnswer`, `ToggleFlag`, `AbandonAttempt`, `RevealStudyAnswer` (in study mode, select, lock and reveal happen in one step).
  *Tests:* a save after deadline plus grace is rejected; saving to someone else's attempt is refused; in study mode, re-selecting a revealed item is refused (AC-10); an abandoned attempt can't take answers.
- [ ] **M4-08 Finalize** · M · Deps: M4-07
  `FinalizeAttempt` (transaction, `lockForUpdate`, idempotent, results plus subtest scores, time used, suspicious flag from config), `FinalizeExpiredAttempts::forUser()`, and the `cse:finalize-expired` command plus its schedule.
  *Tests:* AC-7; AC-8 (two calls → one result, same payload); the time used is capped at the deadline; the suspicious flag is set under the threshold and not set when the threshold is 0; the command finalizes only expired timed attempts.
- [ ] **M4-09 Configure individual test** · M · Deps: M4-06
  `Tests\ConfigureIndividual` at `/practice`: subtest, topics, count, timing; available-count and duration preview; a shortfall offer.
  *Tests:* the preview shows 23 min for 20 items; a count above what's available offers the shorter test; study and untimed show no duration.
- [ ] **M4-10 Runner: server side** · M · Deps: M4-08
  `Attempt\Runner`: `#[Locked]` ID, the current item as `#[Computed]`, actions (`select`, `toggleFlag`, `goTo`, `next`, `prev`, `submit`, `timeUp`), guard on every action, and the navigator array.
  *Tests:* **AC-10a** (HTML and Livewire payload checked for timed, untimed and study attempts); a user can't open someone else's attempt (403); an early `timeUp()` is ignored and the correct `remainingMs` is returned; an expired attempt redirects to results on any action; AC-10.
- [ ] **M4-11 Runner: UI components** · M · Deps: M4-10, M2-04
  Blade components for the stem, passage (two-column at large widths), choices (`fieldset` with radios), media, scrolling data tables, the navigator panel, and the save indicator.
  *Tests:* the choices render as a radio group with labels; a selected choice is checked after reload; the table wrapper has `role="region"` and a label; images have alt text.
- [ ] **M4-12 Keyboard store** · S · Deps: M4-11
  An Alpine `keyboard` store: `1–5`/`A–E`, `←/→`, `F`, `N`; ignored while typing in inputs; focus moves to the stem on navigation.
  *Tests:* AC-12 at the server level (selecting `B` saves the second shown choice); keys are listed in the help dialog markup. Manual keyboard check in What to try.
- [ ] **M4-13 Timer** · M · Deps: M4-10
  An Alpine `clock` store using `remainingMs` from each response and `performance.now()`; live announcements at 10 and 1 minutes; `timeUp()` at 0; `wire:poll.60s` resync; untimed shows elapsed time.
  *Tests:* AC-6 (travel forward 5 minutes, reload: the remaining time comes from the server); the live region exists with `aria-live="polite"`.
- [ ] **M4-14 Autosave queue and offline** · M · Deps: M4-13
  An Alpine queue: optimistic UI, a localStorage stash, retry with backoff, an offline banner, and replay on load.
  *Tests:* a replayed answer within grace is saved; one past grace is rejected, and the results page shows the "not saved" count. Manual offline check.
- [ ] **M4-15 Minimal results page** · S · Deps: M4-08
  `Results\Show` (first version): GR or %, pass/fail or "target 80%", time used, a link to review (review itself comes in M6).
  *Tests:* the owner sees it; others get a 403; an in-progress attempt redirects to the runner.
- [ ] **M4-16 Dashboard resume and abandon** · S · Deps: M4-09, M4-15
  A resume card with the time left; a "resume or abandon" prompt when starting a new test; lazy finalization on load.
  *Tests:* an expired attempt is finalized when the dashboard loads; abandoning sets the status and frees the slot.
- [ ] **M4-17 Delete with purge** · M · Deps: M2-11, M4-01 (also: M2-11 deletes media rows but keeps image files; add the cleanup that deletes files no snapshot references, and removes referenced ones only on purge)
  Add "Also remove this content from past attempts" to `DeleteQuestion` and `DeleteSource` (FR-Q5a): replace the snapshot content, set `purged_at`, remove media files referenced only by snapshots, and record an audit entry.
  *Tests:* AC-15 at the data level (the score is unchanged, the content is a placeholder, `purged_at` is set); without purge the snapshot stays intact; the audit `meta` records whether a purge was done.

**What to try (M4)**
- [ ] Start a 20-item timed Numerical test, check that the timer shows 23:00, answer a few items, refresh: same item, answers kept, the timer continues.
- [ ] Use only the keyboard: `B`, `→`, `F`, `N`, Enter.
- [ ] Turn off Wi-Fi, answer 2 items (the offline banner appears), turn it back on (the answers sync).
- [ ] Study mode: answer item 1 (the answer is revealed); view the page source: no answers for later items.
- [ ] Close the tab on a short timed test, wait past the deadline, open the dashboard: it is finalized as timeout.
- [ ] Try starting a second test while one is in progress: you get the resume-or-abandon prompt.

**⏸ STOP — wait for `approved: start M5`.** Reminder at this stop: back up your local `CLAUDE.md` manually (it is gitignored).

---

## M5 — Combined mock exam

- [ ] **M5-01 Assembler (combined)** · M · Deps: M4 done
  Quotas per subtest from the attempt's `config`, fixed subtest order, an even topic split with shortfall fill within the subtest, and passage fitting.
  *Tests:* with a full bank, 170 items split 50/40/40/40 in V→N→A→GI order; a changed split setting is reflected in new mocks only; within each subtest the topics are balanced ±1.
- [ ] **M5-02 Partial mock** · S · Deps: M5-01
  A bank shortfall reports the counts for each subtest; the user can start a partial mock (`is_partial`, never standard).
  *Tests:* FR-G7; a partial mock is not standard and the flag is stored; the preview message lists the counts for each subtest.
- [ ] **M5-03 Configure mock** · S · Deps: M5-02
  `Tests\ConfigureMock` at `/mock`: timed (one 190-minute clock) or untimed, the split shown with an "unverified" label, availability, and the partial warning.
  *Tests:* timed sets a 190-minute deadline; no time limit for each subtest exists; the "unverified" label is rendered.
- [ ] **M5-04 Navigator for 170 items** · M · Deps: M5-03
  Grouped by subtest with counts (answered / flagged / total), a "flagged only" and "unanswered only" filter, and jumping by click or Enter; usable on mobile (drawer).
  *Tests:* the navigator data has 170 entries with the right subtest groups; `goTo` works across subtests; the payload has no answer data (AC-10a check reused).
- [ ] **M5-05 Submit confirmation** · S · Deps: M5-04
  A dialog with the unanswered and flagged counts and buttons to go to the first unanswered or first flagged item.
  *Tests:* the counts are right; confirming finalizes the attempt; cancelling leaves it in progress.
- [ ] **M5-06 Assembly performance** · S · Deps: M5-01
  A factory-built bank of 5,000 items; assembling 170 takes ≤ 2 s (NFR-2). Fix indexes or queries if not.
  *Tests:* a performance test in the `perf` group (excluded from the default run, run before closing M5).

**What to try (M5)**
- [ ] Start a timed mock: 190:00 on the clock, 170 items, subtests in order.
- [ ] Flag 3 items, use the "flagged only" filter, and jump between them.
- [ ] Submit with items unanswered: the dialog counts them correctly.
- [ ] Change the split in admin settings, start a new mock, and check the new counts (then change it back).

**⏸ STOP — wait for `approved: start M6`.** Reminder at this stop: back up your local `CLAUDE.md` manually (it is gitignored).

---

## M6 — Results, review, progress and leaderboard

- [ ] **M6-01 Full results page** · M · Deps: M5 done
  GR, pass/fail, subtest and topic tables, time used, and the **eligibility reason** ("Not on leaderboard: study mode", "…set a PIN", "…partial", "…private items").
  *Tests:* the breakdown matches the stored snapshot; each eligibility reason renders for its case.
- [ ] **M6-02 Answer review** · M · Deps: M6-01
  `Results\Review` from snapshots only, with all/wrong/flagged/unanswered filters as `#[Url]` parameters, paginated, and notes for "removed from bank" and "Content removed (takedown)".
  *Tests:* AC-14; AC-15 at the UI level (both the deleted and the purged case); filters return the right items.
- [ ] **M6-03 Progress page** · M · Deps: M6-01
  History (date, mode, subtest, score, status) and accuracy per topic across completed attempts, including those that reference deleted questions.
  *Tests:* the topic accuracy maths over factory attempts; abandoned attempts are excluded from accuracy and listed as abandoned.
- [ ] **M6-04 Eligibility scope** · M · Deps: M6-01
  `Eligibility` as a query scope plus a per-attempt reason resolver (FR-L2).
  *Tests:* AC-16 (setting OFF, then ON, then OFF), AC-16a (PIN), **AC-16c (study mode never)**, untimed never, partial never, hidden never, inactive user never, non-standard length never.
- [ ] **M6-05 Leaderboard query** · M · Deps: M6-04
  `LeaderboardQuery` for the full-mock board and each subtest board, with all-time and weekly periods (Asia/Manila week), best attempt per user via `ROW_NUMBER()`, and tie-breakers.
  *Tests:* AC-17, AC-18; the subtest board uses `attempt_subtest_scores`; the selected columns don't include `full_name`; the arch rule allows raw SQL only in this class.
- [ ] **M6-06 Leaderboard page** · S · Deps: M6-05
  `Leaderboard\Index`, public, with the board and period in the URL and an empty state.
  *Tests:* AC-19 (no full name in the HTML); switching board and period works; a guest can view it.
- [ ] **M6-07 Moderation** · M · Deps: M6-04
  `AttemptResource`: read-only details, a "suspicious" filter and badge, and hide/unhide with a required reason and an audit entry.
  *Tests:* AC-16b (a suspicious attempt is still listed until hidden); hiding without a reason fails; hide then unhide restores the entry; both write audit rows; a user gets a 403.

**What to try (M6)**
- [ ] Finish a mock and read the results and review; filter to wrong answers.
- [ ] Edit one of the questions you answered in admin, then reopen the review (it is unchanged).
- [ ] On the leaderboard, check that you aren't listed without a PIN; set a PIN; you appear.
- [ ] Do a study-mode attempt (it never appears).
- [ ] Turn on "Include private sources in leaderboard" with your own material, see your rank, then turn it off.
- [ ] Hide an entry in admin with a reason, check it's gone, then check the audit log.

**⏸ STOP — wait for `approved: start M7`.** Reminder at this stop: back up your local `CLAUDE.md` manually (it is gitignored).

---

## M7 — Polish, accessibility and portability

- [ ] **M7-01 Contrast tests** · S · Deps: M6 done
  A unit test that reads the token values and checks AA contrast (4.5:1 for text, 3:1 for UI and focus) in both themes. Fix any failing tokens.
  *Tests:* the contrast test itself.
- [ ] **M7-02 Keyboard and screen reader pass** · M · Deps: M7-01
  A full run with keyboard only and with NVDA (Windows) on start → test → submit → results → review → leaderboard. Fix focus order, labels and announcements, and record findings in `specs/a11y-checklist.md`.
  *Tests:* regression tests for each fix (labels, ARIA attributes, focus targets); browser tests if the Pest browser plugin is compatible (optional).
- [ ] **M7-03 Mobile and 320 px pass** · S · Deps: M7-02
  Check every page at 320, 375 and 768 px; no horizontal scroll except inside tables; touch targets ≥ 24 px.
  *Tests:* none automated beyond markup checks; findings recorded in the checklist.
- [ ] **M7-04 Empty, error and edge states** · S · Deps: M7-02
  No published items, an empty bank for a topic, offline, 403/404/419 (session expired during a test → re-login keeps the attempt), import with 0 valid rows.
  *Tests:* each state renders its message; after a 419 the user can sign back in and resume the same attempt.
- [ ] **M7-05 Performance check** · S · Deps: M7-02
  Seed 5,000 items and 200 attempts; measure moving between items (≤ 200 ms), autosave (≤ 300 ms), assembly (≤ 2 s) and leaderboard; fix N+1 queries and add missing indexes.
  *Tests:* `perf` group thresholds; a query-count assertion on the runner render (no N+1).
- [ ] **M7-06 Portability run (#15)** · M · Deps: M7-05
  Run the full suite once on MySQL 8 and once on PostgreSQL 15+ (Herd services, a portable install, or Docker, whichever is simplest at the time). Record the results and fix any engine-specific failures. **Required before any deploy.**
  *Tests:* the full suite is green on SQLite, MySQL and PostgreSQL; the run is recorded in the `CLAUDE.md` Decisions log.
- [ ] **M7-07 Docs final** · S · Deps: M7-06
  Finalize the `CLAUDE.md` commands and versions, add a `README.md` (setup, first admin, import workflow), and mark all done tasks.
  *Tests:* a fresh clone followed by the README steps reaches a working app (manual).

**What to try (M7)**
- [ ] Do one full mock using only the keyboard, then with NVDA on.
- [ ] Open the app on your phone (same Wi-Fi, or `herd share` if available) and take a short test.
- [ ] Let your session expire during a test, sign back in, and resume.
- [ ] Read the portability results in `CLAUDE.md`.

**⏸ STOP — MVP complete. Wait for your review; next phases come from `SUGGESTIONS.md`.** Reminder: back up your local `CLAUDE.md` manually (it is gitignored).
