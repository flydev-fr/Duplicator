<?php
require_once(__DIR__ . '/../../../index.php');

if($wire->modules->isInstalled('Duplicator')) {
    $cron = $wire->modules->get('Duplicator');
    if($cron) {
        //$e = class_exists('\ProcessWire\HookEvent') ? new \ProcessWire\HookEvent() : new HookEvent();
        $cron->cronJob(/*$e*/);
    }
}