<?php
/*
* https://developers.google.com/drive/v3/reference/
*/


class GoogleDriveClient
{
    protected $client;
    protected $tempFolder;
    protected $appName;
    protected $googleKeyFile;
    protected $shareWithEmail;
    protected $service;
    protected $maxPackages;

    const GOOGLE_CACHE = 'google-cache';


    public function __construct()
    {
    }

    public function setTempFolder($tempFolder)
    {
        $this->tempFolder = $tempFolder;
    }

    public function setAppName($appName)
    {
        $this->appName = $appName;
    }

    public function setGoogleKeyFile($googleKeyFile)
    {
        $this->googleKeyFile = $googleKeyFile;
    }

    public function setShareWithEmail($shareWithEmail)
    {
        $this->shareWithEmail = $shareWithEmail;
    }

    public function setMaxPackages($maxPackages)
    {
        $this->maxPackages = $maxPackages;
    }

    public function connect()
    {
        //$cacheDir = wireTempDir($this->tempFolder . DIRECTORY_SEPARATOR . self::GOOGLE_CACHE);
        $cacheDir = wireTempDir($this->tempFolder);
        if(is_dir($cacheDir))
            wireMkdir($cacheDir);

        try
        {
            if (!$this->service)
            {
                $client = new \Google_Client();
                $client->setCacheConfig(array(
                    'directory' => $cacheDir
                ));
                $client->setScopes(array(
                    'https://www.googleapis.com/auth/drive'
                ));
                DUP_Logs::log('GoogleDrive: creating client.');
            }
            else
            {
                $client = $this->service->getClient();
            }

            $this->service = new Google_Service_Drive($client);

            if ($client->isAccessTokenExpired())
            {
                DUP_Logs::log('GoogleDrive: token expired - creating auth.');
                $client->setApplicationName($this->appName);
                $client->setAuthConfig(json_decode($this->googleKeyFile, true));
                $client->refreshTokenWithAssertion();
                DUP_Logs::log('GoogleDrive: auth and token created.');
            }
            $this->client = $client;
        }
        catch(\Exception $ex)
        {
            DUP_Logs::log("GoogleDrive error: " . $ex->getMessage());
        }
    }

    // https://developers.google.com/api-client-library/php/guide/media_upload
    public function getFiles(array $parameters = array(), $deadline = null)
    {
        $files = array();
        $result = null;

        $defaults = array(
            "orderBy" => "modifiedTime", // get our result sorted by oldest
            'q' => "",
            'spaces' => 'drive',
            'pageSize' => 100,
            //'fields'   => self::queryfields // TESTING WITHOUT FIELD - v0.0.29
            'fields'   => 'nextPageToken, files(id,name,size)'
        );

        $parameters = array_merge($defaults, $parameters);
        $pageToken = null;

        do
        {
            try
            {
                //if ($pageToken)
                {
                    $parameters['pageToken'] = $pageToken;
                    $result = $this->service->files->listFiles($parameters);
                    $pageToken = $result->getNextPageToken();
                }
            }
            catch (\Exception $e)
            {
                DUP_Logs::log("GoogleDrive error: " . $e->getMessage());
                $pageToken = null;
            }
        }
        while($pageToken);

        try
        {
            if ($result && count($result->getFiles()) > 0)
            {
                $i = 0;
                foreach ($result->getFiles() as $file)
                {
                    $key   = $file['name'];
                    $tsstr = basename($key, '.package.zip');
                    $parts = explode('-', $tsstr);
                    array_pop($parts);
                    $tsstr = implode('-', $parts);
                    $ts = date_create_from_format(Duplicator::DUP_TIMESTAMP_FORMAT, $tsstr);
                    if($ts)
                    {
                        if ($i >= $this->maxPackages || (!empty($deadline) && $ts->getTimestamp() < (strtotime("-{$deadline}"))) )
                        {
                            $files[] = array(
                                'id' => $file->getId(),
                                'name' => $file->getName(),
                                'size' => $file['size'],
                                'object' => $file
                            );
                        }
                    }
                    $i++;
                }
                return $files;
            }
        }
        catch (\Google_Exception $e)
        {
            DUP_Logs::log("GoogleDrive error: " . $e->getMessage());
            $pageToken = null;
        }

        return null;
    }

