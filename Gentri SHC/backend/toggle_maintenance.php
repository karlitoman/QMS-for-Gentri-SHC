<?php
// c:\xampp\htdocs\Gentri SHC\backend\toggle_maintenance.php
session_start();
require __DIR__ . '/common.php';
require_method('POST');
$lock = __DIR__ . '/../storage/maintenance.lock';
$turnOn = isset($_POST['on']) && (int)$_POST['on'] === 1;
$hasAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
if ($turnOn && !$hasAdmin) { json_err(403, 'Admin session required'); exit; }
if (!is_dir(__DIR__ . '/../storage')) { mkdir(__DIR__ . '/../storage', 0777, true); }
if ($turnOn) { touch($lock); } else { if (is_file($lock)) unlink($lock); }
json_ok(['on' => $turnOn]);