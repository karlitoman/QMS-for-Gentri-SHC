<?php
$config = require __DIR__ . '/db_config.php';

function pdo_conn(array $cfg): PDO {
  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['db'], $cfg['charset']);
  return new PDO($dsn, $cfg['user'], $cfg['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
}

function json_ok($data = null, array $extra = []) {
  header('Content-Type: application/json');
  echo json_encode(array_merge(['ok' => true, 'data' => $data], $extra));
}

function json_err(int $code, string $msg) {
  header('Content-Type: application/json');
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg]);
}

function require_method(string $method) {
  if ($_SERVER['REQUEST_METHOD'] !== $method) {
    json_err(405, 'Method Not Allowed');
    exit;
  }
}