# SUGGESTIONS.md — Idea parking lot

Ideas noticed while working. **Not implemented unless the user accepts them.**
Status: `proposed` / `accepted` / `rejected` / `done`. Effort: S (< 0.5 day), M (1–3 days), L (> 3 days).

| # | Date | Idea | Why it's better | Effort | Impact | Status |
|---|---|---|---|---|---|---|
| 1 | 2026-10-27 | Optional 4–6 digit PIN on user accounts (hashed in the nullable `password` column) | Stops anyone from claiming another person's username and leaderboard spot, adds about 5 seconds to onboarding, and upgrades cleanly to full email and password auth later with no schema change | S | High | accepted (Q2) |
| 2 | 2026-10-27 | "Study mode": untimed, shows correct answer and explanation right after each item | Learning is faster with instant feedback; it reuses the attempt engine with one flag | S | High | accepted (MVP) |
| 3 | 2026-10-27 | Visibility per source (`private` / `public`) on question sources | Lets copyrighted reviewer material stay admin-only while original items go public, which reduces the copyright risk before a multi-user launch | S | High | accepted (Q6, default private) |
| 4 | 2026-10-27 | Use Laravel Herd (Windows) for local PHP, Composer and Node setup | PHP and Composer are not installed; Herd is a single installer and avoids Docker overhead on Windows Home | S | Medium | accepted (Q8) |
| 5 | 2026-10-27 | Check if Livewire 4 is the current stable at M1 and use it instead of Livewire 3 | Avoids starting a new project on the previous major version | S | Medium | proposed |
| 6 | 2026-10-27 | Future phase: spaced repetition on missed items, streaks, per-topic analytics, PDF export of results | Out of MVP scope; listed so they are not lost | L | Medium | proposed |
| 7 | 2026-10-27 | Future phase: public multi-user launch (email verification, rate limiting, reporting of bad questions, ToS/privacy page) | Required before strangers use it; out of MVP scope | L | High | proposed |
| 8 | 2026-10-27 | Public content strategy for other users: decide which sources are made public (original items only, licensed material, or user-contributed items with review), plus attribution and a takedown process | Needed before anyone else uses the app; the private/public flag is only the mechanism, not the policy | M | High | proposed |
| 9 | 2026-10-27 | "Purge snapshots" option on takedown: also redact the snapshot text of the deleted item in past attempts, keeping only the score data | A real copyright takedown may need the text gone everywhere, but snapshots keep it so reviews still work. This makes it a choice per delete | S | Medium | accepted (spec FR-Q5a) |
| 10 | 2026-10-27 | Leaderboard only lists accounts that have a PIN set | Without a PIN, anyone can sign in as that username and post or spoil scores under that IGN | S | Medium | accepted (spec FR-L2) |
| 11 | 2026-10-27 | "Retake my mistakes" mode: build an attempt from items the user got wrong (or flagged) in past attempts, optionally filtered by subtest or topic; items answered correctly twice in a row drop out | Targets weak spots directly; reuses the attempt engine and snapshot data. Items deleted from the bank are skipped | S | High | proposed |
| 12 | 2026-10-27 | Optional per-subtest pacing hint in the combined mock (e.g. "Verbal: suggested ~56 min, you've used 61"), display only, no hard limit | Helps practice time management without changing the single-clock rule | S | Medium | proposed |
| 13 | 2026-10-27 | Allow SVG uploads with server-side sanitizing (strip scripts, event handlers, external refs) | Abstract-reasoning figures are often vector; SVG stays sharp and small. Deferred from MVP for security | M | Low | proposed |
| 14 | 2026-10-27 | Filament import accepts a .zip (JSON + images) so media can be imported from the browser, not just the CLI | MVP imports media through the CLI or by attaching it in the question editor; zip makes browser imports complete | M | Medium | proposed |
| 15 | 2026-10-27 | Run the test suite against MySQL and PostgreSQL (Docker or a CI matrix) | Proves the portability rule instead of trusting it | M | Medium | accepted (M7 task, before any deploy) |
