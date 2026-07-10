# CLAUDE.md

## Overview

Standalone PHP CLI scripts that back up remote servers, run on a Linux backup host (typically via cron). Three backup jobs:

- **DBBackup**: dumps every MySQL table with `mysqldump`, gzips each to `backups/<server>/DBBackup/<date>/`.
- **EveryDayBackup** / **FullBackup**: pull files from a remote host with `rsync` over SSH (`rsync.sh` + `expect` for the key passphrase).

Each job reports progress to Discord and deletes backups older than the retention window. In the first 7 days of each month, DBBackup and FullBackup additionally copy an archive to a mounted NAS (EveryDayBackup does not). Uncaught PHP errors are reported to Discord and opened as GitHub issues.

## Running

No build step, no dependency manager, no test suite. Run a job directly with PHP CLI on the backup host:

```bash
php DBBackup/main.php -s <server_name>
php EveryDayBackup/main.php -s <server_name>
php FullBackup/main.php -s <server_name>
```

`<server_name>` must exist as a key under the matching backup type in `config.json`. There is no way to exercise these end-to-end without a real `config.json`, reachable target hosts, and the external binaries below — verify changes by reading the code and, where possible, running the affected PHP/bash in isolation.

## External dependencies (must exist on the host)

`php` (CLI), `mysqldump`, `gzip`, `tar`, `rsync`, `expect`, `bash`, `du`. Several are referenced by absolute path (`/usr/bin/mysqldump`, `/usr/bin/bash`, `/usr/bin/tar`); the monthly archive path `/mnt/WHITEBOX/DataTomachi/backup/...` is a hardcoded mount point.

## Layout

- `DBBackup/`, `EveryDayBackup/`, `FullBackup/` — one `main.php` per job; the rsync jobs also have `rsync.sh` (the actual rsync invocation, driven by `expect`) and `ignores` (rsync `--exclude-from` list).
- `DiscordProgressReporter.class.php` — buffered Discord messaging (`pp`/`flush`/`send`), also gzip-compresses previous log files on construction.
- `errorHandler.lib.php` — `registErrorHandler()` installs shutdown + error handlers that alert Discord and create/reopen/comment GitHub issues.
- `libs.inc.php` — helpers: `formatMicrotime`, `byte_format`, `deleteFolder` (recursive), `calcSize` (via `du`).
- `config.json`, `logs/`, `backups/`, `keys/` — runtime data, all git-ignored. Never commit them.

## config.json shape

Top level: `discordToken`, `discordChannel`, `githubToken`, `repo`. Then one key per backup type (`DBBackup`, `EveryDayBackup`, `FullBackup`), each holding `DATA-HOLD-SEC` (retention in seconds) plus a map of `<server_name>` entries:

- DBBackup entry: `hostname`, `port`, `username`, `password`.
- EveryDayBackup / FullBackup entry: `hostname`, `port`, `username`, `identity` (SSH key path), `passphrase`, `from` (remote source path).

## Conventions

- Procedural PHP, 4-space indentation, no framework.
- Code comments are written in Japanese; keep that when editing existing comments. Error/echo messages are English.
- The three `main.php` files share almost identical structure (config load → Discord reporter → backup → retention cleanup). When changing shared behavior, apply the same edit to all relevant jobs so they stay in sync.

## Security / prohibitions

- `config.json` and `keys/` hold live secrets (Discord bot token, GitHub token, DB passwords, SSH keys and passphrases). Never commit them, never echo their values into logs or Discord messages.
- Shell commands are assembled by interpolating config-derived values into strings passed to `system()`/`expect`. When touching that code, do not widen the injection surface; prefer passing values as arguments over embedding them in a shell string.
