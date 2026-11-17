<?php

namespace ProcessWire;

/**
 * JSON API of the Duplicator admin app (Setup > Duplicator)
 *
 * - GET  op=state            packages, storages, log, what the user may do
 * - GET  op=settings         settings form, built from Duplicator::getModuleConfigInputfields()
 * - POST op=backup           build a package now
 * - POST op=delete           delete a package (local or cloud)
 * - POST op=deleteAll        delete every local package
 * - POST op=sync             copy a cloud package to the local folder
 * - POST op=saveSettings     validate and save the settings (superuser only)
 *
 * Every POST needs the session's CSRF token. Downloads (package, installer, dupcrypt.php)
 * stay plain GET links handled by ProcessDuplicator.
 */
class DupAdminApi extends Wire
{
  /**
   * Settings never sent to the browser: an empty value on save keeps the stored one
   */
  const SECRET_FIELDS = array('ftpPassword', 'dropboxAccessToken', 'dropboxAppSecret', 'googleKeyFile', 'awsSecretKey');

  /** @var Duplicator */
  protected $dup;

  public function __construct(Duplicator $dup)
  {
    parent::__construct();
    $this->dup = $dup;
  }

  /**
   * Run the requested operation and send the JSON answer
   */
  public function handle()
  {
    $input = $this->wire('input');
    $op = $input->requestMethod('POST') ? (string) $input->post('op') : (string) $input->get('op');
    try {
      if ($input->requestMethod('POST')) {
        if (!$this->wire('session')->CSRF->hasValidToken()) {
          return $this->send(array('error' => 'The page has expired, please reload it.'), 403);
        }
        switch ($op) {
          case 'backup':
            return $this->send($this->backup());
          case 'delete':
            return $this->send($this->delete((string) $input->post('service'), (string) $input->post('file')));
          case 'deleteAll':
            DUP_Util::clean(Duplicator::getPath(), 0);
            return $this->send(array('ok' => true));
          case 'sync':
            return $this->send($this->sync((string) $input->post('service'), (string) $input->post('file')));
          case 'saveSettings':
            if (!$this->wire('user')->isSuperuser()) return $this->send(array('error' => 'Only a superuser can change the settings.'), 403);
            return $this->send($this->saveSettings($input->post('settings')));
        }
      } else {
        switch ($op) {
          case 'state':
            return $this->send($this->state());
          case 'settings':
            if (!$this->wire('user')->isSuperuser()) return $this->send(array('error' => 'Only a superuser can see the settings.'), 403);
            return $this->send($this->settings());
        }
      }
      return $this->send(array('error' => 'Unknown operation'), 400);
    } catch (\Exception $e) {
      DUP_Logs::log('Admin app: ' . $e->getMessage(), 'error');
      return $this->send(array('error' => $e->getMessage()), 500);
    }
  }

  protected function send(array $data, $status = 200)
  {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
  }

  /**
   * Everything the packages screen needs
   */
  protected function state()
  {
    $dup = $this->dup;
    $storages = array(
      'local' => (bool) $dup->useLocalFolder,
      'ftp' => (bool) $dup->useFTP,
      'dropbox' => (bool) $dup->useDropbox,
      'googledrive' => (bool) $dup->useGoogleDrive,
      'amazon' => (bool) $dup->useAmazonS3,
    );
    list($packages, $errors) = $this->packages();
    return array(
      'packages' => $packages,
      'errors' => $errors,
      'storages' => $storages,
      'encryption' => (bool) $dup->encryptionEnabled,
      'retention' => array('maxPackages' => (int) $dup->maxPackages, 'deadline' => (string) $dup->deadline),
      'schedule' => array('mode' => (string) $dup->cronMode, 'cycle' => (string) $dup->cycle),
      'log' => $this->logLines(200),
      'canEditSettings' => $this->wire('user')->isSuperuser(),
    );
  }

