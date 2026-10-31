# Spec — CSE Reviewer (MVP)

Status: **approved 2026-10-27, with the changes from review applied** · Governed by `constitution.md` · Open points live in `clarifications.md`.
Assumptions in this spec are tagged **[A]**; each will be listed in `clarifications.md`.

## 1. Glossary
- **Subtest**: Verbal, Numerical, Analytical or General Information.
- **Topic**: a subdivision of a subtest, for example Number sequences.
- **Item**: one multiple-choice question. A **passage** is a shared stem, such as a reading text or a chart, with several items under it.
- **Attempt**: one run of a test by one user. Its **mode** is `individual` or `combined`, and its **timing** is `timed`, `untimed` or `study`.
- **Snapshot**: a copy of an item, made when an attempt is created, that results and review read from.
- **General rating (GR)**: total correct ÷ total items × 100, rounded to 2 decimals. Formula version `v1`.
- **Eligible attempt**: an attempt that may appear on the leaderboard (see FR-L2).

## 2. Roles
| Role | How they sign in | Can do |
|---|---|---|
| `user` | Username plus an optional PIN | Take tests, see own results and history, appear on the leaderboard under their IGN |
| `admin` | Email plus password (Filament, `/admin`) | Everything a user can, plus question bank, sources, imports, users, settings and leaderboard moderation, and practice with private items |

Roles are a `role` column backed by an enum and enforced through policies and gates. Adding a role means adding an enum case and policy rules, with no schema change.

## 3. User stories
**Onboarding and identity**
- US-1. As a new user, I enter a username, an IGN and my full name so I can start practicing in under 30 seconds.
- US-2. As a user, I can set an optional 4–6 digit PIN so no one else can use my username.
- US-3. As a returning user, I sign in with my username, and my PIN if I set one.
- US-4. As an admin, I sign in with email and password to reach the admin panel.

**Taking tests**
- US-5. As a user, I take an individual test: one subtest, optional topics, a number of items, and timed, untimed or study timing.
- US-6. As a user, I take a full combined mock exam: 170 items, 3h10m, with a navigator and flags.
- US-7. As a user, I can refresh, close the tab or lose connection and resume where I left off.
- US-8. As a user, I answer and move between items with the keyboard alone.
- US-9. As a user in study mode, I see the correct answer and explanation right after answering each item.

**Results and progress**
- US-10. As a user, I see my GR, pass or fail against 80.00, and a breakdown by subtest and topic after submitting.
- US-11. As a user, I review every item with my answer, the correct answer and the explanation, and can filter to wrong, flagged or unanswered items.
- US-12. As a user, I see my attempt history and my accuracy per topic across attempts.

**Leaderboard**
- US-13. As a user, I see rankings for the full mock and for each subtest, all-time or this week, showing IGNs only.
- US-14. As an admin, I hide suspicious entries and record a reason.

**Content**
- US-15. As an admin, I create and edit questions, passages, choices, media and sources in the admin panel.
- US-16. As an admin, I upload a JSON or CSV import, see the errors for each row, preview it, and approve or reject items into draft.
- US-17. As an admin, I am warned about likely duplicates and items flagged `needs_review`, and I publish only after reviewing.
- US-18. As an admin, I archive questions, or hard-delete them with confirmation for a takedown, without breaking past results.
- US-19. As an admin, I edit exam settings (item split, time, passing grade). The split is labeled "unverified".

**Theme**
- US-20. As any visitor, I switch between light, dark and system theme. My choice is remembered, and the page never flashes the wrong theme.

## 4. Functional requirements

