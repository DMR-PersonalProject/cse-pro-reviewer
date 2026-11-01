# Handoff (overwrite each session, max 20 lines)
Updated: 2026-11-01
- PAUSED in M5 after group 1. Branch: m5-mock-exam. M4 merged into main, tagged m4-done, pushed.
- Done: M5-01 (4e65860), M5-02 (05d5351), M5-03 (de6117a), M5-06 (ea19e43). Touched-area tests green; full suite not run since M4.
- Next: group 2 = M5-04 navigator for 170 items (test-first), then M5-05 submit confirmation. Then the M5 milestone stop (run `--group=perf` too).
- Mock: `TestAssembler::assembleMock(user, split, seed)` gives a `MockAssembly` (per-subtest `AssemblyResult` by code, order by subtest `position`; `subtestCounts()`).
- `StartAttempt::mock(user, timing, allowShortfall)`: mode combined, no subtest_id, timed deadline = total_minutes (partial: ⌈total × given ÷ configured⌉). Short bank: `StartResult->subtests` (code => available/requested).
- Setup page: `App\Livewire\Tests\ConfigureMock` at /mock (`mock` route), linked from the dashboard. Timed/untimed only.
- Perf: tests/Feature/Perf, group `perf` excluded in phpunit.xml; run with `php artisan test --compact --group=perf`.
- Shared-file check (M4): no item or passage shares a stored file; logged in specs/decisions.md.
- Decisions for M5-01..03 and 06 are logged in specs/decisions.md.
- Mock-bank tests are slow (~15 s each, 340 items); run them by path.
- Don't edit text files with PowerShell Set-Content (BOM/UTF-8); use sed or Edit. sed with `\n` in replacements silently failed on `use` lines this session: check with grep or use Edit. Bash has no php: run php/pint in PowerShell.
