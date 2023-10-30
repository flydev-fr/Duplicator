<?php

namespace ProcessWire;

class BackupDatabase
{
  const DUP_MODE_PWAPI  = 0;
  const DUP_MODE_NATIVE = 1;

  protected $database;
  protected $options;
  protected $mode;
  protected $size;
  protected $OS;
  protected $cachePath;

  public function __construct(array $options = array())
  {
    $this->OS = DUP_Util::getOS();

    $this->options = array(
      'path' => '',
      'backup' => array(
        'filename' => '',
        'description' => '',
        'maxSeconds' => 120,
      ),
      'cachePath' => wire('config')->paths->cache,
      'chmodPermission' => '0700',
      'shellScript' => '',
      'zipbinary' => false,
    );
        
    $this->options = array_merge($this->options, $options);
  }

  public function setDatabase($database)
  {
    $this->database = $database;

    return $this;
  }

  public function setMode($mode)
  {
    $this->mode = $mode;

    return $this;
  }

  // set default shell script from stub file for Windows OS (batch file) or Unix OS (shell script)
  public function setDefaultShellScript($customScriptData = false)
  {
    $stub = '';
    if ($customScriptData) {
      $stub = $customScriptData;
    } else {
      $filename = DUP_Util::getStub($this->OS);
      if (!$filename) return false;
      $stub = file_get_contents($filename);
    }

    if ($stub) {
      $stub = str_replace('%%FILE%%', $this->options['backup']['filename'], $stub);
      $stub = str_replace('%%SERVER%%', wire('config')->dbHost, $stub);
      $stub = str_replace('%%DATABASE%%', wire('config')->dbName, $stub);
      $stub = str_replace('%%USER%%', wire('config')->dbUser, $stub);
      $stub = str_replace('%%PASS%%', wire('config')->dbPass, $stub);
      $stub = str_replace('%%CACHEPATH%%', $this->options['cachePath'], $stub);
      $stub = str_replace('%%PORT%%', wire('config')->dbPort, $stub);
    }

    // set shell script
    $this->options['shellScript'] = $stub;

    return $stub;
  }

  /*
     * do backup using different mode
     * return a SQL file
     *
     * TODO: mysqldump bin
     */
  public function backup()
  {
    DUP_Logs::log("Backup Database");
    switch ($this->mode) {
      case self::DUP_MODE_PWAPI:
        DUP_Logs::log("- Backup using standard mode");
        return $this->fromProcessWire();
        break;

      case self::DUP_MODE_NATIVE:
        DUP_Logs::log("- Backup using native tools");
        return $this->fromNativeTools();
        break;

      default:
        return false;
    }
  }

  /*
     * return a ZIP file containing the SQL backup file
     * (use of wireZipFile)
     */
  public function getZip()
  {
    $cachePath = $this->options['cachePath'];
    $sqlfile = $this->backup();
    $this->size = filesize($sqlfile);
    $zipfile = $this->options['path'] . $this->options['backup']['filename'] . '.zip';
    if ($this->mode === self::DUP_MODE_PWAPI) {
      $result = wireZipFile($zipfile, $sqlfile);
      DUP_Util::deleteFile($sqlfile);
      if (count($result['errors'])) {
        foreach ($result['errors'] as $error) {
          DUP_Logs::log("ZIP add failed: $error");
        }
      }
    } else if ($this->mode === self::DUP_MODE_NATIVE) {
      $return = null;
      $output = array();

      if ($this->OS === 'UNIX') {
        if ($this->options['zipbinary']) {
          // use zip binary as it's available
          exec('zip ' . $zipfile . ' ' . $sqlfile, $output, $return);
        }
        else {
          // use processwire zip function
          $output = wireZipFile($zipfile, $sqlfile);
          if (!count($output['errors'])) {
            $return = 0;
          }
        }
        
        unlink($cachePath . 'duplicator.sh');
      } else {
        exec(str_replace('\\', '/', wire('config')->paths->Duplicator) . 'Bin/7za.exe a ' . $zipfile . ' ' . str_replace('\\', '/', $sqlfile), $output, $return);
        unlink(str_replace('\\', '/', $cachePath) . 'duplicator.bat');
      }

      if ($return !== 0) {
        if (count($output)) {
          foreach ($output as $error) {
            DUP_Logs::log("ZIP error: $error");
          }
        }
      }
    }

    if (file_exists($sqlfile)) {
      unlink($sqlfile);
    }

    if (file_exists($zipfile)) {
      return $zipfile;
    }

    return false;
  }

