## [Unreleased]
- new logging mechanism
- auto-deploy thought FTP/FTPS
- installer optional deletion

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
- ___cronJob() is now hookable.
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
- changed all _() call to __() for translation string


