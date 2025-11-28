<?php
require __DIR__ . '/common.php';
require_method('POST');
$pdo = pdo_conn($config);

$name = isset($_POST['department_name']) ? trim($_POST['department_name']) : '';
$desc = isset($_POST['department_description']) ? trim($_POST['department_description']) : null;

if ($name === '') {
  json_err(400, 'Missing department_name');
  exit;
}

$stmt = $pdo->prepare('INSERT INTO departments (department_name, department_description) VALUES (:n, :d)');
$stmt->execute([':n' => $name, ':d' => $desc]);

json_ok(['department_id' => $pdo->lastInsertId()], ['message' => 'Department created']);