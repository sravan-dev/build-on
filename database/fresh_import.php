<?php
/**
 * Fresh Import - Clear all data and re-import from SQLite
 */

require_once __DIR__ . '/../includes/functions.php';
loadEnv(__DIR__ . '/../.env');

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

echo "=== Fresh MySQL Import ===\n";
echo "This will clear all existing data and re-import from SQLite\n\n";

try {
    // Connect to MySQL
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "Connected to MySQL\n";

    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    echo "Clearing " . count($tables) . " tables...\n";

    // Truncate all tables
    foreach ($tables as $table) {
        try {
            $pdo->exec("TRUNCATE TABLE `$table`");
            echo ".";
        } catch (PDOException $e) {
            echo "X";
        }
    }

    echo "\n\nTables cleared. Now importing data...\n\n";

    // Read and execute data file
    $dataFile = __DIR__ . '/mysql_data.sql';
    $sql = file_get_contents($dataFile);

    // Split by semicolon and newline
    $statements = explode(";\n", $sql);

    $successCount = 0;
    $errorCount = 0;
    $currentTable = '';

    foreach ($statements as $statement) {
        $statement = trim($statement);

        // Skip empty statements and comments
        if (empty($statement) || strpos($statement, '--') === 0 || strpos($statement, 'SET FOREIGN_KEY_CHECKS') === 0) {
            continue;
        }

        // Track current table
        if (preg_match('/INSERT INTO `(\w+)`/', $statement, $matches)) {
            if ($currentTable !== $matches[1]) {
                $currentTable = $matches[1];
                echo "\nImporting $currentTable...\n";
            }
        }

        try {
            $pdo->exec($statement);
            $successCount++;
            echo ".";
        } catch (PDOException $e) {
            $errorCount++;
            echo "X";
        }
    }

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

    echo "\n\n=== Import Complete ===\n";
    echo "Successful: $successCount statements\n";
    echo "Errors: $errorCount statements\n\n";

    // Verify data
    echo "=== Final Verification ===\n";
    $verifyTables = ['employees', 'clients', 'vendors', 'users', 'accounts', 'projects', 'quotations', 'invoices'];
    foreach ($verifyTables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "$table: $count rows\n";
    }

    echo "\n✅ Fresh import completed!\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
