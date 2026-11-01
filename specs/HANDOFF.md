# Handoff (overwrite each session, max 20 lines)
Updated: 2026-11-01 (paused)
- Branch `m7-polish`. M7-01..M7-05 done and committed (last a488e55). Not merged, tagged or pushed.
- M7-06 partly done. In `C:\ai\scratch-dbs`: `dl-mysql\mysql-8.4.11-winx64.zip` unzipped to `mysql\mysql-8.4.11-winx64`; `dl-pg\postgresql-17.11-5-windows-x64-binaries.zip` unzipped to `pg\pgsql` (unused).
- Verified: `mysqld.exe` Authenticode Valid, signer Oracle America, Inc. SHA-256 of both zips logged in specs/decisions.md.
- MySQL data dir initialized (`--initialize-insecure`, root no password) at `C:\ai\scratch-dbs\mysql-data-parent\data`. No DB/user created yet (the first start failed: port 3307 taken).
- Nothing of mine is running. The `mysqld` on 0.0.0.0:3307 is the owner's existing MariaDB Windows service: leave it alone.
- Decisions: scratch MySQL on 127.0.0.1:3308 (`--no-defaults --port=3308 --bind-address=127.0.0.1 --mysqlx=OFF`); PostgreSQL skipped (unsigned, no checksum).
- Left for M7-06: start MySQL on 3308, create throwaway db `cse_portability_test` + user `cse_tmp`@127.0.0.1, run the full suite with DB_* set only in the process env (phpunit.xml `<env>` doesn't force, so process env wins; never .env, never database/database.sqlite), fix real portability bugs only, stop the server, update the decisions.md row, tell owner the scratch folder is safe to delete (don't delete it).
- Then M7-07 (finalize CLAUDE.md commands/versions, README.md, tick tasks), M7 milestone stop (full suite, ≤10-line summary, HANDOFF), and specs/FINAL-CHECK.md (~30 min end-to-end, "as an admin" / "as a user", include ⏳ rows from specs/a11y-checklist.md). Commit; don't merge, tag or push.
- Don't edit text files with PowerShell Set-Content (BOM/UTF-8); use sed or Edit. Bash has no php: run php/pint in PowerShell.
