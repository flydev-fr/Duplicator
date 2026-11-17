<?php

/**
 * Which files the orphaned-log cleanup removes: DUP_Util::orphanedLogFiles()
 */

use ProcessWire\DUP_Util;

require_once __DIR__ . '/../Classes/DupUtil.php';

/**
 * A temporary backup folder holding the given files (name => true) and folders (name => false)
 */
function backupFolder(array $entries)
{
  $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dup-orphaned-' . bin2hex(random_bytes(6));
  mkdir($dir);
  foreach ($entries as $name => $isFile) {
    $isFile ? file_put_contents($dir . DIRECTORY_SEPARATOR . $name, 'x') : mkdir($dir . DIRECTORY_SEPARATOR . $name);
  }
  return $dir;
}

function removeBackupFolder($dir)
{
  foreach (array_diff(scandir($dir), array('.', '..')) as $name) {
    $path = $dir . DIRECTORY_SEPARATOR . $name;
    is_dir($path) ? rmdir($path) : unlink($path);
  }
  rmdir($dir);
}

test('only the log of a package that is gone is orphaned', function () {
  $dir = backupFolder(array(
    '2026-01-01_00-00-00-example.package2.zip' => true,
    '2026-01-01_00-00-00-example.package2.zip.log' => true,
    '2025-12-31_00-00-00-example.package2.zip.log' => true,
  ));
  try {
    checkSame(array('2025-12-31_00-00-00-example.package2.zip.log'), DUP_Util::orphanedLogFiles($dir, 'package2.zip'));
  } finally {
    removeBackupFolder($dir);
  }
});

test('other files and folders are left alone, whatever their name', function () {
  $dir = backupFolder(array(
    '2026-01-01_00-00-00-example.package2.zip' => true,
    // names with a "p", the first letter of the package extension
    'notes-export.txt' => true,
    'php-error.log' => true,
    'dump.sql.zip' => true,
    'PagesExportImport' => false,
    // names that only contain the log suffix
    'example.package2.zip.log.bak' => true,
    'folder.package2.zip.log' => false,
    'readme.md' => true,
  ));
  try {
    checkSame(array(), DUP_Util::orphanedLogFiles($dir, 'package2.zip'));
  } finally {
    removeBackupFolder($dir);
  }
});

test('a log name with ".log" inside it keeps its package name', function () {
  $dir = backupFolder(array(
    '2026-01-01_00-00-00-my.logs.package2.zip' => true,
    '2026-01-01_00-00-00-my.logs.package2.zip.log' => true,
  ));
  try {
    checkSame(array(), DUP_Util::orphanedLogFiles($dir, 'package2.zip'));
  } finally {
    removeBackupFolder($dir);
  }
});

test('a missing folder has no orphaned logs', function () {
  checkSame(array(), DUP_Util::orphanedLogFiles(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dup-no-such-folder-' . bin2hex(random_bytes(6)), 'package2.zip'));
});