    // https://developers.google.com/drive/v3/web/search-parameters
    public function getPackages($extension, $mime_type, $deadline)
    {
        $params = array(
            'q' => "name contains '{$extension}' and mimeType='{$mime_type}'",
        );
        $files = $this->getFiles($params, $deadline);

        return $files;
    }

    public function getPackage($name, $mime_type, $deadline)
    {
        $params = array(
            'q' => "name = '{$name}' and mimeType='{$mime_type}'",
        );
        $files = $this->getFiles($params, $deadline);

        return $files;
    }

    public function download($file, $path) {
        $fileId = $file[0]['id'];
        $response = $this->service->files->get($fileId, array(
            'alt' => 'media' ));
        //$content = $response->getBody()->getContents();
        $f = fopen($path, 'w+');
        while (!$response->getBody()->eof()) {
            fwrite($f, $response->getBody()->read(1024));
        }
        fclose($f);

        return $f;
    }

    public function deleteFiles(array $files)
    {
        try
        {
            $n = 0;

            foreach ($files as $key => $value) {
                $this->service->files->delete($value['id']);
                DUP_Logs::log("GoogleDrive: deleted {$value['name']}");
                $n++;
            }
            DUP_Logs::log("GoogleDrive: deleted {$n} old packages");
        }
        catch (\Exception $e)
        {
            DUP_Logs::log("GoogleDrive error: deleteFiles() - " . $e->getMessage());
        }
    }

    public function deleteFile($file)
    {
        try
        {
            $this->service->files->delete($file);
            DUP_Logs::log("GoogleDrive: deleted $file ");
        }
        catch (\Exception $e)
        {
            DUP_Logs::log("- GoogleDrive error: deleteFile() - " . $e->getMessage());
        }
    }    
    
    public function upload($filename, $mimeType = null)
    {
        try
        {
            $service = $this->service;

            DUP_Logs::log("GoogleDrive: uploading " . basename($filename));
            $file = new \Google_Service_Drive_DriveFile();
            $file->setName(basename($filename));

            //The number of bytes
            $chunkSizeBytes = 5 * 1024 * 1024;

            $service->getClient()->setDefer(true);

            $request = $service->files->create($file);

            $media = new \Google_Http_MediaFileUpload($service->getClient(), $request, $mimeType, null, true, $chunkSizeBytes);

            $media->setFileSize(filesize($filename));

            $count = 0;
            $chunkSizeMB = $chunkSizeBytes / (1024 * 1024);

            $status = false;
            $handle = fopen($filename, "rb");
            while (!$status && !feof($handle))
            {
                $chunk = fread($handle, $chunkSizeBytes);
                $status = $media->nextChunk($chunk);
                $count++;
                DUP_Logs::log("GoogleDrive: uploaded chunk of " . ($count * $chunkSizeMB) . "MB");
            }

            $result = false;
            if ($status != false)
                $result = $status;

            fclose($handle);

            $service->getClient()->setDefer(false);

            $permission = new \Google_Service_Drive_Permission();
            $permission->setRole('writer');
            $permission->setType('user');
            $permission->setKind('duplicator');
            $permission->setEmailAddress($this->shareWithEmail);

            $perm = $service->permissions->create($result->getId(), $permission,
                array(
                    'sendNotificationEmail' => false
                    //'transferOwnership' => true // code": 400, "message": "Bad Request. User message: \"You can't yet change the owner of this item. (We're working on it.)\""
                )
            );

            DUP_Logs::log($perm->getId() ? "- GoogleDrive: successfully uploaded file." : "GoogleDrive: upload failed.", 'message');

            return $perm;
        }
        catch(\Exception $ex)
        {
            DUP_Logs::log("GoogleDrive error: " . $ex->getMessage());
        }

        return null;
    }

    // ref: https://gist.github.com/bshaffer/9bb2cdccd315880ab52f#file-drive-php-L1565
    public function getStorageQuota()
    {
        try {
            $optParams = array(
                'fields' => 'storageQuota'
            );
            $q = $this->service->about->get($optParams);

            return $q->getStorageQuota();
        }
        catch (Exception $e) {
            DUP_Logs::log( "GoogleDrive error: " . $e->getMessage());
        }

        return null;
    }
}


class GoogleDriveClientException extends \Exception
{
    public function __construct($message = null, $code = 0, Exception $previous = null) {
        DUP_Logs::log($message);
        parent::__construct($message, $code, $previous);
    }
}