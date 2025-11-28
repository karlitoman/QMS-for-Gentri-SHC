<?php
session_start();
require __DIR__ . '/common.php';
require_method('POST');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { json_err(403, 'Admin session required'); exit; }

$cfg = $config;
$pdo = pdo_conn($cfg);
$mode = isset($_POST['mode']) && $_POST['mode'] === 'full' ? 'full' : 'incremental';
$dir = __DIR__ . '/../storage/backups'; if (!is_dir($dir)) @mkdir($dir, 0777, true);
$manifestFile = $dir . '/manifest.json';
$manifest = ['last_backup_at'=>null,'last_full_at'=>null,'backups'=>[]];
if (is_file($manifestFile)) { $m = json_decode(file_get_contents($manifestFile), true); if (is_array($m)) $manifest = array_merge($manifest, $m); }
$last = $manifest['last_backup_at']; $lastFull = $manifest['last_full_at'];
$ts = date('Ymd_His');
$fname = "backup_{$mode}_{$ts}.json.gz"; $path = $dir . '/' . $fname;

// discover tables
$tables = [];
$stmt = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = :db");
$stmt->execute([':db'=>$cfg['db']]);
$tables = array_map(fn($r)=>$r['TABLE_NAME'], $stmt->fetchAll());

$data = ['database'=>$cfg['db'],'generated_at'=>date('c'),'mode'=>$mode,'base'=>$lastFull,'tables'=>[]];
foreach ($tables as $t) {
  $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema='{$cfg['db']}' AND table_name='{$t}'")->fetchAll(PDO::FETCH_COLUMN);
  $hasUpd = in_array('updated_at', $cols); $hasCre = in_array('created_at', $cols);
  $where = '';
  if ($mode === 'incremental' && $last) {
    if ($hasUpd) $where = " WHERE updated_at >= '{$last}'";
    else if ($hasCre) $where = " WHERE created_at >= '{$last}'";
  }
  try {
    $rows = $pdo->query("SELECT * FROM `{$t}`{$where}")->fetchAll();
    // if incremental yields empty and table unchanged, skip
    if ($mode==='incremental' && !$rows) continue;
    $data['tables'][$t] = $rows;
  } catch (Throwable $e) { $data['tables'][$t] = ['_error'=>$e->getMessage()]; }
}

$gz = gzencode(json_encode($data)); file_put_contents($path, $gz);
$manifest['last_backup_at'] = date('Y-m-d');
if ($mode==='full' || !$lastFull) $manifest['last_full_at'] = $manifest['last_backup_at'];
$manifest['backups'][] = ['file'=>$fname,'size'=>filesize($path),'created_at'=>date('c'),'mode'=>$mode];
file_put_contents($manifestFile, json_encode($manifest));
json_ok(['file'=>$fname], ['message'=>'Backup written']);