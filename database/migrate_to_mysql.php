<?php
/**
 * SQLite to MySQL Migration Script
 * Exports SQLite schema and data to MySQL-compatible format
 */

require_once __DIR__ . '/../includes/functions.php';
loadEnv(__DIR__ . '/../.env');

// Connect to SQLite
$sqlitePath = __DIR__ . '/../buildon.sqlite';
$sqlite = new PDO('sqlite:' . $sqlitePath);
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== SQLite to MySQL Migration ===\n\n";

// Get all tables
$tables = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$mysqlSchema = "-- MySQL Database Schema\n";
$mysqlSchema .= "-- Generated from SQLite: " . date('Y-m-d H:i:s') . "\n\n";
$mysqlSchema .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

$mysqlData = "-- MySQL Data Import\n";
$mysqlData .= "-- Generated from SQLite: " . date('Y-m-d H:i:s') . "\n\n";
$mysqlData .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    echo "Processing table: $table\n";

    // Get table schema
    $columns = $sqlite->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);

    // Start CREATE TABLE
    $mysqlSchema .= "DROP TABLE IF EXISTS `$table`;\n";
    $mysqlSchema .= "CREATE TABLE `$table` (\n";

    $columnDefs = [];
    $primaryKey = null;

    foreach ($columns as $col) {
        $name = $col['name'];
        $type = strtoupper($col['type']);
        $notnull = $col['notnull'] ? 'NOT NULL' : 'NULL';
        $default = $col['dflt_value'] ? "DEFAULT {$col['dflt_value']}" : '';

        // Convert SQLite types to MySQL types
        $mysqlType = convertTypeToMySQL($type, $name);

        // Handle primary key
        if ($col['pk']) {
            $primaryKey = $name;
            if ($mysqlType === 'INT') {
                $columnDefs[] = "  `$name` $mysqlType AUTO_INCREMENT PRIMARY KEY";
            } else {
                $columnDefs[] = "  `$name` $mysqlType PRIMARY KEY";
            }
        } else {
            $columnDefs[] = "  `$name` $mysqlType $notnull $default";
        }
    }

    $mysqlSchema .= implode(",\n", $columnDefs);
    $mysqlSchema .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

    // Export data
    $rows = $sqlite->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 0) {
        $mysqlData .= "-- Data for table `$table`\n";

        foreach ($rows as $row) {
            $cols = array_keys($row);
            $values = array_map(function ($val) use ($sqlite) {
                if ($val === null)
                    return 'NULL';
                return $sqlite->quote($val);
            }, array_values($row));

            $mysqlData .= "INSERT INTO `$table` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $values) . ");\n";
        }

        $mysqlData .= "\n";
    }

    echo "  - Exported " . count($rows) . " rows\n";
}

$mysqlSchema .= "SET FOREIGN_KEY_CHECKS=1;\n";
$mysqlData .= "SET FOREIGN_KEY_CHECKS=1;\n";

// Save to files
file_put_contents(__DIR__ . '/mysql_schema.sql', $mysqlSchema);
file_put_contents(__DIR__ . '/mysql_data.sql', $mysqlData);

echo "\n=== Migration Complete ===\n";
echo "Schema saved to: database/mysql_schema.sql\n";
echo "Data saved to: database/mysql_data.sql\n";
echo "\nNext steps:\n";
echo "1. Create MySQL database: CREATE DATABASE buildon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
echo "2. Import schema: mysql -u root -p buildon < database/mysql_schema.sql\n";
echo "3. Import data: mysql -u root -p buildon < database/mysql_data.sql\n";

/**
 * Convert SQLite data types to MySQL equivalents
 */
function convertTypeToMySQL($sqliteType, $columnName)
{
    $type = strtoupper($sqliteType);

    // Currency/money fields
    if (
        strpos($columnName, 'amount') !== false ||
        strpos($columnName, 'price') !== false ||
        strpos($columnName, 'salary') !== false ||
        strpos($columnName, 'rate') !== false ||
        strpos($columnName, 'allowance') !== false ||
        strpos($columnName, 'balance') !== false ||
        strpos($columnName, 'total') !== false ||
        strpos($columnName, 'paid') !== false ||
        strpos($columnName, 'invoice') !== false ||
        strpos($columnName, 'business') !== false
    ) {
        return 'DECIMAL(10,2)';
    }

    // Map SQLite types to MySQL
    if (strpos($type, 'INT') !== false) {
        return 'INT';
    } elseif (strpos($type, 'REAL') !== false || strpos($type, 'FLOAT') !== false || strpos($type, 'DOUBLE') !== false) {
        return 'DECIMAL(10,2)';
    } elseif (strpos($type, 'DECIMAL') !== false) {
        return $type;
    } elseif (strpos($type, 'DATETIME') !== false) {
        return 'DATETIME';
    } elseif (strpos($type, 'DATE') !== false) {
        return 'DATE';
    } elseif (strpos($type, 'TIME') !== false) {
        return 'TIME';
    } elseif (strpos($type, 'TEXT') !== false || strpos($type, 'CLOB') !== false) {
        return 'TEXT';
    } elseif (strpos($type, 'BLOB') !== false) {
        return 'BLOB';
    } else {
        // Default to VARCHAR for unknown types
        return 'VARCHAR(255)';
    }
}
