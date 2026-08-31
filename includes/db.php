<?php

// Set Qatar timezone (UTC+3) for the entire application
date_default_timezone_set('Asia/Qatar');

// Load environment variables
require_once __DIR__ . '/functions.php';
loadEnv(__DIR__ . '/../.env');

// Determine environment (development or production)
$env = getenv('APP_ENV') ?: 'development';

// Get database configuration based on environment
if ($env === 'production') {
    $dbType = getenv('PROD_DB_TYPE') ?: 'mysql';
    $dbHost = getenv('PROD_DB_HOST') ?: 'localhost';
    $dbPort = getenv('PROD_DB_PORT') ?: '3306';
    $dbName = getenv('PROD_DB_NAME') ?: '';
    $dbUser = getenv('PROD_DB_USER') ?: '';
    $dbPass = getenv('PROD_DB_PASS') ?: '';
} else {
    $dbType = getenv('DEV_DB_TYPE') ?: 'mysql';
    $dbHost = getenv('DEV_DB_HOST') ?: 'localhost';
    $dbPort = getenv('DEV_DB_PORT') ?: '3306';
    $dbName = getenv('DEV_DB_NAME') ?: 'buildon';
    $dbUser = getenv('DEV_DB_USER') ?: 'root';
    $dbPass = getenv('DEV_DB_PASS') ?: '';
}

try {
    if ($dbType === 'mysql') {
        // MySQL Connection
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);
    } else {
        // SQLite Connection (fallback)
        $sqlitePath = getenv('SQLITE_DB_PATH') ?: 'buildon.sqlite';
        $dsn = 'sqlite:' . __DIR__ . '/../' . $sqlitePath;
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
} catch (PDOException $e) {
    // If MySQL fails, try SQLite as fallback
    if ($dbType === 'mysql') {
        try {
            $sqlitePath = getenv('SQLITE_DB_PATH') ?: 'buildon.sqlite';
            $dsn = 'sqlite:' . __DIR__ . '/../' . $sqlitePath;
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            error_log("MySQL connection failed, using SQLite fallback: " . $e->getMessage());
        } catch (PDOException $e2) {
            die("Database connection failed: " . $e->getMessage() . " | Fallback also failed: " . $e2->getMessage());
        }
    } else {
        die("Connection failed: " . $e->getMessage());
    }
}