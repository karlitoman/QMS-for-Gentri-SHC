<?php
require __DIR__ . '/common.php';
require_method('POST');
$pdo = pdo_conn($config);

$id = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : 0;
if ($id <= 0) { json_err(400, 'Invalid staff_id'); exit; }

// Delete from users table where role is staff and user_id matches
$stmt = $pdo->prepare('DELETE FROM users WHERE user_id = :id AND role = \'staff\'');
$stmt->execute([':id' => $id]);

if ($stmt->rowCount() > 0) {
    json_ok(null, ['message' => 'Staff deleted']);
} else {
    json_err(404, 'Staff not found or could not be deleted');
}