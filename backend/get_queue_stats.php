<?php
require __DIR__ . '/common.php';
require_method('GET');
$pdo = pdo_conn($config);

// Get filter parameters
$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$date = isset($_GET['date']) ? trim($_GET['date']) : '';

try {
    $base_where = "WHERE pv.visit_date = " . ($date !== '' ? '?' : 'CURDATE()');
    $params = $date !== '' ? [$date] : [];
    
    if ($department_id > 0) {
        $base_where .= " AND pv.department_id = ?";
        $params[] = $department_id;
    }
    
    // Get total counts by status
    $sql = "SELECT 
        q.status,
        COUNT(*) as count
    FROM queue_entries q
    JOIN patient_visits pv ON q.visit_id = pv.visit_id
    $base_where
    GROUP BY q.status";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get priority counts
    $sql = "SELECT 
        q.priority,
        COUNT(*) as count
    FROM queue_entries q
    JOIN patient_visits pv ON q.visit_id = pv.visit_id
    $base_where
    GROUP BY q.priority";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $priority_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get department breakdown (if not filtering by department)
    $department_counts = [];
    if ($department_id == 0) {
        $sql = "SELECT 
            d.department_name,
            COUNT(*) as count
        FROM queue_entries q
        JOIN patient_visits pv ON q.visit_id = pv.visit_id
        JOIN departments d ON pv.department_id = d.department_id
        WHERE pv.visit_date = " . ($date !== '' ? '?' : 'CURDATE()') . "
        GROUP BY d.department_id, d.department_name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($date !== '' ? [$date] : []);
        $department_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get average wait time
    $sql = "SELECT 
        AVG(TIMESTAMPDIFF(MINUTE, q.created_at, COALESCE(q.called_at, NOW()))) as avg_wait_time
    FROM queue_entries q
    JOIN patient_visits pv ON q.visit_id = pv.visit_id
    $base_where AND q.status IN ('Waiting', 'Called')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $avg_wait_time = $stmt->fetchColumn();
    
    // Get next queue number
    $sql = "SELECT 
        MIN(q.queue_number) as next_queue_number
    FROM queue_entries q
    JOIN patient_visits pv ON q.visit_id = pv.visit_id
    $base_where AND q.status = 'Waiting'
    ORDER BY q.priority DESC, q.queue_number ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $next_queue_number = $stmt->fetchColumn();
    
    $stats = [
        'date' => $date,
        'department_id' => $department_id,
        'status_counts' => $status_counts,
        'priority_counts' => $priority_counts,
        'department_counts' => $department_counts,
        'avg_wait_time' => round($avg_wait_time ?? 0, 1),
        'next_queue_number' => $next_queue_number
    ];
    
    json_ok($stats);
    
} catch (PDOException $e) {
    json_err(500, 'Database error: ' . $e->getMessage());
}
?>