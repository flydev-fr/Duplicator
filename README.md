<div align="center">

<img src="Assets/Duplicator-logo-xl-v2.png" alt="Duplicator" width="360" />

**Back up a whole ProcessWire site, files and database, and move it to another server.**

![Current version](https://img.shields.io/github/package-json/v/flydev-fr/duplicator)
![GitHub Repo stars](https://img.shields.io/github/stars/flydev-fr/duplicator)
[![ProcessWire](https://img.shields.io/badge/processwire-3.0.34%2B-lightblue)](https://processwire.com)
[![Support thread](https://img.shields.io/badge/support-forum_thread-gray)](https://processwire.com/talk/topic/15345-duplicator-backup-and-move-sites/)

</div>

---

Duplicator builds a **package**: one ZIP file with your site files and a dump of its database. Packages are kept on the server, on an FTP server or in the cloud (Dropbox, Google Drive, Amazon S3), and can be encrypted. To restore a site, or to move it to a new host, you upload a package with `installer.php` and follow the steps in your browser.

## Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [The Duplicator page](#the-duplicator-page)
- [Settings](#settings)
- [Storages](#storages)
- [Scheduled backups](#scheduled-backups)
- [Encryption](#encryption)
- [Restoring or moving a site](#restoring-or-moving-a-site)
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Development](#development)
- [Contributors](#contributors)

## Features

- One-click backup of the site files and the database, from the admin
- Choose what to leave out: core, uploaded files, image variations, logs, cache, sessions, file types, paths
- Five storages, used together or alone: this server, FTP/FTPS, Dropbox, Google Drive, Amazon S3
- Scheduled backups with cron, PwCron or LazyCron
- Retention: keep at most N packages, delete packages older than a given age
- Package encryption with a key pair (PHP sodium, no library to install)
- A browser installer to restore a package on any server, protected by a key
- Database dump with PHP (works everywhere) or `mysqldump` (faster on large databases)
- An admin page that follows the ProcessWire admin theme, dark mode included

## Requirements

| | |
|---|---|
| ProcessWire | 3.0.34 or newer |
| PHP | The `zip` extension, to build packages |
| Encryption (optional) | The `sodium` extension, bundled with PHP since 7.2 |
| Cloud storages (optional) | The PHP library of each provider, see [Cloud libraries](#cloud-libraries) |
| `mysqldump` mode (optional) | `mysqldump` and a shell the web server can run |

The installer needs PHP 5.4 or newer on the target server and a MySQL or MariaDB database.

## Installation

1. Download the module from the [modules directory](https://processwire.com/modules/duplicator/) or from the [releases](https://github.com/flydev-fr/Duplicator/releases).
2. Put its files in `site/modules/Duplicator/`.
3. In the admin, go to **Modules > Refresh**, then install **Duplicator**. It also installs **ProcessDuplicator**, the admin page.
4. Go to **Setup > Duplicator**.

### Cloud libraries

Skip this if you only use this server or FTP. Each cloud storage needs its PHP library. Install only the ones you use.

**With Composer**, in the root folder of the site:

```bash
composer require kunalvarma05/dropbox-php-sdk   # Dropbox
composer require google/apiclient               # Google Drive
composer require aws/aws-sdk-php                # Amazon S3
```

**Without Composer**, download the [Duplicator-SDKs](https://github.com/flydev-fr/Duplicator-SDKs/archive/master.zip) archive. Each provider folder holds an `SDKs` folder: upload it into `site/modules/Duplicator/`, merging it with an existing one.

A storage stays greyed out in the settings until its library is found.

## Quick start

1. Go to **Setup > Duplicator > Settings**.
2. Choose at least one storage. **This server** is on by default.
3. Click **Backup now** on the **Packages** tab.
4. Download the package and `installer.php` from the list.

That's all you need for a manual backup. For automatic backups, see [Scheduled backups](#scheduled-backups).

## The Duplicator page

**Setup > Duplicator** has three tabs.

- **Packages** - every package of every storage, with its size, date and whether it is encrypted. From there you can:
  - build a package now
  - download a package, or `installer.php`
  - copy a cloud package to the local folder
  - delete one package, or all local packages (cloud packages are kept)
- **Settings** - all the settings below, plus a summary of the server: free disk space, PHP extensions, `mysqldump`, cloud libraries.
- **Log** - the latest lines of the Duplicator log.

### Permissions

- Users with the `duplicator` permission can build, download, copy and delete packages.
- Only superusers can change the settings.

The classic settings page (**Modules > Configure > Duplicator**) holds the same settings and still works.

Passwords, tokens and keys (FTP password, Dropbox token, Google key, AWS secret key) are never sent back to the browser. Leave such a field empty to keep the saved value.

## Settings

### Package

| Setting | What it does |
|---|---|
| File name | Packages are named `date-name.package2.zip`. Empty uses the site host name. |
| Included files | Everything in the site folder is included, except what you leave out: ProcessWire core (`wire/`), uploaded files, image variations, logs, cache, sessions, previous packages. The database is always included. |
| Excluded file types | Files with these extensions are left out. |
| Excluded paths and patterns | One per line: a folder like `/site/assets/files/1042/`, a file name, an extension like `*.log`, or a regular expression between `%` signs. |

Leave out **Previous packages** (on by default): otherwise each package contains all the older ones.

Always left out, wherever they are: version control files (`.git`, `.github`, `.gitignore`, `.svn`...), editor and AI tool folders (`.idea`, `.vscode`, `.cursor`, `.claude`...) and system files (`.DS_Store`, `Thumbs.db`...). Other hidden files, such as `.htaccess` or `.user.ini`, are kept.

**Symbolic links** (and Windows junctions), for example a module linked from another repository:
- a link to a folder outside the site is packaged as a real folder, so the restored site works on its own
- a link that PHP cannot read because of `open_basedir`, a broken link, or a link back into the site is left out, without PHP warnings

Every link left out is listed in the Duplicator log and in the package `.log` file.

### Storage

Each package is saved to every storage turned on. See [Storages](#storages) for each one.

### Schedule and retention

| Setting | What it does |
|---|---|
| Automatic backups | Off (or your server's scheduler), LazyCron, or PwCron. See [Scheduled backups](#scheduled-backups). |
| Interval | LazyCron only: how often a visit may start a backup, or when a user with the `duplicator` permission logs out. |
| Keep at most | From 1 to 100 packages. Older packages are deleted after each new one, in every storage. |
| Delete packages older than | Never, 1 day, 1 week, 1 month or 1 year. |

### Encryption settings

| Setting | What it does |
|---|---|
| Encrypt packages | Encrypt every package before it is stored. |
| Public key | Your public key, starting with `dup-pk1:`. |

See [Encryption](#encryption) to create your keys.

### Advanced

| Setting | What it does |
|---|---|
| Database dump | **PHP** works on every server. **mysqldump** is faster for large databases and runs a shell script. It is offered only when `mysqldump` is found. |
| Script permissions | Applied to the generated dump script. `0700` lets only the web server user read and run it. |
| Dump script | The script used in mysqldump mode. Placeholders such as `%%DATABASE%%` are replaced when it runs. |
| Write the archive every (MB) | Saves memory on very large sites. `0` writes the archive once, at the end. |

## Storages

### This server

Packages go to `site/assets/backups/` by default. You can set another folder: an absolute path, or `~/` for a folder inside the site. Keep it out of the web root when you can.

- **Clean up old logs** - removes the log files of deleted packages.
- **Delete the package from this server after upload** - when this server is not a storage, removes the local package once it is uploaded.

### FTP

Any FTP server: host name, port, user name, password, folder (created if needed) and timeout.

- **Encrypted connection** - FTPS (FTP over TLS). Use it whenever the server supports it.
- **Passive mode** - needed by most servers behind a firewall.

### Dropbox

Packages go to the `Apps/Duplicator` folder.

1. Create an app in the [Dropbox App Console](https://www.dropbox.com/developers/apps).
2. Generate an access token and paste it in the settings.

### Google Drive

Packages are uploaded with a Google service account.

1. In the [Google Cloud console](https://console.cloud.google.com/apis/credentials), create a service account and enable the Google Drive API.
2. Create a JSON key for this account and paste its content in **Service account key**.
3. In **Share with**, enter your own Google account to see the packages in your Drive.

A tutorial is available on the [forum thread](https://processwire.com/talk/topic/15345-duplicator-backup-and-move-sites/?page=2#comment-139376).

### Amazon S3

1. Create a bucket and an IAM user in the [AWS console](https://console.aws.amazon.com/iam/home).
2. Give this user access to the bucket only.
3. Enter the access key, secret key, bucket name, an optional sub folder and the region.

Packages are uploaded **private**. Also turn on **Block public access** on the bucket.

Bucket names follow the DNS rules: 3 to 63 characters, lowercase letters, digits, dots and dashes, no underscore, no dash at the end, no two dots in a row, no dash next to a dot.

## Scheduled backups

Choose one of these ways in **Settings > Schedule and retention**.

### Your server's scheduler (recommended)

Set **Automatic backups** to *Off, or with your server's scheduler*, then add a cron job that runs `cron.php`. For example, every night at 00:04:

```cron
4 0 * * * php /path/to/site/modules/Duplicator/cron.php >/dev/null 2>&1
```

Edit your crontab with `crontab -e`, or use the cron page of your hosting panel. The exact command, with the right path, is shown under the setting.

`cron.php` runs from the command line only. It answers 403 over HTTP.

On Windows, use the Task Scheduler to run the same `php` command.

### PwCron

With the [PwCron](https://processwire.com/modules/pwcron/) module installed, choose *With the PwCron module* and run PwCron's own `cron.php` from your scheduler.

### LazyCron

Choose *On site visits (LazyCron)* and an **Interval**. Backups start on a page view, so this can slow down the visit that starts one. Use it when your host has no cron.

### Cron intervals

| When | Expression |
|---|---|
| Every 15 minutes | `*/15 * * * *` |
| Every hour | `0 * * * *` |
| Every 6 hours | `0 */6 * * *` |
| Once a day | `4 0 * * *` |
| Once a week | `4 0 * * 0` |
| Once a month | `4 0 1 * *` |

## Encryption

Packages can be encrypted before they are stored or uploaded, in every storage.

Encryption uses a key pair:
- the server only gets the **public key**: it can encrypt packages, never decrypt them
- the **secret key** stays with you, outside the server, and is needed to restore a package

It needs the PHP `sodium` extension, bundled with PHP since 7.2. If it is disabled, enable `extension=sodium` in `php.ini`. There is no library to install.

### Set it up

1. Download `dupcrypt.php` from **Setup > Duplicator > Settings > Encryption**. It is a single file and does not need ProcessWire.
2. **On your own computer**, not on the server, open a terminal where you saved it and run:
   ```bash
   php dupcrypt.php keygen
   ```
   It prints a public key (`dup-pk1:...`) and a secret key (`dup-sk1:...`).
3. Store the secret key in your password manager. **Without it, your encrypted packages cannot be restored.**
4. In the settings, turn on **Encrypt packages**, paste the public key and save.

If a package cannot be encrypted (missing extension, invalid key, disk full...), it is deleted and not uploaded. An unencrypted copy never leaves the server, and the error is written in the log. If the job stops during the build or the encryption (timeout, fatal error), the unencrypted package is removed at the end of the request.

### Restore an encrypted package

Encrypted packages keep their name. The installer recognizes them and asks for the secret key before extracting.

Send the secret key to the installer over **HTTPS** only. Otherwise, decrypt the package on your computer and upload the decrypted one:

```bash
php dupcrypt.php decrypt 2026-01-01_00-00-00-example.com.package2.zip decrypted.zip
```

The secret key is asked in the terminal, or read from the `DUPLICATOR_SECRET_KEY` environment variable.

### Limits

- Encryption protects the **confidentiality** of packages. It does not prove who made a package: the public key is not secret, so someone who can write to your storage could put a package of their own there. Only restore packages from storages you trust.
- The `.log` file written next to each package lists the files it contains, unencrypted. It stays in the local folder and is not uploaded.

### How it works

Each package is encrypted with a random key, using XChaCha20-Poly1305 by chunks of 1 MiB (`crypto_secretstream`). This key is sealed with your public key (`crypto_box_seal`). A wrong key, a damaged or a truncated package is detected and refused.

## Restoring or moving a site

The same steps restore a site on its own server or move it to a new one.

### 1. Upload

Upload the package and `installer.php` (from **Setup > Duplicator**) to the root folder of the target site. This folder should be empty: the installer overwrites existing files.

Create the database first if your host does not let the installer create it. The user needs these rights: select, insert, update, delete, create, alter, index, drop, create temporary tables and lock tables.

### 2. Unlock the installer

Open `https://your-site/installer.php` in your browser.

The first visit writes a key to `installer-key.php`, next to the installer. Open this file with FTP or your hosting file manager and copy the key into the page. The file shows nothing over HTTP, so only someone with access to the files can run the installer.

### 3. Follow the steps

1. **Check** - PHP version and extensions, folder permissions.
2. **Package** - choose the package. For an encrypted package, paste the secret key.
3. **Extract** - the files are extracted in batches, one request each, so no request hits the PHP time limit. The page goes on by itself and shows the progress.
4. **Database** - host, port, name, user, password, charset and engine. An existing database is overwritten. The installer then imports the dump and updates `site/config.php`, including `$config->httpHosts`.
5. **Finish** - removes the installer, its key, the package and the temporary files.

### 4. After the restore

- Log in to the admin and check the site.
- Make sure `installer.php`, `installer-key.php`, the package and `duplicator-temp/` are gone. Remove them by hand if the Finish step could not.
- If the host name changed, check `$config->httpHosts` in `site/config.php`.

## Security

- **Never leave the installer on a server.** Anyone with its key could restore a package over your site. The Finish step removes it.
- **Packages contain everything**: the database, `site/config.php` with its passwords and salt, uploaded files. Keep them private, and encrypt them when they leave your server.
- **Amazon S3**: packages are private, and **Block public access** should be on for the bucket.
- **Local folder**: keep packages out of the web root when you can. The default folder, `site/assets/backups/`, is protected by ProcessWire's `.htaccess` on Apache. On nginx, deny access to it in your server configuration.
- **cron.php** runs from the command line only.

To report a vulnerability, please use [GitHub's private reporting](https://github.com/flydev-fr/Duplicator/security/advisories/new) rather than a public issue.

## Troubleshooting

### Timeouts or memory errors

Usually on shared hosting with low limits. Try in this order:
- leave out large folders (uploaded files, image variations, cache) and copy them with FTP
- set **Write the archive every (MB)** in Advanced, for example to 200
- use the **mysqldump** mode for large databases
- build packages with cron rather than from the admin: the command line usually has longer time limits

### A storage is greyed out

Its PHP library is missing. See [Cloud libraries](#cloud-libraries).

### "Backup now" does nothing, or the app shows an unexpected answer

Check `$config->httpHosts` in `site/config.php`: it must list every host name the admin is reached with, for example both `example.com` and `www.example.com`.

### PHP warnings about open_basedir

They came from symbolic links to folders PHP may not read, such as modules linked from another repository. Since 1.6.1 these links are left out without warnings, and listed in the log.

### "The file containing the MySQL database couldn't be found"

The package was built without its database dump. Since 1.6.1 Duplicator refuses to save such a package and writes the reason in the log. Build a new package.

### "No storage is enabled"

Turn on at least one storage in the settings.

### The package was built but not uploaded

Look at the **Log** tab. Wrong credentials, a full disk, or a failed encryption are written there.

### The mysqldump mode is not offered

`mysqldump` was not found on the server, or the web server cannot run shell commands. Use the PHP mode.

### Where are the logs?

On the **Log** tab, and in **Setup > Logs > duplicator**. Each package also has a `.log` file next to it in the local folder.

If you are stuck, ask on the [support thread](https://processwire.com/talk/topic/15345-duplicator-backup-and-move-sites/) or open an [issue](https://github.com/flydev-fr/Duplicator/issues).

## Development

### Layout

| Path | Content |
|---|---|
| `Duplicator.module` | Backups, storages, settings |
| `ProcessDuplicator.module` | The Setup > Duplicator page and its API |
| `Classes/` | Database dump, storage clients, encryption, admin API |
| `Deploy/installer.php` | The installer, a single file |
| `scripts/dupcrypt.php` | The command line encryption tool |
| `ui/` | Sources of the admin app (Vite, React, TypeScript) |
| `Assets/app/` | The built admin app, committed |
| `tests/` | Tests |

### Admin app

The app is built from `ui/` into `Assets/app/`. The built files are committed, so installing the module needs no build. After a change in `ui/`:

```bash
cd ui
npm ci
npm run build
```

Commit `Assets/app/` with your change. The CI checks that it matches the sources.

### Tests

```bash
php tests/run.php
```

The CI lints every PHP file and runs the tests on PHP 8.1 to 8.4.

### Releases

Commits follow [Conventional Commits](https://www.conventionalcommits.org/). Each push to `master` with a `fix:` or `feat:` commit creates a release: `fix:` bumps the patch version, `feat:` the minor version. The changelog is written from the commit messages.

## Contributors

Thanks to all the contributors for their help and support.

<!--GAMFC--><a href="https://github.com/flydev-fr" title="flydev-fr"><img src="https://avatars.githubusercontent.com/u/11658616?v=4" width="32;" alt="flydev-fr"/></a>
<a href="https://github.com/adrianbj" title="Adrian Jones"><img src="https://avatars.githubusercontent.com/u/871211?v=4" width="32;" alt="Adrian Jones"/></a>
<a href="https://github.com/netcarver" title="Steve"><img src="https://avatars.githubusercontent.com/u/16049?v=4" width="32;" alt="Steve"/></a>
<a href="https://github.com/StanLindsey" title="Stan Lindsey"><img src="https://avatars.githubusercontent.com/u/3317882?v=4" width="32;" alt="Stan Lindsey"/></a><!--GAMFC-END-->

### Special thanks

- The Duplicator logo is by [@szabeszg](https://github.com/szabeszg)
- The ProcessWire community
