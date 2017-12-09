<?php

define('DS', DIRECTORY_SEPARATOR);

class DUP_Util
{
    static private $limitItems = 0;

    public static function foldersize($path)
    {
        $total_size = 0;
        if (!is_dir($path)) return 0;
        $files = scandir($path);
        $cleanPath = rtrim($path, '/') . '/';

        foreach ($files as $t) {
            if ($t <> "." && $t <> "..") {
                $currentFile = $cleanPath . $t;
                if (is_dir($currentFile)) {
                    $size = self::foldersize($currentFile);
                    $total_size += $size;
                } else {
                    $size = filesize($currentFile);
                    $total_size += $size;
                }
            }
        }

        return $total_size;
    }

    public static function filesize($file)
    {
        $filesize = filesize($file); // bytes
        return round($filesize / 1024 / 1024, 1); // in MB
    }

    public static function human_filesize($bytes, $decimals = 2) {
        $size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f ", $bytes / pow(1024, $factor)) . @$size[$factor];
    }

    // 2017-01-28_13-55-17-anything-i-find-descriptive.package.zip
    public static function formatFilename($name, $extension)
    {
        $date = date('Y-m-d_H-i-s'); // +is
        $filename = $date . '-' . str_replace(' ', '_', $name);
        $filename .= $extension ? '.' . $extension : '';

        return $filename;
    }

    public static function keep($path, $size = 1, $deadline = null)
    {
        if (empty($path)) return false;
        $files = scandir($path);
        $last = null;
        if (!count($files)) return false;
        foreach ($files as $file) {
            if (strrchr($file, '.') != strrchr(Duplicator::DUP_PACKAGE_EXTENSION, '.')) continue;
            $date = filemtime($path . DIRECTORY_SEPARATOR . $file);
            if ($deadline && $date < $deadline) continue;
            $last[$file] = $date;
        }
        if (is_array($last)) {
            arsort($last);
            $last = array_keys($last);
            if (!count($last)) return false;
            return array_slice($last, 0, $size);
        }

        return false;
    }

    public static function clean($path, $size = 1, $deadline = null)
    {
        if (!DUP_Util::keep($path)) return array();

        $cleaned = array();

        $error_message = __("Removing %1s from %2s failed!");
        $keep = DUP_Util::keep($path, $size, $deadline);
        foreach (new \DirectoryIterator($path) as $backup) {
            if ($backup->getExtension() != 'zip' && $backup->getExtension() != 'json') continue;
            $backup = $backup->getFilename();
            if (in_array($backup, $keep)) continue;
            if (DUP_Util::deleteFile($path . DIRECTORY_SEPARATOR . $backup)) {
                $cleaned[] = $backup;
                continue;
            } else DUP_Logs::log(sprintf($error_message, $backup, $path));
        }

        return $cleaned;
    }

    public static function deleteFile($path)
    {
        $res = false;
        if (file_exists($path) && !is_dir($path)) {
            $res = unlink($path);
        }

        return $res;
    }

    // TODO: check timestamp
    public static function getTotalPackages($path, $extension)
    {
        //$ext = '.' . $extension;
        if (!empty($path) && is_dir($path)) {
            $files = scandir($path);
            if (!count($files)) return 0;
            $n = 0;
            foreach ($files as $file) {
                if (strrpos($file, $extension) == false || (strrpos($file, 'json') !== false)) continue;
                $n++;

            }
            return $n;
        }

        return 0;
    }

    public static function getPackages($path, $extension)
    {
        //$ext = '.' . $extension;
        if (!empty($path) && is_dir($path)) {
            $files = scandir($path);
            if (!count($files)) return 0;
            $n = 0;
            foreach ($files as $file) {
                if (strrchr($file, $extension) == false) continue;
                $n++;

            }
            return $files;
        }

        return 0;
    }

