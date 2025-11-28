<?php
require __DIR__ . '/common.php';
require_method('POST');
$pdo = pdo_conn($config);

$id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
if ($id <= 0) { json_err(400, 'Invalid doctor_id'); exit; }

// Delete from users table where role is doctor and user_id matches
$stmt = $pdo->prepare('DELETE FROM users WHERE user_id = :id AND role = "doctor"');
$stmt->execute([':id' => $id]);

if ($stmt->rowCount() > 0) {
    json_ok(null, ['message' => 'Doctor deleted successfully']);
} else {
    json_err(404, 'Doctor not found or could not be deleted');
}