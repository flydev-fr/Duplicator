<?php

namespace ProcessWire;

class BackupDatabase
{
  protected $database;
  protected $options;
  protected $mode;
  protected $size;
  protected $OS;
  protected $cachePath;

  public function __construct(array $options = array())
  {
    $this->options = array(
      'path' => '',
      'backup' => array(
        'filename' => '',
        'description' => '',
        'maxSeconds' => 120,
      ),
      'cachePath' => wire('config')->paths->cache,
      'chmodPermission' => '0600'
    );

    $this->options = array_merge($this->options, $options);

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
      $this->OS = 'WINDOWS';
    } else {
      $this->OS = 'UNIX';
    }
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
      case 'MODE_PWAPI':
        DUP_Logs::log("- Backup using standard mode");
        return $this->fromProcessWire();
        break;

      case 'MODE_NATIVE':
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
    if ($this->mode === 'MODE_PWAPI') {
      $result = wireZipFile($zipfile, $sqlfile);
      DUP_Util::deleteFile($sqlfile);
      if (count($result['errors'])) {
        foreach ($result['errors'] as $error) {
          DUP_Logs::log("ZIP add failed: $error");
        }
      }
    } else if ($this->mode === 'MODE_NATIVE') {
      $return = null;
      $output = array();

      if ($this->OS === 'UNIX') {
        exec('zip ' . $zipfile . ' ' . $sqlfile, $output, $return);
        unlink($sqlfile);
        unlink($cachePath . 'duplicator.sh');
      } else {
        exec(str_replace('\\', '/', wire('config')->paths->Duplicator) . 'Bin/7za.exe a ' . $zipfile . ' ' . str_replace('\\', '/', $sqlfile), $output, $return);
        unlink($sqlfile);
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
    $cachePath = $this->options['cachePath'];
    $data = '
        # (1) set up all the mysqldump variables
        FILE=' . $this->options['backup']['filename'] . '
        DBSERVER=127.0.0.1
        DATABASE=' . wire('config')->dbName . '
        USER=' . wire('config')->dbUser . '
        PASS=' . wire('config')->dbPass . '

        # (2) in case you run this more than once a day, remove the previous version of the file
        unalias rm     2> /dev/null
        rm ${FILE}     2> /dev/null
        rm ${FILE}.zip  2> /dev/null

        # (3) do the mysql database backup (dump)
        # for a database server on a separate host:
        # mysqldump --opt --protocol=TCP --user=${USER} --password=${PASS} --host=${DBSERVER} ${DATABASE} > ' . $cachePath . '${FILE}
        # use this command for a database server on localhost. add other options if need be.
        mysqldump --routines --triggers --single-transaction --log-error=mysqldump_error.log --user=${USER} --password=${PASS} --databases ${DATABASE} > ' . $cachePath . '${FILE}
        ';

    $return = null;
    $output = array();
    file_put_contents($cachePath . 'duplicator.sh', $data);
    /**
     *  Default:
     *    Chmod 600 (chmod a+rwx,u-x,g-rwx,o-rwx) sets permissions so that:
     *      (U)ser / owner can read, can write and can't execute.
     *      (G)roup can't read, can't write and can't execute. 
     *      (O)thers can't read, can't write and can't execute.
     */
    wireChmod($cachePath . 'duplicator.sh', false, $this->options['chmodPermission']);
    chdir($cachePath);
    exec('./duplicator.sh', $output, $return);

    if ($return !== 0) { // (int) The exit status of the command (0 for success, > 0 for errors)
      // bd($return);
      // bd($output);

      // delete `duplicator.sh` script on error
      unlink($cachePath . 'duplicator.sh');

      $ex = json_encode($output);
      throw new WireException("Error while running UnixNative Backup\n, err {$return}: {$ex}\n\n");
    }

    return $cachePath . $this->options['backup']['filename'];
  }

  protected function WindowsNative()
  {
    $cachePath = $this->options['cachePath'];
    $data = '@echo off
        set MYSQLDATABASE=' . wire('config')->dbName . '
        set MYSQLUSER=' . wire('config')->dbUser . '
        set MYSQLPASS=' . wire('config')->dbPass . '
        "mysqldump.exe" -u%MYSQLUSER% -p%MYSQLPASS% --single-transaction --skip-lock-tables --routines --triggers %MYSQLDATABASE% > ' . $cachePath . $this->options['backup']['filename'];

    file_put_contents($cachePath . 'duplicator.bat', $data);

    $return = null;
    $output = array();
    chdir($cachePath);
    exec("\"${cachePath}duplicator.bat\"", $output, $return);
    
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