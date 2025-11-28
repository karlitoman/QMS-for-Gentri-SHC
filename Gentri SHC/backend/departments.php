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
  $pdo = pdo($config);

  $method = $_SERVER['REQUEST_METHOD'];

  if ($method === 'GET') {
    $stmt = $pdo->query('SELECT department_id, department_name, department_description, created_at, updated_at FROM departments ORDER BY department_name');
    echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()]);
    exit;
  }

  if ($method === 'POST') {
    $name = isset($_POST['department_name']) ? trim($_POST['department_name']) : '';
    $desc = isset($_POST['department_description']) ? trim($_POST['department_description']) : null;

    if ($name === '') {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Missing department_name']);
      exit;
    }

    $stmt = $pdo->prepare(
      'INSERT INTO departments (department_name, department_description) VALUES (:n, :d)
       ON DUPLICATE KEY UPDATE department_description = VALUES(department_description), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([':n' => $name, ':d' => $desc]);

    echo json_encode(['ok' => true, 'message' => 'Department upserted']);
    exit;
  }

  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}