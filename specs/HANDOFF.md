# Handoff (overwrite each session, max 20 lines)
Updated: 2026-11-02 (M7 milestone stop)
- Branch `m7-polish`. M7-01..M7-07 done and committed. Not merged, tagged or pushed.
- Waiting for the owner's OK to merge `m7-polish` into main (`--no-ff`), tag `m7-done`, push main + tag.
- Fixes this session: `fix(import)` Check file did nothing (submit handler `upload` collided with Livewire's `$wire.upload` alias; renamed to `checkFile`, guard test added); `fix(auth)` admin sign-in landed on `/` when the user app had stored an intended URL (custom Filament LoginResponse keeps only panel URLs).
- M7-06: full suite on MySQL 8.4.11 (scratch, 127.0.0.1:3308) 788/788 after fixing one test that assumed batch id 1. PostgreSQL skipped (owner decision). Server stopped; `C:\ai\scratch-dbs` can be deleted by the owner.
- Full suite on SQLite: 788/788.
- Owner's manual pass: specs/FINAL-CHECK.md (includes the ⏳ rows from specs/a11y-checklist.md).
- After MVP: next phases come from SUGGESTIONS.md, only on the owner's request.
- Don't edit text files with PowerShell Set-Content (BOM/UTF-8); use Edit. Bash has no php: run php/pint in PowerShell.
