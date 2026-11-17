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
   * The settings form, from the same Inputfields as Modules > Configure > Duplicator
   */
  protected function settings()
  {
    $data = $this->configData();
    $wrapper = Duplicator::getModuleConfigInputfields($data);
    return array('sections' => $this->serialize($wrapper, $data));
  }

  protected function configData()
  {
    $stored = $this->wire('modules')->getConfig('Duplicator');
    return array_merge(Duplicator::getDefaultSettings(), is_array($stored) ? $stored : array());
  }

  /**
   * @param InputfieldWrapper $wrapper
   * @param array $data Stored settings, to tell whether a secret is set
   * @return array Nodes: fieldsets with their fields
   */
  protected function serialize(InputfieldWrapper $wrapper, array $data)
  {
    $sanitizer = $this->wire('sanitizer');
    $text = function ($value) use ($sanitizer) {
      $value = (string) $value;
      return strlen($value) ? $sanitizer->entitiesMarkdown($value, array('fullMarkdown' => false)) : '';
    };
    $nodes = array();
    foreach ($wrapper->children() as $f) {
      /** @var Inputfield $f */
      $node = array(
        'type' => strtolower(str_replace('Inputfield', '', $f->className())),
        'label' => (string) $f->label,
        'description' => $text($f->description),
        'notes' => $text($f->notes),
        'icon' => (string) $f->icon,
        'showIf' => (string) $f->getSetting('showIf'),
      );
      if ($f instanceof InputfieldWrapper && !($f instanceof InputfieldMarkup)) {
        $node['type'] = 'fieldset';
        $node['children'] = $this->serialize($f, $data);
        $nodes[] = $node;
        continue;
      }
      if ($f instanceof InputfieldMarkup) {
        $html = (string) ($f->markupText ?: $f->attr('value'));
        // the native page's header and its link to this app have no place in the app
        if (strpos($html, "id='dup-head'") !== false || strpos($html, "id='dup-app-link'") !== false) continue;
        $node['type'] = 'markup';
        // built by the module itself (no user input): trusted HTML
        $node['html'] = $html;
        $nodes[] = $node;
        continue;
      }
      $name = (string) $f->attr('name');
      if ($name === '') continue;
      $node['name'] = $name;
      $node['required'] = (bool) $f->getSetting('required');
      $node['requiredIf'] = (string) $f->getSetting('requiredIf');
      $node['columnWidth'] = (int) $f->getSetting('columnWidth');
      $errors = $f->getErrors(true);
      $node['errors'] = array_values(array_map('strval', $errors));
      if ($f instanceof InputfieldCheckbox) {
        $node['value'] = $f->attr('checked') ? 1 : 0;
      } else if ($f instanceof InputfieldSelect) {
        $options = array();
        foreach ($f->getOptions() as $value => $label) {
          $options[] = array('value' => (string) $value, 'label' => (string) $label);
        }
        $node['options'] = $options;
        $value = $f->attr('value');
        $node['value'] = is_array($value) ? array_values(array_map('strval', $value)) : (string) $value;
        if ($f instanceof InputfieldSelectMultiple || $f instanceof InputfieldAsmSelect) $node['multiple'] = true;
      } else {
        $node['value'] = (string) $f->attr('value');
      }
      if (in_array($name, self::SECRET_FIELDS, true)) {
        $node['secret'] = true;
        $node['hasValue'] = !empty($data[$name]);
        $node['value'] = '';
      }
      $nodes[] = $node;
    }
    return $nodes;
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

    // the module's Inputfields validate and sanitize, like the native settings page
    $form = $this->wire('modules')->get('InputfieldForm');
    $form->protectCSRF = false; // checked in handle() for every POST
    $form->add(Duplicator::getModuleConfigInputfields($data));
    $input = new WireInputData();
    foreach ($form->getAll() as $f) {
      $name = (string) $f->attr('name');
      if ($name === '' || $f instanceof InputfieldMarkup) continue;
      if ($f instanceof InputfieldCheckbox) {
        if (!empty($values[$name])) $input->set($name, $f->attr('value') ?: 1);
        continue;
      }
      if (!array_key_exists($name, $values)) {
        $input->set($name, $f->attr('value'));
        continue;
      }
      $value = $values[$name];
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
      if ($name === '' || $f instanceof InputfieldMarkup) continue;
      foreach ($f->getErrors(true) as $error) $errors[$name][] = (string) $error;
      $stored[$name] = $f instanceof InputfieldCheckbox ? ($f->attr('checked') ? 1 : 0) : $f->attr('value');
    }
    if (count($errors)) return array('ok' => false, 'errors' => $errors);

    $this->wire('modules')->saveConfig('Duplicator', $stored);
    $data = array_merge(Duplicator::getDefaultSettings(), $stored);
    return array('ok' => true, 'sections' => $this->serialize(Duplicator::getModuleConfigInputfields($data), $data));
  }
}
