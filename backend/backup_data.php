<?php
// c:\xampp\htdocs\Gentri SHC\backend\backup_data.php
session_start();
require __DIR__ . '/common.php';
require_method('POST');
$entry = ['date'=>date('Y-m-d'), 'action'=>'System Backup', 'status'=>'Success'];
json_ok($entry);