<?php
/**
 * Direct SQLite to MySQL Copy
 * Bypass SQL files and copy data directly
 */

require_once __DIR__ . '/../includes/functions.php';
loadEnv(__DIR__ . '/../.env');

// Connect to SQLite
$sqlite = new PDO('sqlite:' . __DIR__ . '/../buildon.sqlite');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Connect to MySQL
$env = getenv('APP_ENV') ?: 'development';
if ($env === 'production') {
    $dbHost = getenv('PROD_DB_HOST') ?: 'localhost';
    $dbPort = getenv('PROD_DB_PORT') ?: '3306';
    $dbName = getenv('PROD_DB_NAME') ?: '';
    $dbUser = getenv('PROD_DB_USER') ?: '';
    $dbPass = getenv('PROD_DB_PASS') ?: '';
} else {
    $dbHost = getenv('DEV_DB_HOST') ?: 'localhost';
    $dbPort = getenv('DEV_DB_PORT') ?: '3306';
    $dbName = getenv('DEV_DB_NAME') ?: 'buildon';
    $dbUser = getenv('DEV_DB_USER') ?: 'root';
    $dbPass = getenv('DEV_DB_PASS') ?: '';
}

$mysql = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

echo "=== Direct SQLite to MySQL Copy ===\n\n";

// Tables to copy
$tables = ['employees', 'clients', 'vendors', 'users', 'projects', 'quotations', 'invoices', 'payments'];

foreach ($tables as $table) {
    echo "Copying $table...\n";

    // Get data from SQLite
    $rows = $sqlite->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) == 0) {
        echo "  No data to copy\n";
        continue;
    }

    // Clear MySQL table
    $mysql->exec("TRUNCATE TABLE `$table`");

    // Get column names
    $columns = array_keys($rows[0]);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $columnList = '`' . implode('`, `', $columns) . '`';

    // Prepare insert statement
    $stmt = $mysql->prepare("INSERT INTO `$table` ($columnList) VALUES ($placeholders)");

    // Insert each row
    $successCount = 0;
    foreach ($rows as $row) {
        try {
            $stmt->execute(array_values($row));
            $successCount++;
        } catch (PDOException $e) {
            echo "  Error: " . $e->getMessage() . "\n";
        }
    }

    echo "  Copied $successCount / " . count($rows) . " rows\n";
}

echo "\n=== Verification ===\n";
foreach ($tables as $table) {
    $sqliteCount = $sqlite->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    $mysqlCount = $mysql->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    $match = ($sqliteCount == $mysqlCount) ? '✓' : '✗';
    echo "$table: SQLite=$sqliteCount, MySQL=$mysqlCount $match\n";
}

echo "\n✅ Direct copy complete!\n";
