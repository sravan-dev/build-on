<?php
/**
 * Direct Copy with Date Conversion
 * Convert all date formats to MySQL-compatible YYYY-MM-DD
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

echo "=== Direct Copy with Date Conversion ===\n\n";

/**
 * Convert various date formats to MySQL YYYY-MM-DD
 */
function convertDate($date)
{
    if (empty($date) || $date === '' || $date === 'NULL') {
        return null;
    }

    // Try different formats
    $formats = [
        'd-m-Y',    // 15-01-2032
        'm/d/Y',    // 10/8/2034
        'Y-m-d',    // 2025-12-31 (already correct)
        'd/m/Y',    // 15/01/2032
    ];

    foreach ($formats as $format) {
        $dateObj = DateTime::createFromFormat($format, $date);
        if ($dateObj && $dateObj->format($format) === $date) {
            return $dateObj->format('Y-m-d');
        }
    }

    // If all else fails, try strtotime
    $timestamp = strtotime($date);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }

    return null;
}

// Copy employees with date conversion
echo "Copying employees with date conversion...\n";
$rows = $sqlite->query("SELECT * FROM employees")->fetchAll(PDO::FETCH_ASSOC);

// Clear MySQL table
$mysql->exec("TRUNCATE TABLE `employees`");

// Date columns that need conversion
$dateColumns = ['qatar_id_expiry', 'hire_date', 'passport_expiry', 'visa_expiry', 'last_ticket_date', 'next_ticket_date'];

$successCount = 0;
$errorCount = 0;

foreach ($rows as $row) {
    // Convert date columns
    foreach ($dateColumns as $col) {
        if (isset($row[$col])) {
            $row[$col] = convertDate($row[$col]);
        }
    }

    // Prepare insert
    $columns = array_keys($row);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $columnList = '`' . implode('`, `', $columns) . '`';

    try {
        $stmt = $mysql->prepare("INSERT INTO `employees` ($columnList) VALUES ($placeholders)");
        $stmt->execute(array_values($row));
        $successCount++;
        echo ".";
    } catch (PDOException $e) {
        $errorCount++;
        echo "X";
        echo "\n  Error for employee '{$row['name']}': " . $e->getMessage() . "\n";
    }
}

echo "\n\nCopied $successCount / " . count($rows) . " employees\n";

if ($errorCount > 0) {
    echo "Errors: $errorCount\n";
}

// Verify
$sqliteCount = $sqlite->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$mysqlCount = $mysql->query("SELECT COUNT(*) FROM `employees`")->fetchColumn();

echo "\n=== Verification ===\n";
echo "SQLite employees: $sqliteCount\n";
echo "MySQL employees: $mysqlCount\n";

if ($sqliteCount == $mysqlCount) {
    echo "✅ All employees imported successfully!\n";
} else {
    echo "⚠️  Missing " . ($sqliteCount - $mysqlCount) . " employees\n";
}

// Show sample
echo "\n=== Sample Employees ===\n";
$sample = $mysql->query("SELECT id, name, employee_id, hire_date FROM employees LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($sample as $emp) {
    echo "ID: {$emp['id']}, Name: {$emp['name']}, EmpID: {$emp['employee_id']}, Hire: {$emp['hire_date']}\n";
}
