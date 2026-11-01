# CSE Reviewer

Practice web app for the Philippine CSC Career Service Professional Exam: individual tests, full mock exam, scoring, review, leaderboard (IGN only). Personal project first, built so others can use it later.
Stack: Laravel 13, Livewire 4 + Alpine (user app), Filament 5 (admin only), Tailwind 4, Pest 5, Pint, SQLite locally. Exact versions: specs/plan.md §1.3. Windows 11 + Herd: run PHP commands in PowerShell, not Git Bash.
Exam: 170 items, 3h10m, pass = general rating >= 80.00. The per-subtest split is NOT official: it lives in DB settings (default 50/40/40/40). Never hardcode it.

## Where things live
- specs/ : constitution, spec, clarifications, plan, tasks (tick tasks when done)
- specs/decisions.md : decisions log. Check it before any design choice.
- specs/HANDOFF.md : where the last session stopped (overwrite each session, max 20 lines). Read it first.
- SUGGESTIONS.md : parked ideas. Never implement unprompted.
- .claude/rules/ : area rules (testing, ui, data), loaded only when matching files are touched
- content/README.md : how source material is prepared and imported (M3)

## Commands
- One area: `php artisan test --compact --filter=<Name>` (or pass a test path)
- Full suite (milestone stops and risky changes only): `php artisan test --compact`
- Format: `vendor/bin/pint --dirty`
- Reset DB with sample content: `php artisan migrate:fresh --seed`
- Create admin: `php artisan cse:make-admin`
- PowerShell eats `^` and `|` in arguments (php.bat): pass test paths, not `--filter='a|b'`.

## Workflow rules
1. Tests first for risky logic (scoring, timer, answer saving, import validation, PIN/login/session checks): write the failing test, run only it, show the failure line, then implement. For simple screens and plain admin resources, write the tests with the code and run them once.
2. Per task: run tests for the touched area plus `vendor/bin/pint --dirty`, then one commit.
3. Full suite only at milestone stops or before risky changes (migrations, shared base classes).
4. Milestone stop: full suite, summary of 10 lines or less, update and commit specs/HANDOFF.md, then wait for my exact reply `approved: start M<n+1>`. Do not start the next milestone, merge, tag or push before that.
5. Decision not in specs: stop, give 2-3 options with a recommendation, wait.
6. Never without asking: push (except main + milestone tag after approval), rebase, reset, force, branch deletion, deleting files, installing or removing packages, editing .env, changing committed migrations.
7. Output: task summary of 5 lines or less (what changed, test status, commit hash). No full logs unless I ask. After each task group (or on pause), say in one line how I can check it by hand (a page to open, a command to run).
8. Pause: when I say 'pause', finish the current task, commit with tests green, overwrite specs/HANDOFF.md, commit it, and stop. Don't merge, tag or push. A pause is not a milestone stop.

## Project rules (every task)
- Every feature traces to a story in specs/spec.md and a task in specs/tasks.md.
- Server is authoritative for time, answers and scores. Never trust the client.
- Never fabricate a correct answer or explanation. Missing means flag `needs_review`.
- Imports are never published: they land as `draft` or `needs_review`, in a private source by default.
- Code must stay portable to MySQL/PostgreSQL (no SQLite-only SQL).
- UI copy is English, wrapped in `__()`.
- Keep it simple. Don't add abstractions, options or features the task doesn't need. Comment only non-obvious code.
- Stay inside the current milestone's tasks. If you spot something that could be better, add it to SUGGESTIONS.md and keep going. Don't change it.

## Git
- Repo: https://github.com/DMR-PersonalProject/cse-pro-reviewer (private, `origin`). Default branch `main`. Never rewrite history.
- Commit format: `<type>(<task-id>): <summary>`, e.g. `feat(M3-02): add import schema validator`. Types: feat, fix, test, docs, chore, refactor.
- One branch per milestone (`m3-content-import`, `m4-individual-tests`, `m5-mock-exam`, `m6-results-leaderboard`, `m7-polish`). On approval: merge `--no-ff` into main, tag `m<n>-done`, push main + tag, create the next branch.
- Never commit: .env, vendor/, node_modules/, database/*.sqlite, storage logs, content/inbox/, content/staged/, uploaded media, secrets, real user data.
- CLAUDE.md is tracked; changes to it are committed like any other file.

# Compact instructions
When compacting, keep: current task id, files changed, failing test names, next step. Drop passing test output and long logs.
