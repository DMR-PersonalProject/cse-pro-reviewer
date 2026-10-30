# Clarifications — CSE Reviewer

Status: **approved 2026-10-27** · Last updated 2026-10-27
Answering an item here means: change its status, update `spec.md` if the behavior changes, and add a line to the Decisions log in `CLAUDE.md`.

## 1. Resolved (from the clarifying questions and reviews)
| ID | Question | Answer | Recorded in |
|---|---|---|---|
| R-1 | Item split per subtest | 50/40/40/40, an editable setting labeled "unverified" | FR-C1 |
| R-2 | Identity | Optional PIN; `email` and `password` nullable for a later upgrade | FR-I2, FR-I5 |
| R-3 | General rating | Correct ÷ total × 100 (`v1`), plus each subtest % stored | FR-S3, FR-S4 |
| R-4 | Admin panel | Filament for admins only; user app on plain Livewire | §2, plan |
| R-5 | Hosting and database | Local only, SQLite; code portable to MySQL and PostgreSQL | NFR-4 |
| R-6 | Copyrighted material | Private/public flag on each source, imports default to private | FR-Q4, FR-M6 |
| R-7 | Interface language | English, with all text through `__()` | NFR-5 |
| R-8 | Local setup | Laravel Herd | `CLAUDE.md` |
| R-9 | Deleting questions | Snapshots plus hard delete with typed confirmation and an optional purge | FR-G8, FR-Q5, FR-Q5a |
| R-10 | Suspicious attempts | Flagged for review only; an admin decides | FR-L4 |
| R-11 | Private items on the leaderboard | Setting `leaderboard_include_private`, default OFF | FR-L2 |
| R-12 | Thresholds | Similarity, lockout and suspicious speed are settings | FR-C1 |
| R-13 | Leaderboard identity | Only users with a PIN are listed | FR-L2 |
| R-14 | Mock timing (was O-2) | One shared 190-min clock, no per-subtest limits; a pacing hint is suggestion #12 | FR-G2 |
| R-15 | SVG uploads (was O-6) | Not in MVP. PNG/JPG/WebP only, real file type and size validated on upload and import; SVG with sanitizing is suggestion #13 | FR-Q1, FR-M4a |
| R-16 | O-1, O-3, O-4, O-5, O-7, O-8, O-9 | Stated defaults accepted (see §3) | — |

## 2. Assumptions (in effect unless you change them)
| ID | Assumption | Used in | Why | To change |
|---|---|---|---|---|
| A-1 | Split 50/40/40/40 (V/N/A/GI). **Unverified**: CSC doesn't publish it | FR-C1, FR-G2 | Adds up to 170 and follows common reviewer guidance | Admin setting |
| A-2 | Subtests appear in the order V → N → A → GI, with free navigation across the whole mock | FR-G2, FR-T3 | Paper exams allow free movement | Spec change |
| A-3 | Within a subtest, items are spread evenly across topics, and any shortfall is filled from other topics in the same subtest | FR-G2 | No official topic weighting exists | Spec change; topic weights could later become a setting |
| A-4 | No target English/Filipino ratio inside Verbal; the mix follows what's in the bank | FR-G2 | No official ratio known | Add a setting later |
| A-5 | Unanswered = wrong, no penalty for wrong answers, GR rounded half-up to 2 decimals | FR-S1, FR-S3 | Matches how CSC results are commonly reported | Formula version `v2` |
| A-6 | Timed attempts can't be paused; the clock runs while the tab is closed | FR-T5 | Realistic practice | Spec change |
| A-7 | One attempt in progress per user; starting a new one asks to resume or abandon | FR-G9 | Simpler state, no orphaned timers | Spec change |
| A-8 | Autosave grace of 10 s after the deadline | FR-T6 | Covers network latency without giving extra answering time | Setting |
| A-9 | Duplicate threshold 0.90 on normalized stems in the same subtest | FR-Q6 | Catches reworded copies without many false alarms | Setting |
| A-10 | Lockout after 5 failures per 15 min per username plus IP | FR-I4 | Laravel default range | Settings |
| A-11 | Suspicious below 5 s per item on average | FR-L4 | Faster than you can read most stems | Setting |
| A-12 | Individual test: min 5, max 100 items, default 20; timed duration ⌈190 × count ÷ 170⌉ min | FR-G1 | Proportional share of the real exam time | Spec change |
| A-13 | Leaderboard week runs Monday 00:00 to Sunday 23:59 Asia/Manila; ties broken by less time used, then earlier submit | FR-L1, FR-L3 | Common convention | Spec change |
| A-14 | Subtest boards count only full-length, timed, unfiltered individual attempts | FR-L2 | Fair comparison | Spec change |
| A-15 | 2–5 choices per item, 4 by default, exactly one correct | FR-Q1 | CSC items are single-answer, usually 4 or 5 options | Spec change |
| A-16 | Deleting a user (admin only) also deletes their attempts; there is no self-service delete in MVP | FR-I6 | Simple and fully removes the user's data | Spec change |
| A-17 | Media is stored on the local `public` disk, PNG/JPG/WebP only (max 2 MB each, required alt text) | FR-Q1 | Local-only deployment; no SVG (R-15) | Plan / setting |
| A-18 | The first admin is created with an artisan command that asks for email and password; there are no default credentials in seeds | M1 | No known passwords in the repo | Plan |
| A-19 | Seed content: at least 2 original items per topic (34+), all public, labeled "Original — seed" | FR-Q7 | The app works before your content arrives | Spec change |

## 3. Open questions
All are closed or running on their default (R-14 to R-16). They stay listed so they can be revisited.

| ID | Question | Needed by | Default (in effect) |
|---|---|---|---|
| O-1 | Can you find the official item split (CSC advisory, exam notice or reviewer center)? | Before you rely on mock scores | A-1, still labeled unverified |
| O-2 | ~~Separate subtest clocks or one shared clock?~~ | — | **Resolved: R-14** |
| O-3 | Should Verbal aim for a set English/Filipino ratio? | M4 | No ratio (A-4) |
| O-4 | Is a general rating of correct ÷ total right, or do you have a source for a weighted CSC formula? | M6 | `v1` (R-3); formula is versioned so it can change |
| O-5 | How will you hand over content: which file types first, rough volume, and whether you have answer keys? | M3 | Process described in `content/README.md` (written with the plan) |
| O-6 | ~~Allow SVG uploads?~~ | — | **Resolved: R-15** |
| O-7 | Public content policy for other users (suggestion #8) | Before any second user | Everything private except seeds |
| O-8 | Should we back up the local SQLite database (for example, a daily copy to a folder)? | M7 | Not in MVP; listed in the plan |
| O-9 | Library versions: confirm Laravel, Livewire and Filament versions that work together at M1 (suggestion #5) | M1 | Latest stable versions that are mutually compatible, checked at M1 |

## 4. Risks
| Risk | Effect | Mitigation |
|---|---|---|
| The item split is wrong | Mock exams don't match the real one | A setting with an "unverified" label (A-1, O-1) |
| Copyrighted reviewer content shown to others | Legal exposure | Private by default, provenance per item, takedown with purge (R-6, R-9) |
| Extraction errors (OCR, missing keys) | Wrong answers get learned | `needs_review` gate, never guess, admin publishes (FR-M4, FR-M5) |
| Only one user | The leaderboard can't be tested meaningfully | `leaderboard_include_private` plus factory-made fake users in tests |
| Concurrent SQLite writes (two tabs) | Rare "database is locked" errors | WAL mode, short transactions; not an issue on MySQL or PostgreSQL |
