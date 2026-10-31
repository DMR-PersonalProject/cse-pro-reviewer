# Handoff (overwrite each session, max 20 lines)
Updated: 2026-10-31
- PAUSED after M4-13 (not a milestone stop). Branch: m4-individual-tests. Full suite: 621 green.
- Done and ticked: M4-01..13. This session: M4-09 setup page /practice (3c2ea5f), M4-10 Runner server side (a325be5), M4-11 runner Blade components (212d527), M4-12 keyboard store (8b5973b), M4-13 clock store (fe0783c).
- Runner: `App\Livewire\Attempt\Runner` at /attempts/{attempt} (route `attempts.show`). Every action calls `guard()` (owner, in progress, `AttemptClock::guard`); ended attempts redirect to `/attempts/{id}/results`, which doesn't exist yet (M4-15 adds `Results\Show`; switch the path to a named route then).
- JS: resources/js/keyboard.js (`$store.keyboard`, `go()` focuses #stem) and resources/js/clock.js (`$store.clock`, listens to `clock-sync`). Run `npm run build` after JS/Blade class changes.
- Components: resources/views/components/attempt/{stem,passage,choices,media,navigator,save-indicator,keyboard-help,clock}.blade.php.
- Save indicator is wire:loading only; M4-14 adds the Alpine queue, localStorage stash and offline state (select currently fires via `wire:change`).
- Decisions for M4-09..13 logged in specs/decisions.md.
- Next: M4-14 autosave queue, then M4-15 results, M4-16 dashboard resume/abandon + lazy sweep, M4-17 purge.
- Don't edit text files with PowerShell Set-Content (BOM/UTF-8); use sed or Edit. Bash has no php: run php/pint in PowerShell.
- At the M4 stop: full suite, 10-line summary, What to try (M4) checklist in specs/tasks.md, wait for `approved: start M5`.
