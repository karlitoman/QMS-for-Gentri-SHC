<?php
require __DIR__ . '/common.php';
require_method('GET');
$pdo = pdo_conn($config);

// Get filter parameters
$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$date = isset($_GET['date']) ? trim($_GET['date']) : '';

// Build query
$sql = "SELECT 
    q.queue_id,
    q.queue_number,
    q.priority,
    q.status,
    q.estimated_wait_time,
    q.called_at,
    q.completed_at,
    q.created_at,
    p.patient_id,
    p.case_number,
    p.first_name,
    p.last_name,
    p.middle_name,
    p.age,
    p.sex,
    pv.visit_id,
    pv.visit_type,
    pv.client_type,
    d.department_name,
    CONCAT(u.first_name, ' ', u.last_name) as assigned_doctor
FROM queue_entries q
JOIN patient_visits pv ON q.visit_id = pv.visit_id
JOIN patients p ON pv.patient_id = p.patient_id
JOIN departments d ON pv.department_id = d.department_id
LEFT JOIN users u ON pv.assigned_doctor_id = u.user_id
WHERE pv.visit_date = " . ($date !== '' ? '?' : 'CURDATE()');

$params = $date !== '' ? [$date] : [];

if ($department_id > 0) {
    $sql .= " AND pv.department_id = ?";
    $params[] = $department_id;
}

if (!empty($status)) {
    $sql .= " AND q.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY (CASE WHEN pv.priority_level IN ('Emergency','Priority') THEN 1 ELSE 2 END) ASC, q.created_at ASC, q.queue_number ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    json_ok($queue);
} catch (PDOException $e) {
    json_err(500, 'Database error: ' . $e->getMessage());
}
?>