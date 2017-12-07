<?php

class FTPClient
{
    protected $host;
    protected $user;
    protected $password;
    // resource
    protected $connId;
    // conf
    protected $port = 21;
    protected $ssl = false;
    protected $passive_mode = true;
    protected $timeout = 90;

    protected $path;


    public function __construct()
    {
        if (!extension_loaded('ftp'))
        {
            throw new FTPClientException('PHP extension FTP is not loaded.');
        }
    }

    public function setPath($path)
    {
        $this->path = $path;
    }

    public function setHost($host)
    {
        $this->host = $host;
    }

    public function setPort($port)
    {
        $this->port = $port;
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function connect()
    {
        DUP_Logs::log("FTP: connecting to {$this->host}:{$this->port}...");
        $this->connId = ftp_connect($this->host, $this->port, (int)$this->timeout);
        if(!is_resource($this->connId))
            throw new FTPClientException("cannot connect to <{$this->host}> on port <{$this->port}>");

        DUP_Logs::log("FTP: connection established.");
    }

    public function ssl_connect()
    {
        if(!function_exists('ftp_ssl_connect'))
        {
            if (DUP_Util::isWinOS())
            {
                throw new FTPClientException("to make of use ftp_ssl_connect() on Windows you must compile your own PHP binaries.");
            }
            else
            {
                if(!extension_loaded('openssl'))
                {
                    throw new FTPClientException("PHP extension OPENSSL is not loaded.");
                }
            }

            throw new FTPClientException("cannot call ftp_ssl_connect().");
        }

        DUP_Logs::log("FTP: connecting to {$this->host}:{$this->port}...");
        $this->connId = ftp_ssl_connect($this->host, $this->port, $this->timeout);
        if(!is_resource($this->connId))
            throw new FTPClientException("cannot connect to <{$this->host}> on port <{$this->port}>");

        DUP_Logs::log("FTP: connection established.");
    }

    public function disconnect()
    {
        if(is_resource($this->connId))
        {
            @ftp_close($this->connId);
            DUP_Logs::log("FTP: disconnected from server.");
        }
    }

    public function login()
    {
        if(!is_resource($this->connId))
            throw new FTPClientException("cannot login. Invalid resource.");
        elseif(!@ftp_login($this->connId, $this->user, $this->password))
            throw new FTPClientException("cannot login. Please verify your connection informations.");

        DUP_Logs::log("FTP: logged in.");
    }

    public function put($remotefile, $localfile)
    {
        DUP_Logs::log("FTP: starting upload of {$localfile}");
        if(!@ftp_put($this->connId, $remotefile, $localfile, FTP_BINARY))
            throw new FTPClientException("cannot upload file <" . basename($localfile) . "> on server.");

        DUP_Logs::log("FTP: file transfer successfull.");
        return true;


    }

    public function pasv($passive_mode)
    {
        @ftp_pasv($this->connId, $passive_mode);
    }

    public function chdir($dir)
    {
        DUP_Logs::log("FTP: retrieving directory listing of {$dir}...");
        if(!@ftp_chdir($this->connId, $dir))
        {
            DUP_Logs::log("FTP: directory listing of {$dir} failed.");
            return false;
        }
        DUP_Logs::log("FTP: directory listing of {$dir} successfull.");
        return true;
    }

    public function mkdir($dir)
    {
        if(!@ftp_mkdir($this->connId, $dir))
        {
            DUP_Logs::log("cannot create directory <{$dir}>");
            return false;
        }

        DUP_Logs::log("FTP: created dir {$dir}");
        return true;
    }

    public function pwd()
    {
        return @ftp_pwd($this->connId);
    }

    public function mksubdirs($basedir, $path)
    {
        $this->chdir($basedir);
        $parts = explode('/', $path);

        foreach($parts as $part)
        {
            if(!$this->chdir($part))
            {
                $this->mkdir($part);
                $this->chdir($part);
            }
        }
    }

    public function delete($file)
    {
        DUP_Logs::log("FTP: deleting $file...");
        return @ftp_delete($this->connId, $file);
    }

    public function deleteFile($path, array $files)
    {
        foreach ($files as $key => $value)
        {
            $this->delete('/' . $path . '/' . $value['Key']);
        }
    }

    public function upload($remotefile, $localfile, $path)
    {
        if(!is_file($localfile))
            throw new FTPClientException("invalid file.");

        $parts = explode(DIRECTORY_SEPARATOR, $localfile);

        if(!strpos(array_reverse($parts)[0], Duplicator::DUP_PACKAGE_EXTENSION))
        {
            throw new FTPClientException("invalid file {$remotefile}, bad extension");
        }

        $path = ltrim(rtrim($path, '/'), '/');
        $parts = explode('/', $path);

        foreach ($parts as $part)
        {
            if(!$this->chdir($part))
            {
                $this->mkdir($part);
                $this->chdir($part);
            }
        }

        return $this->put($remotefile, $localfile);
    }

    public function deleteOldBackups($retaincount, $deadline = null)
    {
        if ($retaincount < 1 || empty($deadline)) return; // cleanup disabled.

        $toDelete = array();
        $n = 0;
        $paths = @ftp_nlist($this->connId, $this->pwd());
        $objects = array();
        foreach ($paths as $path)
        {
            $parts = explode('/', $path);
            $parts = array_reverse($parts);
            array_push($objects, $parts[0]);
        }

        array_multisort($objects, SORT_DESC);

        foreach ($objects as $object)
        {
            $n++;
            $key   = $object;
            $tsstr = basename($key, '.package.zip');
            $parts = explode('-', $tsstr);
            array_pop($parts);
            $tsstr = implode('-', $parts);
            $ts    = date_create_from_format(Duplicator::DUP_TIMESTAMP_FORMAT, $tsstr);
            if($ts == false) continue;
            $shouldDelete = ($retaincount > 0 && $n > $retaincount) || $ts->getTimestamp() < (strtotime("-{$deadline}")) ;
            if ($shouldDelete)
            {
                $toDelete[] = array('Key' => $key);
            }
        }

        if (count($toDelete))
        {
            $this->deleteFile('/' . $this->path . '/', $toDelete);
        }
    }
}


class FTPClientException extends Exception {}