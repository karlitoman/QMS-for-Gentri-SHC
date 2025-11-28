<?php
session_start();
require_once __DIR__ . '/backend/common.php';

function alertAndBack($msg) {
  header('Location: login.php?error=' . urlencode($msg));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  alertAndBack('Invalid request method.');
}

$email = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
if ($email === '' || $password === '') {
  alertAndBack('Please enter both email and password.');
}

try {
  $pdo = pdo_conn($config);
  $stmt = $pdo->prepare('SELECT u.user_id, u.email, u.password, u.role, u.first_name, u.last_name, u.department_id, d.department_name FROM users u LEFT JOIN departments d ON u.department_id = d.department_id WHERE u.email = ? LIMIT 1');
  $stmt->execute([$email]);
  $user = $stmt->fetch();

  $lock = __DIR__ . '/storage/maintenance.lock';
  if (is_file($lock) && (!$user || strtolower($user['role']) !== 'admin')) {
    alertAndBack('System is under maintenance. Only admin can sign in.');
  }

  $ok = false;
  if ($user) {
    $stored = $user['password'] ?? '';
    $info = password_get_info($stored);
    if (($info['algo'] ?? 0) !== 0) {
      $ok = password_verify($password, $stored);
    } else {
      $ok = (strcasecmp($stored, md5($password)) === 0);
    }
  }

  if ($ok) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $_SESSION['department_id'] = isset($user['department_id']) ? (int)$user['department_id'] : 0;
    $_SESSION['department_name'] = $user['department_name'] ?? '';

    switch ($user['role']) {
      case 'admin':
        header('Location: admin_index.html');
        exit;
      case 'doctor':
        if (!headers_sent()) {
          setcookie('doctor_id', (string)$user['user_id'], 0, '/');
          setcookie('doctor_department_id', (string)($_SESSION['department_id'] ?? 0), 0, '/');
        }
        header('Location: doctor_index.html');
        exit;
      case 'staff':
        header('Location: staff_index.html');
        exit;
      default:
        alertAndBack('Unknown user role.');
    }
  } else {
    alertAndBack('Invalid email or password.');
  }
} catch (Throwable $e) {
  alertAndBack('Server error. Please try again.');
}
?>