  /**
   * Local and cloud packages, newest first
   *
   * @return array [packages, errors]
   */
  protected function packages()
  {
    $dup = $this->dup;
    $ext = Duplicator::DUP_PACKAGE_EXTENSION;
    $list = array();
    $errors = array();

    $path = $dup->path;
    if ($dup->useLocalFolder && $path && is_dir($path)) {
      foreach (DUP_Util::getPackages($path, $ext) ?: array() as $file) {
        $full = $path . DS . $file;
        $list[] = $this->package('local', $file, $file, filesize($full), DupCrypto::isEncryptedFile($full));
      }
    }

    if ($dup->useDropbox) {
      try {
        $dropbox = new \DropboxClient();
        $dropbox->setAccessToken($dup->dropboxAccessToken);
        foreach ($dropbox->getFiles() ?: array() as $f) {
          if (!$this->isPackageName($f['name'])) continue;
          $list[] = $this->package('dropbox', $f['name'], $f['name'], (int) $f['size']);
        }
      } catch (\Exception $e) {
        $errors[] = 'Dropbox: ' . $e->getMessage();
      }
    }

    if ($dup->useGoogleDrive) {
      try {
        $google = new GoogleDriveClient();
        $google->setTempFolder(Duplicator::DUP_TEMP_FOLDER);
        $google->setAppName(Duplicator::DUP_APPLICATION_NAME);
        $google->setGoogleKeyFile($dup->googleKeyFile);
        $google->connect();
        foreach ($google->getFiles() ?: array() as $f) {
          if (!$this->isPackageName($f['name']) || !preg_match('#^[A-Za-z0-9_-]+$#', (string) $f['id'])) continue;
          $list[] = $this->package('googledrive', $f['name'], $f['id'], (int) $f['size']);
        }
      } catch (\Exception $e) {
        $errors[] = 'Google Drive: ' . $e->getMessage();
      }
    }

    if ($dup->useAmazonS3) {
      try {
        $s3 = new AmazonS3Client();
        $s3->setAccessKey($dup->awsAccessKey);
        $s3->setSecretKey($dup->awsSecretKey);
        $s3->setRegion($dup->awsRegion);
        $s3->setBucket($dup->awsBucketName);
        foreach ($s3->getFiles() ?: array() as $f) {
          $name = basename($f['Key']);
          if (!$this->isPackageName($name) || !preg_match('#^[A-Za-z0-9._/-]+$#', $f['Key'])) continue;
          $list[] = $this->package('amazon', $name, $f['Key'], (int) $f['Size']);
        }
      } catch (\Exception $e) {
        $errors[] = 'Amazon S3: ' . $e->getMessage();
      }
    }

    usort($list, function ($a, $b) {
      return $b['time'] - $a['time'];
    });
    return array($list, $errors);
  }

  protected function package($service, $name, $id, $size, $encrypted = null)
  {
    $parts = explode('-', $name);
    array_pop($parts);
    $ts = date_create_from_format(Duplicator::DUP_TIMESTAMP_FORMAT, implode('-', $parts));
    return array(
      'service' => $service,
      'name' => $name,
      'id' => $id,
      'size' => $size,
      'time' => $ts ? $ts->getTimestamp() : 0,
      'encrypted' => $encrypted,
    );
  }

  /**
   * Only names Duplicator writes: never anything that could carry markup
   */
  protected function isPackageName($name)
  {
    $ext = '.' . Duplicator::DUP_PACKAGE_EXTENSION;
    return is_string($name) && substr($name, -strlen($ext)) === $ext && preg_match('#^[A-Za-z0-9._-]+$#', $name) === 1;
  }

  protected function logLines($max)
  {
    $lines = array();
    $entries = $this->wire('log')->getEntries(Duplicator::DUP_LOG_FILENAME, array('limit' => $max));
    foreach ($entries as $entry) {
      $lines[] = array('date' => $entry['date'], 'text' => $entry['text']);
    }
    return $lines;
  }

