<?php
require __DIR__ . '/common.php';
require_method('GET');
$pdo = pdo_conn($config);

// Get optional filters
$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
$status = isset($_GET['status']) ? trim($_GET['status']) : null;
$date = isset($_GET['date']) ? trim($_GET['date']) : null;

// Build query
$where_conditions = [];
$params = [];

if ($department_id) {
    $where_conditions[] = 'pv.department_id = :department_id';
    $params[':department_id'] = $department_id;
}

if ($status) {
    $where_conditions[] = 'pv.status = :status';
    $params[':status'] = $status;
}

if ($date) {
    $where_conditions[] = 'pv.visit_date = :date';
    $params[':date'] = $date;
} else {
    // Default to today's visits
    $where_conditions[] = 'pv.visit_date = CURDATE()';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$sql = "
    SELECT 
        p.patient_id,
        p.case_number,
        p.first_name,
        p.last_name,
        p.middle_name,
        p.extension_name,
        p.age,
        p.sex,
        p.phone,
        pv.visit_id,
        pv.visit_type,
        pv.client_type,
        pv.priority_level,
        pv.status as visit_status,
        pv.visit_date,
        d.department_name,
        CONCAT(doc.first_name, ' ', doc.last_name) as assigned_doctor,
        q.queue_number,
        q.status as queue_status,
        q.estimated_wait_time,
        pv.created_at
    FROM patients p
    JOIN patient_visits pv ON p.patient_id = pv.patient_id
    JOIN departments d ON pv.department_id = d.department_id
    LEFT JOIN users doc ON pv.assigned_doctor_id = doc.user_id
    LEFT JOIN queue_entries q ON pv.visit_id = q.visit_id
    $where_clause
    ORDER BY q.priority DESC, q.queue_number ASC, pv.created_at DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $patients = $stmt->fetchAll();
    
    json_ok($patients);
    
} catch (Exception $e) {
    json_err(500, 'Database error: ' . $e->getMessage());
}
?>