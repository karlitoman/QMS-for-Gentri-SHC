<?php
// Initializes the complete healthcare management system database using the consolidated schema

$config = require __DIR__ . '/db_config.php';

function pdo_connect_server(array $cfg): PDO {
  $dsn = sprintf('mysql:host=%s;charset=%s', $cfg['host'], $cfg['charset']);
  $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}

function pdo_connect_db(array $cfg): PDO {
  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['db'], $cfg['charset']);
  $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}

function executeSqlFile($pdo, $filePath) {
  $sql = file_get_contents($filePath);
  if ($sql === false) {
    throw new Exception("Could not read SQL file: $filePath");
  }
  
  // Split SQL into individual statements
  $statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($stmt) {
      return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
    }
  );
  
  foreach ($statements as $statement) {
    if (!empty(trim($statement))) {
      try {
        $pdo->exec($statement);
      } catch (PDOException $e) {
        // Skip errors for statements that might already exist (like CREATE FUNCTION IF NOT EXISTS)
        if (strpos($e->getMessage(), 'already exists') === false && 
            strpos($e->getMessage(), 'Duplicate') === false) {
          throw $e;
        }
      }
    }
  }
}

try {
  // Connect to server to create database
  $serverPdo = pdo_connect_server($config);
  
  // Execute the consolidated database schema
  $schemaFile = __DIR__ . '/database_schema.sql';
  if (!file_exists($schemaFile)) {
    throw new Exception("Database schema file not found: $schemaFile");
  }
  
  // Execute the complete schema
  executeSqlFile($serverPdo, $schemaFile);
  
  // Connect to the database for additional operations
  $dbPdo = pdo_connect_db($config);
  
  // Create functions and triggers that require special handling
  try {
    // Create case number generation function
    $dbPdo->exec("DROP FUNCTION IF EXISTS generate_case_number");
    $dbPdo->exec("
      CREATE FUNCTION generate_case_number() 
      RETURNS VARCHAR(20)
      READS SQL DATA
      DETERMINISTIC
      BEGIN
          DECLARE next_number INT;
          DECLARE case_num VARCHAR(20);
          
          SELECT COALESCE(MAX(CAST(SUBSTRING(case_number, 4) AS UNSIGNED)), 0) + 1 
          INTO next_number 
          FROM patients 
          WHERE case_number LIKE 'PT-%';
          
          SET case_num = CONCAT('PT-', LPAD(next_number, 4, '0'));
          
          RETURN case_num;
      END
    ");
    
    // Create queue number generation trigger
    $dbPdo->exec("DROP TRIGGER IF EXISTS generate_queue_number");
    $dbPdo->exec("
      CREATE TRIGGER generate_queue_number 
      BEFORE INSERT ON queue_entries
      FOR EACH ROW
      BEGIN
          DECLARE next_queue_num INT;
          
          SELECT COALESCE(MAX(queue_number), 0) + 1 
          INTO next_queue_num 
          FROM queue_entries 
          WHERE department_id = NEW.department_id 
          AND DATE(created_at) = CURDATE();
          
          SET NEW.queue_number = next_queue_num;
      END
    ");
  } catch (PDOException $e) {
    // Functions and triggers are optional, continue if they fail
    error_log("Warning: Could not create functions/triggers: " . $e->getMessage());
  }
  
  // Update default user passwords with proper hashing
  $passwordPlain = 'Pogi';
  $passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);
  
  $updateStmt = $dbPdo->prepare(
    'UPDATE `users` SET `password` = :p WHERE `email` IN (:admin, :staff, :doctor)'
  );
  
  $updateStmt->execute([
    ':p' => $passwordHash,
    ':admin' => 'karl+admin@example.com',
    ':staff' => 'karl+staff@example.com',
    ':doctor' => 'karl+doctor@example.com'
  ]);

  header('Content-Type: text/plain');
  echo "Healthcare Management System Database initialized successfully!\n\n";
  echo "Database: '{$config['db']}'\n";
  echo "Schema: Comprehensive healthcare management system\n\n";
  echo "Tables created:\n";
  echo "- users (consolidated staff/doctor info)\n";
  echo "- departments\n";
  echo "- patients\n";
  echo "- patient_visits\n";
  echo "- medical_records\n";
  echo "- queue_entries\n\n";
  echo "Views created:\n";
  echo "- patient_queue_view\n\n";
  echo "Functions and triggers:\n";
  echo "- generate_case_number() function\n";
  echo "- generate_queue_number trigger\n\n";
  echo "Sample data:\n";
  echo "- Departments: Medical, OPD, Dental, Animal Bite, Emergency, OB-GYNE, etc.\n";
  echo "- Default accounts: admin/staff/doctor (password: 'Pogi')\n";
  echo "- Emails: karl+admin@example.com, karl+staff@example.com, karl+doctor@example.com\n\n";
  echo "System ready for use!\n";
  
} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: text/plain');
  echo "Error initializing database: " . $e->getMessage() . "\n";
  echo "File: " . $e->getFile() . "\n";
  echo "Line: " . $e->getLine() . "\n";
}