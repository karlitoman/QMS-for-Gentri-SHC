<?php
require __DIR__ . '/common.php';
require_method('POST');
$pdo = pdo_conn($config);

// Get patient data
$philhealth_id = isset($_POST['philhealth_id']) ? trim($_POST['philhealth_id']) : null;
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
$middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : null;
$extension_name = isset($_POST['extension_name']) ? trim($_POST['extension_name']) : null;
$date_of_birth = isset($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : '';
$age = isset($_POST['age']) ? (int)$_POST['age'] : 0;
$sex = isset($_POST['sex']) ? trim($_POST['sex']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
$address = isset($_POST['address']) ? trim($_POST['address']) : null;
$emergency_contact = isset($_POST['emergency_contact']) ? trim($_POST['emergency_contact']) : null;
$emergency_phone = isset($_POST['emergency_phone']) ? trim($_POST['emergency_phone']) : null;

//# Get visit data
$department_id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
$visit_type = isset($_POST['visit_type']) ? trim($_POST['visit_type']) : '';
$client_type = isset($_POST['client_type']) ? trim($_POST['client_type']) : '';

// Get medical data
$chief_complaint = isset($_POST['chief_complaint']) ? trim($_POST['chief_complaint']) : null;
$symptoms_ros2 = isset($_POST['symptoms_ros2']) ? trim($_POST['symptoms_ros2']) : null;
$symptoms_ros3 = isset($_POST['symptoms_ros3']) ? trim($_POST['symptoms_ros3']) : null;
$symptoms_ros4 = isset($_POST['symptoms_ros4']) ? trim($_POST['symptoms_ros4']) : null;
$symptoms_ros5 = isset($_POST['symptoms_ros5']) ? trim($_POST['symptoms_ros5']) : null;
$symptoms_ros6 = isset($_POST['symptoms_ros6']) ? trim($_POST['symptoms_ros6']) : null;
$symptoms_ros8 = isset($_POST['symptoms_ros8']) ? trim($_POST['symptoms_ros8']) : null;

// Review of Systems responses
$ros2 = isset($_POST['ros2']) ? trim($_POST['ros2']) : null;
$ros3 = isset($_POST['ros3']) ? trim($_POST['ros3']) : null;
$ros4 = isset($_POST['ros4']) ? trim($_POST['ros4']) : null;
$ros5 = isset($_POST['ros5']) ? trim($_POST['ros5']) : null;
$ros6 = isset($_POST['ros6']) ? trim($_POST['ros6']) : null;
$ros8 = isset($_POST['ros8']) ? trim($_POST['ros8']) : null;

// Female-specific fields
$last_menstrual_period = isset($_POST['last_menstrual_period']) ? trim($_POST['last_menstrual_period']) : null;
$first_menstrual_period = isset($_POST['first_menstrual_period']) ? trim($_POST['first_menstrual_period']) : null;
$number_of_pregnancies = isset($_POST['number_of_pregnancies']) ? (int)$_POST['number_of_pregnancies'] : null;

// Personal/Social History
$smoking_status = isset($_POST['smoking_status']) ? trim($_POST['smoking_status']) : null;
$smoking_years = isset($_POST['smoking_years']) ? (int)$_POST['smoking_years'] : null;
$alcohol_status = isset($_POST['alcohol_status']) ? trim($_POST['alcohol_status']) : null;
$alcohol_years = isset($_POST['alcohol_years']) ? (int)$_POST['alcohol_years'] : null;

// Past Medical History
$past_medical_history = isset($_POST['past_medical_history']) ? trim($_POST['past_medical_history']) : null;
$pmh_others = isset($_POST['pmh_others']) ? trim($_POST['pmh_others']) : null;



// Validation
if (empty($first_name) || empty($last_name) || empty($date_of_birth) || $age <= 0 || empty($sex)) {
    json_err(400, 'Required fields: first_name, last_name, date_of_birth, age, sex');
    exit;
}

if ($department_id <= 0 || empty($visit_type) || empty($client_type)) {
    json_err(400, 'Required fields: department_id, visit_type, client_type');
    exit;
}

if (!in_array($sex, ['Male', 'Female'])) {
    json_err(400, 'Sex must be Male or Female');
    exit;
}

if (!in_array($visit_type, ['New', 'Old (Follow-up)'])) {
    json_err(400, 'Invalid visit_type');
    exit;
}

if (!in_array($client_type, ['PWD', 'Senior', 'Pregnant', 'Regular'])) {
    json_err(400, 'Invalid client_type');
    exit;
}

$philhealth_id = ($philhealth_id === '' ? null : $philhealth_id);
if ($philhealth_id !== null) {
    $stmt = $pdo->prepare('SELECT patient_id FROM patients WHERE philhealth_id = :pid LIMIT 1');
    $stmt->execute([':pid' => $philhealth_id]);
    if ($stmt->fetch()) { json_err(409, 'PhilHealth ID already exists'); exit; }
}

try {
    $pdo->beginTransaction();
    
    // Generate case number
    $stmt = $pdo->query("SELECT generate_case_number() as case_number");
    $case_number = $stmt->fetch()['case_number'];
    
    // Check if department exists
    $stmt = $pdo->prepare('SELECT department_id FROM departments WHERE department_id = :dept_id');
    $stmt->execute([':dept_id' => $department_id]);
    if (!$stmt->fetch()) {
        json_err(400, 'Invalid department_id');
        exit;
    }
    
    // Insert patient
    $stmt = $pdo->prepare('
        INSERT INTO patients (case_number, philhealth_id, first_name, last_name, middle_name, 
                             extension_name, date_of_birth, age, sex, phone, address, 
                             emergency_contact, emergency_phone)
        VALUES (:case_number, :philhealth_id, :first_name, :last_name, :middle_name, 
                :extension_name, :date_of_birth, :age, :sex, :phone, :address, 
                :emergency_contact, :emergency_phone)
    ');
    
    $stmt->execute([
        ':case_number' => $case_number,
        ':philhealth_id' => $philhealth_id ?: null,
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':middle_name' => $middle_name ?: null,
        ':extension_name' => $extension_name ?: null,
        ':date_of_birth' => $date_of_birth,
        ':age' => $age,
        ':sex' => $sex,
        ':phone' => $phone ?: null,
        ':address' => $address ?: null,
        ':emergency_contact' => $emergency_contact ?: null,
        ':emergency_phone' => $emergency_phone ?: null
    ]);
    
    $patient_id = $pdo->lastInsertId();
    
    // Determine priority based on client type
    $priority = 'Regular';
    if ($client_type === 'Senior' || $client_type === 'PWD' || $client_type === 'Pregnant') {
        $priority = 'Priority';
    }
    
    // Insert patient visit
    $stmt = $pdo->prepare('
        INSERT INTO patient_visits (patient_id, department_id, visit_type, client_type, 
                                   priority_level, visit_date)
        VALUES (:patient_id, :department_id, :visit_type, :client_type, :priority_level, CURDATE())
    ');
    
    $stmt->execute([
        ':patient_id' => $patient_id,
        ':department_id' => $department_id,
        ':visit_type' => $visit_type,
        ':client_type' => $client_type,
        ':priority_level' => $priority
    ]);
    
    $visit_id = $pdo->lastInsertId();
    
    // Insert queue entry
    $stmt = $pdo->prepare('
        INSERT INTO queue_entries (patient_id, visit_id, department_id, priority)
        VALUES (:patient_id, :visit_id, :department_id, :priority)
    ');
    
    $stmt->execute([
        ':patient_id' => $patient_id,
        ':visit_id' => $visit_id,
        ':department_id' => $department_id,
        ':priority' => $priority
    ]);
    
    $queue_id = $pdo->lastInsertId();
    
    // Get queue number
    $stmt = $pdo->prepare('SELECT queue_number FROM queue_entries WHERE queue_id = :queue_id');
    $stmt->execute([':queue_id' => $queue_id]);
    $queue_number = $stmt->fetch()['queue_number'];
    
    // Insert medical record if there's medical data
    if ($chief_complaint || $symptoms_ros2 || $symptoms_ros3 || $symptoms_ros4 || $symptoms_ros5 || 
        $symptoms_ros6 || $symptoms_ros8 || $ros2 || $ros3 || $ros4 || $ros5 || $ros6 || $ros8 ||
        $last_menstrual_period || $first_menstrual_period || $number_of_pregnancies ||
        $smoking_status || $smoking_years || $alcohol_status || $alcohol_years ||
        $past_medical_history || $pmh_others) {
        
        // For now, we'll use a default user_id (admin) for medical records created via patient form
        // In a real system, this would be the logged-in user
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE role = "admin" LIMIT 1');
        $stmt->execute();
        $admin_user = $stmt->fetch();
        
        if ($admin_user) {
            $stmt = $pdo->prepare('
                INSERT INTO medical_records (visit_id, chief_complaint, symptoms_ros2, symptoms_ros3, 
                                           symptoms_ros4, symptoms_ros5, symptoms_ros6, symptoms_ros8,
                                           ros2, ros3, ros4, ros5, ros6, ros8,
                                           last_menstrual_period, first_menstrual_period, number_of_pregnancies,
                                           smoking_status, smoking_years, alcohol_status, alcohol_years,
                                           past_medical_history, pmh_others, created_by)
                VALUES (:visit_id, :chief_complaint, :symptoms_ros2, :symptoms_ros3, :symptoms_ros4,
                        :symptoms_ros5, :symptoms_ros6, :symptoms_ros8, :ros2, :ros3, :ros4, :ros5,
                        :ros6, :ros8, :last_menstrual_period, :first_menstrual_period, 
                        :number_of_pregnancies, :smoking_status, :smoking_years, :alcohol_status,
                        :alcohol_years, :past_medical_history, :pmh_others, :created_by)
            ');
            
            $stmt->execute([
                ':visit_id' => $visit_id,
                ':chief_complaint' => $chief_complaint ?: null,
                ':symptoms_ros2' => $symptoms_ros2 ?: null,
                ':symptoms_ros3' => $symptoms_ros3 ?: null,
                ':symptoms_ros4' => $symptoms_ros4 ?: null,
                ':symptoms_ros5' => $symptoms_ros5 ?: null,
                ':symptoms_ros6' => $symptoms_ros6 ?: null,
                ':symptoms_ros8' => $symptoms_ros8 ?: null,
                ':ros2' => $ros2 ?: null,
                ':ros3' => $ros3 ?: null,
                ':ros4' => $ros4 ?: null,
                ':ros5' => $ros5 ?: null,
                ':ros6' => $ros6 ?: null,
                ':ros8' => $ros8 ?: null,
                ':last_menstrual_period' => $last_menstrual_period ?: null,
                ':first_menstrual_period' => $first_menstrual_period ?: null,
                ':number_of_pregnancies' => $number_of_pregnancies ?: null,
                ':smoking_status' => $smoking_status ?: null,
                ':smoking_years' => $smoking_years ?: null,
                ':alcohol_status' => $alcohol_status ?: null,
                ':alcohol_years' => $alcohol_years ?: null,
                ':past_medical_history' => $past_medical_history ?: null,
                ':pmh_others' => $pmh_others ?: null,
                ':created_by' => $admin_user['user_id']
            ]);
        }
    }
    
    $pdo->commit();
    
    json_ok([
        'patient_id' => $patient_id,
        'case_number' => $case_number,
        'visit_id' => $visit_id,
        'queue_number' => $queue_number,
        'priority' => $priority
    ], ['message' => 'Patient registered successfully']);
    
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e instanceof PDOException && $e->getCode() === '23000' && strpos($e->getMessage(), 'uniq_philhealth_id') !== false) {
        json_err(409, 'PhilHealth ID already exists');
    } else {
        json_err(500, 'Database error: ' . $e->getMessage());
    }
}
?>