  protected function backup()
  {
    $result = $this->dup->cronJob(new HookEvent());
    if ($result === false) {
      return array('ok' => false, 'error' => 'No storage is enabled: choose at least one in the settings.');
    }
    if ($result === null) {
      return array('ok' => false, 'error' => 'The package could not be built, see the log.');
    }
    return array('ok' => true);
  }

  protected function delete($service, $file)
  {
    $dup = $this->dup;
    switch ($service) {
      case 'local':
        $file = $this->wire('sanitizer')->filename($file);
        $packages = DUP_Util::getPackages($dup->path, Duplicator::DUP_PACKAGE_EXTENSION);
        if (!is_array($packages) || !in_array($file, $packages, true)) return array('ok' => false, 'error' => 'Package not found.');
        DUP_Util::deleteFile($dup->path . DS . $file);
        if (is_file($dup->path . DS . $file . '.log')) DUP_Util::deleteFile($dup->path . DS . $file . '.log');
        return array('ok' => true);
      case 'dropbox':
        if (!$this->isPackageName($file)) break;
        $dropbox = new \DropboxClient();
        $dropbox->setAccessToken($dup->dropboxAccessToken);
        $dropbox->setFolder(Duplicator::DUP_APPLICATION_NAME);
        $dropbox->deleteFile($file, Duplicator::DUP_APPLICATION_NAME);
        return array('ok' => true);
      case 'googledrive':
        if (!preg_match('#^[A-Za-z0-9_-]+$#', $file)) break;
        $google = new GoogleDriveClient();
        $google->setTempFolder(Duplicator::DUP_TEMP_FOLDER);
        $google->setAppName(Duplicator::DUP_APPLICATION_NAME);
        $google->setGoogleKeyFile($dup->googleKeyFile);
        $google->connect();
        $google->deleteFile($file);
        return array('ok' => true);
      case 'amazon':
        if (!preg_match('#^[A-Za-z0-9._/-]+$#', $file) || !$this->isPackageName(basename($file))) break;
        $s3 = new AmazonS3Client();
        $s3->setAccessKey($dup->awsAccessKey);
        $s3->setSecretKey($dup->awsSecretKey);
        $s3->setRegion($dup->awsRegion);
        $s3->setBucket($dup->awsBucketName);
        $s3->deleteFile(array(array('Key' => $file)));
        return array('ok' => true);
    }
    return array('ok' => false, 'error' => 'Invalid package.');
  }

  protected function sync($service, $file)
  {
    $dup = $this->dup;
    $target = $this->wire('config')->paths->assets . Duplicator::DUP_DEFAULT_LOCAL_FOLDER;
    switch ($service) {
      case 'dropbox':
        if (!$this->isPackageName($file)) break;
        $dropbox = new \DropboxClient();
        $dropbox->setAccessToken($dup->dropboxAccessToken);
        $dropbox->setIdentifier("Duplicator/1");
        $dropbox->setFolder(Duplicator::DUP_APPLICATION_NAME);
        $dropbox->setQuery(Duplicator::DUP_PACKAGE_EXTENSION);
        $dropbox->setMimeType(Duplicator::DUP_PACKAGE_MIME_TYPE);
        return array('ok' => $dropbox->download('/' . $file, $target) === true);
      case 'googledrive':
        if (!$this->isPackageName($file)) break;
        $google = new GoogleDriveClient();
        $google->setTempFolder(Duplicator::DUP_TEMP_FOLDER);
        $google->setAppName(Duplicator::DUP_APPLICATION_NAME);
        $google->setGoogleKeyFile($dup->googleKeyFile);
        $google->connect();
        $found = $google->getPackage($file, Duplicator::DUP_PACKAGE_MIME_TYPE, 0);
        if (empty($found[0]['name']) || !$this->isPackageName($found[0]['name'])) break;
        $google->download($found, $target . DS . $found[0]['name']);
        return array('ok' => true);
      case 'amazon':
        if (!preg_match('#^[A-Za-z0-9._/-]+$#', $file) || !$this->isPackageName(basename($file))) break;
        $s3 = new AmazonS3Client();
        $s3->setAccessKey($dup->awsAccessKey);
        $s3->setSecretKey($dup->awsSecretKey);
        $s3->setRegion($dup->awsRegion);
        $s3->setBucket($dup->awsBucketName);
        $s3->download($file, $target . DS . basename($file));
        return array('ok' => true);
    }
    return array('ok' => false, 'error' => 'Invalid package.');
  }

