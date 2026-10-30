# Constitution — CSE Reviewer

These principles rank above every spec, plan and task. If a later document conflicts with them, the later document is wrong. Change this file only through an entry in the Decisions log in `CLAUDE.md`.

## 1. Simplicity first
- Build the smallest thing that meets an acceptance criterion. Don't add abstractions until a second real use exists.
- Use the framework's own tools before adding packages: Eloquent, policies, Livewire, Blade components. Every new dependency needs one sentence of justification in `plan.md`.
- Keep the UI minimal: whitespace, one accent color, readable type, nothing decorative that competes with the question.
- Ideas outside the current task go to `SUGGESTIONS.md`, not into the code.

## 2. Accessibility is a requirement
- WCAG 2.2 AA in both light and dark themes: contrast, focus visibility, target size.
- The whole test flow works by keyboard alone: 1–4 or A–D to answer, arrows for previous/next, a key to flag. Screen readers announce the question, choices, selection state and remaining time without flooding updates.
- Mobile first. No horizontal scrolling at 320 px except inside data tables, which scroll inside their own container.
- Respect `prefers-color-scheme` and `prefers-reduced-motion`. The theme choice is saved, and the page never flashes the wrong theme.
- Charts and images that a question needs have text alternatives. If an item can't be made accessible, it is flagged, not hidden.

## 3. Test-first
- Every task in `tasks.md` starts with a failing Pest test that maps to an acceptance criterion in `spec.md`.
- Scoring, timing, import validation and leaderboard eligibility have unit tests that cover their edge cases, including boundaries such as exactly 80.00.
- A task is done only when tests pass, Pint is clean, and the box in `tasks.md` is ticked.
- Tests run on SQLite. Nothing may depend on SQLite-specific behavior, so the same suite stays valid on MySQL or PostgreSQL.

## 4. Data integrity
- **The server is authoritative.** Start time, deadline, answers, submission and scores are decided and stored on the server. Client timers are only for display.
- Submission is idempotent. A double submit, a late autosave or a timeout racing a manual submit always produces exactly one final result.
- Results are stored as snapshots: the general rating and each subtest and topic percentage are written at submit time, together with the formula version used. Later edits to questions or settings never change past results.
- **Attempt answers are snapshots.** Each attempt answer stores a copy of the question text, passage, choices, correct answer and explanation as they were when the attempt was created. Results and answer reviews read only from these snapshots, never from the live question.
- Admins archive questions in the normal way. Hard-deleting a question (for example, a takedown) needs an explicit typed confirmation. After a delete, past results and reviews still work, and the attempt answer's link to the question is set to null.
- Configuration lives in data, not code. Thresholds and limits (duplicate similarity, lockout, suspicious speed) are settings too. Item distribution, time allotment and the passing grade are settings. The 50/40/40/40 split is labeled **unverified** wherever it appears.
- Code stays portable to MySQL and PostgreSQL. Use Eloquent and the query builder; no engine-specific SQL.

## 5. Review before publish
- No question reaches a test unless its status is `published`. Imports always arrive as `draft` or `needs_review`.
- **Never fabricate.** If an answer key, explanation or required image is missing or unclear, the item is flagged `needs_review` with a reason. A guess is never stored as the correct answer.
- Every item records where it came from: file, page and notes. That makes it possible to find and remove any item, or a whole source, later.
- Every source defaults to **private**. Private items appear only to admins. An attempt that contains any private item is not eligible for the leaderboard unless the admin turns on the `leaderboard_include_private` setting (default OFF), which exists for single-user testing.
- Publishing copyrighted reviewer material to other users is a risk the owner decides on. The code never makes that decision by default.

## 6. Privacy and fairness
- Store only what's needed: username, IGN, full name, an optional hashed PIN, and attempt data.
- In public views the only identity shown is the IGN. Full names are visible to admins only.
- The leaderboard counts only completed, server-validated attempts that used published items (public only, unless `leaderboard_include_private` is ON) by users who have set a PIN. Automatic checks only flag attempts. Hiding is an admin decision, and every hide is recorded.
- The identity schema is ready for an upgrade: `email` and `password` are nullable now, and requiring them later needs no table rewrite.

## 7. Spec-driven workflow
- Work in this order: constitution → spec → clarifications → plan → tasks → implementation, one milestone at a time.
- No application code until the owner replies `approved: start M1`. After that, each milestone ends with a summary under 10 lines and a pause.
- Decisions go in the Decisions log in `CLAUDE.md`, and assumptions go in `clarifications.md`. Nothing is decided silently.
