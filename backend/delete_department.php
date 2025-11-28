<?php
require __DIR__ . '/common.php';
require_method('POST');
$pdo = pdo_conn($config);

$id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
if ($id <= 0) { json_err(400, 'Invalid department_id'); exit; }

$stmt = $pdo->prepare('DELETE FROM departments WHERE department_id = :id');
$stmt->execute([':id' => $id]);

json_ok(null, ['message' => 'Department deleted']);