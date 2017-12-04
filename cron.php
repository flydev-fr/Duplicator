<?php
require_once(__DIR__ . '/../../../index.php');

if($wire->modules->isInstalled('Duplicator')) {
    $cron = $wire->modules->get('Duplicator');
    if($cron) {
        $e = new HookEvent();
        $cron->cronJob($e);
    }
}