  public function getSize()
  {
    return $this->size;
  }

  /*
     * Backup the database using ProcessWire API
     */
  protected function fromProcessWire()
  {
    $backup = new WireDatabaseBackup($this->options['path']);
    $backup->setDatabase($this->database);
    $backup->setDatabaseConfig(wire('config'));
    $sqlfile = $backup->backup($this->options);

    return $sqlfile;
  }

  /*
     * Backup the database using native tools
     */
  protected function fromNativeTools()
  {
    switch ($this->OS) {
      case 'UNIX':
        return $this->UnixNative();

      case 'WINDOWS':
        return $this->WindowsNative();

      default:
        // not supported platform
        return false;
    }

    return false;
  }

  protected function UnixNative()
  {
    if ($this->options['shellScript'] == '') {
      $data = $this->setDefaultShellScript(); 
      DUP_Logs::log("- using default shell script");
    } 
    else {
      $data = $this->setDefaultShellScript($this->options['shellScript']);
      DUP_Logs::log("- using custom shell script");
    }

    if (!$data) {
      throw new WireException("!! Error while running UnixNative Backup\n, err: shell script not found\n\n");
    }
    
    // remove carriage return or script will fail on unix
    $data = str_replace("\r", "", $data);

    $zipExec = shell_exec("which zip");
    // check if zip tool is available on env path
    if ($zipExec !== null) {
      $this->options['zipbinary'] = true;
      DUP_Logs::log("- zip binary is available");
    } else {
      DUP_Logs::log("- zip binary not available, fallback using wireZipFile function");
    }  

    $cachePath = $this->options['cachePath'];
    
    $return = null;
    $output = array();
    file_put_contents($cachePath . 'duplicator.sh', $data);
    /**
     *  Default:
     *    Chmod 700 (chmod a+rwx,g-rwx,o-rwx) sets permissions so that:
     *      (U)ser / owner can read, can write and can execute
     *      (G)roup can't read, can't write and can't execute
     *      (O)thers can't read, can't write and can't execute     
     */
    wireChmod($cachePath . 'duplicator.sh', false, $this->options['chmodPermission']);
    chdir($cachePath);
    exec('./duplicator.sh', $output, $return);

    if ($return !== 0) { // (int) The exit status of the command (0 for success, > 0 for errors)
      // bd($return);
      // bd($output);

      // delete `duplicator.sh` script on error
      unlink($cachePath . 'duplicator.sh');
      
      $meaning = '';
      if ($return == 1) {
        $meaning = 'general error in command line';
      } 
      else if ($return == 2) {
        $meaning = 'misuse of shell builtins (according to Bash documentation), verify that "mysqldump" or "zip" is available on env path';
      } 
      else if ($return == 126) {
        $meaning = 'command invoked cannot execute';
      } 
      else if ($return == 127) {
        $meaning = 'error while running the script, maybe the script is not well formatted (carriage return)';
      }
      else if ($return == 126) {
        $meaning = 'command invoked cannot execute';
      }
      else {
        $meaning = 'unknown error';
      }
      
      throw new WireException("!! Error while running UnixNative Backup\n, err {$return}: $meaning\n\n");
    }

    return $cachePath . $this->options['backup']['filename'];
  }

  protected function WindowsNative()
  {
    // exit if shell script is empty
    if ($this->options['shellScript'] === '') {
      return false;
    }

    $data = $this->setDefaultShellScript($this->options['shellScript']);
    if (!$data) {
      throw new WireException("!! Error while running UnixNative Backup\n, err: shell script not found\n\n");
    }

    $return = null;
    $output = array();
    $cachePath = $this->options['cachePath'];
    
    file_put_contents($cachePath . 'duplicator.bat', $data);

    chdir($cachePath);
    exec("duplicator.bat", $output, $return);

    if ($return !== 0) {
      // bd($return); // (int) The exit status of the command (0 for success, > 0 for errors)
      // bd($output);

      // delete `duplicator.bat` script on error
      unlink($cachePath . 'duplicator.bat');

      $ex = json_encode($output);
      throw new WireException("Error while running WindowsNative Backup\n, err {$return}: {$ex}\n\n");
    }

    return $cachePath . $this->options['backup']['filename'];
  }
}
