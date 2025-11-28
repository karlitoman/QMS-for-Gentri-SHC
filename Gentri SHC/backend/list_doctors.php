<?php
require __DIR__ . '/common.php';
require_method('GET');
$pdo = pdo_conn($config);
$sql = 'SELECT u.user_id, u.email, u.role, u.created_at,
               COALESCE(u.last_name, "") as last_name, 
               COALESCE(u.first_name, "") as first_name, 
               COALESCE(u.middle_name, "") as middle_name,
               u.department_id, 
               COALESCE(dept.department_name, "") as department_name
        FROM users u
        LEFT JOIN departments dept ON u.department_id = dept.department_id
        WHERE u.role = "doctor"
        ORDER BY u.last_name, u.first_name, u.email';
$stmt = $pdo->query($sql);
json_ok($stmt->fetchAll());