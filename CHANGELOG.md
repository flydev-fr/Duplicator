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



## [1.5.1](https://github.com/flydev-fr/Duplicator/compare/v1.5.0...v1.5.1) (2023-10-30)


### Bug Fixes

* Added missing `%%port%%` to stub config ([ea30f34](https://github.com/flydev-fr/Duplicator/commit/ea30f3442150375c240895f1247057d87f9700fd))



# [1.5.0](https://github.com/flydev-fr/Duplicator/compare/v1.4.29...v1.5.0) (2023-10-28)


### Bug Fixes

* If zip binary is not found on the system, fallback to wireZipFile ([ad53225](https://github.com/flydev-fr/Duplicator/commit/ad53225f29ad10bf3a9d67ed506b41c7564cf0e3))


### Features

* Set custom dump shell script from configuration ([30c5143](https://github.com/flydev-fr/Duplicator/commit/30c514340a2e5d67c5f934888a528f179fad20a0))



