<?php

/**
 * Duplicator package encryption tool (command line, does not need ProcessWire)
 *
 *   php dupcrypt.php keygen
 *       Print a new key pair. Paste the public key in Duplicator's settings,
 *       keep the secret key somewhere safe, outside the server.
 *
 *   php dupcrypt.php decrypt <encrypted package> <output.zip>
 *       Decrypt a package. The secret key is read from the DUPLICATOR_SECRET_KEY
 *       environment variable, or asked on the terminal.
 *
 *   php dupcrypt.php encrypt <file> <output> <public key>
 *       Encrypt a file, like Duplicator does after building a package.
 */

namespace ProcessWire;

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit;
}

// the downloadable version of this script (DupCrypto::standaloneScript) already contains the class
if (!class_exists(__NAMESPACE__ . '\DupCrypto', false)) {
  require_once __DIR__ . '/../Classes/DupCrypto.php';
}

function usage()
{
  fwrite(STDERR, "Usage:\n"
    . "  php dupcrypt.php keygen\n"
    . "  php dupcrypt.php decrypt <encrypted package> <output.zip>\n"
    . "  php dupcrypt.php encrypt <file> <output> <public key>\n");
  exit(2);
}

$command = isset($argv[1]) ? $argv[1] : '';

try {
  switch ($command) {
    case 'keygen':
      $keys = DupCrypto::generateKeyPair();
      echo "Public key (paste it in Duplicator's settings):\n{$keys['public']}\n\n";
      echo "Secret key (keep it safe, outside the server; it is needed to restore):\n{$keys['secret']}\n";
      break;

    case 'decrypt':
      if ($argc !== 4) usage();
      $secretKey = getenv('DUPLICATOR_SECRET_KEY');
      if ($secretKey === false || $secretKey === '') {
        fwrite(STDERR, "Secret key: ");
        $secretKey = trim((string) fgets(STDIN));
      }
      DupCrypto::decryptFile($argv[2], $argv[3], $secretKey);
      echo "Decrypted to {$argv[3]}\n";
      break;

    case 'encrypt':
      if ($argc !== 5) usage();
      DupCrypto::encryptFile($argv[2], $argv[3], $argv[4]);
      echo "Encrypted to {$argv[3]}\n";
      break;

    default:
      usage();
  }
} catch (\Exception $e) {
  fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
  exit(1);
}
