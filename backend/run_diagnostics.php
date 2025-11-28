<?php
// c:\xampp\htdocs\Gentri SHC\backend\run_diagnostics.php
session_start();
require __DIR__ . '/common.php';
require_method('POST');
$log = __DIR__ . '/../storage/maintenance_log.json';
$entry = ['date'=>date('Y-m-d'), 'action'=>'Diagnostics', 'status'=>'Success'];
$data = [];
if (is_file($log)) { $data = json_decode(file_get_contents($log), true) ?: []; }
$data[] = $entry;
if (!is_dir(__DIR__ . '/../storage')) mkdir(__DIR__ . '/../storage', 0777, true);
file_put_contents($log, json_encode($data));
json_ok($entry);