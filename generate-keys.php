<?php

namespace ProcessWire;

include_once(__DIR__ . '/../../../index.php');
require_once(__DIR__ . '/../../../vendor/autoload.php');

echo "> Generating encryption keys for Duplicator...\n";

$seal_keypair = \ParagonIE\Halite\KeyFactory::generateEncryptionKeyPair();
$seal_secret = $seal_keypair->getSecretKey();
\ParagonIE\Halite\KeyFactory::save($seal_secret, wire('config')->paths->assets . 'backups/duplicator.secret.key.new');
echo "> Secret key saved to site/assets/backups/duplicator.secret.key.new\n";
$seal_public = $seal_keypair->getPublicKey();
\ParagonIE\Halite\KeyFactory::save($seal_public, wire('config')->paths->assets . 'backups/duplicator.public.key.new');
echo "> Public key saved to site/assets/backups/duplicator.public.key.new\n";
echo "> Keys generated successfully.\n-\n";
echo "> To use the new keys, you need to:\n";
echo "\t1. Rename the new keys to duplicator.secret.key and duplicator.public.key.\n";
echo "\t2. Copy the content of duplicator.public.key and paste the public key into Duplicator's config, field  'Encryption > Public Key' and save settings.\n";
echo "=> Now you can run 'php /site/modules/Duplicator/cron.php' to generate a backup and encrypt it.\n";
echo "?) For testing decryption, edit the module's code and uncomment the lines 640-651 then run cron.php again.\n";

// end of generate-keys.php