# Handoff (overwrite each session, max 20 lines)
Updated: 2026-10-31
- PAUSED (not a milestone stop). Branch: m4-individual-tests (from main after the M3 merge).
- M3 merged --no-ff into main, tagged m3-done, main + tag pushed.
- M4 group 1 done and ticked: M4-01 attempt schema (e7f4905), M4-02 ItemPool (9bb16e6), M4-03 TestAssembler (1fe7acf, encoding fix 611096c), M4-04 ChoiceShuffler + SnapshotBuilder (4a017ce), M4-05 AttemptClock + Scorer/GeneralRatingV1 (659310e).
- Full suite was 502 green after M4-01; each later task's own tests are green. Run the full suite before the next migration or at the M4 stop.
- Planned pauses: group 2 = M4-06 to M4-11, group 3 = M4-12 to M4-17.
- Next: M4-06 StartAttempt (duration ceil(190 x n / 170), config snapshot from settings, one-active guard with user lockForUpdate, all answer rows in one transaction, passage_key, has_private_items from snapshot source_visibility).
- M4-08 must add AttemptClock::guard() (finalize as timeout) on top of isExpired().
- Decisions logged 2026-10-31: scoring is in M4 (correction); end_reason reuses AttemptStatus; positions start at 1; "seen" = answered in last N attempts; private passage source hides the item from users; snapshot keeps stem media; empty attempt = 0.00, never passes.
- Don't edit text files with PowerShell Set-Content (it adds a BOM and mangles UTF-8); use sed or Edit.
- At the M4 stop: full suite, 10-line summary, What to try (M4) checklist in specs/tasks.md, wait for `approved: start M5`.
