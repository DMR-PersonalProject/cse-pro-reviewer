Decisions log. Append new rows at the bottom. Check this file before design choices.

| Date | Decision | Status |
|---|---|---|
| 2026-10-27 | Spec-driven workflow; no implementation before `approved: start M1` | Accepted |
| 2026-10-27 | Per-subtest item distribution is configurable, defaults unverified | Accepted |
| 2026-10-27 | Two roles (`admin`, `user`) from day one; extensible | Accepted |
| 2026-10-27 | Imported content is draft until admin review; provenance tracked per item | Accepted |
| 2026-10-27 | Q1: item split 50/40/40/40, editable, labeled "unverified" | Accepted |
| 2026-10-27 | Q2: optional user PIN; email/password nullable for later upgrade | Accepted |
| 2026-10-27 | Q3: rating = correct ÷ 170 × 100; store per-subtest % as well | Accepted |
| 2026-10-27 | Q4: Filament for admin only; user app on plain Livewire | Accepted |
| 2026-10-27 | Q5: local-only, SQLite; code must be MySQL/PostgreSQL portable | Accepted |
| 2026-10-27 | Q6: private/public source flag, imports default to private | Accepted |
| 2026-10-27 | Q7: English UI, i18n-ready for Filipino | Accepted |
| 2026-10-27 | Q8: Laravel Herd for local setup | Accepted |
| 2026-10-27 | Study mode (instant answer and explanation) is in MVP | Accepted |
| 2026-10-27 | Constitution approved. Snapshot answers instead of "never delete"; hard delete with typed confirmation | Accepted |
| 2026-10-27 | Spec approved. Suspicious flag is for review only (no auto-exclude) | Accepted |
| 2026-10-27 | Setting `leaderboard_include_private` (default OFF); constitution §5/§6 amended | Accepted |
| 2026-10-27 | Similarity, lockout and suspicious thresholds are DB settings | Accepted |
| 2026-10-27 | Takedown purge option (#9) and PIN required for leaderboard (#10) in MVP | Accepted |
| 2026-10-27 | Plan approved. #15 accepted: full suite on MySQL and PostgreSQL in M7, before any deploy | Accepted |
| 2026-10-27 | Study mode: server reveals one item's key only after that item is submitted; study never counts on leaderboard (AC-10a, AC-16c) | Accepted |
| 2026-10-31 | M2-16: `DatabaseSeeder` no longer uses `WithoutModelEvents` (seeded stems must be normalized and duplicate-checked). Seed items live in `database/seeders/data/sample_content.php`; the abstract-reasoning PNG is drawn with GD at seed time. Suggestions #19 (throttle current-PIN checks) and #20 (edit full name/email) added | Accepted |
| 2026-10-31 | M2-13 (user chose): promote asks for email + admin password (replaces PIN); demote clears password; reset PIN = clear it (user accounts only, confirm first); no own-role change; last active admin can't be demoted/deactivated/deleted; role change, deactivate and PIN reset end sessions (DB session rows + remember token); no secrets in audit. `User::leaderboardEligible()` scope = account part of FR-L2. Spec FR-I6 updated | Accepted |
| 2026-10-30 | M2-09: possible duplicates stored in `questions.duplicate_of_question_id` + `duplicate_similarity` (user chose real columns), recomputed on every stem or subtest change; editor saves never change status. Plan §3.2 + ERD updated | Accepted |
| 2026-10-30 | M2-01 topic codes (user chose proposed): snake_case of the topic names; symbolic logic + abstract reasoning is one topic `abstract_reasoning`; GI uses `ra_6713`, `peace_human_rights`, `environment`. Listed in plan §3.2 | Accepted |
| 2026-10-30 | M1 approved, merged to `main`, tagged `m1-done`, pushed to private `origin`. #17 accepted as M2-17; #16 stays proposed; old CLAUDE.md copies stay in history | Accepted |
| 2026-10-30 | CLAUDE.md was wiped by `git switch main` (old main tracked it) and restored from `95f2aac` plus this session's edits; compare with the user's own backup | Note |
| 2026-10-30 | M1-13: theme shared via Filament's localStorage key `filament:theme:mode` (plan said `theme`); `theme` cookie unencrypted, re-synced from localStorage by the head script; toggle uses `window.cseTheme` from that script instead of an Alpine store; `POST /logout` added for the header | Accepted |
| 2026-10-30 | M1-12: admin password rule is Laravel `Password::defaults()` (min 8); inactive admins are signed out (not 403) via `AuthenticateAdmin`; ~~changing a PIN does not need the current PIN (spec literal, #17 proposes it)~~ (superseded by M2-17: changing a PIN now requires the current PIN) | Accepted (PIN part superseded) |
| 2026-10-30 | M1-07 setting ranges (user chose proposed): split 1–100 each (total not forced), minutes 10–600, passing 0–100 (2 dp), repeat 0–20, grace 0–60 s, suspicious 0–60 s; recorded in spec FR-C1 | Accepted |
| 2026-10-30 | `CLAUDE.md` is local-only (gitignored, untracked); old copy stays in `d90dcc1`; user backs it up at each milestone stop | Accepted |
| 2026-10-30 | Git workflow adopted (see section); git initialized on `main` before scaffolding; `content/staged/` is gitignored | Accepted |
| 2026-10-27 | Clarifications approved. Mock uses one 190-min clock; no SVG (PNG/JPG/WebP only, validated); other open questions use defaults | Accepted |
| 2026-10-31 | Formula version check: current GR formula is `v1` (spec glossary + FR-S3, plan `GeneralRatingV1`, clarifications R-3/O-4); no scoring code yet (M5). A-5 "To change" cell reworded: `v2` is what a future formula change would ship as, not the current formula | Accepted |
| 2026-10-31 | M3-02: `import_batches` also gets `name` and `meta` (json: `default_source`, `passages` by `ref`, `warnings`) so a JSON file's passages and source survive until approval; each passage is created once per batch and reused | Accepted |
| 2026-10-31 | M3-05: the `missing_alt_text` flag maps to `NeedsReviewReason::MissingMedia`; the image is not attached; the flag code stays in the preview and row, and the item's `source_notes` name the image file and its provenance. Alt text is never generated to pass the check. A separate filterable reason is suggestion #21 | Accepted |
| 2026-10-31 | M3-07: a source created by an import is always private, whatever the file says; `visibility: public` in a file gives a preview warning and an audit entry, never a failure. An existing source with the same name is reused unchanged; imports never change a source's visibility. FR-M6 updated | Accepted |
| 2026-10-31 | M3-09: admin upload limit 5 MB (JSON/CSV, extension checked by the parser); error rows can't be selected, so the batch page has "Approve all rows without errors" and "Reject all rows with errors"; batches have no edit or delete | Accepted |
| 2026-10-31 | Tests: `phpunit.xml` sets `memory_limit` 512M; the full suite (496 tests) exceeded PHP's 128M default | Accepted |
| 2026-10-31 | Correction: scoring (Scorer v1 / `GeneralRatingV1`, subtest and topic %, stored `formula_version`) is in M4 (M4-05, M4-08), as plan §11 says; the earlier "(M5)" in the formula version note was wrong. M4-01: `attempt_results.end_reason` reuses `AttemptStatus` (values submitted/timeout/abandoned); answer positions start at 1 | Accepted |
| 2026-10-31 | M4-02: "seen" means answered (`answered_at` set) in the user's last N attempts, as FR-G6 says (shown but skipped items stay fresh); for users, a passage item also needs a public passage source, so private passage text never reaches them | Accepted |
| 2026-10-31 | M4-04: the snapshot also keeps the stem's own images as `media[]` (plan listed only passage and choice media); each media entry copies disk, path, mime, width, height and alt text. `source_visibility` is private when the question's or its passage's source is private | Accepted |
| 2026-10-31 | M4-05: `AttemptClock` gives `remainingMs` (null without a deadline), `acceptsAnswers`, `isExpired` and `hasReachedDeadline`; `guard()` (which finalizes) arrives with `FinalizeAttempt` in M4-08. `Scorer` uses integer hundredths throughout; an empty attempt is 0.00 and never passes | Accepted |
| 2026-10-31 | M4-06: `StartAttempt::individual()` returns a `StartResult` (new attempt, the active one to resume or abandon, or a shortfall with `available`). A shortfall starts nothing unless `allowShortfall` is passed (the user accepted the offer); then `is_partial` is set and `item_count`/duration use the items actually given; 0 items never starts. `is_standard` = timed, no topic filter, requested count equals the subtest's configured split, not partial. Duration denominator is the configured item total. `config` keeps item_split, total_minutes, passing_grade, grace_seconds, suspicious_seconds_per_item. `passage_key` = `p{passage_id}` | Accepted |
| 2026-10-31 | M4-07: answer actions share `AttemptItems::change()` (attempt row `lockForUpdate`, `AttemptPolicy@update` via Gate, `acceptsAnswers`, position lookup). A wrong owner or a finalized attempt raises `AuthorizationException`; time up, unknown key or position, plain save in study mode and changing a revealed item raise `AnswerRefused`. `SaveAnswer` also sets `is_correct`. Flags follow the same time rule. `AbandonAttempt` only sets status and `finalized_at` for now; M4-08 routes it through `FinalizeAttempt` so an abandoned result row with `end_reason = abandoned` is stored (FR-S4) | Accepted |
