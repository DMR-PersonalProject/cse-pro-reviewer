# Final check (M7)

About 30 minutes, end to end, on http://cse.test after `php artisan migrate:fresh --seed` and `php artisan cse:make-admin`. Tick each row; note anything odd in SUGGESTIONS.md or tell Claude.

## As an admin

- [ ] Sign in at `/admin/login` and land on `/admin` (also after first opening `/` signed out).
- [ ] Imports → Upload questions: choose a JSON file (e.g. your `batch-1.json`), wait for "Upload complete", click **Check file**, and land on the batch preview with the right row count.
- [ ] Upload a file with 0 valid rows: the batch page says so and nothing can be approved.
- [ ] Approve valid rows: they appear in Questions as `draft` or `needs_review`, under a private source. Nothing is published.
- [ ] Publish a few items from the question editor; attach an image to an item flagged "attach in editor".
- [ ] Settings: change the item split, save, and see it used by the next mock (then set it back).
- [ ] Attempts and leaderboard: hide one entry with a reason; it disappears from the user leaderboard.
- [ ] Toggle light/dark: the theme carries over to the user app.

## As a user

- [ ] Register at `/start` with a username, IGN, full name and a PIN; sign out and back in.
- [ ] Individual test, study mode: the answer and explanation show after each item.
- [ ] Individual test, timed: answer with 1–4 / A–D, move with arrows, refresh mid-test and resume.
- [ ] Full mock: navigator and flags work; submit; results show the general rating, pass/fail and subtest breakdown; review shows every item.
- [ ] Let the session expire during a test (or clear the session cookie), sign back in, and resume the same attempt.
- [ ] Progress page and leaderboard (mock and per subtest, all-time and this week) show IGN only.
- [ ] Open the app on your phone and take a short test.

## Still open from specs/a11y-checklist.md (⏳)

- [ ] Leaderboard: changing board/period updates the table without an announcement; judge it in NVDA (idea parked in SUGGESTIONS.md).
- [ ] Full mock, keyboard only: one complete run without a mouse.
- [ ] Full mock, NVDA on: one complete run with NVDA + Firefox or Chrome.
- [ ] Every page at 320 / 375 / 768 px: no horizontal page scroll.

## Before any deploy

- [ ] PostgreSQL run of the full suite (skipped in M7-06, see specs/decisions.md).
- [ ] Fresh clone + README steps reach a working app.
