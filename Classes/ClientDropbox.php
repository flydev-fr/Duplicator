<?php


class DropboxClient
{
    protected $accessToken;
    protected $identifier;
    protected $folder;
    protected $query;
    protected $maxPackages;
    protected $mime_type;


    public function __construct($token = null, $identifier = null, $folder = null, $query = null, $maxPackages = 0, $mime_type = null)
    {
        $this->accessToken = $token;
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
        $client = null;
        DUP_Logs::log("Dropbox: connecting...");
        try {
            $client = new \Dropbox\Client($this->accessToken, $this->identifier);
        } catch (\Exception $ex) {
            DUP_Logs::log("Dropbox error: cannot create client, invalid AccessToken.", 'error');
        }

        return $client;
    }

    public function download($dropboxPath, $path)
    {
        $client = $this->connect();
        $pathError = \Dropbox\Path::findErrorNonRoot($dropboxPath);
        if ($pathError !== null) {
            DUP_Logs::log("Invalid <dropbox-path>: $pathError\n");
        }
        $metadata = $client->getFile($dropboxPath, fopen($path, "w+"));
        if ($metadata === null) {
            DUP_Logs::log("File not found on Dropbox.\n");
        }
        return $metadata;
    }

    public function upload($package)
    {
        $client = $this->connect();
        $f = @fopen($package, "rb");
        DUP_Logs::log("Dropbox: uploading {$package}...");
        $client->uploadFile('/' . basename($package), \Dropbox\WriteMode::add(), $f);
        fclose($f);
        /*
                if(strlen($this->query) > 0 && strlen($this->mime_type) > 0)
                {
                    $files = $this->getFiles();
                    $this->deleteFile($files);
                }
        */

        DUP_Logs::log("Dropbox: $package uploaded successfully.", 'message');

        return true;
    }

    public function getFiles()
    {
        $client = $this->connect();
        DUP_Logs::log("Dropbox: getting files...");
        $folderMetadata = $client->getMetadataWithChildren("/");
        $children = null;
        $files = array();

        if ($folderMetadata['is_dir']) {
            $children = $folderMetadata['contents'];
            unset($folderMetadata['contents']);
        }
        if ($children != null && count($children) > 0) {
            foreach ($children as $child) {
                $name = \Dropbox\Path::getName($child['path']);
                if ($child['is_dir']) continue;
                if (strchr($name, $this->query) && $child['mime_type'] == $this->mime_type) {
                    //$files[] = $name;
                    $info = array(
                        'name' => $name,
                        'size' => $child['size'],
                    );
                    array_push($files, $info);
                }
            }
        }
        return $files;
    }

    public function deleteFiles(array $files) {
        $client = $this->connect();
        foreach ($files as $file)
        {
            $client->delete('/' . $file['Key']);
            DUP_Logs::log("Dropbox: deleted {$file['Key']}");
        }
    }

    public function deleteFile($file) {
        $client = $this->connect();
        $client->delete('/' . $file);
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
            if($ts == false) continue;
            $shouldDelete = ($retaincount > 0 && $n > $retaincount) || $ts->getTimestamp() < (strtotime("-{$deadline}")) ;
            if ($shouldDelete) {
                $toDelete[] = array('Key' => $key);
            }
        }
        if (count($toDelete)) {
            $this->deleteFiles($toDelete);
        }
    }
}


class DropBoxClientException extends \Exception
{}