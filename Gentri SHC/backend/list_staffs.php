<?php
require __DIR__ . '/common.php';
require_method('GET');
$pdo = pdo_conn($config);
$sql = 'SELECT user_id, email, role, created_at,
               COALESCE(last_name, "") as last_name, 
               COALESCE(first_name, "") as first_name, 
               COALESCE(middle_name, "") as middle_name
        FROM users
        WHERE role = "staff"
        ORDER BY last_name, first_name, email';
$stmt = $pdo->query($sql);
json_ok($stmt->fetchAll());