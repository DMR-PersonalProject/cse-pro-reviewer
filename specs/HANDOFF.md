# Handoff (overwrite each session, max 20 lines)
Updated: 2026-11-01
- M6 in progress on branch `m6-results-leaderboard` (M5 merged, tagged `m5-done`, pushed).
- M6 groups: G1 = M6-04, M6-01, M6-02, M6-03 (done); G2 = M6-05, M6-06, M6-07 (next).
- Done: M6-04 (294f510), M6-01 (6bac9bd), M6-02 (0a7ae04), M6-03 (a40f3ea).
- `Domain\Leaderboard\Eligibility`: `apply(Builder)` scope (qualified `attempts.*` columns) + `reason(Attempt)` → `IneligibleReason` enum (labels shown on results).
- Results: `Results\Show` has subtest/topic tables + eligibility line; `Results\Review` at `results.review` (`#[Url] filter`, 20/page, snapshot only).
- Progress: `Progress\Index` at `/progress`; `Topic::attemptAnswers` relation for accuracy `withCount`s.
- Next: M6-05 `LeaderboardQuery` (only raw SQL allowed: ROW_NUMBER window; build on `Eligibility::apply`), test-first.
- Tests: tests/Feature/Leaderboard/EligibilityTest.php, tests/Feature/Results/{ResultsBreakdown,Review,Progress}Test.php.
- Don't edit text files with PowerShell Set-Content (BOM/UTF-8); use sed or Edit. Bash has no php: run php/pint in PowerShell.
