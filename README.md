![Duplicator](https://s3.us-west-2.amazonaws.com/processwire-forums/monthly_2017_11/Duplicator-logo-v2-Dlong.thumb.png.8898ec8cda79046779b10d8527fc0def.png)

### WIP

- The readme is being re-written.

# Introduction
Duplicator will create a ZIP archive called `package` bundled with a full database backup and all your files.
This package is stored on local disk or in the cloud and can be used to restore your site.
You can use Duplicator to duplicate and deploy/restore your site on a new host. It can be used as backup utility. 

## Install

1. Download the module from the [directory](https://modules.processwire.com/modules/duplicator/) or [Github]()
2. Drop the module files in /site/modules/Duplicator/
3. In your admin, click Modules > Refresh
4. Click "install" for "Duplicator"
6. Go to Setup > Duplicator and build your package!

### SDK Installation (optional)
You can skip these instructions if you plan to only use the built-in features `Local Folder` or `FTP/FTPS`.

If you want to use `GoogleDrive` or `AmazonS3`, you have two choices of type of installation. Using Composer or downloading directly a ZIP archive from the repository.

Installing all SDK are not required, you can choose the one you need.

#### Note:
- `Dropbox` dropped support for the version 1 of the API since september 2017 - [api-v1-deprecation-timeline](https://blogs.dropbox.com/developers/2017/06/updated-api-v1-deprecation-timeline/) - so I removed it from Duplicator until I got the time to code an interface for the API v2
- `GoogleDrive` tested the 2023-03-20 `successfully` ✅
- `AmazonS3` tested the 2017-12-03 `todo` ⏳

### Using Composer
In the root directory of your ProcessWire installation, just type in a terminal :
* `composer require google/apiclient`
* `composer require aws/aws-sdk-php`

### ZIP Archive
After downloading the archive, open it, you will find a folder called **`SDKs`** under each provider named directory. Extract or Upload this folder in the **`Duplicator module folder`**. If the folder already exist, merge it. Example: `/www/public_html/site/modules/Duplicator/SDKs`

Download it from the [**Duplicator-SDKs**](https://github.com/flydev-fr/Duplicator-SDKs/archive/master.zip) repository.

Now you can refresh the Duplicator's settings page, the corresponding checkbox will be enabled.

# Duplicator Features

## Backend Permission
Permission set is `duplicator`. User with this permission can initiate a backup, download or sync a package. The module's settings is only accessible to `superuser`.

## Encryption
- Encryption use libsodium 1.0.18 through Halite 5.0.x or 5.1 and newer
- Version of PHP supported: PHP >= v8.x
  Note: performance on PHP 8.0.x is poor. You must consider using PHP 8.1.x for better performance.

### Windows Install
Use `vcpkg` to install libsodium:
  a. ``cd vcpkg
  b. `.\bootstrap-vcpkg.bat`
  c. `vcpkg install libsodium`

You can find pre-built libraries there: https://download.libsodium.org/libsodium/releases/

### Linux
Run on terminal:
`sudo apt-get install php8.1-sodium`

Once all prequisites installed, insatll Halite by running **composer** in the root directory:
`composer require paragonie/halite:^5`

## CRON job
Actually you have 3 choices to setup a cron job.

### System CRON
#### The standard way
To edit a crontab through the command line, type: `cronjob -e` then add for example the following line to build a package once a day :
> `4 0 * * * php /www/mysite/wwwroot/site/modules/Duplicator/cron.php >/dev/null 2>&1`

### PwCron
#### modules
You can rely on [PwCron](https://modules.processwire.com/modules/pwcron/) to setup the job.
> `4 0 * * * php /www/mysite/wwwroot/site/modules/PwCron/cron.php >/dev/null 2>&1`

### LazyCron
#### modules
Because it's triggered by a pageview, this choice can slowdown your site - [LazyCron documentation](https://modules.processwire.com/modules/lazy-cron/)

### Notes :
#### For Windows
Please look at this answer on stackoverflow to set up it: http://stackoverflow.com/questions/7195503/setting-up-a-cron-job-in-windows

#### Using a control panel ?
If you are running CRON via a panel, please rely on the documentation of your hosting provider. Do not hesitate to ping on the support forum thread.

#### Some hosting companies don’t allow access to cron
If this the case, you can rely on [LazyCron](https://processwire.com/api/modules/lazy-cron/) module.

#### Example CRON delay table:

| When | Settings |
| -------- | -------- |
| Every 1 minute   | */1 * * * *   |
| Every 15 minutes   | */15 * * * *   |
| Every 30 minutes |	*/30 * * * *
| Every 1 hour |	0 * * * * |
| Every 6 hours |	0 */6 * * * |
| Every 12 hours |	0 */12 * * * |
| Once a day |	4 0 * * * |
| Once a week |	4 0 * * 0 |
| Once a month |	4 0 1 * * |

## Cloud Backup/Store Settings

### GoogleDrive
Obtain credentials there: [https://console.developers.google.com/apis/credentials](https://console.developers.google.com/apis/credentials)

A tutorial is available on the forum :  [Duplicator Dev Thread](https://processwire.com/talk/topic/15345-duplicator-backup-and-move-sites/?page=2#comment-139376)

### AmazonS3
Obtain credentials there: [https://console.aws.amazon.com/iam/home](https://console.aws.amazon.com/iam/home)

#### Note:
A **bucket name** should conform with **DNS requirements**:
* Should not contain uppercase characters
* Should not contain underscores (_)
* Should be between 3 and 63 characters long
* Should not end with a dash
* Cannot contain two, adjacent periods
* Cannot contain dashes next to periods (e.g., "my-.bucket.com" and "my.-bucket" are invalid)

## Issues

### Timeout
Timeout usually happen if you are on a low-end budget host with limitations. The easiest way to solve this issue, is to use directory filters on some of your larger directories like your `files`, `vendor`, or even `bower_components` directory to get the size down. Then reinstall the package at the new location and then FTP all the files over that you filtered.

**Hosting Provider Issue List:**
* 1and1 Shared hosting (resolved by using Filters)
* Dreamhost hosting (resolved using the `Archive Flush` option); Need to be tested again.


<!-- readme: szabeszg,contributors,tofahrn,adrianbj -start -->
<!-- readme: contributors -end -->

# Special thanks
- Duplicator's logo made by [@szabeszg](https://github.com/szabeszg)
- The processwire community 💙



