<?php

/**
 * Minimal test runner, no dependency: php tests/run.php
 *
 * Runs every tests/*Test.php file. A test file calls test('name', function () { ... })
 * and uses the check helpers below. Exit code is 1 if a test failed.
 */

$GLOBALS['dupTests'] = array('passed' => 0, 'failed' => 0, 'skipped' => 0);

class TestSkipped extends Exception
{
}

function test($name, $fn)
{
  try {
    $fn();
    $GLOBALS['dupTests']['passed']++;
    echo "  ok    $name\n";
  } catch (TestSkipped $e) {
    $GLOBALS['dupTests']['skipped']++;
    echo "  skip  $name (" . $e->getMessage() . ")\n";
  } catch (Throwable $e) {
    $GLOBALS['dupTests']['failed']++;
    echo "  FAIL  $name\n        " . get_class($e) . ': ' . $e->getMessage() . "\n";
  }
}

function skipTest($reason)
{
  throw new TestSkipped($reason);
}

function check($condition, $message = 'check failed')
{
  if (!$condition) throw new Exception($message);
}

function checkSame($expected, $actual, $message = '')
{
  if ($expected !== $actual) {
    throw new Exception(trim("$message expected " . var_export($expected, true) . ', got ' . var_export($actual, true)));
  }
}

/**
 * @return string The exception message, so the caller can check it
 */
function checkThrows($fn, $messageContains = '')
{
  try {
    $fn();
  } catch (Exception $e) {
    if ($messageContains !== '' && strpos($e->getMessage(), $messageContains) === false) {
      throw new Exception("exception message '" . $e->getMessage() . "' does not contain '$messageContains'");
    }
    return $e->getMessage();
  }
  throw new Exception('no exception thrown');
}

/**
 * Load one class from Deploy/installer.php, which cannot be included because it runs itself
 */
function loadInstallerClass($className)
{
  if (class_exists($className, false)) return;
  $src = file_get_contents(__DIR__ . '/../Deploy/installer.php');
  $tokens = token_get_all($src);
  $start = null;
  $depth = 0;
  $code = '';
  foreach ($tokens as $i => $token) {
    if ($start === null) {
      if (is_array($token) && $token[0] === T_CLASS) {
        $j = $i + 1;
        while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        if (is_array($tokens[$j]) && $tokens[$j][1] === $className) $start = $i;
      }
      if ($start === null) continue;
    }
    $text = is_array($token) ? $token[1] : $token;
    $code .= $text;
    if ($text === '{' || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES)))) $depth++;
    if ($text === '}') {
      $depth--;
      if ($depth === 0) break;
    }
  }
  if ($start === null) throw new Exception("class $className not found in installer.php");
  eval($code);
}

function tempDir()
{
  $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator-tests-' . getmypid();
  if (!is_dir($dir)) mkdir($dir);
  return $dir;
}

foreach (glob(__DIR__ . '/*Test.php') as $file) {
  echo "\n# " . basename($file) . "\n";
  require $file;
}

// cleanup: links are removed, never followed
function removeTree($path)
{
  require_once __DIR__ . '/../Classes/DupFileFilter.php';
  if (\ProcessWire\DupFileFilter::isLink($path)) {
    if (!@unlink($path)) @rmdir($path); // a link to a folder, on Windows
  } else if (is_dir($path)) {
    foreach (scandir($path) as $entry) {
      if ($entry !== '.' && $entry !== '..') removeTree($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
  } else if (file_exists($path)) {
    @unlink($path);
  }
}
removeTree(tempDir());

$r = $GLOBALS['dupTests'];
echo "\n{$r['passed']} passed, {$r['failed']} failed, {$r['skipped']} skipped\n";
exit($r['failed'] ? 1 : 0);
