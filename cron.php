<?php
// command line only: over HTTP (e.g. on nginx, where ProcessWire's .htaccess does not apply)
// anyone could start backups
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once(__DIR__ . '/../../../index.php');
/**
 * @var Wire $wire
 */
if($wire->modules->isInstalled('Duplicator')) {
    $cron = $wire->modules->get('Duplicator');
    if($cron) {
        $cron->cronJob();
    }
}
