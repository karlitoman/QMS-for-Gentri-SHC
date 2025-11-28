<?php
require __DIR__ . '/common.php';
require_method('GET');
$pdo = pdo_conn($config);
$stmt = $pdo->query('SELECT department_id, department_name, department_description, created_at, updated_at FROM departments ORDER BY department_name');
json_ok($stmt->fetchAll());