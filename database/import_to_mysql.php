<?php
/**
 * Import MySQL Schema and Data
 * Run this script to import the exported schema and data into MySQL
 */

require_once __DIR__ . '/../includes/functions.php';
loadEnv(__DIR__ . '/../.env');

// Get MySQL credentials from environment
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

echo "=== MySQL Import Script ===\n";
echo "Environment: $env\n";
echo "Database: $dbName\n\n";

try {
    // Connect to MySQL server (without database)
    $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "Connected to MySQL server\n";

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '{$dbName}' created/verified\n";

    // Select database
    $pdo->exec("USE `{$dbName}`");

    // Import schema
    echo "\nImporting schema...\n";
    $schemaFile = __DIR__ . '/mysql_schema.sql';
    if (file_exists($schemaFile)) {
        $schema = file_get_contents($schemaFile);
        $pdo->exec($schema);
        echo "Schema imported successfully\n";
    } else {
        die("Error: mysql_schema.sql not found. Run migrate_to_mysql.php first.\n");
    }

    // Import data
    echo "\nImporting data...\n";
    $dataFile = __DIR__ . '/mysql_data.sql';
    if (file_exists($dataFile)) {
        $data = file_get_contents($dataFile);

        // Split into individual statements and execute
        $statements = array_filter(array_map('trim', explode(';', $data)));
        $count = 0;

        foreach ($statements as $statement) {
            if (!empty($statement) && strpos($statement, '--') !== 0) {
                try {
                    $pdo->exec($statement);
                    $count++;
                } catch (PDOException $e) {
                    echo "Warning: " . $e->getMessage() . "\n";
                }
            }
        }

        echo "Data imported successfully ($count statements)\n";
    } else {
        die("Error: mysql_data.sql not found. Run migrate_to_mysql.php first.\n");
    }

    // Verify import
    echo "\n=== Verification ===\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Total tables: " . count($tables) . "\n\n";

    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "  $table: $count rows\n";
    }

    echo "\n=== Import Complete ===\n";
    echo "Your MySQL database is ready!\n";
    echo "Update your .env file APP_ENV to use this database.\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
