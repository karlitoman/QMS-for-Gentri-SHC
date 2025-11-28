<?php
// c:\xampp\htdocs\Gentri SHC\backend\maintenance_status.php<?php
session_start();
require __DIR__ . '/common.php';
$lock = __DIR__ . '/../storage/maintenance.lock';
$on = is_file($lock);
json_ok(['maintenance' => $on]);