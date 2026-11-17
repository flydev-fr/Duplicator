<?php

namespace ProcessWire;

/**
 * Package encryption with the PHP sodium extension (bundled with PHP since 7.2)
 *
 * Packages are encrypted with a public key, so the server that builds the backups
 * cannot decrypt them: only the secret key can, and it should never be stored on that server.
 *
 * File format (version 1):
 *   - MAGIC (8 bytes)
 *   - file key sealed with the public key: sodium_crypto_box_seal() (80 bytes)
 *   - secretstream header (24 bytes)
 *   - chunks of CHUNK_SIZE bytes encrypted with crypto_secretstream_xchacha20poly1305,
 *     each one CHUNK_SIZE + 17 bytes, the last one shorter and tagged TAG_FINAL
 *
 * Deploy/installer.php contains a copy of the decryption code (DupDecrypt), because
 * the installer runs alone on the target server. Keep both in sync.
 */
class DupCrypto
{
  const MAGIC = "DUPENC\x01\x00";
  const CHUNK_SIZE = 1048576; // 1 MiB
  const PUBLIC_KEY_PREFIX = 'dup-pk1:';
  const SECRET_KEY_PREFIX = 'dup-sk1:';

  /**
   * @return bool True if the PHP sodium extension provides what encryption needs
   */
  public static function isAvailable()
  {
    return function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push')
      && function_exists('sodium_crypto_box_seal');
  }

  /**
   * Generate a new key pair
   *
   * @return array ['public' => 'dup-pk1:...', 'secret' => 'dup-sk1:...']
   */
  public static function generateKeyPair()
  {
    self::requireSodium();
    $keypair = sodium_crypto_box_keypair();
    $keys = array(
      'public' => self::PUBLIC_KEY_PREFIX . base64_encode(sodium_crypto_box_publickey($keypair)),
      'secret' => self::SECRET_KEY_PREFIX . base64_encode(sodium_crypto_box_secretkey($keypair)),
    );
    sodium_memzero($keypair);
    return $keys;
  }

  /**
   * @param string $publicKey Public key as given by generateKeyPair()
   * @return string Binary public key
   * @throws \RuntimeException If the key is not a valid public key
   */
  public static function parsePublicKey($publicKey)
  {
    return self::parseKey($publicKey, self::PUBLIC_KEY_PREFIX, 'public');
  }

  /**
   * @param string $secretKey Secret key as given by generateKeyPair()
   * @return string Binary key pair, as needed by sodium_crypto_box_seal_open()
   * @throws \RuntimeException If the key is not a valid secret key
   */
  public static function parseSecretKey($secretKey)
  {
    $secret = self::parseKey($secretKey, self::SECRET_KEY_PREFIX, 'secret');
    $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($secret, sodium_crypto_box_publickey_from_secretkey($secret));
    sodium_memzero($secret);
    return $keypair;
  }

  /**
   * @param string $filename
   * @return bool True if the file starts like a package encrypted by this class
   */
  public static function isEncryptedFile($filename)
  {
    $fp = @fopen($filename, 'rb');
    if (!$fp) return false;
    $magic = fread($fp, strlen(self::MAGIC));
    fclose($fp);
    return $magic === self::MAGIC;
  }

