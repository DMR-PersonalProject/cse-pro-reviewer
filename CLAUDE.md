# CLAUDE.md — CSE Reviewer

> Read this first every session. Keep it short and current. Update the Decisions log whenever a decision changes.

## Status
**Phase: M1 in progress on branch `m1-foundation`** (approved 2026-10-30). Do only M1 tasks; stop after M1 and wait for `approved: start M2`. M1-15 (remote) only when asked.
All planning docs approved. Deliverable order: `constitution.md` → `spec.md` → `clarifications.md` → `plan.md` → `tasks.md`. Stop after each one and summarize in under 10 lines, listing any new `SUGGESTIONS.md` entries.

## Project summary
Web app for practicing the Philippine CSC **Career Service Professional Examination**. Individual tests (one subtest, optional topic filter, item count, timed or untimed) and a combined full mock exam, with scoring, answer review with explanations, progress tracking and a leaderboard (IGN only). Personal project first, designed for other users later.

## Exam facts (source of truth: CSC Exam Advisory No. 03 s. 2025)
- 4 subtests, **170 items**, multiple choice, **3h 10m**, pass = general rating **≥ 80.00**.
- Verbal (EN+FIL): word meaning, sentence completion, error recognition, sentence structure, paragraph organization, reading comprehension.
- Numerical: basic operations, number sequences, word problems.
- Analytical (Professional only): word analogy, symbolic logic/abstract reasoning, assumptions and conclusions, data interpretation.
- General Information: PH Constitution, R.A. 6713, peace and human rights, environmental management and protection.
- **Per-subtest item counts are NOT published by CSC.** Default 50/40/40/40 (Verbal/Numerical/Analytical/GI) lives in DB-backed settings, shown in admin with an "unverified" label. Never hardcode them.
- **General rating** = total correct ÷ total items × 100. Each subtest percentage is stored too, so the formula can change later without data loss.

## Stack
Laravel (latest stable), PHP 8.3+, Livewire + Alpine.js for the user-facing app, **Filament for the admin panel only**, Tailwind CSS (dark mode via `class`), Pest, Pint.
Database: SQLite locally (local-only deployment for now). **Code must stay portable to MySQL/PostgreSQL**: no SQLite-only SQL, no raw DB-specific functions, Eloquent/query builder only, JSON columns accessed via Laravel casts.
Environment: Windows 11 with Laravel Herd.

## Local setup (Windows, Laravel Herd)
1. Download and install Herd for Windows from https://herd.laravel.com/windows (it bundles PHP, Composer, the Laravel installer and Node via nvm).
2. Open Herd → PHP tab → make sure PHP 8.3 or newer is the default.
3. Open a **new** terminal and verify: `php -v`, `composer -V`, `laravel --version`.
4. In the project folder run `herd link cse`, then open http://cse.test (or run `php artisan serve`).
5. First run (after M1 scaffolding): `composer install`, `npm install`, `cp .env.example .env`, `php artisan key:generate`, `php artisan migrate --seed`, `npm run dev`.

**Recorded toolchain (M1-01, 2026-10-30):** PHP 8.4.26 NTS x64 (Herd), Composer 2.10.3, Laravel installer 5.31.1, Node 26.11.1, npm 11.20.0. Extensions include pdo_sqlite, pdo_mysql, pdo_pgsql, intl, gd, mbstring, zip. Herd's `php`/`composer` are on the PowerShell PATH but not on Git Bash's, so run PHP commands from PowerShell.

## Conventions
- Spec-driven: every feature traces to a story in `specs/spec.md` and a task in `specs/tasks.md`. Tick tasks when done.
- Test-first with Pest: write the failing test, then the code. Feature tests for HTTP/Livewire, unit tests for scoring and import validation.
- Run Pint before declaring a task done.
- Server is authoritative for time, answers and scores. Never trust client timers or client-computed scores.
- Nothing reaches users unreviewed: imported questions start as `draft` or `needs_review`; only admins publish.
- Never fabricate a correct answer or explanation. Missing → flag `needs_review`.
- Leaderboard shows IGN only, never full name. Only users with a PIN are listed. Automatic checks flag; only admins hide.
- Attempt answers snapshot the question (text, passage, choices, correct answer, explanation). Results and review read snapshots only. Questions can be archived; hard delete needs typed confirmation, and `question_id` on attempt answers is nullable with `nullOnDelete`.
- Users: optional PIN (hashed, stored in nullable `password`); `email` and `password` nullable now, required later without a schema rewrite.
- Every imported item defaults to a **private** source; private items are admin-only and never count toward the leaderboard.
- UI copy is English, wrapped in `__()` so Filipino can be added later.
- Ideas outside the current task go to `SUGGESTIONS.md`, not into code.
- Minimal UI: one accent color, WCAG AA in both themes, keyboard first (1-4 / A-D to answer, arrows to navigate).

## Folder structure (planned)
```
/specs        constitution, spec, clarifications, plan, tasks
/content      README.md, inbox/ (raw files, gitignored), staged/ (*.json ready to import), schema/
/app, /resources, /database, /tests   standard Laravel layout (created in M1)
```

## Common commands (valid once M1 scaffolds the app)
```
php artisan test                 # run Pest suite
./vendor/bin/pint                # format code
php artisan migrate:fresh --seed # reset DB with sample questions
php artisan cse:import content/staged/<file>.json   # planned import command
npm run dev / npm run build      # assets
```

## Git workflow
- Repo: `cse-pro-reviewer` (private GitHub, not created yet). Default branch `main`. Never rewrite history.
- **Never commit** `.env` (only `.env.example`), `vendor/`, `node_modules/`, `database/*.sqlite`, storage logs/compiled files, `content/inbox/`, `content/staged/`, uploaded media, secrets, real user data or the user's source material.
- **Commits:** Conventional Commits (`feat`, `fix`, `test`, `docs`, `chore`, `refactor`). One commit per task or small group of related tasks, task ID in the scope: `feat(M2-04): add question model and factory`. Run `php artisan test` and Pint before every commit; never commit failing tests.
- **Branches:** one per milestone (`m1-foundation`, `m2-question-bank`, `m3-content-import`, `m4-individual-tests`, `m5-mock-exam`, `m6-results-leaderboard`, `m7-polish`). On `approved: start M1` create `m1-foundation` from `main`. On `approved: start M(n+1)`: merge the finished branch into `main` with `--no-ff`, tag `m<n>-done`, then create the next branch.
- **Remote:** never create or push to a remote without the user's say-so. When asked: create a PRIVATE repo `cse-pro-reviewer` with `gh` (or give manual steps), push `main` and tags. After that, push only after each milestone approval.

## Pointers
- `/specs` — all planning artifacts
- `SUGGESTIONS.md` — parked ideas with status; do not implement unprompted
- `/content/README.md` — how to prepare source material and how it gets processed (first task of M3; format defined in `specs/plan.md` §8)

## Decisions log
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
| 2026-10-30 | Git workflow adopted (see section); git initialized on `main` before scaffolding; `content/staged/` is gitignored | Accepted |
| 2026-10-27 | Clarifications approved. Mock uses one 190-min clock; no SVG (PNG/JPG/WebP only, validated); other open questions use defaults | Accepted |
