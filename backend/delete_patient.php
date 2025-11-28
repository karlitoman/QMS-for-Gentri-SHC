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
$stmt = $pdo->prepare('SELECT patient_id, first_name, last_name FROM patients WHERE patient_id = :id');
$stmt->execute([':id' => $patient_id]);
$patient = $stmt->fetch();

if (!$patient) {
    json_err(404, 'Patient not found');
    exit;
}

try {
    // Delete patient (CASCADE will handle related records)
    $stmt = $pdo->prepare('DELETE FROM patients WHERE patient_id = :id');
    $stmt->execute([':id' => $patient_id]);
    
    if ($stmt->rowCount() > 0) {
        json_ok(null, [
            'message' => 'Patient deleted successfully',
            'deleted_patient' => [
                'patient_id' => $patient_id,
                'name' => $patient['first_name'] . ' ' . $patient['last_name']
            ]
        ]);
    } else {
        json_err(500, 'Failed to delete patient');
    }
    
} catch (Exception $e) {
    json_err(500, 'Database error: ' . $e->getMessage());
}
?>