  /**
   * Encrypt a file with a public key
   *
   * @param string $source File to encrypt
   * @param string $destination Encrypted file to write (overwritten)
   * @param string $publicKey Public key as given by generateKeyPair()
   * @throws \RuntimeException On any failure; $destination is then removed
   */
  public static function encryptFile($source, $destination, $publicKey)
  {
    self::requireSodium();
    $publicKey = self::parsePublicKey($publicKey);
    $in = self::open($source, 'rb');
    $out = self::open($destination, 'wb');
    $fileKey = sodium_crypto_secretstream_xchacha20poly1305_keygen();
    try {
      self::write($out, self::MAGIC . sodium_crypto_box_seal($fileKey, $publicKey));
      list($state, $header) = sodium_crypto_secretstream_xchacha20poly1305_init_push($fileKey);
      self::write($out, $header);
      do {
        $chunk = self::read($in, self::CHUNK_SIZE);
        $final = feof($in);
        $tag = $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
        self::write($out, sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag));
      } while (!$final);
    } catch (\Exception $e) {
      fclose($out);
      @unlink($destination);
      throw $e;
    } finally {
      sodium_memzero($fileKey);
      fclose($in);
    }
    if (!fclose($out)) {
      @unlink($destination);
      throw new \RuntimeException("Could not write $destination");
    }
  }

  /**
   * Decrypt a file with a secret key
   *
   * @param string $source Encrypted file
   * @param string $destination Decrypted file to write (overwritten)
   * @param string $secretKey Secret key as given by generateKeyPair()
   * @throws \RuntimeException On any failure (wrong key, damaged or truncated file); $destination is then removed
   */
  public static function decryptFile($source, $destination, $secretKey)
  {
    self::requireSodium();
    $keypair = self::parseSecretKey($secretKey);
    $in = self::open($source, 'rb');
    $out = self::open($destination, 'wb');
    $fileKey = null;
    try {
      if (self::read($in, strlen(self::MAGIC)) !== self::MAGIC) {
        throw new \RuntimeException("$source is not an encrypted Duplicator package");
      }
      $sealed = self::read($in, SODIUM_CRYPTO_BOX_SEALBYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
      $fileKey = sodium_crypto_box_seal_open($sealed, $keypair);
      if ($fileKey === false) {
        throw new \RuntimeException("This secret key cannot decrypt this package (wrong key or damaged file)");
      }
      $header = self::read($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
      $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $fileKey);
      do {
        $chunk = self::read($in, self::CHUNK_SIZE + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
        if ($chunk === '') throw new \RuntimeException("The package is truncated");
        $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
        if ($result === false) throw new \RuntimeException("The package is damaged");
        list($plain, $tag) = $result;
        self::write($out, $plain);
      } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
      if (self::read($in, 1) !== '') throw new \RuntimeException("The package has unexpected data at its end");
    } catch (\Exception $e) {
      fclose($out);
      @unlink($destination);
      throw $e;
    } finally {
      if (is_string($fileKey)) sodium_memzero($fileKey);
      sodium_memzero($keypair);
      fclose($in);
    }
    if (!fclose($out)) {
      @unlink($destination);
      throw new \RuntimeException("Could not write $destination");
    }
  }

  protected static function parseKey($key, $prefix, $kind)
  {
    self::requireSodium();
    $key = preg_replace('/\s+/', '', (string) $key);
    if (strpos($key, $prefix) !== 0) {
      throw new \RuntimeException("This is not a Duplicator $kind key (it should start with $prefix)");
    }
    $binary = base64_decode(substr($key, strlen($prefix)), true);
    if ($binary === false || strlen($binary) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
      throw new \RuntimeException("The Duplicator $kind key is damaged");
    }
    return $binary;
  }

  protected static function requireSodium()
  {
    if (!self::isAvailable()) {
      throw new \RuntimeException("Encryption needs the PHP sodium extension (bundled with PHP 7.2+, enable it in php.ini)");
    }
  }

  protected static function open($filename, $mode)
  {
    $fp = @fopen($filename, $mode);
    if (!$fp) throw new \RuntimeException("Could not open $filename");
    return $fp;
  }

  /**
   * Read $length bytes, or less only at the end of the file
   */
  protected static function read($fp, $length)
  {
    $data = '';
    while (strlen($data) < $length && !feof($fp)) {
      $part = fread($fp, $length - strlen($data));
      if ($part === false) throw new \RuntimeException("Read error");
      $data .= $part;
    }
    return $data;
  }

  protected static function write($fp, $data)
  {
    if (fwrite($fp, $data) !== strlen($data)) throw new \RuntimeException("Write error (disk full?)");
  }
}
