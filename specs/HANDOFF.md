# Handoff (overwrite each session, max 20 lines)
Updated: 2026-11-01
- M6 complete on branch `m6-results-leaderboard`; milestone stop, waiting for `approved: start M7`. Not merged, tagged or pushed.
- Done: M6-04 (294f510), M6-01 (6bac9bd), M6-02 (0a7ae04), M6-03 (a40f3ea), M6-05 (136fed4), M6-06 (0d20a26), M6-07 (e12df33). Full suite green (745).
- `LeaderboardQuery::mock(Period)` / `subtest(Subtest, Period)`: two `ROW_NUMBER()` windows on `Eligibility::apply`; rows = rank, ign, score, time_used_seconds. `Period` enum (`all`, `week`, Manila Monday start).
- `tests/Arch/RawSqlTest.php`: raw SQL only in `LeaderboardQuery`; no `full_name` in Leaderboard code/view. `RecountImportBatch` moved off `selectRaw`.
- `Leaderboard\Index` at `/leaderboard` (public, header link), `#[Url] board` (`mock` or subtest code) and `period`.
- Admin `AttemptResource`: read-only, Suspicious/Hidden badges and filters, hide/unhide via `SetAttemptHidden` (reason required, audited).
- On approval: merge `--no-ff` into main, tag `m6-done`, push main + tag, create `m7-polish`.
- Don't edit text files with PowerShell Set-Content (BOM/UTF-8); use sed or Edit. Bash has no php: run php/pint in PowerShell.
