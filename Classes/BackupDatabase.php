<?php


class BackupDatabase
{
    protected $database;
    protected $options;
    protected $mode;
    protected $size;

    public function __construct(array $options = array())
    {
        $this->options = array(
            'path' => '',
            'backup' => array(
                'filename' => '',
                'description' => '',
                'maxSeconds' => 120
            )
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

    /*
     * do backup using different mode
     * return a SQL file
     *
     * TODO: mysqldump bin
     */
    public function backup()
    {
        switch ($this->mode) {
            case 'MODE_PWAPI':
                return $this->fromProcessWire();
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
        $sqlfile = $this->backup();
        $this->size = filesize($sqlfile);
        $zipfile = $this->options['path'] . $this->options['backup']['filename'] . '.zip';
        $result = wireZipFile($zipfile, $sqlfile);
        DUP_Util::deleteFile($sqlfile);
        if(count($result['errors'])) {
            foreach ($result['errors'] as $error) {
                DUP_Logs::log("ZIP add failed: $error");
            }
        }
        if (file_exists($zipfile)) return $zipfile;

        return false;
    }

    public function getSize() {
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
}