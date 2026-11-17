<?php

/**
 * Package encryption: Classes/DupCrypto.php and its copy in Deploy/installer.php (DupDecrypt)
 */

use ProcessWire\DupCrypto;

require_once __DIR__ . '/../Classes/DupCrypto.php';

if (!DupCrypto::isAvailable()) {
  test('encryption', function () {
    skipTest('PHP sodium extension not loaded (php -d extension=sodium tests/run.php)');
  });
  return;
}

loadInstallerClass('DupDecrypt');

$dir = tempDir();
$keys = DupCrypto::generateKeyPair();
$otherKeys = DupCrypto::generateKeyPair();

$sizes = array(0, 1, DupCrypto::CHUNK_SIZE - 1, DupCrypto::CHUNK_SIZE, DupCrypto::CHUNK_SIZE + 1, 3 * DupCrypto::CHUNK_SIZE + 123);
foreach ($sizes as $size) {
  test("round trip of $size bytes, decrypted by the module and by the installer", function () use ($dir, $keys, $size) {
    file_put_contents("$dir/plain", $size ? random_bytes($size) : '');
    DupCrypto::encryptFile("$dir/plain", "$dir/enc", $keys['public']);
    check(DupCrypto::isEncryptedFile("$dir/enc"), 'encrypted file not detected');
    check(\DupDecrypt::isEncryptedFile("$dir/enc"), 'encrypted file not detected by the installer');
    check(!DupCrypto::isEncryptedFile("$dir/plain"), 'plain file detected as encrypted');
    DupCrypto::decryptFile("$dir/enc", "$dir/dec", $keys['secret']);
    checkSame(sha1_file("$dir/plain"), sha1_file("$dir/dec"), 'module decryption:');
    \DupDecrypt::decryptFile("$dir/enc", "$dir/dec2", $keys['secret']);
    checkSame(sha1_file("$dir/plain"), sha1_file("$dir/dec2"), 'installer decryption:');
  });
}

test('encrypting twice gives different files', function () use ($dir, $keys) {
  file_put_contents("$dir/plain", 'same content');
  DupCrypto::encryptFile("$dir/plain", "$dir/enc1", $keys['public']);
  DupCrypto::encryptFile("$dir/plain", "$dir/enc2", $keys['public']);
  check(file_get_contents("$dir/enc1") !== file_get_contents("$dir/enc2"));
});

test('keys have the expected format', function () use ($keys) {
  check(preg_match('#^dup-pk1:[A-Za-z0-9+/]{43}=$#', $keys['public']) === 1, $keys['public']);
  check(preg_match('#^dup-sk1:[A-Za-z0-9+/]{43}=$#', $keys['secret']) === 1, 'secret key format');
});

test('keys pasted with spaces and line breaks are accepted', function () use ($keys) {
  checkSame(32, strlen(DupCrypto::parsePublicKey("  " . chunk_split($keys['public'], 10, "\r\n"))));
});

test('a secret key is refused where the public key is expected', function () use ($dir, $keys) {
  file_put_contents("$dir/plain", 'data');
  checkThrows(function () use ($dir, $keys) {
    DupCrypto::encryptFile("$dir/plain", "$dir/enc-x", $keys['secret']);
  }, 'not a Duplicator public key');
  check(!file_exists("$dir/enc-x"), 'output left behind');
});

test('a damaged public key is refused', function () use ($dir, $keys) {
  checkThrows(function () use ($dir, $keys) {
    DupCrypto::encryptFile("$dir/plain", "$dir/enc-x", substr($keys['public'], 0, -4));
  }, 'damaged');
  check(!file_exists("$dir/enc-x"), 'output left behind');
});

// a 2-chunk encrypted file for the failure cases
file_put_contents("$dir/plain", random_bytes(DupCrypto::CHUNK_SIZE + 5000));
DupCrypto::encryptFile("$dir/plain", "$dir/enc", $keys['public']);
$encrypted = file_get_contents("$dir/enc");
$headerSize = strlen(DupCrypto::MAGIC) + 80 + 24;