### 4.1 Identity (FR-I)
- FR-I1. **Username:** 3–20 characters `[a-z0-9_]`, stored lowercase, unique without regard to case. **IGN:** 3–20 characters (letters, digits, spaces, `_` and `-`), unique without regard to case, and an admin can change it. **Full name:** 2–100 characters, required, never shown publicly.
- FR-I2. The PIN is optional, 4–6 digits, hashed in `users.password`. It can be set, changed or removed from the profile; changing or removing it needs the current PIN, while setting a first PIN doesn't (#17, M2-17).
- FR-I3. Without a PIN, the username alone signs in. The profile shows a persistent notice recommending a PIN.
- FR-I4. Sign-in is rate-limited per username plus IP. The limits are settings: `lockout_max_attempts` (default 5) and `lockout_window_minutes` (default 15) **[A]**.
- FR-I5. `email` and `password` are nullable on `users`. Admins must have both, enforced in application code. Making them required for users later is a validation change, not a schema rewrite.
- FR-I6. Admins can deactivate a user (they can't sign in and are hidden from the leaderboard), reset a PIN, change an IGN and change a role. There is no self-service account deletion in the MVP; an admin can delete a user, which cascades to that user's attempts **[A]**. Details (M2-13): promoting asks for an email and an admin password, which replace any PIN; demoting clears the password (no PIN until the owner sets one). Resetting a PIN clears it (user accounts only, after a confirmation). An admin can't change their own role or delete themselves, and the last active admin can't be demoted, deactivated or deleted. Role changes, deactivation and PIN resets sign the account out everywhere. Every action is audited, and no PIN, password or hash is ever logged.

### 4.2 Question bank (FR-Q)
- FR-Q1. Each item has: subtest, topic, an optional passage, stem text, 2–5 choices (4 by default) with exactly one correct, an explanation (nullable), difficulty (`easy|medium|hard`, nullable), language (`en|fil`), tags, media (PNG, JPG or WebP images only, max 2 MB each, with required alt text; **no SVG in MVP**), source plus provenance (file, page, notes), status, `needs_review_reasons[]`, and `lock_choice_order` (for "All of the above"-type choices).
- FR-Q2. Status flow: `draft → needs_review ↔ draft → published → archived`. `archived → published` is allowed. An item can't be published if it has no correct choice, has an unresolved needs_review reason, has media without alt text, or belongs to a passage that has no text and no media.
- FR-Q3. Passages hold text and/or media (tables as Markdown or HTML tables, charts as images with alt text or a data table). An item in a passage inherits its subtest and topic.
- FR-Q4. Sources have a name, type (`reviewer|book|website|original|other`), visibility (`private|public`, **default private**) and notes. Only admins can see items from private sources.
- FR-Q5. Hard delete needs the admin to type the item ID (or the source name, when deleting a source together with its items). Attempt answers keep their snapshots, and `question_id` becomes null.
- FR-Q5a. **Purge snapshots (takedown):** the delete dialog has an option "Also remove this content from past attempts", off by default. When it is on, the snapshot's stem, passage, choice text, explanation and media are replaced with a placeholder. Correctness, subtest, topic and all scores stay. Review shows "Content removed (takedown)". The purge is recorded in the audit log.
- FR-Q6. Duplicate detection: the stem is normalized (lowercase, collapsed whitespace, no punctuation) and compared in PHP against items in the same subtest. A similarity at or above the `duplicate_similarity_threshold` setting (default 0.90) is flagged as a "possible duplicate" with a link to the match **[A]**. It is a warning only and never blocks. Candidates are prefiltered by length: shorter ÷ longer ≥ min(0.7, t ÷ (2 − t)), i.e. ±30% unless the threshold t is low enough that ±30% could skip a real match (M2-06).
- FR-Q7. Seeder: at least 2 original sample items for each of the 17 topics, including one reading passage with 3 sub-items, one data interpretation table and one abstract-reasoning image. All are published and public, with the source "Original — seed".

### 4.3 Import pipeline (FR-M)
- FR-M1. Formats: JSON, which supports everything including passages and media, and CSV, for single items with no passage or media. The schema lives in `content/schema/` and is documented in `content/README.md`.
- FR-M2. Import in Filament: upload → each row is validated → a preview table shows errors per row → the admin approves or rejects each row or all valid rows → approved rows are created as `draft`, or as `needs_review` if any flag is set. Rows with errors can't be approved. An import batch records the file name, who uploaded it, when, and counts.
- FR-M3. The command `php artisan cse:import {path} {--dry-run}` runs the same validator and creates the same batch.
- FR-M4. A row becomes `needs_review` automatically when: there is no answer key, no explanation, a duplicate is suspected, extraction confidence is below 0.8, media is referenced but missing, or the source row says `needs_review`. The reasons are stored.
- FR-M4a. Media is validated by real file type (not just the extension) and size, both on admin upload and on import. A row that references SVG or any other unsupported or oversized file gets a row error.
- FR-M5. A missing answer key is never filled in by guessing, either by Claude during extraction or by the app.
- FR-M6. Every imported item gets a source. If none is given, the batch's default source is used. A source created by an import is always **private**, even if the file asks for public: the preview shows a warning and the request is audited, but the import doesn't fail. An existing source with the same name is reused unchanged; an import never changes a source's visibility.
- FR-M7. Raw files go in `content/inbox/` (gitignored) and extracted JSON in `content/staged/`. Claude never writes directly to the database from raw files; everything goes through staged JSON and validation.

### 4.4 Test generation (FR-G)
- FR-G1. **Individual:** the user picks a subtest, topics (any or all), an item count (min 5, max 100 or the number available, default 20) and a timing. Timed duration is ⌈total_minutes × count ÷ total_items⌉ minutes (190 × 20 ÷ 170 → 23 min).
- FR-G2. **Combined:** subtests appear in the fixed order Verbal → Numerical → Analytical → GI, with counts taken from settings (default 50/40/40/40). Timing is timed (one shared 190-minute clock for the whole mock, with **no per-subtest time limits**) or untimed. Within each subtest, items are spread evenly across topics, and any shortfall is filled from other topics in the same subtest **[A]**.
- FR-G3. **Pool:** published items only. Items from private sources are included only for admins.
- FR-G4. **Passages:** selected as a unit. All of a passage's items are kept together in their authored order. A passage is used only if it fits within the remaining count.
- FR-G5. **Shuffling:** item order is shuffled within each subtest (passage blocks move as a unit). Choice order is shuffled per attempt unless `lock_choice_order` is set. The displayed letters follow the shuffled order, and the order is stored on the attempt.
- FR-G6. **Avoiding repeats:** items the user answered in their last N attempts (setting, default 3) are left out when there are enough alternatives. Otherwise the least recently seen items are used first.
- FR-G7. **Not enough items:** if fewer than the requested number of published items exist, the user is shown how many are available and can start a shorter test. A shortened combined mock is labeled "partial" and is never eligible.
- FR-G8. **Snapshots:** when the attempt is created, every item is written as an attempt answer row with its snapshot (stem, passage, choices in shown order, correct choice, explanation, subtest, topic, source visibility).
- FR-G9. A user can have only one attempt in progress at a time. Starting a new one asks whether to resume or abandon the current one **[A]**. An abandoned attempt is kept in history as "abandoned" and is never scored for the leaderboard.

### 4.5 Taking a test (FR-T)
- FR-T1. One item per screen, with its passage shown beside it on wide screens or above it on narrow ones. Data tables scroll inside their own box.
- FR-T2. Keys: `1–5` or `A–E` select a choice, `←/→` move to the previous or next item, `F` toggles a flag, `N` opens the navigator. Every control can also be reached and used with Tab and Enter.
- FR-T3. The navigator shows each item as answered, unanswered, flagged or current, grouped by subtest, and jumps to an item on click or Enter. The individual mode has it too.
- FR-T4. **Autosave:** each selection is sent to the server straight away (with a short debounce of 500 ms or less). A small "Saved" or "Saving…" or "Offline" indicator shows the state. Nothing is lost on refresh.
- FR-T5. **Timer:** the server stores `started_at` and `deadline_at`, and the client shows the remaining time calculated from the server. At 10 and at 1 minute left there is a polite screen-reader announcement and a visual cue. Timed attempts have **no pause**, and the clock keeps running while the tab is closed, as in the real exam.
- FR-T6. **Server enforcement:** an answer received more than 10 seconds after `deadline_at` (grace setting **[A]**) is rejected. Any request that touches an expired attempt finalizes it first (lazy finalization). A scheduled command also sweeps expired attempts every minute, if a scheduler is running.
- FR-T7. **Submit:** a confirmation shows the counts of unanswered and flagged items. Finalizing runs in a transaction with a row lock, so it happens exactly once. Later calls return the existing result.
- FR-T8. **Study mode:** picking a choice submits that item. The item locks, and only then does the server render the correct answer and explanation for **that one item** (or "No explanation yet"). The server never sends the answer key of an item that hasn't been submitted, and never sends the whole attempt's key at once; it's not in the HTML, the Livewire state, or any response. Items already revealed stay revealed when the user goes back to them. Study mode is untimed and **never eligible for the leaderboard**, whatever its length or content.
- FR-T9. **Untimed attempts** have no deadline. They stay in progress until the user submits or abandons them.

### 4.6 Scoring and results (FR-S)
- FR-S1. Unanswered counts as wrong, and there is no penalty for wrong answers.
- FR-S2. Subtest % = correct in that subtest ÷ items in that subtest × 100. Topic % works the same way.
- FR-S3. GR (formula `v1`) = total correct ÷ total items × 100, rounded half-up to 2 decimals. Passed = GR ≥ the passing-grade setting (default 80.00). At exactly 80.00 the user passes.
- FR-S4. At finalization a score snapshot is stored: GR, formula version, passing grade used, passed, correct and total counts, the percentage for each subtest and each topic, time used, and how the attempt ended (`submitted|timeout|abandoned`). Results never get recalculated from live data.
- FR-S5. Results page: GR in large type, pass or fail (labeled "target 80%" for individual tests), a table by subtest and by topic, time used, and a link to the review.
- FR-S6. Review page: every item with the passage, all choices, the user's answer, the correct answer and the explanation, all from the snapshot. Filters: all, wrong, flagged, unanswered. A deleted source item shows a small "removed from bank" note.
- FR-S7. Progress page: attempt history (date, mode, subtest, GR or %, status) and accuracy per topic across completed attempts.

### 4.7 Leaderboard (FR-L)
- FR-L1. Boards: **Full mock** (combined) and **one per subtest** (individual). Periods: **all-time** and **this week** (Monday 00:00 to Sunday 23:59, Asia/Manila).
- FR-L2. An attempt is **eligible** if it is: completed (`submitted` or `timeout`) and timed with the standard duration; not in study mode; full length (combined uses the configured split; subtest boards need the configured count for that subtest with no topic filter); made only of public items at creation, **unless** the admin setting `leaderboard_include_private` is ON (default OFF); by an active user **who has a PIN set** (admins qualify through their password); and not hidden by an admin. Eligibility is checked when the board is read, so changing the setting or setting a PIN takes effect straight away. Each attempt stores a `has_private_items` flag at creation.
- FR-L3. Each user's best eligible attempt per board and period counts. The ranking is by GR (or subtest %) descending, then time used ascending, then submit time ascending. Rows show rank, IGN, score and time used.
- FR-L4. An attempt that averages fewer seconds per item than the `suspicious_seconds_per_item` setting (default 5) is marked "suspicious" in the admin panel for review **[A]**. **The flag never removes it from the leaderboard.** Only an admin hide (FR-L5) does that.
- FR-L5. Admins can hide or unhide an entry with a required reason, and the change is recorded in an audit log.
- FR-L6. Leaderboard entries are calculated from score snapshots. A `leaderboard_entries` table can be used as a cache, but it must be rebuildable from the snapshots.

### 4.8 Settings (FR-C)
- FR-C1. Settings are stored in the database and edited in Filament, each with validation and its default shown:

  | Setting | Default | Notes |
  |---|---|---|
  | Item count per subtest | 50 / 40 / 40 / 40 | 1–100 each. Labeled **unverified**, total shown (not forced to 170) |
  | Total minutes | 190 | 10–600 |
  | Passing grade | 80.00 | 0.00–100.00, 2 decimals |
  | Repeat window (attempts) | 3 | 0–20 (0 = off), FR-G6 |
  | Autosave grace (seconds) | 10 | 0–60, FR-T6 |
  | `duplicate_similarity_threshold` | 0.90 | 0.50–1.00, FR-Q6 |
  | `lockout_max_attempts` | 5 | 1–20, FR-I4 |
  | `lockout_window_minutes` | 15 | 1–1440, FR-I4 |
  | `suspicious_seconds_per_item` | 5 | 0–60, 0 turns it off, FR-L4 |
  | `leaderboard_include_private` | OFF | Admin only. Lets attempts with private items rank, for single-user testing |
- FR-C2. A setting change applies only to attempts created afterwards. Existing attempts keep the values stored on them.

### 4.9 Theming and UI (FR-U)
- FR-U1. Theme options are light, dark and system. The choice is saved in localStorage plus a cookie, and an inline script in `<head>` sets the `dark` class before first paint.
- FR-U2. One accent color, with tokens for both themes, all meeting AA contrast.
- FR-U3. Filament uses the same accent color and supports dark mode natively.

## 5. Non-functional requirements
- NFR-1. **Accessibility:** WCAG 2.2 AA. Choices are a real radio group, the timer uses `aria-live="polite"` and announces only at thresholds, focus moves to the stem on navigation, and nothing depends on color alone.
- NFR-2. **Performance (local):** moving to the next or previous item takes 200 ms or less, an autosave round trip 300 ms or less, and generating a 170-item mock 2 s or less on a bank of 5,000 items.
- NFR-3. **Security:** CSRF, policies on every Livewire action (a user can only touch their own attempts), the answer key is never sent to the client before an item is answered in study mode or the attempt is finalized, PINs and passwords are bcrypt-hashed, Filament access is limited to the admin role, and uploads are checked by type and size.
- NFR-4. **Portability:** Eloquent and the query builder only, so the code runs unchanged on SQLite, MySQL 8 and PostgreSQL 15+.
- NFR-5. **i18n:** all UI strings go through `__()`, with English only for now.
- NFR-6. **Browsers:** the last 2 versions of Chrome, Edge, Firefox and Safari, including mobile Safari and Chrome. Widths from 320 px.
- NFR-7. **Time:** stored in UTC and shown in Asia/Manila.
- NFR-8. **Testability:** the clock is faked in tests (`Carbon::setTestNow`), and shuffling is seedable.

## 6. Acceptance criteria (Given / When / Then)

**Identity**
- AC-1. Given no account "juan", when I submit username "Juan", IGN "JuanPogi" and a full name, then the account is created with username "juan", I am signed in, and I land on the dashboard.
- AC-2. Given a user "juan" exists, when someone registers "JUAN", then they see "Username is taken".
- AC-3. Given "juan" has a PIN and the default lockout settings, when someone signs in as "juan" with no PIN or the wrong one, then sign-in fails, and on the 6th failure within 15 minutes they are throttled. Given an admin changes `lockout_max_attempts` to 3, the 4th failure is throttled.
- AC-4. Given a `user` role, when they open `/admin`, then they get a 403.

**Tests**
- AC-5. Given 30 published Number-sequence items, when I start an individual Numerical test with that topic, 20 items, timed, then I get 20 distinct items from that topic and a 23-minute deadline.
- AC-6. Given a timed attempt with 5 minutes left, when I refresh the page, then I return to the same item with my answers intact and about 5 minutes left (server time).
- AC-7. Given an expired timed attempt, when I next load any page, then the attempt is finalized with end reason `timeout`, and answers saved before the deadline are scored.
- AC-8. Given an attempt being submitted, when "Submit" is sent twice at once, then exactly one score snapshot exists and both requests show the same result.
- AC-9. Given a passage with 3 sub-items, when it is selected, then its 3 items are next to each other in authored order and count as 3 items.
- AC-10. Given study mode, when I pick a choice, then the correct answer and explanation appear, and my choice can't be changed.
- AC-10a. Given a 20-item study attempt where items 1–3 are submitted, when any page or Livewire response for that attempt is rendered, then it contains the correct key and explanation for items 1–3 only, and no answer data for items 4–20. The same check on a timed or untimed in-progress attempt finds no answer data for any item.
- AC-11. Given a question whose choices include "All of the above" with `lock_choice_order`, when an attempt is generated, then its choice order is unchanged.
- AC-12. Given focus on an item, when I press `2` or `B`, then the second choice is selected and saved, and the screen reader announces the selection.

**Scoring**
- AC-13. Given 136 of 170 correct, then GR = 80.00 and the result is passed. Given 135 of 170, GR = 79.41 and the result is failed.
- AC-14. Given a finished attempt, when an admin edits that question's correct answer, then the attempt's result and review are unchanged.
- AC-15. Given a finished attempt, when an admin hard-deletes one of its questions after typing the confirmation, then the results and review still load, and that item shows "removed from bank". Given the admin also ticked "Also remove this content from past attempts", the review shows "Content removed (takedown)" with no question text, and the GR is unchanged.

**Leaderboard**
- AC-16. Given `leaderboard_include_private` is OFF and a combined attempt included one private item, then it does not appear on any board. Given the admin turns the setting ON, it appears on the next load, and disappears again when the setting is turned OFF.
- AC-16a. Given a user without a PIN with an eligible attempt, then they are not listed. Given they set a PIN, their best attempt appears.
- AC-16c. Given a finished study-mode attempt with the full standard length of a subtest, made of public items, by a user with a PIN, then it appears on no board, including when `leaderboard_include_private` is ON.
- AC-16b. Given an attempt averaging 3 seconds per item, then it is marked "suspicious" in admin and **is still listed** until an admin hides it.
- AC-17. Given two eligible mocks by the same user, then only the higher one is listed. Given equal GRs from two users, the one with less time used ranks higher.
- AC-18. Given an eligible attempt submitted last Sunday 23:00 Manila time, then it is not on this week's board on Monday.
- AC-19. The leaderboard HTML never contains users' full names.

**Content**
- AC-20. Given a JSON import where row 4 has no correct answer, then row 4 is created as `needs_review` with the reason "missing answer key", and no correct choice is set.
- AC-21. Given a CSV row with 1 choice, then the preview shows that row's error and the row can't be approved.
- AC-21a. Given an import row whose media is an SVG, or a PNG over 2 MB, then the row shows "unsupported image type" or "file too large" and can't be approved. The same checks apply to uploads in the question editor.
- AC-22. Given the default threshold and an imported stem that matches an existing stem at 95%, then the row is flagged "possible duplicate" and links to the match.
- AC-23. Given an import with no source specified, then its items belong to a private source.
- AC-24. Given an item in `needs_review` with unresolved reasons, when an admin tries to publish it, then publishing is blocked with the list of reasons.

**Theme**
- AC-25. Given the OS is in dark mode and no saved preference, when I load any page, then it renders dark on first paint with no light flash. Given I choose light, it stays light after a reload.

## 7. Edge cases
| Case | Expected behavior |
|---|---|
| Timeout while the user is mid-answer | An answer that arrives within the grace window is saved; after that it is rejected, and the UI moves to the results page |
| Refresh or crash mid-test | Resume from the last saved state; the timer is unaffected |
| Two tabs on the same attempt | Both read and write the same server state, last write wins per item, and the navigator refreshes on focus |
| Double submit, or submit racing an auto-submit | The finalize lock makes it happen once (AC-8) |
| Offline | Banner shows "Offline — answers will sync"; pending answers are retried with backoff; anything not saved within the deadline plus grace is lost, and the results page shows how many weren't saved |
| Clock skew on the client | The client shows server time remaining (it gets an offset from each response) and never decides when the deadline is |
| Duplicate username or IGN | Case-insensitive unique check, plus a DB unique index on lowercased columns |
| Someone claims another person's username | Prevented once a PIN is set; the profile nudges users to set one; accounts without a PIN never appear on the leaderboard |
| Leaderboard cheating (tampered requests, replays, editing the score client-side) | Scores only come from server snapshots; answers can only be written to the user's own open attempt; suspiciously fast attempts are flagged for review, not removed; admins decide and hide entries |
| Answer key in the page source | Never sent to the client before it may be revealed (NFR-3) |
| Question edited or deleted after an attempt | Snapshots keep results and review the same (AC-14, AC-15) |
| Settings changed mid-attempt | The attempt keeps the values it was created with (FR-C2) |
| Bank too small for a mock | Offer a partial test, not eligible (FR-G7) |
| Item needs an image that is missing | `needs_review`, so it can't be published |
| Admin taking tests | Allowed; attempts with private items are eligible only while `leaderboard_include_private` is ON |
| Takedown of copyrighted item | Hard delete, optionally with purge (FR-Q5a); scores are kept either way |
| Deactivated user with leaderboard rows | Hidden from boards; the data is kept |

## 8. Privacy note (shown on the onboarding page and in `/privacy`)
- **Stored:** username, IGN, full name, an optional PIN (hashed, never readable), your attempts and answers, and timestamps.
- **Public:** your IGN, leaderboard scores, and the time used on eligible attempts. You appear on the leaderboard only after you set a PIN.
- **Private:** your full name and attempt details, visible to you and to admins only.
- No email, no tracking or analytics, no third-party services in the MVP. To have your data removed, ask an admin (who can delete your account, including its attempts).

## 9. Out of scope (MVP)
- A public multi-user launch: email verification, password reset by email, ToS (suggestions #7 and #8).
- Spaced repetition, streaks, advanced analytics, PDF export (#6).
- A Filipino interface (questions can already be in Filipino).
- Questions that aren't single-answer multiple choice; negative marking.
- Pausing timed attempts, or proctoring and anti-cheat beyond FR-L4 and FR-L5.
- Running OCR inside the app (Claude extracts content outside the app into staged JSON).
- Users contributing or reporting questions.
- Native mobile apps, offline-first (PWA), real-time features such as websockets.
- Deploying to production hosting.
