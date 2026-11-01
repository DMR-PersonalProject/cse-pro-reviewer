# Handoff (overwrite each session, max 20 lines)
Updated: 2026-11-01
- M5 MILESTONE STOP. Branch: m5-mock-exam (not merged, tagged or pushed). Waiting for `approved: start M6`.
- Done: M5-01 (4e65860), M5-02 (05d5351), M5-03 (de6117a), M5-04 (503297e), M5-05 (5a0f767), M5-06 (ea19e43).
- Full suite green (680 tests); perf group green (`php artisan test --compact --group=perf`).
- On approval: merge `--no-ff` into main, tag `m5-done`, push main + tag, create `m6-results-leaderboard`.
- Runner: `navigatorGroups` (computed) gives per-subtest name/answered/flagged/total/entries in subtest `position` order.
  Filters (all / flagged / unanswered) and the mobile drawer are Alpine-only in `components/attempt/navigator`; `N` opens the drawer.
- Submit: `confirmSubmit()` / `cancelSubmit()` toggle `$confirmingSubmit`; `submitSummary` (computed) has counts and first positions.
  Dialog is `components/attempt/submit-dialog` (native `<dialog>`, Escape = cancel). `goTo` closes it.
- Mock: `TestAssembler::assembleMock`, `StartAttempt::mock(user, timing, allowShortfall)`, setup at /mock (`ConfigureMock`).
- Mock tests: tests/Feature/Attempts/MockNavigatorTest.php, StartMockTest.php, Assembly/MockAssemblerTest.php.
- Don't edit text files with PowerShell Set-Content (BOM/UTF-8); use sed or Edit. No python in Bash. Bash has no php: run php/pint in PowerShell.
