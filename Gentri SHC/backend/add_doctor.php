<?php
require __DIR__ . '/common.php';
require_method('POST');
$pdo = pdo_conn($config);

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$lastName = isset($_POST['docLastName']) ? trim($_POST['docLastName']) : '';
$firstName = isset($_POST['docFirstName']) ? trim($_POST['docFirstName']) : '';
$middleName = isset($_POST['docMiddleName']) ? trim($_POST['docMiddleName']) : '';
$departmentId = isset($_POST['departmentId']) ? (int)$_POST['departmentId'] : 0;

if ($email === '') { json_err(400, 'Email is required'); exit; }
if ($password === '') { json_err(400, 'Password is required'); exit; }
if ($lastName === '') { json_err(400, 'Last name is required'); exit; }
if ($firstName === '') { json_err(400, 'First name is required'); exit; }

// Check if email already exists
$stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email');
$stmt->execute([':email' => $email]);
if ($stmt->fetch()) { json_err(400, 'Email already exists'); exit; }

// Generate a unique username bound to role=doctor
$base = strtolower(preg_replace('/[^a-z0-9]+/','', explode('@',$email)[0]));
if ($base === '') { $base = strtolower(preg_replace('/[^a-z0-9]+/','', $firstName.$lastName)); }
if ($base === '') { $base = 'doctor'; }
$username = $base;
$i = 1;
$chk = $pdo->prepare('SELECT 1 FROM users WHERE username = :u AND role = "doctor"');
while (true) {
  $chk->execute([':u' => $username]);
  if (!$chk->fetch()) break;
  $username = $base . $i;
  $i++;
}

try {
  $hash = password_hash($password, PASSWORD_DEFAULT);
  $stmt = $pdo->prepare('INSERT INTO users (username, email, password, role, first_name, last_name, middle_name, department_id, created_at) VALUES (:username, :email, :password, "doctor", :first_name, :last_name, :middle_name, :department_id, NOW())');
  $stmt->execute([
    ':username' => $username,
    ':email' => $email,
    ':password' => $hash,
    ':first_name' => $firstName,
    ':last_name' => $lastName,
    ':middle_name' => $middleName,
    ':department_id' => $departmentId > 0 ? $departmentId : null
  ]);
  $userId = (int)$pdo->lastInsertId();
  json_ok(['user_id' => $userId, 'username' => $username], ['message' => 'Doctor created successfully']);
} catch (Throwable $e) { json_err(500, $e->getMessage()); }