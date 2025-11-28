<?php
require __DIR__ . '/common.php';
require_method('POST');
$pdo = pdo_conn($config);

// Get parameters
$queue_id = isset($_POST['queue_id']) ? (int)$_POST['queue_id'] : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$assigned_doctor_id = isset($_POST['assigned_doctor_id']) ? (int)$_POST['assigned_doctor_id'] : null;
$priority = isset($_POST['priority']) ? trim($_POST['priority']) : null;

// Validation
if ($queue_id <= 0) {
    json_err(400, 'queue_id is required');
    exit;
}

if (empty($action)) {
    json_err(400, 'action is required');
    exit;
}

$valid_actions = ['call', 'complete', 'cancel', 'update_priority', 'assign_doctor'];
if (!in_array($action, $valid_actions)) {
    json_err(400, 'Invalid action. Valid actions: ' . implode(', ', $valid_actions));
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get current queue entry
    $stmt = $pdo->prepare("SELECT * FROM queue_entries WHERE queue_id = ?");
    $stmt->execute([$queue_id]);
    $queue_entry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$queue_entry) {
        json_err(404, 'Queue entry not found');
        exit;
    }
    
    switch ($action) {
        case 'call':
            $stmt = $pdo->prepare("UPDATE queue_entries SET status = 'Called', called_at = NOW() WHERE queue_id = ?");
            $stmt->execute([$queue_id]);
            break;
            
        case 'complete':
            $stmt = $pdo->prepare("UPDATE queue_entries SET status = 'Completed', completed_at = NOW() WHERE queue_id = ?");
            $stmt->execute([$queue_id]);
            break;
            
        case 'cancel':
            $stmt = $pdo->prepare("UPDATE queue_entries SET status = 'No Show' WHERE queue_id = ?");
            $stmt->execute([$queue_id]);
            break;
            
        case 'update_priority':
            if (!in_array($priority, ['Emergency', 'Urgent', 'Regular'])) {
                json_err(400, 'Invalid priority. Valid priorities: Emergency, Urgent, Regular');
                exit;
            }
            $stmt = $pdo->prepare("UPDATE queue_entries SET priority = ? WHERE queue_id = ?");
            $stmt->execute([$priority, $queue_id]);
            break;
            
        case 'assign_doctor':
            if ($assigned_doctor_id <= 0) {
                json_err(400, 'assigned_doctor_id is required for assign_doctor action');
                exit;
            }
            
            // Verify doctor exists and is a doctor
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'doctor'");
            $stmt->execute([$assigned_doctor_id]);
            if (!$stmt->fetch()) {
                json_err(400, 'Invalid doctor ID');
                exit;
            }
            
            // Update the patient visit with assigned doctor
            $stmt = $pdo->prepare("UPDATE patient_visits SET assigned_doctor_id = ? WHERE visit_id = ?");
            $stmt->execute([$assigned_doctor_id, $queue_entry['visit_id']]);
            break;
    }
    
    $pdo->commit();
    json_ok(null, ['message' => 'Queue updated successfully']);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    json_err(500, 'Database error: ' . $e->getMessage());
}
?>