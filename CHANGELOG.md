## [Unreleased]

- auto-deploy thought FTP/FTPS
- installer optional deletion
- UI rewrite

## [1.4.18] - 2022-03-05

### Fix

- PHP-8 Deprecated message

### Update

- UI update
- File reformatting

## [1.4.17] - 2022-03-04

### New

- Backup with native tool

### Fixed

- See commits and ATO changes

## [1.4.16] - 2020-01-13

### Fixed

- Fix: chmod duplicator.sh to 0744 on unix

## [1.4.15] - 2020-01-13

### Added

- Added: backup can be done with native tools (windows version)

## [1.4.14] - 2020-01-13

### Added

- Added: backup can be done with native tools (unix version)

## [1.3.14] - 2020-01-06

**`ATO MASTER version`**

### Fixed

- Fix: undefined variable ($zipFilename)

## [1.3.13-ATO] - 2020-01-06

**`ATO DEVELOPMENT version`**

### Added

- Supporting ~ in local path to prefix input with current webroot ($config->paths->root)

### Fixed

- Fix: Removing colon from default package name (port in URL)
- Fix: Custom filename not used
- Fix: Archive function not returning ZIP (lead to fail of FTP etc.)

## [1.3.12-ATO] - 2019-05-15

**`ATO DEVELOPMENT version`**

### Added

- Duplicator: added a config option to set the quantity of megabytes saved before an archive flush

## [1.3.11-ATO] - 2019-05-14

**`ATO DEVELOPMENT version`**

### Fixed

- ProcessDuplicator: support dash char in domain name

## [1.3.10-ATO] - 2019-03-18

**`ATO DEVELOPMENT version`**

### Added

- Added regexp exclusion feature
- Added checkbox for ProcessWire image variations exclusion (checked by default)

### Fixed

- fix installer.php

### Changed

- zipData revised
- package structure: SQL dump included in site structure zip
- exclusion feature: extensions are now case-insensitive

## [1.2.9] - 2018-04-09

### Added

- added Dropbox v2 support (composer support only)
- added Package Manager shortcut to Duplicator

## [1.1.9] - 2018-04-09

### Fixed

- fix ProcessDuplicator: log file existence check on initialization

## [1.1.8] - 2018-04-09

### Changed

- ProcessDuplicator UI

### Fixed

- fix Duplicator: ZIP file (zipData) work properly on Windows and Unix

## [1.1.7] - 2017-12-12

### Changed

- changed check for cron triggers from ready() to init()
- changed support links in both modules

## [1.1.6] - 2017-12-09

### Added

- added js debug mode

### Fixed

- fix Duplicator: zipData() RecursiveFilterIterator check for unreadable dir
- fix Duplicator: getLocalFolderInfo() check if the destination path exist
- fix ProcessDuplicator: corrected the trash-icon action link
- fix ProcessDuplicator: packages stored on the cloud are shown in the table even if LocalFodler is not used
- various fix in DUP_Util class

## [1.1.5] - 2017-12-07

### Fixed

- fix ProcessDuplicator: checking for mod_userdir is not needed anymore
- fix: packages are retrieved, blended and re-ordered by timestamp

## [1.1.4] - 2017-12-07

### Fixed

- fix ProcessDuplicator: now it work with Apache mod_userdir

## [1.1.3] - 2017-12-07

### Added

- \_\_\_cronJob() is now hookable.

### Fixed

- fix FTP class were DEFINE() was replaced by Constants in earlier version
- fix HookEvent in cronJob()

## [1.1.2] - 2017-12-04

### Added

- added support for running cron jobs wihtout external module

## [1.0.2] - 2017-12-03

### Fixed

- fix the module's version number for the ProcessWire modules directory

### Removed

- removed Dropbox support due to the deprecation of the API v1 since september 2017 - [link](https://blogs.dropbox.com/developers/2017/06/updated-api-v1-deprecation-timeline/)

## [1.0.1] - 2017-12-01

### Fixed

- the installer parse correctly the config.php file on windows machine
- small typo in ProcessDuplicator

## [1.0.0] - 2017-11-30

### Added

- check if ProcessDuplicator module is installed then display the link in the local folder overview to the manager if yes.

### Fixed

- changed all \_() call to \_\_() for translation string
