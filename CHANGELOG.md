# [1.6.0](https://github.com/flydev-fr/Duplicator/compare/v1.5.5...v1.6.0) (2026-11-17)


### Bug Fixes

* Apply FTP max packages limit without a deadline ([3b15483](https://github.com/flydev-fr/Duplicator/commit/3b1548317173c71f6785475f528e12cda77b7940)), closes [#27](https://github.com/flydev-fr/Duplicator/issues/27)
* Avoid a PHP 8 deprecation notice in the installer extraction step ([ff91a1d](https://github.com/flydev-fr/Duplicator/commit/ff91a1dc59950b619dca02cca48596c679e00c52))
* Harden package handling in the admin and the native backup mode ([0bac0be](https://github.com/flydev-fr/Duplicator/commit/0bac0bee744196bd8ebcd81b0554c324a73be977))
* Keep packages uploaded to Amazon S3 private ([3a5a624](https://github.com/flydev-fr/Duplicator/commit/3a5a6249f519ba8c3ae2d8aa48b44f41364fd952))
* Never leave a plain package behind when encryption is on ([0759723](https://github.com/flydev-fr/Duplicator/commit/075972369bc71725a5f66582415cfd37f12856fd))
* Require an installer key and write config.php safely ([c42ff5a](https://github.com/flydev-fr/Duplicator/commit/c42ff5ad4e2a0913ae56bd89a28c30dade2955a6))
* Resolve module files from the module's own folder ([b0e1707](https://github.com/flydev-fr/Duplicator/commit/b0e1707759f34fb802522c92722bc594c23ddc41)), closes [#59](https://github.com/flydev-fr/Duplicator/issues/59)
* Restore large MySQL 8 dumps in the installer ([126f20a](https://github.com/flydev-fr/Duplicator/commit/126f20acf0cad18e4380b59ef63e3f7ee44ed6b0)), closes [#58](https://github.com/flydev-fr/Duplicator/issues/58) [#2](https://github.com/flydev-fr/Duplicator/issues/2)
* Show the keygen command as code in the encryption settings ([599ff88](https://github.com/flydev-fr/Duplicator/commit/599ff8897ee4baa0dab69b8791444008db427d2a))


### Features

* Download the encryption tool as a single file ([394b030](https://github.com/flydev-fr/Duplicator/commit/394b03016d6616c9907c2e0f242b6dca03d26b16))
* Encrypt packages with the PHP sodium extension ([73a7b4b](https://github.com/flydev-fr/Duplicator/commit/73a7b4b1524f801817c850f06f29d9b1dee21f30))
* Link to the GitHub repository from the installer footer ([566a47f](https://github.com/flydev-fr/Duplicator/commit/566a47f6348a2e8d3a2c0670099f634b80747328))
* New admin app for packages and settings ([4f6bd9e](https://github.com/flydev-fr/Duplicator/commit/4f6bd9e35db25e16672ad74dc12c4682ef324fd3))
* Redesign the settings and remove the old assets ([291dbd6](https://github.com/flydev-fr/Duplicator/commit/291dbd66d4da735b7cdf8dfd8ccc6f8207427f57))
* Refresh the installer UI ([dada1cd](https://github.com/flydev-fr/Duplicator/commit/dada1cd5443e28d5ee9a34d5d64cb69ca6181cd8))
* Sober installer design with the Duplicator and ProcessWire logos ([b5afd66](https://github.com/flydev-fr/Duplicator/commit/b5afd660d32103286abcede9fd21910783d4fcd3))
* Square corners in the installer, like ProcessWire ([1ad55a7](https://github.com/flydev-fr/Duplicator/commit/1ad55a757df944e46a86e4cb32ced62ee9dc630e))
* Take the colours from the ProcessWire admin theme ([9d3bea6](https://github.com/flydev-fr/Duplicator/commit/9d3bea68c88200da4e2d98ce40fab13f5224b159))



## [1.5.5](https://github.com/flydev-fr/Duplicator/compare/v1.5.4...v1.5.5) (2026-11-17)



## [1.5.4](https://github.com/flydev-fr/Duplicator/compare/v1.5.3...v1.5.4) (2024-06-07)


### Bug Fixes

* PHP deprecated var [#51](https://github.com/flydev-fr/Duplicator/issues/51) ([8c333f0](https://github.com/flydev-fr/Duplicator/commit/8c333f065a67eb622bb0dd4a0277dd753f69f65e)), closes [#48](https://github.com/flydev-fr/Duplicator/issues/48)



## [1.5.3](https://github.com/flydev-fr/Duplicator/compare/v1.5.2...v1.5.3) (2024-06-05)


### Bug Fixes

* Avoid error when first configuring S3 backups [#49](https://github.com/flydev-fr/Duplicator/issues/49) ([d338fc2](https://github.com/flydev-fr/Duplicator/commit/d338fc2e53778de595c7d99d23c71b2c4c7c1b35))



## [1.5.2](https://github.com/flydev-fr/Duplicator/compare/v1.5.1...v1.5.2) (2023-11-01)


### Bug Fixes

* `set_time_limit` is called only when web cron is used ([4fe8e2b](https://github.com/flydev-fr/Duplicator/commit/4fe8e2babadf42ac08d8dadcd032436b51b3c4d7))
* Added missing variable `PORT` and placeholder in shell scripts ([19dd37e](https://github.com/flydev-fr/Duplicator/commit/19dd37e51cd6fc466237d1858afe40758ed7c8dc))
* Check if timestamp is not false ([c338f15](https://github.com/flydev-fr/Duplicator/commit/c338f15424d22806d982e0ce2c55e5fc84910383))
* class "DUP_Logs" not found ([67b90c7](https://github.com/flydev-fr/Duplicator/commit/67b90c7e87fda7ada27b9c24e890a46beafed38e))
* Remove AWS3 subfolder name from returned filename ([073ca0a](https://github.com/flydev-fr/Duplicator/commit/073ca0af46ddcf9f35e1eff597892ace3f7da200))



