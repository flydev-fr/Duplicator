<?php

/**
 * Which files and symbolic links go into a package: Classes/DupFileFilter.php
 */

use ProcessWire\DupFileFilter;

require_once __DIR__ . '/../Classes/DupFileFilter.php';

test('version control, editor and OS files are skipped', function () {
  foreach (array('.git', '.github', '.gitignore', '.cursor', '.vscode', '.DS_Store', 'Thumbs.db') as $name) {
    check(DupFileFilter::isSkippedName($name), "$name should be skipped");
  }
  foreach (array('.htaccess', '.user.ini', '.well-known', 'git', 'site', 'index.php') as $name) {
    check(!DupFileFilter::isSkippedName($name), "$name should be kept");
  }
});

test('paths are normalized without touching the disk', function () {
  checkSame('/var/www/site', DupFileFilter::normalizePath('/var/www/./modules/../site/'));
  checkSame('C:/www/site', DupFileFilter::normalizePath('C:\\www\\modules\\..\\site'));
  checkSame('/', DupFileFilter::normalizePath('/var/..'));
});

test('open_basedir is a prefix match, like PHP', function () {
  $sep = PATH_SEPARATOR;
  check(DupFileFilter::openBasedirAllows('/anything', ''), 'no open_basedir allows everything');
  check(DupFileFilter::openBasedirAllows('/var/www/site/x', "/tmp{$sep}/var/www"), 'inside a listed folder');
  check(DupFileFilter::openBasedirAllows('/var/www2/x', '/var/www'), 'PHP allows this prefix too');
  check(!DupFileFilter::openBasedirAllows('/home/repos/module', "/tmp{$sep}/var/www/"), 'outside every listed folder');
});

test('isInside compares whole path segments', function () {
  check(DupFileFilter::isInside('/a/b/c', '/a/b'), 'child');
  check(DupFileFilter::isInside('/a/b', '/a/b/'), 'same folder');
  check(!DupFileFilter::isInside('/a/bc', '/a/b'), 'sibling with the same prefix');
});

/**
 * A link to a folder: a symbolic link, or on Windows without the privilege, a junction
 */
function linkFolder($target, $link)
{
  if (@symlink($target, $link)) return 'symlink';
  if (DIRECTORY_SEPARATOR === '\\') {
    exec('mklink /J ' . escapeshellarg(str_replace('/', '\\', $link)) . ' ' . escapeshellarg(str_replace('/', '\\', $target)) . ' 2>NUL', $out, $code);
    if ($code === 0) return 'junction';
  }
  return false;
}

$base = tempDir() . '/links';
$site = "$base/site";
$repo = "$base/repos/MyModule";
mkdir("$site/site/modules", 0777, true);
mkdir("$site/site/templates", 0777, true);
mkdir($repo, 0777, true);
file_put_contents("$repo/MyModule.module", '<?php');

$kind = linkFolder($repo, "$site/site/modules/MyModule");   // a module from another repository
if (!$kind) {
  test('symbolic links', function () {
    skipTest('cannot create links here');
  });
  return;
}
linkFolder("$base/repos", "$site/site/modules/AllRepos");    // contains the folder linked above
linkFolder("$site/site/templates", "$site/site/modules/T");  // back into the site
linkFolder($site, "$site/site/modules/Loop");                // an ancestor: would loop forever
linkFolder("$base/missing", "$site/site/modules/Broken");
$siteReal = realpath($site);

test("links are detected ($kind)", function () use ($site, $repo) {
  check(DupFileFilter::isLink("$site/site/modules/MyModule"), 'link not detected');
  check(DupFileFilter::isLink("$site/site/modules/Broken"), 'broken link not detected');
  check(!DupFileFilter::isLink("$site/site/templates"), 'a plain folder is not a link');
  check(!DupFileFilter::isLink("$repo/MyModule.module"), 'a plain file is not a link');
  check(!DupFileFilter::isLink("$site/site/modules/MyModule/MyModule.module"), 'a file inside a linked folder is not a link');
});

test('a linked module outside the site is followed once', function () use ($site, $siteReal, $repo) {
  $followed = array();
  list($reason, $target) = DupFileFilter::checkLink("$site/site/modules/MyModule", $siteReal, $followed);
  checkSame(null, $reason);
  checkSame(DupFileFilter::normalizePath(realpath($repo)), $target);
  list($reason) = DupFileFilter::checkLink("$site/site/modules/AllRepos", $siteReal, $followed);
  check($reason !== null, 'a folder containing a followed one would package it twice');
});

test('links into the site, to an ancestor or broken are skipped', function () use ($site, $siteReal) {
  foreach (array('T', 'Loop', 'Broken') as $name) {
    $followed = array();
    list($reason) = DupFileFilter::checkLink("$site/site/modules/$name", $siteReal, $followed);
    check($reason !== null, "$name should be skipped");
    checkSame(array(), $followed);
  }
});

test('a link outside open_basedir is skipped without a warning', function () use ($site, $siteReal) {
  $followed = array();
  $warnings = array();
  set_error_handler(function ($no, $str) use (&$warnings) {
    $warnings[] = $str;
    return true;
  });
  list($reason) = DupFileFilter::checkLink("$site/site/modules/MyModule", $siteReal, $followed, $siteReal);
  restore_error_handler();
  checkSame('target outside open_basedir', $reason);
  checkSame(array(), $warnings);
});
