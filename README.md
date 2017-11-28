# Install
Download the module and [install it](https://modules.processwire.com/install-uninstall/)

### SDK Installation (optional)
You can skip these instructions if you plan to only use the built-in features `Local Folder` or `FTP/FTPS`.

If you want to use `GoogleDrive`, `Dropbox` or `AmazonS3`, you have two choices of type of installation. Using Composer or downloading directly a ZIP archive from the repository.

Installing all SDK are not required, you can choose the one you need.

#### Using Composer
In the root directory of your ProcessWire installation, just type in a terminal :
* `composer require google/apiclient`
* `composer require dropbox/dropbox-sdk`
* `composer require aws/aws-sdk-php`

#### ZIP Archive
After downloading an archive, open it, you will find a folder called **`SDKs`**. Extract or Upload this folder in the **`Duplicator module folder`**. If the folder already exist, merge it. Example: `/www/mysite/wwwroot/site/modules/Duplicator/SDKs`

Download Links:
* [GoogleDrive SDK (not uploaded yet)]()
* [Dropbox SDK (not uploaded yet)]()
* [AmazonS3 SDK (not uploaded yet)]()


Now you can refresh the Duplicator's settings page, the corresponding checkbox will be enabled.


# Installing a CRON job
First of all, you must install PWCron:[http://modules.processwire.com/modules/pwcron/](http://modules.processwire.com/modules/pwcron/)


To edit a crontab through the command line, type: `cronjob -e` then add for example the following line:
> `*/1 * * * * /usr/bin/php /www/mysite/wwwroot/site/modules/PWCron/cron.php >/dev/null 2>&1`


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


# Providers Settings

## GoogleDrive
Obtain credentials there: [https://console.developers.google.com/apis/credentials](https://console.developers.google.com/apis/credentials)

A tutorial is available on the forum :  [Duplicator Dev Thread](https://processwire.com/talk/topic/15345-duplicator-backup-and-move-sites/?page=2#comment-139376)

## Dropbox
Obtain credentials there:  [https://www.dropbox.com/developers/apps](https://www.dropbox.com/developers/apps)

## AmazonS3
Obtain credentials there: [https://console.aws.amazon.com/iam/home](https://console.aws.amazon.com/iam/home).

#### Note:
A **bucket name** should conform with **DNS requirements**:
* Should not contain uppercase characters
* Should not contain underscores (_)
* Should be between 3 and 63 characters long
* Should not end with a dash
* Cannot contain two, adjacent periods
* Cannot contain dashes next to periods (e.g., "my-.bucket.com" and "my.-bucket" are invalid)


# Issues

## Timeout
Timeout usually happen if you are on a low-end budget host with limitations. The easiest way to solve this issue, is to use directory filters on some of your larger directories like your `files`, `vendor`, or even `bower_components` directory to get the size down. Then reinstall the package at the new location and then FTP all the files over that you filtered.

**Hosting Provider Issue List:**
* 1and1 Shared hosting (resolved by using Filters)
* Dreamhost hosting (resolved using the `Archive Flush` option); Need to be tested again.


# FAQ
### GENERAL QUESTIONS
##### What is the primary goal of this module ?
Duplicator is a module which duplicate and deploy your website on a new host. It can be used as backup utility too.

##### And how it works ?
Duplicator will create a ZIP archive called `package` bundled with a full database backup and all your files.
This package is stored on local disk or in the cloud and can be used to restore your website.
The package is also used in the deployment process.

##### Is it compatible with PW2 ? Guess not.
Yes! Duplicator is compatible with ProcessWire 2.7.2, 2.8.x and 3.x.x.

##### I don't use composer. Any other way to install ?
Just download the SDK you are looking for from the repository.

##### It is possible to do a manual backup without changing the module's Event trigger ?
Yes, simply click on the "Initiate Backup Process" button from the Package Manager.

##### Can I backup my site from scheduled cron job ?
Yes, in order to get it working, you are required to install `PwCron`, then read our doc about setting up a cron job.

##### I'm on windows. Will PWcron run ?
Without any problem. *(Look at this answer to set up it: http://stackoverflow.com/questions/7195503/setting-up-a-cron-job-in-windows)*

##### Can I exclude files or folders from a package ?
Yes, you can exclude a single file, filter them by extension or exclude a whole folder. **Keep in mind that excluding core files or folder will not allow your deployed site to work**.

##### Can I download a package after creation ?
Yes!

##### Can I manage my packages in the cloud ?
Yes!

##### Does the module screen is protected by role/user ?
Yes, the permission set is `duplicator`. User with this permission can initiate a backup, download or sync a package. The module's settings is only accessible to `superuser`.