    public static function getPackagesDetails($path, $extension)
    {
        //$extension = '.' . $extension;
        $data = null;
        $rows = array();
        $modal = isset(wire('input')->get->modal) ? '&modal=1' : '';
        if (!empty($path) && is_dir($path)) {
            $files = scandir($path);
            if (!count($files)) return $data;
            $n = 0;
            foreach ($files as $file) {
                if ((strrpos($file, $extension) == false) || (strrpos($file, 'json') !== false)) continue;
                $originalFilename = $file;
                $parts = explode('-', $file);
                array_pop($parts);
                $tsstr = implode('-', $parts);
                $ts = date_create_from_format(Duplicator::DUP_TIMESTAMP_FORMAT, $tsstr);
                $createdOn = ($ts === false) ? 'invalid timestamp' : wireRelativeTimeStr($ts->getTimestamp());

                $href1 = '<a href="#" onclick="location.href=\'?action=packages&installer='. $originalFilename .'\';" class="btnlink"><i class="fa fa-bolt"></i> Installer</a>';
                $href2 = '<a href="#" onclick="location.href=\'?action=packages&file='. $originalFilename .'\';" class="btnlink"><i class="fa fa-download"></i> Package</a>';
                $href3 = '<a href="#" onclick="location.href=\'?action=delete&file='. $originalFilename.$modal .'\';" class="btnlink"><i class="fa fa-trash"></i></a>';

                $data = array(
                    $originalFilename,
                    $createdOn,
                    self::human_filesize(filesize($path . DIRECTORY_SEPARATOR . $file)),
                    $href1 . ' ' . $href2 . ' ',
                    $href3
                );
                array_push($rows, $data);

                $n++;
            }

            return array_reverse($rows);
        }
    }

    public static function isWinOS()
    {
        return (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    }

    static public function safePath($path)
    {
        return str_replace("\\", "/", $path);
    }

    static public function getMicrotime()
    {
        return microtime(true);
    }

    static public function setMemoryLimit($value = 30)
    {
        $prevLimit = @ini_get('memory_limit');
        if(!$prevLimit) return false;
        $timeLimit = (int) ($prevLimit > $value ? $prevLimit : $value);
        //DUP_Logs::log('setting memory limit to :' . $timeLimit);
        return @ini_set('memory_limit', $timeLimit.'M');
    }

    static public function setMaxExecutionTime($value = 30)
    {
        $prevLimit = @ini_get('max_execution_time');
        if(!$prevLimit) return false;
        $timeLimit = (int) ($prevLimit > $value ? $prevLimit : $value);
        return @ini_set('max_execution_time', $timeLimit);
    }

    static public function isEnabled($func) {
        return is_callable($func) && false === stripos(ini_get('disable_functions'), $func);
    }

    static public function FcgiFlush() {
        @ob_start();
        echo(str_repeat(' ', 300));
        @session_write_close();
        @ob_end_flush();
        @flush();
    }

    static function timer($name = 'default', $unset_timer = TRUE)  {
        static $timers = array();

        if (isset($timers[$name]))  {
            list($s_sec, $s_mic) = explode(' ', $timers[$name]);
            list($e_sec, $e_mic) = explode(' ', microtime());

            if ($unset_timer)
                unset($timers[$name]);

            return $e_sec - $s_sec + ( $e_mic - $s_mic );
        }

        $timers[$name] = microtime();

        return $timers;
    }
}


class DUP_DataFilter extends RecursiveFilterIterator {

    protected $excluded;
    protected $excludedList = array();

    public function __construct(RecursiveIterator $iterator, $excluded) {
        $this->excluded = $excluded;
        parent::__construct($iterator);
    }

    public function accept() {
        return ($this->current()->isReadable() &&
                !in_array($this->current(), $this->excluded['exclude']) &&
                !in_array($this->getExtension(), $this->excluded['extension'])
        );
    }

    public function getChildren() {
        return new self($this->getInnerIterator()->getChildren(), $this->excluded);
    }
}