$failures = array(
  'wrong secret key' => array($encrypted, $otherKeys['secret'], 'cannot decrypt'),
  'public key given as secret key' => array($encrypted, $keys['public'], 'not a Duplicator secret key'),
  'truncated in the last chunk' => array(substr($encrypted, 0, -100), $keys['secret'], 'damaged'),
  'cut right after a full chunk' => array(substr($encrypted, 0, $headerSize + DupCrypto::CHUNK_SIZE + 17), $keys['secret'], 'truncated'),
  'one bit flipped' => array(substr_replace($encrypted, chr(ord($encrypted[300000]) ^ 1), 300000, 1), $keys['secret'], 'damaged'),
  'data appended' => array($encrypted . 'x', $keys['secret'], 'damaged'),
  'not encrypted' => array('PK' . random_bytes(500), $keys['secret'], 'not an encrypted Duplicator package'),
);
foreach ($failures as $name => $case) {
  list($content, $secret, $message) = $case;
  test("decryption fails cleanly: $name", function () use ($dir, $content, $secret, $message) {
    file_put_contents("$dir/bad", $content);
    checkThrows(function () use ($dir, $secret) {
      DupCrypto::decryptFile("$dir/bad", "$dir/out", $secret);
    }, $message);
    check(!file_exists("$dir/out"), 'module left a partial output');
    checkThrows(function () use ($dir, $secret) {
      \DupDecrypt::decryptFile("$dir/bad", "$dir/out", $secret);
    }, $message);
    check(!file_exists("$dir/out"), 'installer left a partial output');
  });
}

test('the installer copy uses the same format constants', function () {
  checkSame(DupCrypto::MAGIC, \DupDecrypt::MAGIC);
  checkSame(DupCrypto::CHUNK_SIZE, \DupDecrypt::CHUNK_SIZE);
  checkSame(DupCrypto::SECRET_KEY_PREFIX, \DupDecrypt::SECRET_KEY_PREFIX);
});

test('scripts/dupcrypt.php: keygen, encrypt, decrypt', function () use ($dir) {
  // a child php process does not get "-d extension=sodium" given to this one: add it when php.ini lacks it
  $php = escapeshellarg(PHP_BINARY) . ' ';
  exec($php . '-r ' . escapeshellarg("exit(extension_loaded('sodium') ? 0 : 1);"), $out, $code);
  if ($code !== 0) $php .= '-d extension=sodium ';
  $script = escapeshellarg(__DIR__ . '/../scripts/dupcrypt.php');
  exec("$php$script keygen", $out, $code);
  checkSame(0, $code, 'keygen exit code');
  $text = implode("\n", $out);
  check(preg_match('/(dup-pk1:\S+)/', $text, $pk) && preg_match('/(dup-sk1:\S+)/', $text, $sk), 'keys not printed');
  file_put_contents("$dir/cli-plain", random_bytes(70000));
  exec("$php$script encrypt " . escapeshellarg("$dir/cli-plain") . ' ' . escapeshellarg("$dir/cli-enc") . ' ' . escapeshellarg($pk[1]), $out, $code);
  checkSame(0, $code, 'encrypt exit code');
  putenv('DUPLICATOR_SECRET_KEY=' . $sk[1]);
  exec("$php$script decrypt " . escapeshellarg("$dir/cli-enc") . ' ' . escapeshellarg("$dir/cli-dec"), $out, $code);
  putenv('DUPLICATOR_SECRET_KEY');
  checkSame(0, $code, 'decrypt exit code');
  checkSame(sha1_file("$dir/cli-plain"), sha1_file("$dir/cli-dec"));
});

test('downloadable dupcrypt.php works alone and decrypts what the module encrypts', function () use ($dir, $keys) {
  // alone in its own folder: no Classes/DupCrypto.php next to it
  $standaloneDir = "$dir/standalone";
  if (!is_dir($standaloneDir)) mkdir($standaloneDir);
  file_put_contents("$standaloneDir/dupcrypt.php", DupCrypto::standaloneScript());
  $php = escapeshellarg(PHP_BINARY) . ' ';
  exec($php . '-r ' . escapeshellarg("exit(extension_loaded('sodium') ? 0 : 1);"), $out, $code);
  if ($code !== 0) $php .= '-d extension=sodium ';
  $script = escapeshellarg("$standaloneDir/dupcrypt.php");
  $out = array();
  exec("$php$script keygen", $out, $code);
  checkSame(0, $code, 'keygen exit code');
  check(preg_match('/dup-pk1:\S+/', implode("\n", $out)) === 1, 'no key printed');
  file_put_contents("$dir/sa-plain", random_bytes(DupCrypto::CHUNK_SIZE + 99));
  DupCrypto::encryptFile("$dir/sa-plain", "$dir/sa-enc", $keys['public']);
  putenv('DUPLICATOR_SECRET_KEY=' . $keys['secret']);
  exec("$php$script decrypt " . escapeshellarg("$dir/sa-enc") . ' ' . escapeshellarg("$dir/sa-dec"), $out, $code);
  putenv('DUPLICATOR_SECRET_KEY');
  checkSame(0, $code, 'decrypt exit code');
  checkSame(sha1_file("$dir/sa-plain"), sha1_file("$dir/sa-dec"));
  @unlink("$standaloneDir/dupcrypt.php");
  @rmdir($standaloneDir);
});
