<?php
session_start();
require __DIR__ . '/common.php';
require_method('POST');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { json_err(403, 'Admin session required'); exit; }

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) { json_err(400, 'No file uploaded'); exit; }
$tmp = $_FILES['file']['tmp_name'];
$raw = file_get_contents($tmp);
if ($raw === false) { json_err(400, 'Failed to read upload'); exit; }
if (substr($_FILES['file']['name'], -3) === '.gz') {
  if (function_exists('gzdecode')) { $raw = gzdecode($raw); } else { json_err(500, 'gzdecode not available'); exit; }
}
$data = json_decode($raw, true);
if (!$data || !isset($data['tables']) || !is_array($data['tables'])) { json_err(400, 'Invalid backup format'); exit; }

$cfg = $config;
$pdo = pdo_conn($cfg);
try {
  $pdo->beginTransaction();
  foreach ($data['tables'] as $table => $rows) {
    if (!is_array($rows) || (isset($rows['_error']))) continue;
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $pdo->exec("TRUNCATE TABLE `{$table}`");
    if (!$rows) continue;
    $cols = array_keys($rows[0]);
    $colList = '`' . implode('`,`', $cols) . '`';
    $placeholders = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
    $stmt = $pdo->prepare("INSERT INTO `{$table}` ({$colList}) VALUES {$placeholders}");
    foreach ($rows as $r) {
      $vals = [];
      foreach ($cols as $c) { $vals[] = $r[$c] ?? null; }
      $stmt->execute($vals);
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
  }
  $pdo->commit();
  json_ok(null, ['message' => 'Restore completed']);
} catch (Throwable $e) {
  $pdo->rollBack();
  json_err(500, 'Restore error: ' . $e->getMessage());
}