<?php namespace ProcessWire;


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
        $sqlfile = $this->backup();
        $this->size = filesize($sqlfile);
        $zipfile = $this->options['path'] . $this->options['backup']['filename'] . '.zip';
        if($this->mode === 'MODE_PWAPI') {
            $result = wireZipFile($zipfile, $sqlfile);
            DUP_Util::deleteFile($sqlfile);
            if(count($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    DUP_Logs::log("ZIP add failed: $error");
                }
            }
        }
        else if($this->mode === 'MODE_NATIVE') {
            $return = null;
            $output = array();
            exec('zip '. $zipfile . ' '. $sqlfile, $output, $return);
            exec('rm '. $sqlfile);
            exec('rm '. wire('config')->paths->cache .'duplicator.sh');
            if($return !== 0) {
                if(count($output)) {
                    foreach ($output as $error) {
                        DUP_Logs::log("ZIP error: $error");
                    }
                }
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

    /*
     * Backup the database using native tools
     */
    protected function fromNativeTools()
    {
        $OS = 'UNIX';

        switch($OS) {
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

    protected function UnixNative() {

        $data = '
        # (1) set up all the mysqldump variables
        FILE='. $this->options['backup']['filename'] .'
        DBSERVER=127.0.0.1
        DATABASE='. wire('config')->dbName .'
        USER='. wire('config')->dbUser .'
        PASS='. wire('config')->dbPass .'

        # (2) in case you run this more than once a day, remove the previous version of the file
        unalias rm     2> /dev/null
        rm ${FILE}     2> /dev/null
        rm ${FILE}.zip  2> /dev/null

        # (3) do the mysql database backup (dump)
        # for a database server on a separate host:
        # mysqldump --opt --protocol=TCP --user=${USER} --password=${PASS} --host=${DBSERVER} ${DATABASE} > '. wire('config')->paths->cache . '${FILE}
        # use this command for a database server on localhost. add other options if need be.
        mysqldump --routines --triggers --single-transaction --log-error=mysqldump_error.log --user=${USER} --password=${PASS} --databases ${DATABASE} > '. wire('config')->paths->cache . '${FILE}
        ';

        file_put_contents(wire('config')->paths->cache . 'duplicator.sh', $data);
        wireChmod(wire('config')->paths->cache . 'duplicator.sh', false, "0744");

        $return = null;
        $output = array();
        chdir(wire('config')->paths->cache);
        exec('./duplicator.sh', $output, $return);
        if($return !== 0) {
            bd($return); // (int) The exit status of the command (0 for success, > 0 for errors)
            bd($output);
        }

        return wire('config')->paths->cache . $this->options['backup']['filename'];
    }

    protected function WindowsNative() {
        $data = '@echo off
        set MYSQLDATABASE='. wire('config')->dbName .'
        set MYSQLUSER='. wire('config')->dbUser .'
        set MYSQLPASS='. wire('config')->dbPass .'
        set DUMPPATH="'. wire('config')->paths->cache . '"

        mysqldump -u%MYSQLUSER% -p%MYSQLPASS% --routines --triggers %MYSQLDATABASE% > '. $this->options['backup']['filename'];

        file_put_contents('./'. wire('config')->paths->cache . 'duplicator.bat', $data);

        $return = null;
        $output = array();
        exec(wire('config')->paths->cache . 'duplicator.bat', $output, $return);
        if($return !== 0) {
            bd($return); // (int) The exit status of the command (0 for success, > 0 for errors)
            bd($output);
        }

        return wire('config')->paths->cache . $this->options['backup']['filename'];
    }
}