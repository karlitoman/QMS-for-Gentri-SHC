<?php
require __DIR__ . '/common.php';
require_method('POST');
$pdo = pdo_conn($config);

$patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;

if ($patient_id <= 0) {
    json_err(400, 'Invalid patient_id');
    exit;
}

// Check if patient exists
$stmt = $pdo->prepare('SELECT patient_id FROM patients WHERE patient_id = :id');
$stmt->execute([':id' => $patient_id]);
if (!$stmt->fetch()) {
    json_err(404, 'Patient not found');
    exit;
}

$fields = [];
$params = [':id' => $patient_id];

// Update patient fields
$updateable_fields = [
    'philhealth_id', 'first_name', 'last_name', 'middle_name', 'extension_name',
    'date_of_birth', 'age', 'sex', 'phone', 'address', 'emergency_contact', 'emergency_phone'
];

foreach ($updateable_fields as $field) {
    if (isset($_POST[$field])) {
        $value = trim($_POST[$field]);
        if ($value !== '') {
            $fields[] = "$field = :$field";
            $params[":$field"] = $value;
        } else {
            // Allow setting to NULL for optional fields
            if (in_array($field, ['philhealth_id', 'middle_name', 'extension_name', 'phone', 'address', 'emergency_contact', 'emergency_phone'])) {
                $fields[] = "$field = NULL";
            }
        }
    }
}

if (!$fields) {
    json_err(400, 'No fields to update');
    exit;
}

try {
    $sql = 'UPDATE patients SET ' . implode(', ', $fields) . ', updated_at = CURRENT_TIMESTAMP WHERE patient_id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    json_ok(null, ['message' => 'Patient updated successfully']);
    
} catch (Exception $e) {
    json_err(500, 'Database error: ' . $e->getMessage());
}
?>