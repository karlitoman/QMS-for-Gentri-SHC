<?php
header('Content-Type: application/json');
session_start();
$dir = __DIR__ . '/../storage/backups';
$manifestFile = $dir . '/manifest.json';
if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
$manifest = ['last_backup_at' => null, 'backups' => []];
if (is_file($manifestFile)) {
  $json = json_decode(file_get_contents($manifestFile), true);
  if (is_array($json)) $manifest = array_merge($manifest, $json);
}
$last = $manifest['last_backup_at'];
$next = $last ? date('Y-m-d', strtotime($last . ' +14 days')) : date('Y-m-d', strtotime('+14 days'));
echo json_encode([
  'ok' => true,
  'last_backup_at' => $last,
  'next_due' => $next,
  'backups' => $manifest['backups'],
]);