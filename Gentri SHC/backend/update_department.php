<?php
require __DIR__ . '/common.php';
require_method('POST');
$pdo = pdo_conn($config);

$id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
$name = isset($_POST['department_name']) ? trim($_POST['department_name']) : null;
$desc = isset($_POST['department_description']) ? trim($_POST['department_description']) : null;

if ($id <= 0) { json_err(400, 'Invalid department_id'); exit; }

$fields = [];
$params = [':id' => $id];
if ($name !== null && $name !== '') { $fields[] = 'department_name = :n'; $params[':n'] = $name; }
if ($desc !== null) { $fields[] = 'department_description = :d'; $params[':d'] = $desc; }
if (!$fields) { json_err(400, 'No fields to update'); exit; }

$sql = 'UPDATE departments SET ' . implode(', ', $fields) . ', updated_at = CURRENT_TIMESTAMP WHERE department_id = :id';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

json_ok(null, ['message' => 'Department updated']);