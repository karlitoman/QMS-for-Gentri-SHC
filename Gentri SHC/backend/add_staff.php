<?php
require __DIR__ . '/common.php';
require_method('POST');
$pdo = pdo_conn($config);

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$lastName = isset($_POST['lastName']) ? trim($_POST['lastName']) : '';
$firstName = isset($_POST['firstName']) ? trim($_POST['firstName']) : '';
$middleName = isset($_POST['middleName']) ? trim($_POST['middleName']) : '';

if ($email === '') { json_err(400, 'Email is required'); exit; }
if ($password === '') { json_err(400, 'Password is required'); exit; }
if ($lastName === '') { json_err(400, 'Last name is required'); exit; }
if ($firstName === '') { json_err(400, 'First name is required'); exit; }

// Check if email already exists
$stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email');
$stmt->execute([':email' => $email]);
if ($stmt->fetch()) {
  json_err(400, 'Email already exists');
  exit;
}

try {
  $hash = password_hash($password, PASSWORD_DEFAULT);
  $stmt = $pdo->prepare('INSERT INTO users (email, password, role, first_name, last_name, middle_name, created_at) VALUES (:email, :password, "staff", :first_name, :last_name, :middle_name, NOW())');
  $stmt->execute([
    ':email' => $email, 
    ':password' => $hash,
    ':first_name' => $firstName,
    ':last_name' => $lastName,
    ':middle_name' => $middleName
  ]);
  $userId = (int)$pdo->lastInsertId();
  
  json_ok(['user_id' => $userId], ['message' => 'Staff created successfully']);
} catch (Throwable $e) {
  json_err(500, $e->getMessage());
}