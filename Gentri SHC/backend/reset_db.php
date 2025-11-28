<?php
/**
 * Reset Database Script
 * Drops and recreates the database to start fresh
 */

$config = require __DIR__ . '/db_config.php';

try {
    // Connect without specifying database
    $dsn = sprintf('mysql:host=%s;charset=%s', $config['host'], $config['charset']);
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "Dropping database if exists...\n";
    $pdo->exec("DROP DATABASE IF EXISTS `{$config['db']}`");
    
    echo "Creating fresh database...\n";
    $pdo->exec("CREATE DATABASE `{$config['db']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    echo "Database reset completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>