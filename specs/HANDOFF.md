# Handoff (overwrite each session, max 20 lines)
Updated: 2026-10-31
- M4 MILESTONE STOP after M4-17. Branch: m4-individual-tests (not merged, tagged or pushed). Full suite: 657 green.
- Waiting for the exact reply `approved: start M5`. Then: merge --no-ff into main, tag m4-done, push main + tag, create m5-mock-exam.
- This session: M4-14 autosave queue (de99d88), M4-15 results page (bc4c53a), M4-16 dashboard resume/abandon + sweep (3c81bc9), M4-17 delete with purge (438c79e).
- Autosave: resources/js/autosave.js (`$store.autosave`, stash `attempt:{id}:pending`). Choices call `save(position, key)`, which calls Runner `answer(position, key)`. That returns false once the attempt has ended, so the stash is kept for the results page.
- Results: `App\Livewire\Results\Show` at /attempts/{id}/results (`results.show`). `replay()` is read-only and counts the not-saved answers once. The review link appears when a `results.review` route exists (M6).
- Purge: `DeleteQuestion::handle(..., purge)` / `DeleteSource`. The placeholder is `DeleteQuestion::PLACEHOLDER`, and `purged_at` is set; the M6 review should show its translated notice based on `purged_at`. The checkbox lives in `TypedDeleteAction` (`purgeLabel`).
- Time format helper: `AttemptClock::format(seconds)`.
- Decisions for M4-14..17 are logged in specs/decisions.md.
- The "What to try (M4)" checklist in specs/tasks.md is for manual checks; it is not ticked by the agent.
- Don't edit text files with PowerShell Set-Content (BOM/UTF-8); use sed or Edit. Bash has no php or python: run php/pint in PowerShell.