  /**
   * Settings for the app: raw values, the choices of each select, and what the server supports
   *
   * Labels and layout live in the app (ui/src/SettingsView.tsx). Values are validated on save
   * by the module's own Inputfields, the same as Modules > Configure > Duplicator.
   */
  protected function settings()
  {
    $data = $this->configData();
    $form = $this->settingsForm($data);

    $values = array();
    $secretsSet = array();
    foreach ($this->fieldNames($form) as $name) {
      if (in_array($name, self::SECRET_FIELDS, true)) {
        $values[$name] = '';
        $secretsSet[$name] = !empty($data[$name]);
        continue;
      }
      $value = isset($data[$name]) ? $data[$name] : '';
      $values[$name] = is_array($value) ? array_values(array_map('strval', $value)) : (is_int($value) ? $value : (string) $value);
    }

    $options = array();
    foreach (array('cycle', 'deadline', 'awsRegion', 'ignoredExtensions', 'backup_mode') as $name) {
      $f = $form->getChildByName($name);
      if (!$f instanceof InputfieldSelect) continue;
      $list = array();
      foreach ($f->getOptions() as $value => $label) {
        $list[] = array('value' => (string) $value, 'label' => html_entity_decode((string) $label, ENT_QUOTES, 'UTF-8'));
      }
      $options[$name] = $list;
    }

    return array('values' => $values, 'secretsSet' => $secretsSet, 'options' => $options, 'env' => $this->environment($data));
  }

  /**
   * What the server can do, for the app to explain what is available
   */
  protected function environment(array $data)
  {
    $modules = $this->wire('modules');
    $config = $this->wire('config');
    $shell = DUP_Util::isEnabled('shell_exec');
    $which = function ($tool) use ($shell) {
      if (!$shell) return false;
      $out = DUP_Util::isWinOS() ? shell_exec('where ' . $tool . ' 2>NUL') : shell_exec('command -v ' . $tool . ' 2>/dev/null');
      return is_string($out) && trim($out) !== '';
    };

    $publicKey = null;
    if (!empty($data['encryptionPublicKey']) && DupCrypto::isAvailable()) {
      try {
        DupCrypto::parsePublicKey($data['encryptionPublicKey']);
        $publicKey = 'valid';
      } catch (\Exception $e) {
        $publicKey = $e->getMessage();
      }
    }

    $path = $this->dup->path;
    return array(
      'host' => (string) $config->httpHost,
      'defaultPath' => (string) $this->dup->defaultPath,
      'folder' => (string) $path,
      'folderWritable' => $path && is_dir($path) && is_writable($path),
      'diskFree' => $path && function_exists('disk_free_space') && is_dir($path) ? (int) @disk_free_space($path) : null,
      'cronScript' => rtrim($config->paths->{Duplicator::DUP_APPLICATION_NAME}, '/\\') . '/cron.php',
      'lazyCron' => $modules->isInstalled('LazyCron'),
      'pwCron' => $modules->isInstalled('PwCron'),
      'phpZip' => extension_loaded('zip'),
      'mysqldump' => $which('mysqldump'),
      'zipBinary' => !DUP_Util::isWinOS() && $which('zip'),
      'sodium' => DupCrypto::isAvailable(),
      'publicKey' => $publicKey,
      'sdk' => array(
        'dropbox' => class_exists('\Kunnu\Dropbox\DropboxApp') || class_exists('\DropboxClient', false),
        'googledrive' => class_exists('\Google_Client'),
        'amazon' => class_exists('\Aws\S3\S3Client'),
      ),
      'maxPackages' => Duplicator::DUP_MAX_PACKAGES,
      'dupcryptUrl' => $this->wire('page')->url . '?action=dupcrypt',
    );
  }

