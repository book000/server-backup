# Copilot code review instructions

Standalone PHP CLI backup scripts (MySQL dumps + rsync file backups) run on a Linux host via cron. No framework, no build, no dependency manager, no automated tests. Procedural PHP, 4-space indentation.

## Review priorities

- **Secret leakage**: `config.json` holds Discord/GitHub tokens, DB passwords, and SSH passphrases. Flag any change that writes these values into log files, Discord messages, echoed output, or committed files.
- **Shell command injection**: commands are built by string-interpolating config-derived values into `system()` and `expect` scripts (e.g. `mysqldump ... $databaseName`, `rsync ... $FROM`). Flag any change that interpolates new externally-influenced values into a shell string without quoting/escaping, and prefer argument passing over string building.
- **Destructive filesystem/DB operations**: `deleteFolder()` recursively `unlink`/`rmdir`s, and retention cleanup deletes dated backup directories. Verify path construction and the date/retention comparison so the wrong directory can never be removed. rsync uses `--delete`.
- **Error/exit handling**: CLI scripts `exit(1)` on missing config, JSON parse failure, unknown server, or DB connection failure. New failure paths should follow the same explicit-exit pattern rather than continuing silently. Check `$ret`/status codes returned by `system()` and HTTP calls.
- **Cross-job consistency**: `DBBackup/main.php`, `EveryDayBackup/main.php`, `FullBackup/main.php` share near-identical structure. If a fix applies to shared logic, confirm it was applied to all relevant jobs.

## Project conventions (do not flag as errors)

- Code comments in Japanese are intentional; do not request translation to English.
- Absolute binary paths (`/usr/bin/mysqldump`, `/usr/bin/bash`, `/usr/bin/tar`) and the hardcoded `/mnt/WHITEBOX/...` archive mount are environment-specific and expected.
- Procedural, framework-less PHP is intentional; do not suggest introducing a framework, Composer, or an ORM.
- Global `$dpr` (the Discord reporter) is used inside the error handler by design.

## Notes

- There is no test suite; do not ask for tests to be added as a blocking condition, but do point out logic that would be hard to verify manually.
- Keep review comments specific and actionable; cite the file and line and the concrete risk.
