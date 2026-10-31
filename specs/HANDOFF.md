# Handoff (overwrite each session, max 20 lines)
Updated: 2026-10-31
- PAUSED (not a milestone stop). Branch: m4-individual-tests.
- M4 done and ticked: M4-01..05 (see git log), M4-06 StartAttempt (5437a18), M4-07 answer actions (9afec80), M4-08 FinalizeAttempt + sweep + schedule (c8b6c0c).
- tests/Feature/Attempts and tests/Arch are green. Full suite not run since M4-01 (502 green then); run it before the next migration or at the M4 stop.
- API: `StartAttempt::individual(user, subtest, topicIds, count, timing, allowShortfall)` returns `StartResult` (attempt | active | available). Answer actions take (user, attempt, position[, key]) via `AttemptItems::change()`; they raise `AuthorizationException` or `AnswerRefused`.
- `AttemptClock::guard()` finalizes an expired attempt as timeout and returns true (caller stops and redirects). `FinalizeExpiredAttempts::forUser()` is the lazy sweep for Dashboard, Runner mount and sign-in (wire it up in M4-10/M4-16).
- Abandon goes through FinalizeAttempt, so it stores a result with end_reason abandoned.
- Decisions for M4-06..08 are logged in specs/decisions.md (2026-10-31).
- Next: M4-09 ConfigureIndividual at /practice (preview the available count with ItemPool/TestAssembler, offer the shortfall, then call StartAttempt with allowShortfall; on `active`, offer resume or abandon), then M4-10 and M4-11.
- Planned pauses: group 2 ends after M4-11, group 3 = M4-12 to M4-17.
- Don't edit text files with PowerShell Set-Content (it adds a BOM and mangles UTF-8); use sed or Edit.
- At the M4 stop: full suite, 10-line summary, What to try (M4) checklist in specs/tasks.md, wait for `approved: start M5`.