  protected function configData()
  {
    $stored = $this->wire('modules')->getConfig('Duplicator');
    return array_merge(Duplicator::getDefaultSettings(), is_array($stored) ? $stored : array());
  }

  /**
   * The module's own settings Inputfields, used to validate and sanitize
   */
  protected function settingsForm(array $data)
  {
    $form = $this->wire('modules')->get('InputfieldForm');
    $form->protectCSRF = false; // checked in handle() for every POST
    $form->add(Duplicator::getModuleConfigInputfields($data));
    return $form;
  }

  protected function fieldNames(InputfieldForm $form)
  {
    $names = array();
    foreach ($form->getAll() as $f) {
      $name = (string) $f->attr('name');
      if ($name !== '' && !$f instanceof InputfieldMarkup && !$f instanceof InputfieldWrapper) $names[] = $name;
    }
    return $names;
  }

  /**
   * Validate the posted settings with the module's own Inputfields, then save them
   *
   * @param mixed $posted JSON object of name => value
   */
  protected function saveSettings($posted)
  {
    $values = json_decode((string) $posted, true);
    if (!is_array($values)) return array('ok' => false, 'error' => 'Invalid settings.');
    $data = $this->configData();

    $form = $this->settingsForm($data);
    $input = new WireInputData();
    foreach ($form->getAll() as $f) {
      $name = (string) $f->attr('name');
      if ($name === '' || $f instanceof InputfieldMarkup || $f instanceof InputfieldWrapper) continue;
      if ($f instanceof InputfieldCheckbox) {
        $checked = array_key_exists($name, $values) ? !empty($values[$name]) : !empty($data[$name]);
        if ($checked) $input->set($name, $f->attr('value') ?: 1);
        continue;
      }
      $value = array_key_exists($name, $values) ? $values[$name] : (isset($data[$name]) ? $data[$name] : '');
      // an empty secret keeps the stored one (secrets are never sent to the browser)
      if (in_array($name, self::SECRET_FIELDS, true) && ($value === '' || $value === null)) {
        $value = isset($data[$name]) ? $data[$name] : '';
      }
      $input->set($name, is_array($value) ? array_map('strval', $value) : (string) $value);
    }
    $form->processInput($input);

    // only the form's fields are written over the stored settings, like the native page does:
    // defaults must not be stored, some are computed at runtime (defaultPath)
    $stored = $this->wire('modules')->getConfig('Duplicator');
    $stored = is_array($stored) ? $stored : array();
    unset($stored['defaultPath']);
    $errors = array();
    foreach ($form->getAll() as $f) {
      $name = (string) $f->attr('name');
      if ($name === '' || $f instanceof InputfieldMarkup || $f instanceof InputfieldWrapper) continue;
      foreach ($f->getErrors(true) as $error) $errors[$name][] = html_entity_decode((string) $error, ENT_QUOTES, 'UTF-8');
      $stored[$name] = $f instanceof InputfieldCheckbox ? ($f->attr('checked') ? 1 : 0) : $f->attr('value');
    }
    if (count($errors)) return array('ok' => false, 'errors' => $errors);

    $this->wire('modules')->saveConfig('Duplicator', $stored);
    // the module instance of this request still has the old values
    foreach ($stored as $key => $value) $this->dup->set($key, $value);
    return array('ok' => true) + $this->settings();
  }
}
