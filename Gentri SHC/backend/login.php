<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/db_config.php';

function pdo(array $cfg): PDO {
  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['db'], $cfg['charset']);
  return new PDO($dsn, $cfg['user'], $cfg['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
  }

  $email = isset($_POST['username']) ? trim($_POST['username']) : '';
  $password = isset($_POST['password']) ? $_POST['password'] : '';
  $role     = isset($_POST['role']) ? trim($_POST['role']) : '';

  if ($email === '' || $password === '' || $role === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing email, password, or role']);
    exit;
  }

  // Validate role
  $validRoles = ['admin','staff','doctor'];
  if (!in_array($role, $validRoles, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid role']);
    exit;
  }

  session_start();
  $captcha = isset($_POST['captcha']) ? trim($_POST['captcha']) : '';
  $expected = isset($_SESSION['captcha_code']) ? $_SESSION['captcha_code'] : '';
  if ($expected !== '' && strcasecmp($captcha, $expected) !== 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid captcha']);
    exit;
  }
  $_SESSION['captcha_code'] = '';

  $pdo = pdo($config);
  // All users now authenticate using email
  $stmt = $pdo->prepare('SELECT user_id, password FROM users WHERE email = :e AND role = :r LIMIT 1');
  $stmt->execute([':e' => $email, ':r' => $role]);
  $row = $stmt->fetch();

  $ok = false;
  if ($row) {
    $stored = $row['password'] ?? '';
    $info = password_get_info($stored);
    if (($info['algo'] ?? 0) !== 0) {
      $ok = password_verify($password, $stored);
    } else {
      $ok = (strcasecmp($stored, md5($password)) === 0);
    }
  }

  if (!$ok) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid email or password']);
    exit;
  }

  $redirectMap = [
    'admin'  => 'admin_index.html',
    'staff'  => 'staff_index.html',
    'doctor' => 'doctor_index.html',
  ];

  echo json_encode(['ok' => true, 'redirect' => $redirectMap[$role]]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}