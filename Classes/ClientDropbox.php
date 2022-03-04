<?php

use \Kunnu\Dropbox\DropboxApp;
use Kunnu\Dropbox\Dropbox;

class DropboxClient
{
  protected $accessToken;
  protected $appKey;
  protected $appSecret;
  protected $dropboxService;
  protected $identifier;
  protected $folder;
  protected $query;
  protected $maxPackages;
  protected $mime_type;


  public function __construct($token = null, $appKey = null, $appSecret = null, $identifier = null, $folder = null, $query = null, $maxPackages = 0, $mime_type = null)
  {
    $this->accessToken = $token;
    $this->appKey = $appKey;
    $this->appSecret = $appSecret;
    $this->identifier = $identifier;
    $this->folder = $folder;
    $this->query = $query;
    $this->maxPackages = $maxPackages;
    $this->mime_type = $mime_type;
  }

  public function setAccessToken($accessToken)
  {
    $this->accessToken = $accessToken;
  }

  public function setIdentifier($identifier)
  {
    $this->identifier = $identifier;
  }

  public function setFolder($folder)
  {
    $this->folder = $folder;
  }

  public function setQuery($query)
  {
    $this->query = $query;
  }

  public function setMaxPackages($maxPackages)
  {
    $this->maxPackages = $maxPackages;
  }

  public function setMimeType($mime_type)
  {
    $this->mime_type = $mime_type;
  }

  protected function connect()
  {
    $app = null;
    DUP_Logs::log("Dropbox: connecting...");
    try {
      $app = new DropboxApp($this->appKey, $this->appSecret, $this->accessToken);
      $dropboxService = new Dropbox($app);
    } catch (\Exception $ex) {
      DUP_Logs::log("Dropbox error: cannot create client, invalid AccessToken.", 'error');
    }

    return $dropboxService;
  }

  public function download($file, $destination)
  {
    $this->dropboxService = $this->connect();
    $searchQuery = $file;
    $searchResults = $this->dropboxService->search("/", $searchQuery, ['start' => 0, 'max_results' => 1]);
    $items = $searchResults->getItems();
    if ($items === null) {
      DUP_Logs::log("Dropbox error: File not found on Dropbox.\n");
    }
    $item = $items->first();
    $filename = $item->metadata['name'];
    DUP_Logs::log("Dropbox: downloading $filename\n");
    $file = $this->dropboxService->download("/" . $filename, $destination . "/" . $filename);
    return file_exists($destination . "/" . $filename);
  }

  public function upload($package)
  {
    $this->dropboxService = $this->connect();
    DUP_Logs::log("Dropbox: uploading " . basename($package) . "...");
    $this->dropboxService->upload($package, '/' . basename($package), ['autorename' => false]);
    DUP_Logs::log("Dropbox: " . basename($package) . " uploaded successfully.", 'message');

    return true;
  }

  public function getFiles()
  {
    $this->dropboxService = $this->connect();
    DUP_Logs::log("Dropbox: getting files...");
    $folderMetadata = $this->dropboxService->listFolder("/");
    $children = null;
    $data = $folderMetadata->getData();

    return $data['entries'];
  }

  public function deleteFiles(array $files)
  {
    $this->dropboxService = $this->connect();
    foreach ($files as $file) {
      $this->dropboxService->delete('/' . $file['Key']);
      DUP_Logs::log("Dropbox: deleted {$file['Key']}");
    }
  }

  public function deleteFile($file)
  {
    $this->dropboxService = $this->connect();
    $this->dropboxService->delete('/' . $file);
    DUP_Logs::log("Dropbox: deleted {$file}");
  }

  public function deleteOldBackups($retaincount, $deadline)
  {
    if ($retaincount < 1 && empty($deadline)) return; // cleanup disabled.
    $toDelete = array();
    $n = 0;
    $objects = $this->getFiles();
    array_multisort($objects, SORT_DESC);

    foreach ($objects as $object) {
      $n++;
      $key   = $object['name'];
      $tsstr = basename($key, '.package.zip');
      $parts = explode('-', $tsstr);
      array_pop($parts);
      $tsstr = implode('-', $parts);
      $ts    = date_create_from_format(Duplicator::DUP_TIMESTAMP_FORMAT, $tsstr);
      if ($ts == false) continue;
      $shouldDelete = ($retaincount > 0 && $n > $retaincount) || $ts->getTimestamp() < (strtotime("-{$deadline}"));
      if ($shouldDelete) {
        $toDelete[] = array('Key' => $key);
      }
    }
    if (count($toDelete)) {
      $this->deleteFiles($toDelete);
    }

    DUP_Logs::log("Dropbox: end");
  }
}


class DropBoxClientException extends \Exception
{
}