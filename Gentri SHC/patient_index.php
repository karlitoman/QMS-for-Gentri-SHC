<?php
session_start();
require __DIR__ . '/backend/common.php';

$pdo = pdo_conn($config);
$errors = [];
$result = null;

// Post/Redirect/Get: load ticket from session on GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['patient_ticket']) && is_array($_SESSION['patient_ticket'])) {
  $result = $_SESSION['patient_ticket'];
}

$departments = [];
try {
  $stmt = $pdo->query('SELECT department_id, department_name FROM departments ORDER BY department_name');
  $departments = $stmt->fetchAll();
} catch (Throwable $e) {
  $errors[] = 'Unable to load departments.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $philhealth_id     = trim($_POST['philhealth_id'] ?? '');
  $first_name        = trim($_POST['first_name'] ?? '');
  $last_name         = trim($_POST['last_name'] ?? '');
  $middle_name       = trim($_POST['middle_name'] ?? '');
  $extension_name    = trim($_POST['extension_name'] ?? '');
  $date_of_birth     = trim($_POST['date_of_birth'] ?? '');
  $age               = (int)($_POST['age'] ?? 0);
  $sex               = trim($_POST['sex'] ?? '');
  $phone             = trim($_POST['phone'] ?? '');
  $address           = trim($_POST['address'] ?? '');
  $emergency_contact = trim($_POST['emergency_contact'] ?? '');
  $emergency_phone   = trim($_POST['emergency_phone'] ?? '');

  $client_type       = trim($_POST['client_type'] ?? '');
  $visit_type        = trim($_POST['visit_type'] ?? '');
  $department_id     = (int)($_POST['department_id'] ?? 0);

  $chief_complaint   = trim($_POST['chief_complaint'] ?? '');
  $ros2              = trim($_POST['ros2'] ?? '');
  $ros3              = trim($_POST['ros3'] ?? '');
  $ros4              = trim($_POST['ros4'] ?? '');
  $ros5              = trim($_POST['ros5'] ?? '');
  $ros6              = trim($_POST['ros6'] ?? '');
  $ros8              = trim($_POST['ros8'] ?? '');
  $symptoms_ros2     = trim($_POST['symptoms_ros2'] ?? '');
  $symptoms_ros3     = trim($_POST['symptoms_ros3'] ?? '');
  $symptoms_ros4     = trim($_POST['symptoms_ros4'] ?? '');
  $symptoms_ros5     = trim($_POST['symptoms_ros5'] ?? '');
  $symptoms_ros6     = trim($_POST['symptoms_ros6'] ?? '');
  $symptoms_ros8     = trim($_POST['symptoms_ros8'] ?? '');

  $last_menstrual_period = trim($_POST['last_menstrual_period'] ?? '');
  $first_menstrual_period = trim($_POST['first_menstrual_period'] ?? '');
  $number_of_pregnancies = isset($_POST['number_of_pregnancies']) ? (int)$_POST['number_of_pregnancies'] : null;

  $smoking_status = trim($_POST['smoking_status'] ?? '');
  $smoking_years  = isset($_POST['smoking_years']) ? (int)$_POST['smoking_years'] : null;
  $alcohol_status = trim($_POST['alcohol_status'] ?? '');
  $alcohol_years  = isset($_POST['alcohol_years']) ? (int)$_POST['alcohol_years'] : null;

  $pmh_values = isset($_POST['pmh']) && is_array($_POST['pmh']) ? $_POST['pmh'] : [];
  $past_medical_history = count($pmh_values) ? implode(', ', array_map('trim', $pmh_values)) : '';
  $pmh_others = trim($_POST['pmh_others'] ?? '');

  if ($first_name === '' || $last_name === '' || $date_of_birth === '' || $age <= 0 || $sex === '') {
    $errors[] = 'Required: First Name, Last Name, Date of Birth, Age, Sex.';
  }
  if ($department_id <= 0 || $visit_type === '' || $client_type === '') {
    $errors[] = 'Required: Department, Visit Type, Client Type.';
  }
  if ($sex && !in_array($sex, ['Male','Female'], true)) {
    $errors[] = 'Sex must be Male or Female.';
  }
  if ($visit_type && !in_array($visit_type, ['New','Old (Follow-up)'], true)) {
    $errors[] = 'Invalid Visit Type.';
  }
  if ($client_type && !in_array($client_type, ['PWD','Senior','Pregnant','Regular'], true)) {
    $errors[] = 'Invalid Client Type.';
  }
  if (count($pmh_values) === 0 && $pmh_others === '') {
    $errors[] = 'Past medical history is required.';
  }

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      $hasDept = $pdo->prepare('SELECT department_id FROM departments WHERE department_id = :id');
      $hasDept->execute([':id' => $department_id]);
      if (!$hasDept->fetch()) {
        throw new RuntimeException('Invalid department selection.');
      }

      $skipPatientInsert = false;
      $philhealth_id = ($philhealth_id === '' ? null : $philhealth_id);
      if ($philhealth_id !== null) {
        $existing = $pdo->prepare('SELECT patient_id, case_number FROM patients WHERE philhealth_id = :pid LIMIT 1');
        $existing->execute([':pid' => $philhealth_id]);
        $row = $existing->fetch();
        if ($row) {
          $patient_id = (int)$row['patient_id'];
          $case_number = (string)$row['case_number'];
          $skipPatientInsert = true;
        }
      }
      if (!$skipPatientInsert) {
        $caseNoStmt = $pdo->query("SELECT generate_case_number() AS case_number");
        $caseRow = $caseNoStmt->fetch();
        $case_number = $caseRow && $caseRow['case_number'] ? $caseRow['case_number'] : null;
        if (!$case_number) {
          $maxStmt = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(case_number, 4) AS UNSIGNED)), 0) + 1 AS next_num FROM patients WHERE case_number LIKE 'PT-%'");
          $next = (int)($maxStmt->fetch()['next_num'] ?? 1);
          $case_number = sprintf('PT-%04d', $next);
        }
      }

      if (!$skipPatientInsert) {
        $insPatient = $pdo->prepare('
          INSERT INTO patients (case_number, philhealth_id, first_name, last_name, middle_name,
                                extension_name, date_of_birth, age, sex, phone, address,
                                emergency_contact, emergency_phone)
          VALUES (:case_number, :philhealth_id, :first_name, :last_name, :middle_name,
                  :extension_name, :date_of_birth, :age, :sex, :phone, :address,
                  :emergency_contact, :emergency_phone)
        ');
        $insPatient->execute([
          ':case_number' => $case_number,
          ':philhealth_id' => $philhealth_id,
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
          ':emergency_phone' => $emergency_phone ?: null,
        ]);
        $patient_id = (int)$pdo->lastInsertId();
      }

      $priority = in_array($client_type, ['Senior','PWD','Pregnant'], true) ? 'Priority' : 'Regular';

      $insVisit = $pdo->prepare('
        INSERT INTO patient_visits (patient_id, department_id, visit_type, client_type, priority_level, visit_date)
        VALUES (:patient_id, :department_id, :visit_type, :client_type, :priority_level, CURDATE())
      ');
      $insVisit->execute([
        ':patient_id' => $patient_id,
        ':department_id' => $department_id,
        ':visit_type' => $visit_type,
        ':client_type' => $client_type,
        ':priority_level' => $priority,
      ]);
      $visit_id = (int)$pdo->lastInsertId();

      $insQueue = $pdo->prepare('
        INSERT INTO queue_entries (patient_id, visit_id, department_id, priority)
        VALUES (:patient_id, :visit_id, :department_id, :priority)
      ');
      $insQueue->execute([
        ':patient_id' => $patient_id,
        ':visit_id' => $visit_id,
        ':department_id' => $department_id,
        ':priority' => $priority,
      ]);
      $queue_id = (int)$pdo->lastInsertId();

      $qNumStmt = $pdo->prepare('SELECT queue_number FROM queue_entries WHERE queue_id = :id');
      $qNumStmt->execute([':id' => $queue_id]);
      $queue_number = (int)($qNumStmt->fetch()['queue_number'] ?? 0);

      if ($chief_complaint || $symptoms_ros2 || $symptoms_ros3 || $symptoms_ros4 || $symptoms_ros5 || $symptoms_ros6 || $symptoms_ros8 || $ros2 || $ros3 || $ros4 || $ros5 || $ros6 || $ros8 || $last_menstrual_period || $first_menstrual_period || $number_of_pregnancies || $smoking_status || $smoking_years || $alcohol_status || $alcohol_years || $past_medical_history || $pmh_others) {
        $adminStmt = $pdo->query('SELECT user_id FROM users WHERE role = "admin" LIMIT 1');
        $adminUser = $adminStmt->fetch();
        $created_by = $adminUser ? (int)$adminUser['user_id'] : null;

        $insMed = $pdo->prepare('
          INSERT INTO medical_records
          (visit_id, chief_complaint, symptoms_ros2, symptoms_ros3, symptoms_ros4, symptoms_ros5, symptoms_ros6, symptoms_ros8,
           ros2, ros3, ros4, ros5, ros6, ros8,
           last_menstrual_period, first_menstrual_period, number_of_pregnancies,
           smoking_status, smoking_years, alcohol_status, alcohol_years,
           past_medical_history, pmh_others, created_by)
          VALUES
          (:visit_id, :chief_complaint, :symptoms_ros2, :symptoms_ros3, :symptoms_ros4, :symptoms_ros5, :symptoms_ros6, :symptoms_ros8,
           :ros2, :ros3, :ros4, :ros5, :ros6, :ros8,
           :last_menstrual_period, :first_menstrual_period, :number_of_pregnancies,
           :smoking_status, :smoking_years, :alcohol_status, :alcohol_years,
           :past_medical_history, :pmh_others, :created_by)
        ');
        $insMed->execute([
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
          ':created_by' => $created_by,
        ]);
      }

      $pdo->commit();
      $_SESSION['patient_ticket'] = [
        'case_number' => $case_number,
        'queue_number' => $queue_number,
        'priority' => $priority,
        'department_id' => $department_id,
        'visit_id' => $visit_id,
      ];
      $_SESSION['queue_broadcast_once'] = true;
      header('Location: patient_index.php');
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = 'Database error: ' . $e->getMessage();
    }
  }
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$showTicket = (bool)$result;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>General Trias Super Health Center - Patient</title>
  <link rel="stylesheet" href="assets/css/admin_style.css">
  <link rel="stylesheet" href="assets/css/login_style.css">
  <style>
    body { display: flex; flex-direction: column; min-height: 100vh; }
    main.content { flex: 1; background: none !important; background-image: none !important; }
    header { border-bottom: none !important; box-shadow: none !important; }
    .patient-modal { max-width: 960px; border-radius: 12px; box-shadow: 0 12px 24px rgba(0,0,0,0.15); }
    .patient-modal .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #e6e9f0; }
    .patient-modal .modal-header h3 { margin: 0; font-size: 20px; color: #1f2937; }
    .patient-modal .modal-close { background: transparent; border: 1px solid var(--border); font-size: 20px; cursor: pointer; color: var(--primary-600); border-radius: 6px; padding: 4px 10px; }
    .patient-modal .modal-close:hover { color: var(--primary-700); }
    .patient-modal .modal-body { padding: 16px 20px; max-height: 70vh; overflow-y: auto; }
    #addPatientForm { display: grid; grid-template-columns: 1fr; gap: 12px; }
    @media (min-width: 768px) { #addPatientForm { grid-template-columns: 1fr 1fr; } }
    #addPatientForm .page-title, #addPatientForm .modal-actions { grid-column: 1 / -1; }
    .form-group { background: #ffffff; border: 1px solid #e6e9f0; border-radius: 10px; padding: 12px 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
    .form-group label { font-weight: 600; color: #374151; margin-bottom: 6px; display: block; }
    .form-group input[type="text"],
    .form-group input[type="number"],
    .form-group input[type="date"],
    .form-group input[type="tel"],
    .form-group select,
    .form-group textarea { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #cdd6e4; outline: none; font-size: 14px; color: #111827; background: #fff; }
    .form-group textarea { resize: vertical; }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus { border-color: #2c7be5; box-shadow: 0 0 0 3px rgba(44, 123, 229, 0.15); }
    .form-group > div label { font-weight: 500; display: inline-flex; align-items: center; gap: 6px; }
    .page-title { font-size: 18px; color: #1f2937; margin: 10px 0; padding-left: 10px; border-left: 4px solid #2c7be5; }
    .modal-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 4px; }
    .ticket-section { position:relative; display:flex; justify-content:center; align-items:center; min-height:70vh; padding:24px; width:100%; background: linear-gradient(135deg, #f6fbff 0%, #eaf6f1 100%); overflow:hidden; }
    .ticket-container { background: linear-gradient(135deg,#ffffff 0%,#f7f9ff 100%); border-radius: 20px; box-shadow: 0 18px 42px rgba(0, 0, 0, 0.12); padding: 40px; max-width: 560px; width: 100%; text-align: center; margin: 0 auto; border:1px solid #e6e9f0; }
    .ticket-number { font-size: 84px; font-weight: 800; color: #4f46e5; margin: 8px 0 14px; line-height: 1; text-shadow: 0 6px 18px rgba(79,70,229,0.25); }
    .now-serving-number { font-size: 38px; font-weight: 700; letter-spacing: 4px; padding: 10px 16px; border-radius: 14px; background: linear-gradient(135deg,#5b7cf8 0%, #4f46e5 100%); color:#fff; box-shadow:0 10px 24px rgba(79,70,229,0.25); }
    .ticket-actions .btn { min-width: 220px; justify-content:center; border-radius: 16px; }
    .next-badge { display:inline-block; margin-top:10px; padding:6px 10px; background:#eef2ff; color:#1e3a8a; border-radius:10px; box-shadow:0 4px 12px rgba(30,58,138,0.12); }
    .error { background:#fff2f2; border:1px solid #f5c2c7; color:#842029; border-radius:8px; padding:10px 12px; margin-bottom:10px; }
    .success { background:#f0fff4; border:1px solid #c6f6d5; color:#22543d; border-radius:8px; padding:10px 12px; margin-bottom:10px; }

    @keyframes glowPulse { 0% { box-shadow: 0 0 0 rgba(79,70,229,0); } 50% { box-shadow: 0 0 24px rgba(79,70,229,0.35); } 100% { box-shadow: 0 0 0 rgba(79,70,229,0); } }
    .glow { animation: glowPulse 900ms ease-in-out 1; }
    .floating-circles { position:absolute; inset:0; pointer-events:none; z-index:0; }
    .floating-circles span { position:absolute; border-radius:50%; filter: blur(6px); opacity:0.9; animation: floatMove 10s ease-in-out infinite; }
    .floating-circles .blue { background: radial-gradient(closest-side, rgba(44,123,229,0.16), rgba(44,123,229,0)); }
    .floating-circles .green { background: radial-gradient(closest-side, rgba(22,163,74,0.16), rgba(22,163,74,0)); }
    .floating-circles span:nth-child(1){ top:8%; left:6%; width:220px; height:220px; animation-delay:0s; }
    .floating-circles span:nth-child(2){ bottom:10%; right:8%; width:320px; height:320px; animation-delay:1.1s; }
    .floating-circles span:nth-child(3){ top:52%; left:60%; width:200px; height:200px; animation-delay:2s; }
    .floating-circles span:nth-child(4){ top:20%; right:20%; width:180px; height:180px; animation-delay:3s; }
    .floating-circles span:nth-child(5){ bottom:18%; left:22%; width:260px; height:260px; animation-delay:4s; }
    .floating-circles .dot { width:110px; height:110px; background: radial-gradient(closest-side, rgba(108,92,231,0.16), rgba(108,92,231,0)); animation: floatMove 7s ease-in-out infinite; }
    @keyframes floatMove { 0%{ transform: translate(0,0) } 50%{ transform: translate(12px,-12px) } 100%{ transform: translate(0,0) } }
    @keyframes ringSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @keyframes breathe { 0% { box-shadow: 0 0 0 rgba(34,197,94,0); } 50% { box-shadow: 0 0 32px rgba(34,197,94,0.35); } 100% { box-shadow: 0 0 0 rgba(34,197,94,0); } }
    .ticket-container { background: rgba(255,255,255,0.78); backdrop-filter: blur(8px); border-radius: 24px; box-shadow: 0 24px 48px rgba(17,24,39,0.18); padding: 36px; max-width: 640px; width: 100%; text-align: center; margin: 0 auto; border:1px solid #e6e9f0; }
    .ticket-ring { width: 220px; height: 220px; margin: 12px auto 18px; border-radius: 50%; display:flex; align-items:center; justify-content:center; background: conic-gradient(from 0deg, #2c7be5, #6c5ce7, #2c7be5); padding: 10px; box-shadow: inset 0 0 24px rgba(79,70,229,0.15); position: relative; overflow: hidden; }
    .ticket-ring .inner { background:#fff; border-radius:50%; width:100%; height:100%; display:flex; align-items:center; justify-content:center; box-shadow: 0 10px 22px rgba(0,0,0,0.08); position: relative; z-index: 1; }
    .ticket-ring.invited { background: conic-gradient(#22c55e, #16a34a, #22c55e); }
    .ticket-ring.regular { background: conic-gradient(#3b82f6, #2563eb, #3b82f6); }
    .ticket-ring.priority { background: conic-gradient(#ef4444, #dc2626, #ef4444); }
    .ticket-ring::before { content:''; position:absolute; inset:-16px; border-radius:50%; background: conic-gradient(from 0deg, rgba(44,123,229,0.28), rgba(44,123,229,0), rgba(108,92,231,0.28), rgba(44,123,229,0)); filter: blur(8px); animation: ringSpin 14s linear infinite; z-index:0; }
    .ticket-ring.priority::before { background: conic-gradient(#dc262666, transparent, #ef444466, transparent); }
    .ticket-ring.invited::before { background: conic-gradient(#16a34a66, transparent, #22c55e66, transparent); animation: ringSpin 10s linear infinite; }
    .ticket-ring.invited .inner { animation: breathe 1200ms ease-in-out infinite; }
    .ticket-ring.invited { animation: ringPulse 2s ease-in-out infinite; }
    .ticket-ring.invited::before { content:''; position:absolute; inset:-28px; border-radius:50%; background: radial-gradient(closest-side, rgba(34,197,94,0.18), rgba(34,197,94,0)); filter: blur(12px); animation: haloPulse 2.6s ease-in-out infinite, swirl 16s linear infinite; }
    .ticket-ring.invited::after { content:''; position:absolute; top:50%; left:50%; width:20px; height:20px; border-radius:50%; background:#22c55e; box-shadow:0 0 24px rgba(34,197,94,0.85); transform: translate(-50%, -50%); animation: orbit 7s linear infinite; }
    .ticket-ring.invited .inner::after { content:''; position:absolute; inset:4px; border-radius:50%; background: radial-gradient(closest-side, rgba(34,197,94,0.32), transparent 70%); animation: innerPulse 2.2s ease-in-out infinite; }
    @keyframes ringPulse { 0%{ box-shadow: 0 0 0 rgba(34,197,94,0), 0 0 0 rgba(34,197,94,0); } 50%{ box-shadow: 0 0 72px rgba(34,197,94,0.55), 0 0 36px rgba(34,197,94,0.35); } 100%{ box-shadow: 0 0 0 rgba(34,197,94,0), 0 0 0 rgba(34,197,94,0); } }
    @keyframes orbit { from { transform: translate(-50%, -50%) rotate(0deg) translate(150px); } to { transform: translate(-50%, -50%) rotate(360deg) translate(150px); } }
    @keyframes innerPulse { 0%{ transform: scale(0.88); opacity:0.4; } 50%{ transform: scale(1.15); opacity:0.7; } 100%{ transform: scale(0.88); opacity:0.4; } }
    @keyframes haloPulse { 0%{ opacity:0.25; transform: scale(0.96); } 50%{ opacity:0.6; transform: scale(1.06); } 100%{ opacity:0.25; transform: scale(0.96); } }
    @keyframes swirl { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .ticket-number { font-size: 84px; font-weight: 800; color: #1f2937; margin: 0; line-height: 1; }
    .ticket-meta { color:#374151; font-size:14px; margin-top:8px; }
    .ticket-meta .pill { display:inline-block; padding:6px 10px; border-radius:999px; background:#eef2ff; color:#1e3a8a; margin:4px; }

    .toast-container { position:fixed; top:18px; right:18px; display:flex; flex-direction:column; gap:8px; z-index:10000; }
    .toast-container .toast { position:relative; left:auto; transform:none; }
    .toast { position:fixed; top:18px; left:50%; transform:translateX(-50%) translateY(-8px); background:#111827; color:#fff; padding:12px 18px; border-radius:14px; font-size:16px; font-weight:700; box-shadow:0 10px 24px rgba(0,0,0,0.18); opacity:0; transition:opacity .2s ease, transform .2s ease; z-index:10000; letter-spacing:.3px; }
    .toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
    .toast-success { background:#22c55e; }
    .toast-error { background:#dc2626; }
    .toast-invite { background:#16a34a; font-size:22px; padding:16px 22px; border-radius:18px; }


    .btn { display:inline-flex; align-items:center; gap:8px; padding:10px 16px; border-radius:12px; border:1px solid #cdd6e4; background:#ffffff; color:#1f2937; font-weight:600; letter-spacing:0.2px; box-shadow:0 2px 6px rgba(0,0,0,0.08); transition:transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease; }
    .btn:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(0,0,0,0.12); }
    .btn:active { transform:translateY(0); box-shadow:0 2px 6px rgba(0,0,0,0.08); }
    .btn-primary { background: linear-gradient(135deg, #2c7be5 0%, #6c5ce7 100%); color:#ffffff; border:none; }
    .btn-primary:hover { opacity:0.95; }

    .ticket-actions .btn { min-width: 200px; justify-content:center; }

    .loading { width:16px; height:16px; border:2px solid rgba(255,255,255,0.6); border-top-color:#ffffff; border-radius:50%; display:inline-block; animation: spin 0.8s linear infinite; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <header>
    <div class="header-content">
      <div class="logo-placeholder left-logo"></div>
      <h1>GENERAL TRIAS SUPER HEALTH CENTER</h1>
      <p>Arnaldo Hwy, General Trias, Cavite</p>
    </div>
  </header>

  <main class="content" style="display:<?= $showTicket ? 'none' : 'flex' ?>; align-items:center; justify-content:center; min-height:60vh;">
    <div class="card" style="max-width:680px; width:100%; text-align:center;">
      <h2 class="page-title center" style="margin-bottom:12px;">Welcome to General Trias Health Center</h2>
      <p style="color:#555; margin-bottom:18px;">Please enter the queue and fill out your details so we can serve you promptly.</p>
      <div style="display:flex; justify-content:center;">
        <button id="enterQueueBtn" class="form-save-btn" style="padding:10px 24px; font-size:18px; border-radius:20px;">Enter Queue</button>
      </div>
      <?php if ($errors): ?>
        <div class="error" style="margin-top:12px;">
          <?php foreach ($errors as $e): ?>
            <div>- <?= h($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($result): ?>
        <div class="success" style="margin-top:12px;">Patient registered successfully.</div>
      <?php endif; ?>
    </div>
  </main>

  <div id="patientQueueModal" class="modal-overlay hidden" aria-hidden="true">
    <div class="modal patient-modal" role="dialog" aria-modal="true" aria-labelledby="patientModalTitle">
      <div class="modal-header">
        <h3 id="patientModalTitle">Add Patient</h3>
        <button type="button" class="modal-close" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <form id="addPatientForm" method="POST" action="patient_index.php">
          <h2 class="page-title">Patient Registration</h2>
          <div class="form-group">
            <label for="caseNumber">Case Number</label>
            <input type="text" id="caseNumber" readonly value="<?= $result ? h($result['case_number']) : '' ?>" />
          </div>
          <div class="form-group">
            <label for="philHealthId">PhilHealth Identification Number</label>
            <input type="text" id="philHealthId" name="philhealth_id" />
          </div>

          <h3 class="page-title">Personal Information</h3>
          <div class="form-group">
            <label for="lastNameFull">Last Name *</label>
            <input type="text" id="lastNameFull" name="last_name" required />
          </div>
          <div class="form-group">
            <label for="firstNameFull">First Name *</label>
            <input type="text" id="firstNameFull" name="first_name" required />
          </div>
          <div class="form-group">
            <label for="middleNameFull">Middle Name</label>
            <input type="text" id="middleNameFull" name="middle_name" />
          </div>
          <div class="form-group">
            <label for="extensionName">Extension Name</label>
            <input type="text" id="extensionName" name="extension_name" placeholder="Jr., Sr., III, etc." />
          </div>
          <div class="form-group">
            <label for="age">Age *</label>
            <input type="number" id="age" name="age" min="0" max="150" required readonly />
          </div>
          <div class="form-group">
            <label for="dob">Date of Birth *</label>
            <input type="date" id="dob" name="date_of_birth" required />
          </div>
          <div class="form-group">
            <label>Sex *</label>
            <div>
              <label><input type="radio" name="sex" value="Male" required> Male</label>
              <label style="margin-left:12px;"><input type="radio" name="sex" value="Female" required> Female</label>
            </div>
          </div>

          <h3 class="page-title">Contact Information</h3>
          <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone" placeholder="09123456789" />
          </div>
          <div class="form-group">
            <label for="address">Address</label>
            <textarea id="address" name="address" rows="2" placeholder="Complete address"></textarea>
          </div>
          <div class="form-group">
            <label for="emergencyContact">Emergency Contact Name</label>
            <input type="text" id="emergencyContact" name="emergency_contact" />
          </div>
          <div class="form-group">
            <label for="emergencyPhone">Emergency Contact Phone</label>
            <input type="tel" id="emergencyPhone" name="emergency_phone" placeholder="09123456789" />
          </div>

          <h3 class="page-title">Visit Information</h3>
          <div class="form-group">
            <label for="clientType">Client Type *</label>
            <select id="clientType" name="client_type" required>
              <option value="">Select client type</option>
              <option value="PWD">PWD</option>
              <option value="Senior">Senior</option>
              <option value="Pregnant">Pregnant</option>
              <option value="Regular">Regular</option>
            </select>
          </div>
          <div class="form-group">
            <label for="visitType">Visit Type *</label>
            <select id="visitType" name="visit_type" required>
              <option value="">Select visit type</option>
              <option value="New">New</option>
              <option value="Old (Follow-up)">Old (Follow-up)</option>
            </select>
          </div>
          <div class="form-group">
            <label for="department">Department *</label>
            <select id="department" name="department_id" required>
              <option value="">Select department</option>
              <?php foreach ($departments as $d): ?>
                <option value="<?= (int)$d['department_id'] ?>"><?= h($d['department_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <h2 class="page-title">2. REVIEW OF SYSTEMS</h2>
          <div class="form-group">
            <label for="chiefComplaint">1. Chief complaint (please describe)</label>
            <textarea id="chiefComplaint" name="chief_complaint" rows="2"></textarea>
          </div>
          <div class="form-group">
            <label>2. Loss of appetite, lack of sleep, weight loss, depression, fever, headache, memory loss, blurred vision, hearing loss?</label>
            <div>
              <label><input type="radio" name="ros2" value="Yes"> Yes</label>
              <label style="margin-left:12px;"><input type="radio" name="ros2" value="No"> No</label>
            </div>
            <label>If yes, please explain:</label>
            <textarea id="ros2Explain" name="symptoms_ros2" rows="2"></textarea>
          </div>
          <div class="form-group">
            <label>3. Cough/colds, chest pain, palpitations, or difficulty in breathing?</label>
            <div>
              <label><input type="radio" name="ros3" value="Yes"> Yes</label>
              <label style="margin-left:12px;"><input type="radio" name="ros3" value="No"> No</label>
            </div>
            <label>If yes, please explain:</label>
            <textarea id="ros3Explain" name="symptoms_ros3" rows="2"></textarea>
          </div>
          <div class="form-group">
            <label>4. Abdominal pain, vomiting, change in bowel movement, rectal bleeding, or bloody/tarry stools?</label>
            <div>
              <label><input type="radio" name="ros4" value="Yes"> Yes</label>
              <label style="margin-left:12px;"><input type="radio" name="ros4" value="No"> No</label>
            </div>
            <label>If yes, please explain:</label>
            <textarea id="ros4Explain" name="symptoms_ros4" rows="2"></textarea>
          </div>
          <div class="form-group">
            <label>5. Frequent urination, frequent eating, frequent intake of fluids?</label>
            <div>
              <label><input type="radio" name="ros5" value="Yes"> Yes</label>
              <label style="margin-left:12px;"><input type="radio" name="ros5" value="No"> No</label>
            </div>
            <label>If yes, please explain:</label>
            <textarea id="ros5Explain" name="symptoms_ros5" rows="2"></textarea>
          </div>
          <div class="form-group">
            <label>6. Pain/discomfort on urination, frequency/dribbling of urine, pain during/after sex, blood in urine, or foul-smelling genital discharge?</label>
            <div>
              <label><input type="radio" name="ros6" value="Yes"> Yes</label>
              <label style="margin-left:12px;"><input type="radio" name="ros6" value="No"> No</label>
            </div>
            <label>If yes, please explain:</label>
            <textarea id="ros6Explain" name="symptoms_ros6" rows="2"></textarea>
          </div>
          <div class="form-group">
            <label>7. (Females only) Last menstrual period / First menstrual period / Number of pregnancies</label>
            <input type="text" id="lmp" name="last_menstrual_period" placeholder="Last menstrual period (mm/dd/yyyy)" />
            <input type="text" id="fmp" name="first_menstrual_period" placeholder="First menstrual period (mm/dd/yyyy)" style="margin-top:6px;" />
            <input type="number" id="pregnancies" name="number_of_pregnancies" placeholder="Number of pregnancy" style="margin-top:6px;" />
          </div>
          <div class="form-group">
            <label>8. Muscle spasm, tremors, weakness; muscle/joint pain, stiffness, limitation of movement?</label>
            <div>
              <label><input type="radio" name="ros8" value="Yes"> Yes</label>
              <label style="margin-left:12px;"><input type="radio" name="ros8" value="No"> No</label>
            </div>
            <label>If yes, please explain:</label>
            <textarea id="ros8Explain" name="symptoms_ros8" rows="2"></textarea>
          </div>

          <h2 class="page-title">3. PERSONAL/SOCIAL HISTORY</h2>
          <div class="form-group">
            <label>Do you smoke cigar, cigarette, e-cigarette, vape, or similar products?</label>
            <div>
              <label><input type="radio" name="smoking_status" value="Yes"> Yes</label>
              <label style="margin-left:12px;"><input type="radio" name="smoking_status" value="No"> No</label>
            </div>
            <input type="number" id="smokeYears" name="smoking_years" placeholder="Number of years" style="margin-top:6px;" />
          </div>
          <div class="form-group">
            <label>Do you drink alcohol or alcohol-containing beverages?</label>
            <div>
              <label><input type="radio" name="alcohol_status" value="Yes"> Yes</label>
              <label style="margin-left:12px;"><input type="radio" name="alcohol_status" value="No"> No</label>
            </div>
            <input type="number" id="alcoholYears" name="alcohol_years" placeholder="Number of years" style="margin-top:6px;" />
          </div>

          <h2 class="page-title">4. PAST MEDICAL HISTORY</h2>
          <div class="form-group">
            <div style="display:flex; flex-wrap:wrap; gap:10px;">
              <label><input type="checkbox" name="pmh[]" value="Cancer"> Cancer</label>
              <label><input type="checkbox" name="pmh[]" value="Allergies"> Allergies</label>
              <label><input type="checkbox" name="pmh[]" value="Diabetes Mellitus"> Diabetes Mellitus</label>
              <label><input type="checkbox" name="pmh[]" value="Hypertension"> Hypertension</label>
              <label><input type="checkbox" name="pmh[]" value="Heart Disease"> Heart Disease</label>
              <label><input type="checkbox" name="pmh[]" value="Stroke"> Stroke</label>
              <label><input type="checkbox" name="pmh[]" value="Bronchial asthma"> Bronchial asthma</label>
              <label><input type="checkbox" name="pmh[]" value="COPD/emphysema/bronchitis"> COPD/emphysema/bronchitis</label>
              <label><input type="checkbox" name="pmh[]" value="Tuberculosis"> Tuberculosis</label>
              <label><input type="checkbox" name="pmh[]" value="Others"> Others</label>
              <label><input type="checkbox" name="pmh[]" value="None"> None</label>
            </div>
            <input type="text" id="pmhOthers" name="pmh_others" placeholder="If others, please specify" style="margin-top:6px;" />
          </div>

          <div class="modal-actions">
            <button type="button" class="modal-cancel">Cancel</button>
            <button type="submit" class="modal-save">Submit</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    (function() {
      const openBtn = document.getElementById('enterQueueBtn');
      const overlay = document.getElementById('patientQueueModal');
      const closeBtn = overlay.querySelector('.modal-close');
      const cancelBtn = overlay.querySelector('.modal-cancel');

      function openModal() {
        overlay.classList.remove('hidden');
        overlay.setAttribute('aria-hidden', 'false');
      }
      function closeModal() {
        overlay.classList.add('hidden');
        overlay.setAttribute('aria-hidden', 'true');
      }
      if (openBtn) openBtn.addEventListener('click', openModal);
      if (closeBtn) closeBtn.addEventListener('click', closeModal);
      if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
      overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });
      const dobEl = document.getElementById('dob');
      const ageEl = document.getElementById('age');
      const calcAge = (v) => { const d = new Date(v); if (isNaN(d)) return; const t = new Date(); let a = t.getFullYear() - d.getFullYear(); const m = t.getMonth() - d.getMonth(); if (m < 0 || (m === 0 && t.getDate() < d.getDate())) a--; ageEl && (ageEl.value = String(Math.max(0, a))); };
      if (dobEl) dobEl.addEventListener('change', () => calcAge(dobEl.value));
      const formEl = document.getElementById('addPatientForm');
      if (formEl) formEl.addEventListener('submit', (e) => { const any = Array.from(document.querySelectorAll('input[name="pmh[]"]')).some(x => x.checked) || ((document.getElementById('pmhOthers')?.value || '').trim() !== ''); if (!any) { e.preventDefault(); try { showToast('Review of systems form is required.', 'error', 3500); } catch(_) { /* no-op */ } } });
    })();

    let ticketData = {};
    let autoRefreshInterval;

    function showTicketDisplay(data) {
      ticketData = data;
      document.querySelector('main.content').style.display = 'none';
      document.getElementById('ticketSection').style.display = 'block';
      document.getElementById('ticketNumber').textContent = data.ticketNumber;
      document.getElementById('caseNumber').textContent = data.caseNumber;
      document.getElementById('priorityLevel').textContent = data.priority;
      const waitTimes = { 'Priority': '5 Minutes', 'Senior': '8 Minutes', 'PWD': '8 Minutes', 'Regular': '15 Minutes' };
      document.getElementById('estimatedWaitTime').textContent = waitTimes[data.priority] || '15 Minutes';
      const ring = document.getElementById('ticketRing');
      if (ring) { ring.classList.remove('regular','priority','invited'); ring.classList.add((data.priority === 'Priority' || data.priority === 'Emergency') ? 'priority' : 'regular'); }
      loadCurrentServingNumber();
      startAutoRefresh();
    }

    async function loadCurrentServingNumber() {
      try {
        const response = await fetch('backend/get_queue_stats.php');
        const result = await response.json();
        if (result.ok && result.data) {
          const currentServing = result.data.next_queue_number ? (result.data.next_queue_number - 1) : ((ticketData.ticketNumber || 1) - 1);
          document.getElementById('nowServingNumber').textContent = currentServing > 0 ? currentServing : '000';
        }
      } catch (error) {
        document.getElementById('nowServingNumber').textContent = '000';
      }
    }

    async function refreshTicketStatus() {
      const refreshBtn = document.getElementById('refreshText');
      const refreshLoader = document.getElementById('refreshLoader');
      refreshBtn.style.display = 'none';
      refreshLoader.style.display = 'inline-block';
      try {
        await loadCurrentServingNumber();
        const currentServing = parseInt(document.getElementById('nowServingNumber').textContent);
        const ticketNumber = parseInt(document.getElementById('ticketNumber').textContent);
        if (ticketNumber <= currentServing + 1) {
          document.getElementById('ticketStatus').textContent = 'You are next to Check-in';
        } else if (ticketNumber <= currentServing + 3) {
          document.getElementById('ticketStatus').textContent = 'Please prepare to Check-in';
        } else {
          document.getElementById('ticketStatus').textContent = 'Please wait for your turn';
        }
      } finally {
        refreshLoader.style.display = 'none';
        refreshBtn.style.display = 'inline';
      }
    }

    function startAutoRefresh() {
      if (autoRefreshInterval) clearInterval(autoRefreshInterval);
      autoRefreshInterval = setInterval(() => { refreshTicketStatus(); }, 30000);
    }
    function stopAutoRefresh() { if (autoRefreshInterval) { clearInterval(autoRefreshInterval); autoRefreshInterval = null; } }
    window.addEventListener('beforeunload', stopAutoRefresh);

    const myDeptId = <?= isset($_SESSION['patient_ticket']['department_id']) ? (int)$_SESSION['patient_ticket']['department_id'] : 0 ?>;
    const myQueueNumber = <?= isset($_SESSION['patient_ticket']['queue_number']) ? (int)$_SESSION['patient_ticket']['queue_number'] : 0 ?>;

    function showToast(msg, type='success', ms=2500){
      var cls = 'toast ';
      if (type==='error') cls += 'toast-error';
      else if (type==='invite') cls += 'toast-invite';
      else cls += 'toast-success';
      var container = document.getElementById('toastContainer');
      if (!container) { container = document.createElement('div'); container.id = 'toastContainer'; container.className = 'toast-container'; document.body.appendChild(container); }
      var el = document.createElement('div');
      el.className = cls;
      el.textContent = msg;
      container.appendChild(el);
      requestAnimationFrame(()=> el.classList.add('show'));
      setTimeout(()=>{ el.classList.remove('show'); setTimeout(()=> { el.remove(); if (!container.children.length) container.remove(); }, 220); }, ms);
    }
    function animateNumberChange(el, newText){
      if (!el) return;
      const old = el.textContent;
      if (String(old) !== String(newText)) { el.textContent = newText; el.classList.add('glow'); setTimeout(()=> el.classList.remove('glow'), 900); }
    }

    async function notifyNextFromQueue(){
      try {
        const res = await fetch('backend/list_queue.php' + (myDeptId>0 ? ('?department_id='+myDeptId) : ''));
        const json = await res.json();
        const items = json.data || json || [];
        const called = (items||[]).filter(x=> (x.status||'').toLowerCase()==='called').sort((a,b)=> (a.queue_number||0)-(b.queue_number||0))[0] || null;
        const serveEl = document.getElementById('nowServingNumber');
        animateNumberChange(serveEl, called ? String(called.queue_number||'000') : '000');
        const me = (items||[]).find(x=> Number(x.queue_number||0) === Number(myQueueNumber||0));
        const ring = document.getElementById('ticketRing');
        if (me && (me.status||'').toLowerCase()==='called') {
          if (ring) { ring.classList.add('invited'); }
          showToast('You are invited!', 'invite');
        } else { if (ring) ring.classList.remove('invited'); }
        refreshTicketStatus();
      } catch(_) {}
    }

    (function subscribeQueueUpdates(){
      try {
        const bc = new BroadcastChannel('queue-updates');
        bc.onmessage = function(){ notifyNextFromQueue(); };
      } catch(_) { /* fallback to existing auto refresh */ }
    })();

    notifyNextFromQueue();
  </script>

  <div id="ticketSection" class="ticket-section" style="display: <?= $showTicket ? 'block' : 'none' ?>;">
    <div class="floating-circles"><span></span><span></span><span></span></div>
      <div class="floating-circles"><span class="blue"></span><span class="green"></span><span class="blue"></span><span class="green"></span><span class="blue"></span><span class="dot"></span><span class="dot"></span></div>
      <div class="ticket-container">
        <h1 class="page-title center" style="margin-top:0;">Your Queue Ticket</h1>
        <div class="ticket-ring <?= $showTicket ? (($result['priority'] === 'Priority' || $result['priority'] === 'Emergency') ? 'priority' : 'regular') : '' ?>" id="ticketRing"><div class="inner">
          <div class="ticket-number" id="ticketNumber"><?= $showTicket ? h($result['queue_number']) : '000' ?></div>
        </div></div>
        <div class="ticket-meta">
          <span>Estimated Wait: <strong id="estimatedWaitTime"><?= $showTicket ? ($result['priority'] === 'Priority' ? '5 Minutes' : '15 Minutes') : '5 Minutes' ?></strong></span>
          <span class="pill" id="priorityLevel"><?= $showTicket ? h($result['priority']) : 'Regular' ?></span>
          <span class="pill">Case: <span id="caseNumber"><?= $showTicket ? h($result['case_number']) : '-' ?></span></span>
        </div>
        <div id="nowServingNumber" style="position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0,0,0,0);">000</div>
      </div>
  </div>

  <footer class="footer">
    <div class="footer-logo-placeholder"></div>
    <p>Let's join forces! For a more progressive General Trias.</p>
  </footer>
  <?php
    $queueBroadcast = false;
    if (isset($_SESSION['queue_broadcast_once']) && $_SESSION['queue_broadcast_once']) {
      $queueBroadcast = true;
      unset($_SESSION['queue_broadcast_once']);
    }
  ?>
  <script>
  try {
    if (<?= $queueBroadcast ? 'true' : 'false' ?>) {
      const bc = new BroadcastChannel('queue-updates');
      bc.postMessage({ type: 'queue_update' });
      bc.close();
    }
  } catch (_) {}
  </script>
</body>
</html>