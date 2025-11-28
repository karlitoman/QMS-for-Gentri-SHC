<?php
require __DIR__ . '/common.php';
require_method('POST');
$pdo = pdo_conn($config);

$id = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : 0;
$email = isset($_POST['email']) ? trim($_POST['email']) : null;

if ($id <= 0) { json_err(400, 'Invalid staff_id'); exit; }

// Check if staff exists
$stmt = $pdo->prepare('SELECT user_id FROM users WHERE user_id = :id AND role = "staff"');
$stmt->execute([':id' => $id]);
if (!$stmt->fetch()) {
  json_err(404, 'Staff not found');
  exit;
}

$fields = [];
$params = [':id' => $id];

if ($email !== null && $email !== '') {
  // Check if email already exists for another user
  $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email AND user_id != :id');
  $stmt->execute([':email' => $email, ':id' => $id]);
  if ($stmt->fetch()) {
    json_err(400, 'Email already exists');
    exit;
  }
  $fields[] = 'email = :em';
  $params[':em'] = $email;
}

if (!$fields) { json_err(400, 'No fields to update'); exit; }

$sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE user_id = :id AND role = "staff"';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

json_ok(null, ['message' => 'Staff updated successfully']);