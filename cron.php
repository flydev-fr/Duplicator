<?php
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