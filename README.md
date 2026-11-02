# CSE Pro Reviewer

A practice app for the Philippine Civil Service Commission **Career Service Professional Examination**. The real exam has 4 subtests (Verbal, Numerical, Analytical, General Information), 170 multiple-choice items, a 3 h 10 min time limit, and a passing general rating of 80.00. The app offers individual tests (one subtest, optional topics) and a full mock exam, with server-side scoring, answer review with explanations, progress tracking and a leaderboard that shows in-game names (IGN) only. It has two roles, `admin` and `user`, and an admin-only question bank fed by a review-first import pipeline.

## Status

Personal project, MVP complete, run locally only.

| Milestone | State |
|---|---|
| M1–M6 Foundation, question bank, import pipeline, individual tests, mock exam, results and leaderboard | Done |
| M7 Polish, accessibility and portability | Done, awaiting review |

The full suite passes on SQLite and MySQL 8.4. PostgreSQL has not been run yet; do that before deploying on it.

## Stack

Pinned in `specs/plan.md` §1.3:

- PHP 8.3+ (developed on 8.4), Laravel 13.39
- Livewire 4.4 with Alpine.js for the user app
- Filament 5.11 for the admin panel only
- Tailwind CSS 4.3 with the typography plugin, Vite 8
- Pest 5 and Pint for tests and code style
- SQLite locally; code is kept portable to MySQL and PostgreSQL

## Local setup (Windows, Laravel Herd)

Install [Laravel Herd](https://herd.laravel.com/windows) with PHP 8.3 or newer. Run PHP and Composer commands in **PowerShell**: Herd puts them on the PowerShell path, not on Git Bash's.

```powershell
git clone https://github.com/DMR-PersonalProject/cse-pro-reviewer.git
cd cse-pro-reviewer
composer install
npm install
npm run build
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed      # answer "yes" to create database/database.sqlite
php artisan cse:make-admin      # prompts for email, username, IGN, full name, password
herd link cse
```

Open http://cse.test for the user app and http://cse.test/admin for the admin panel. There are no default accounts: users register at `/start`, and admins are created only with `cse:make-admin`.

### First admin

`php artisan cse:make-admin` asks for an email, username, IGN, full name and password. Sign in at http://cse.test/admin/login; it lands on `/admin`.

### Timed attempts

Timed attempts that run out while nobody has the page open are finalized by `cse:finalize-expired`, scheduled every minute. Locally, keep `php artisan schedule:work` running in a second terminal (attempts are also finalized when their owner next opens them).

## Common commands

```powershell
composer test                     # run the Pest suite
composer lint                     # check formatting (pint --test)
composer fix                      # apply formatting (pint)
php artisan migrate:fresh --seed  # reset the local database
npm run dev                       # Vite dev server with hot reload
npm run build                     # production assets
```

## Import workflow

Full guide: `content/README.md`.

1. Put source material in `content/inbox/` (gitignored). Prepare it into a JSON or CSV file in `content/staged/` using the format in `content/schema/`. Missing answers or explanations are never guessed: those rows are flagged `needs_review`.
2. Check the file: `php artisan cse:import content/staged/<file>.json --dry-run`.
3. Create a pending batch: run the same command without `--dry-run`, or upload the file at **/admin → Imports → Upload questions → Check file** (no media files there; rows with images are flagged "attach in editor").
4. Review the batch preview in `/admin` and approve rows. Approved rows become `draft` or `needs_review` under a private source. Nothing is published by an import.
5. Publish items one by one from the question editor.

## MVP features

- **Accounts:** register with a username, IGN and full name; an optional 4–6 digit PIN protects the username. Admins sign in with email and password.
- **Individual tests:** one subtest, optional topics, item count, and timed, untimed or study mode (answer and explanation shown after each item).
- **Full mock exam:** all 4 subtests on one 190-minute clock, with a navigator and flags.
- **Test runner:** keyboard-first (1–5 or A–E to answer, arrows to move), autosave, and resume after a refresh or lost connection. The server keeps the time and the answers.
- **Results and review:** general rating, pass/fail, breakdown by subtest and topic, and a review of every item with the correct answer and explanation.
- **Progress:** attempt history and accuracy per topic.
- **Leaderboard:** full mock and per subtest, all-time and this week, IGN only. Only users with a PIN are listed; admins can hide entries with a reason.
- **Question bank (admin):** questions, passages, choices, images and sources; draft → review → publish flow; duplicate warnings; archive, or hard delete with typed confirmation.
- **Imports (admin):** JSON and CSV with per-row validation, preview and approval. Imported items start as draft or needs-review and are private by default.
- **Settings (admin):** item split, time, passing grade and thresholds, stored in the database.
- **Light, dark and system theme**, shared between the app and the admin panel.

## Not yet

- Public multi-user launch features: email verification, password reset by email, terms of service
- Filipino interface (questions can already be in Filipino)
- Question types other than single-answer multiple choice; negative marking
- Pausing timed attempts; anti-cheat beyond the suspicious-speed flag and admin hiding
- SVG images; OCR inside the app
- Spaced repetition, streaks, advanced analytics, PDF export
- Users contributing or reporting questions
- Mobile apps, offline-first (PWA), real-time features
- Production hosting

## Notes

- **Item counts per subtest are unverified.** CSC doesn't publish an official split. The defaults (Verbal 50, Numerical 40, Analytical 40, General Information 40) are editable in the admin settings and labeled "unverified" there.
- **Question content** is the owner's own study material. The repo contains no copyrighted reviewer content: raw source files and extracted imports (`content/inbox/`, `content/staged/`) are gitignored, and the bundled sample items are original.

## Project docs

- `specs/`: constitution, spec, clarifications, plan and tasks (the source of truth for what gets built).
- `SUGGESTIONS.md`: parked ideas and their status.
- `specs/decisions.md`: decisions log, including the portability runs.
- `specs/FINAL-CHECK.md`: the end-to-end manual check.
- `CLAUDE.md`: working rules and commands for Claude Code sessions.
