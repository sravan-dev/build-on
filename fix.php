<?php
/**
 * BuildOn Application - Live Server Database Fix Script
 * 
 * This script applies all necessary database schema fixes and updates
 * for MySQL compatibility. Run this once on the live server after deployment.
 * 
 * Usage: Upload this file to your server and access it via browser or run via CLI:
 *        php fix.php
 */

// Prevent direct access in production (remove this line to run)
// die("Remove this line to enable the fix script");

require_once 'includes/db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>BuildOn Database Fix</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 10px 0; }
        .section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #007bff; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 30px; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>🔧 BuildOn Database Fix Script</h1>
    <p><strong>Server Time:</strong> " . date('Y-m-d H:i:s') . "</p>
";

$errors = [];
$successes = [];

try {
    // Get database driver
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "<div class='info'>✓ Connected to database: <strong>$driver</strong></div>";

    if ($driver !== 'mysql') {
        echo "<div class='error'>⚠ Warning: This script is designed for MySQL. Current driver: $driver</div>";
    }

    // ========================================
    // FIX 0: Projects Table - Add Missing Columns
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 0: Projects Table - Add Missing Columns</h2>";

    try {
        // Check if projects table exists
        if ($driver === 'mysql') {
            $tableExists = $pdo->query("SHOW TABLES LIKE 'projects'")->fetch();
        } else {
            $tableExists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='projects'")->fetch();
        }

        if ($tableExists) {
            echo "<p>✓ Projects table exists</p>";

            // Get current columns
            if ($driver === 'mysql') {
                $currentCols = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $currentCols = $pdo->query("PRAGMA table_info(projects)")->fetchAll(PDO::FETCH_COLUMN, 1);
            }
            echo "<p>Current columns: " . implode(', ', $currentCols) . "</p>";

            // Define required columns for projects table
            $requiredProjectColumns = [
                'total_value' => "DECIMAL(15,2) DEFAULT 0",
                'start_date' => "DATE",
                'end_date' => "DATE",
                'status' => "VARCHAR(50) DEFAULT 'Active'",
                'created_at' => $driver === 'mysql' ? "TIMESTAMP DEFAULT CURRENT_TIMESTAMP" : "TEXT DEFAULT CURRENT_TIMESTAMP",
                'updated_at' => $driver === 'mysql' ? "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP" : "TEXT DEFAULT CURRENT_TIMESTAMP"
            ];

            $columnsAdded = 0;

            foreach ($requiredProjectColumns as $column => $definition) {
                if (!in_array($column, $currentCols)) {
                    echo "<p>Adding column: <strong>$column</strong>...</p>";
                    try {
                        $pdo->exec("ALTER TABLE projects ADD COLUMN $column $definition");
                        $successes[] = "Added column to projects: $column";
                        $columnsAdded++;
                        echo "<div class='success'>✓ Added $column to projects table</div>";
                    } catch (PDOException $e) {
                        $errors[] = "Failed to add $column to projects: " . $e->getMessage();
                        echo "<div class='error'>✗ Failed to add $column: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                }
            }

            if ($columnsAdded === 0) {
                echo "<p>✓ All required columns already exist in projects table</p>";
                $successes[] = "Projects table schema is complete";
            } else {
                echo "<div class='success'>✓ Added $columnsAdded column(s) to projects table</div>";
            }
        } else {
            echo "<div class='error'>✗ Projects table does not exist!</div>";
            $errors[] = "Projects table does not exist";
        }
    } catch (PDOException $e) {
        $errors[] = "Projects table fix failed: " . $e->getMessage();
        echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 1: Vehicles Table - Add All Missing Columns
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 1: Vehicles Table - Complete Schema</h2>";

    try {
        // Check if vehicles table exists (cross-database compatible)
        if ($driver === 'mysql') {
            $tableExists = $pdo->query("SHOW TABLES LIKE 'vehicles'")->fetch();
        } else {
            $tableExists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='vehicles'")->fetch();
        }

        if ($tableExists) {
            echo "<p>✓ Vehicles table exists</p>";

            // Get current columns (cross-database compatible)
            if ($driver === 'mysql') {
                $currentCols = $pdo->query("SHOW COLUMNS FROM vehicles")->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $currentCols = $pdo->query("PRAGMA table_info(vehicles)")->fetchAll(PDO::FETCH_COLUMN, 1);
            }
            echo "<p>Current columns: " . implode(', ', $currentCols) . "</p>";

            // Define required columns with their specifications
            $requiredColumns = [
                'vehicle_number' => "VARCHAR(50)",
                'model' => "VARCHAR(100)",
                'make' => "VARCHAR(100)",
                'year' => "INT",
                'chassis_number' => "VARCHAR(100)",
                'engine_number' => "VARCHAR(100)",
                'fuel_type' => "VARCHAR(50)",
                'assigned_driver' => "VARCHAR(100)",
                'registration_renewal_date' => "DATE",
                'insurance_renewal_date' => "DATE",
                'purchase_date' => "DATE",
                'purchase_price' => "DECIMAL(10,2)",
                'current_mileage' => "DECIMAL(10,2)",
                'vehicle_status' => "VARCHAR(50) DEFAULT 'Active'",
                'created_at' => $driver === 'mysql' ? "TIMESTAMP DEFAULT CURRENT_TIMESTAMP" : "TEXT DEFAULT CURRENT_TIMESTAMP",
                'updated_at' => $driver === 'mysql' ? "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP" : "TEXT DEFAULT CURRENT_TIMESTAMP"
            ];

            $columnsAdded = 0;

            // Check and add each missing column
            foreach ($requiredColumns as $column => $definition) {
                if (!in_array($column, $currentCols)) {
                    echo "<p>Adding column: <strong>$column</strong>...</p>";
                    try {
                        $pdo->exec("ALTER TABLE vehicles ADD COLUMN $column $definition");
                        $successes[] = "Added column: $column";
                        $columnsAdded++;
                        echo "<div class='success'>✓ Added $column</div>";
                    } catch (PDOException $e) {
                        $errors[] = "Failed to add $column: " . $e->getMessage();
                        echo "<div class='error'>✗ Failed to add $column: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                }
            }

            if ($columnsAdded === 0) {
                echo "<p>✓ All required columns already exist</p>";
                $successes[] = "Vehicles table schema is complete";
            } else {
                echo "<div class='success'>✓ Added $columnsAdded column(s) to vehicles table</div>";
            }

            // Show final schema (cross-database compatible)
            echo "<p><strong>Final vehicles table schema:</strong></p>";
            echo "<table>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";

            if ($driver === 'mysql') {
                $cols = $pdo->query("DESCRIBE vehicles")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $col) {
                    echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>" . ($col['Default'] ?? 'NULL') . "</td></tr>";
                }
            } else {
                $cols = $pdo->query("PRAGMA table_info(vehicles)")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $col) {
                    echo "<tr><td>{$col['name']}</td><td>{$col['type']}</td><td>" . ($col['notnull'] ? 'NO' : 'YES') . "</td><td>" . ($col['dflt_value'] ?? 'NULL') . "</td></tr>";
                }
            }
            echo "</table>";

        } else {
            echo "<div class='error'>✗ Vehicles table does not exist. Creating it now...</div>";

            // Create vehicles table with all required columns
            if ($driver === 'mysql') {
                $createTableSQL = "
                    CREATE TABLE vehicles (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        vehicle_number VARCHAR(50),
                        model VARCHAR(100),
                        make VARCHAR(100),
                        year INT,
                        chassis_number VARCHAR(100),
                        engine_number VARCHAR(100),
                        fuel_type VARCHAR(50),
                        assigned_driver VARCHAR(100),
                        registration_renewal_date DATE,
                        insurance_renewal_date DATE,
                        purchase_date DATE,
                        purchase_price DECIMAL(10,2),
                        current_mileage DECIMAL(10,2),
                        vehicle_status VARCHAR(50) DEFAULT 'Active',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ";
            } else {
                $createTableSQL = "
                    CREATE TABLE vehicles (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        vehicle_number TEXT,
                        model TEXT,
                        make TEXT,
                        year INTEGER,
                        chassis_number TEXT,
                        engine_number TEXT,
                        fuel_type TEXT,
                        assigned_driver TEXT,
                        registration_renewal_date TEXT,
                        insurance_renewal_date TEXT,
                        purchase_date TEXT,
                        purchase_price REAL,
                        current_mileage REAL,
                        vehicle_status TEXT DEFAULT 'Active',
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                    )
                ";
            }

            try {
                $pdo->exec($createTableSQL);
                $successes[] = "Created vehicles table with all required columns";
                echo "<div class='success'>✓ Successfully created vehicles table</div>";
            } catch (PDOException $e) {
                $errors[] = "Failed to create vehicles table: " . $e->getMessage();
                echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    } catch (PDOException $e) {
        $errors[] = "Vehicles table fix failed: " . $e->getMessage();
        echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 2: Create Missing Vehicle Tables
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 2: Create Missing Vehicle Tables</h2>";

    // Define all vehicle-related tables that need to exist
    $vehicleTableDefinitions = [
        'vehicle_daily_logs' => $driver === 'mysql' ? "
            CREATE TABLE IF NOT EXISTS vehicle_daily_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vehicle_id INT NOT NULL,
                log_date DATE NOT NULL,
                opening_km DECIMAL(10,2) NOT NULL,
                closing_km DECIMAL(10,2) NOT NULL,
                total_km DECIMAL(10,2) NOT NULL,
                driver_name VARCHAR(255) NULL,
                route_trip TEXT NULL,
                remarks TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY vehicle_date_unique (vehicle_id, log_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        " : "
            CREATE TABLE IF NOT EXISTS vehicle_daily_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                vehicle_id INTEGER NOT NULL,
                log_date TEXT NOT NULL,
                opening_km REAL NOT NULL,
                closing_km REAL NOT NULL,
                total_km REAL NOT NULL,
                driver_name TEXT,
                route_trip TEXT,
                remarks TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(vehicle_id, log_date)
            )
        ",
        'vehicle_expenses' => $driver === 'mysql' ? "
            CREATE TABLE IF NOT EXISTS vehicle_expenses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vehicle_id INT NOT NULL,
                expense_date DATE NOT NULL,
                expense_type VARCHAR(100) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                vendor_garage VARCHAR(255) NULL,
                invoice_number VARCHAR(100) NULL,
                description TEXT NULL,
                attachment_path VARCHAR(500) NULL,
                odometer_reading DECIMAL(10,2) NULL,
                paid_by VARCHAR(255) NULL,
                payment_method VARCHAR(100) DEFAULT 'company_cash',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        " : "
            CREATE TABLE IF NOT EXISTS vehicle_expenses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                vehicle_id INTEGER NOT NULL,
                expense_date TEXT NOT NULL,
                expense_type TEXT NOT NULL,
                amount REAL NOT NULL,
                vendor_garage TEXT,
                invoice_number TEXT,
                description TEXT,
                attachment_path TEXT,
                odometer_reading REAL,
                paid_by TEXT,
                payment_method TEXT DEFAULT 'company_cash',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ",
        'vehicle_fuel_records' => $driver === 'mysql' ? "
            CREATE TABLE IF NOT EXISTS vehicle_fuel_records (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vehicle_id INT NOT NULL,
                fuel_date DATE NOT NULL,
                liters DECIMAL(10,2) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                price_per_liter DECIMAL(10,2) NOT NULL,
                odometer_reading DECIMAL(10,2) NOT NULL,
                driver_name VARCHAR(255) NULL,
                fuel_station VARCHAR(255) NULL,
                mileage_km_per_liter DECIMAL(10,2) NULL,
                previous_odometer DECIMAL(10,2) NULL,
                paid_by VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        " : "
            CREATE TABLE IF NOT EXISTS vehicle_fuel_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                vehicle_id INTEGER NOT NULL,
                fuel_date TEXT NOT NULL,
                liters REAL NOT NULL,
                amount REAL NOT NULL,
                price_per_liter REAL NOT NULL,
                odometer_reading REAL NOT NULL,
                driver_name TEXT,
                fuel_station TEXT,
                mileage_km_per_liter REAL,
                previous_odometer REAL,
                paid_by TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ",
        'vehicle_maintenance' => $driver === 'mysql' ? "
            CREATE TABLE IF NOT EXISTS vehicle_maintenance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vehicle_id INT NOT NULL,
                service_date DATE NOT NULL,
                service_type VARCHAR(100) NOT NULL,
                details TEXT NULL,
                km_reading DECIMAL(10,2) NOT NULL,
                amount DECIMAL(10,2) DEFAULT 0,
                next_due_km DECIMAL(10,2) NULL,
                garage_name VARCHAR(255) NULL,
                invoice_number VARCHAR(100) NULL,
                attachment_path VARCHAR(500) NULL,
                paid_by VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        " : "
            CREATE TABLE IF NOT EXISTS vehicle_maintenance (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                vehicle_id INTEGER NOT NULL,
                service_date TEXT NOT NULL,
                service_type TEXT NOT NULL,
                details TEXT,
                km_reading REAL NOT NULL,
                amount REAL DEFAULT 0,
                next_due_km REAL,
                garage_name TEXT,
                invoice_number TEXT,
                attachment_path TEXT,
                paid_by TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ",
        'vehicle_alerts' => $driver === 'mysql' ? "
            CREATE TABLE IF NOT EXISTS vehicle_alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vehicle_id INT NOT NULL,
                alert_type VARCHAR(100) NOT NULL,
                alert_message TEXT NOT NULL,
                due_date DATE NULL,
                due_km DECIMAL(10,2) NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                dismissed_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        " : "
            CREATE TABLE IF NOT EXISTS vehicle_alerts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                vehicle_id INTEGER NOT NULL,
                alert_type TEXT NOT NULL,
                alert_message TEXT NOT NULL,
                due_date TEXT,
                due_km REAL,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                dismissed_at TEXT
            )
        ",
        'vehicle_income' => $driver === 'mysql' ? "
            CREATE TABLE IF NOT EXISTS vehicle_income (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vehicle_id INT NOT NULL,
                income_date DATE NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                description TEXT NULL,
                project_id INT NULL,
                invoice_number VARCHAR(100) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        " : "
            CREATE TABLE IF NOT EXISTS vehicle_income (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                vehicle_id INTEGER NOT NULL,
                income_date TEXT NOT NULL,
                amount REAL NOT NULL,
                description TEXT,
                project_id INTEGER,
                invoice_number TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        "
    ];

    echo "<p>Creating missing vehicle tables...</p>";
    $tablesCreated = 0;

    foreach ($vehicleTableDefinitions as $tableName => $createSQL) {
        // Check if table exists
        if ($driver === 'mysql') {
            $exists = $pdo->query("SHOW TABLES LIKE '$tableName'")->fetch();
        } else {
            $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$tableName'")->fetch();
        }

        if (!$exists) {
            echo "<p>Creating table: <strong>$tableName</strong>...</p>";
            try {
                $pdo->exec($createSQL);
                $successes[] = "Created table: $tableName";
                $tablesCreated++;
                echo "<div class='success'>✓ Created $tableName</div>";
            } catch (PDOException $e) {
                $errors[] = "Failed to create $tableName: " . $e->getMessage();
                echo "<div class='error'>✗ Failed to create $tableName: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            echo "<p style='color: green;'>✓ $tableName already exists</p>";
        }
    }

    if ($tablesCreated === 0) {
        echo "<p>✓ All vehicle tables already exist</p>";
        $successes[] = "All vehicle tables present";
    } else {
        echo "<div class='success'>✓ Created $tablesCreated vehicle table(s)</div>";
    }

    echo "</div>";

    // ========================================
    // FIX 3: Verify all required tables exist
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 3: Table Verification</h2>";

    $requiredTables = [
        'vehicles',
        'vehicle_daily_logs',
        'vehicle_expenses',
        'vehicle_fuel_records',
        'vehicle_maintenance',
        'vehicle_alerts',
        'employees',
        'clients',
        'vendors',
        'quotations',
        'invoices',
        'payments',
        'advance_payments',
        'daily_attendance'
    ];

    echo "<p>Verifying all required tables...</p>";
    $missingTables = [];

    foreach ($requiredTables as $table) {
        if ($driver === 'mysql') {
            $exists = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        } else {
            $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'")->fetch();
        }

        if ($exists) {
            echo "<p style='color: green;'>✓ $table</p>";
        } else {
            echo "<p style='color: red;'>✗ $table (MISSING)</p>";
            $missingTables[] = $table;
        }
    }

    if (empty($missingTables)) {
        $successes[] = "All required tables exist";
        echo "<div class='success'>✓ All " . count($requiredTables) . " required tables verified!</div>";
    } else {
        echo "<div class='error'>⚠ " . count($missingTables) . " tables are still missing: " . implode(', ', $missingTables) . "</div>";
        $errors[] = "Missing tables: " . implode(', ', $missingTables);
    }
    echo "</div>";

    // ========================================
    // FIX 4: Database Statistics
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 4: Database Statistics</h2>";

    try {
        // Count records in key tables
        $stats = [];
        $tablesToCount = ['employees', 'clients', 'vendors', 'vehicles'];

        foreach ($tablesToCount as $table) {
            try {
                $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
                $stats[$table] = $count;
            } catch (PDOException $e) {
                $stats[$table] = 'N/A';
            }
        }

        echo "<table>";
        echo "<tr><th>Table</th><th>Record Count</th></tr>";
        foreach ($stats as $table => $count) {
            echo "<tr><td>$table</td><td>$count</td></tr>";
        }
        echo "</table>";

        $successes[] = "Database statistics retrieved";
    } catch (PDOException $e) {
        echo "<div class='error'>Could not retrieve statistics: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 5: Date Function Compatibility Test
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 5: Date Function Compatibility Test</h2>";

    try {
        // Test date functions (cross-database compatible)
        if ($driver === 'mysql') {
            $testDate = $pdo->query("SELECT DATE_FORMAT(NOW(), '%Y-%m') as test_date")->fetch();
            echo "<p>✓ MySQL DATE_FORMAT working: " . htmlspecialchars($testDate['test_date']) . "</p>";
        } else {
            $testDate = $pdo->query("SELECT strftime('%Y-%m', 'now') as test_date")->fetch();
            echo "<p>✓ SQLite strftime working: " . htmlspecialchars($testDate['test_date']) . "</p>";
        }
        $successes[] = "Date functions working correctly";
    } catch (PDOException $e) {
        $errors[] = "Date function test failed: " . $e->getMessage();
        echo "<div class='error'>✗ Date function error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 6: Users Table - Check Role Column
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 6: Users Table - Check Role Column</h2>";
    try {
        if ($driver === 'mysql') {
            $cols = $pdo->query("SHOW COLUMNS FROM users WHERE Field = 'role'")->fetch(PDO::FETCH_ASSOC);
            echo "<p>Role column type: " . htmlspecialchars($cols['Type']) . "</p>";
            // If it's an ENUM and doesn't contain 'driver', we would need to alter it. 
            // Often it's safer to use VARCHAR(50).
            if (strpos(strtoupper($cols['Type']), 'ENUM') !== false && strpos($cols['Type'], "'driver'") === false) {
                 echo "<p>Role is ENUM and missing 'driver'. Converting to VARCHAR...</p>";
                 $pdo->exec("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'employee'");
                 echo "<div class='success'>✓ Converted role column to VARCHAR(50)</div>";
                 $successes[] = "Updated users role column to VARCHAR";
            } elseif (strpos(strtoupper($cols['Type']), 'ENUM') !== false) {
                 echo "<p>Role is ENUM. Current values: " . htmlspecialchars($cols['Type']) . "</p>";
            } else {
                 echo "<p>✓ Role column is compatible (VARCHAR or TEXT)</p>";
            }
        }
    } catch (PDOException $e) {
        $errors[] = "Users table check failed: " . $e->getMessage();
        echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 7: Active Users Columns
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 7: Active Users - Add `last_active` Column</h2>";
    try {
        $tables = ['users', 'employees'];
        foreach ($tables as $table) {
            // Check if column exists
            $colExists = false;
            if ($driver === 'mysql') {
                $check = $pdo->query("SHOW COLUMNS FROM $table LIKE 'last_active'");
                $colExists = ($check->rowCount() > 0);
            } else {
                $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $col) {
                    if ($col['name'] === 'last_active') {
                        $colExists = true;
                        break;
                    }
                }
            }

            if (!$colExists) {
                echo "<p>Adding `last_active` to <strong>$table</strong>...</p>";
                $sql = "ALTER TABLE $table ADD COLUMN last_active " . ($driver === 'mysql' ? "DATETIME" : "TEXT") . " DEFAULT NULL";
                $pdo->exec($sql);
                echo "<div class='success'>✓ Added `last_active` to $table</div>";
                $successes[] = "Added last_active to $table";
            } else {
                echo "<p>✓ `last_active` already exists in $table</p>";
            }
        }
    } catch (PDOException $e) {
        $errors[] = "Active Users fix failed: " . $e->getMessage();
        echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 8: General Ledger Accounts
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 8: General Ledger - Seed Mandatory Accounts</h2>";
    try {
        // Ensure accounts table exists first
        if ($driver === 'mysql') {
            $pdo->query("CREATE TABLE IF NOT EXISTS accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_code VARCHAR(50) NOT NULL,
                account_name VARCHAR(100) NOT NULL,
                account_type VARCHAR(50) NOT NULL,
                parent_id INT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            $pdo->query("CREATE TABLE IF NOT EXISTS accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_code TEXT NOT NULL,
                account_name TEXT NOT NULL,
                account_type TEXT NOT NULL,
                parent_id INTEGER,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )");
        }

        $mandatory_accounts = [
            // Assets
            ['code' => '1001', 'name' => 'Cash', 'type' => 'asset'],
            ['code' => '1002', 'name' => 'Bank – Company Account', 'type' => 'asset'],
            // Liabilities
            ['code' => '2100', 'name' => 'Credit Card Payable', 'type' => 'liability'],
            ['code' => '2201', 'name' => 'Rahees – Cash', 'type' => 'liability'],
            ['code' => '2202', 'name' => 'Rahees – Card', 'type' => 'liability'],
            ['code' => '2203', 'name' => 'Salman – Cash', 'type' => 'liability'],
            ['code' => '2204', 'name' => 'Salman – Card', 'type' => 'liability'],
            // Equity
            ['code' => '3000', 'name' => 'Opening Balance Adjustment', 'type' => 'equity'],
             // Expense Accounts
            ['code' => '5001', 'name' => 'Transport', 'type' => 'expense'],
            ['code' => '5002', 'name' => 'Food', 'type' => 'expense'],
            ['code' => '5003', 'name' => 'Labor', 'type' => 'expense'],
            ['code' => '5004', 'name' => 'Materials', 'type' => 'expense'],
            ['code' => '5005', 'name' => 'Equipment', 'type' => 'expense'],
            ['code' => '5006', 'name' => 'Communication', 'type' => 'expense'],
            ['code' => '5007', 'name' => 'Office Supplies', 'type' => 'expense'],
            ['code' => '5008', 'name' => 'Utilities', 'type' => 'expense'],
            ['code' => '5011', 'name' => 'Maintenance', 'type' => 'expense'], 
            ['code' => '5012', 'name' => 'Miscellaneous', 'type' => 'expense'],
            // Vehicle Specific
            ['code' => '5100', 'name' => 'Fuel', 'type' => 'expense'],
            ['code' => '5101', 'name' => 'Oil Change', 'type' => 'expense'],
            ['code' => '5102', 'name' => 'Tyres', 'type' => 'expense'],
            ['code' => '5103', 'name' => 'Battery', 'type' => 'expense'],
            ['code' => '5104', 'name' => 'Repair', 'type' => 'expense'], // Maintenance already exists but Repair is specific
            ['code' => '5105', 'name' => 'Registration Renewal', 'type' => 'expense'],
            ['code' => '5106', 'name' => 'Insurance Renewal', 'type' => 'expense'],
            ['code' => '5107', 'name' => 'Fines', 'type' => 'expense'],
            ['code' => '5108', 'name' => 'Washing', 'type' => 'expense'],
            ['code' => '5109', 'name' => 'Service', 'type' => 'expense'],
        ];

        $accountsSeeded = 0;
        foreach ($mandatory_accounts as $acc) {
            $stmt = $pdo->prepare("SELECT id FROM accounts WHERE account_code = ?");
            $stmt->execute([$acc['code']]);
            $existing = $stmt->fetch();

            if (!$existing) {
                // Double check by name to avoid duplicates if code changed
                $stmt = $pdo->prepare("SELECT id FROM accounts WHERE account_name = ?");
                $stmt->execute([$acc['name']]);
                $existingByName = $stmt->fetch();
                
                if ($existingByName) {
                     // Update code if name matches
                     $upd = $pdo->prepare("UPDATE accounts SET account_code = ?, account_type = ? WHERE id = ?");
                     $upd->execute([$acc['code'], $acc['type'], $existingByName['id']]);
                     echo "<p>Updated account code for: <strong>{$acc['name']}</strong></p>";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO accounts (account_code, account_name, account_type, is_active) VALUES (?, ?, ?, 1)");
                    $stmt->execute([$acc['code'], $acc['name'], $acc['type']]);
                    echo "<p>✓ Created account: <strong>{$acc['name']}</strong></p>";
                    $accountsSeeded++;
                }
            } else {
                // Update name/type to ensure consistency
                $stmt = $pdo->prepare("UPDATE accounts SET account_name = ?, account_type = ? WHERE id = ?");
                $stmt->execute([$acc['name'], $acc['type'], $existing['id']]);
            }
        }
        
        if ($accountsSeeded > 0) {
            echo "<div class='success'>✓ Seeded $accountsSeeded new GL accounts</div>";
            $successes[] = "Seeded $accountsSeeded GL accounts";
        } else {
            echo "<p>✓ All mandatory GL accounts exist</p>";
        }

    } catch (PDOException $e) {
        $errors[] = "GL Account seed failed: " . $e->getMessage();
        echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 9: Clear Transaction Data (Optional)
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 9: Clear Transaction Data</h2>";
    
    // Check for clear trigger
    if (isset($_GET['action']) && $_GET['action'] === 'clear_transactions') {
        echo "<p>Clearing transaction data...</p>";
        
        $tables_to_clear = [
            'expense_related' => ['vehicle_expenses', 'vehicle_fuel_records', 'vehicle_maintenance', 'vehicle_daily_logs', 'expenses', 'vendor_payments', 'purchase_payments', 'labour_payments'],
            'purchase_related' => ['purchases', 'purchase_items', 'purchase_payments'],
            'ledger_related' => ['vouchers', 'voucher_entries']
        ];
        
        // Flatten list
        $all_clear_tables = array_merge($tables_to_clear['expense_related'], $tables_to_clear['purchase_related'], $tables_to_clear['ledger_related']);
        $all_clear_tables = array_unique($all_clear_tables);

        foreach ($all_clear_tables as $table) {
            try {
                // Determine driver for TRUNCATE vs DELETE
                if ($driver === 'mysql') {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                    $pdo->exec("TRUNCATE TABLE $table");
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                } else {
                    $pdo->exec("DELETE FROM $table");
                    $pdo->exec("DELETE FROM sqlite_sequence WHERE name='$table'"); // Reset auto-increment
                }
                echo "<div class='success'>✓ Cleared table: $table</div>";
            } catch (PDOException $e) {
                // If table doesn't exist, it's fine
                // echo "<div class='error'>could not clear $table: " . $e->getMessage() . "</div>";
            }
        }
        $successes[] = "Cleared transaction data";
        echo "<p><strong>Transaction data wiped successfully.</strong> <a href='fix.php'>Reload Script</a></p>";
    } else {
        echo "<div class='info'>";
        echo "<p>⚠ <strong>Warning:</strong> This will delete ALL data from Expenses, Purchases, Vehicle Logs, and General Ledger.</p>";
        echo "<p>Only use this if you want to reset the system for a fresh start.</p>";
        echo "<a href='?action=clear_transactions' onclick=\"return confirm('Are you sure you want to DELETE ALL TRANSACTION DATA? This cannot be undone.');\" style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>🗑 Clear All Transaction Data</a>";
        echo "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 10: Correct Vouchers Schema
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 10: Verify Vouchers Schema</h2>";
    try {
        if ($driver === 'mysql') {
            // Check paid_to_received_from type
            $col = $pdo->query("SHOW FIELDS FROM vouchers LIKE 'paid_to_received_from'")->fetch();
            if (!$col) {
                 echo "<p>Adding missing `paid_to_received_from` column...</p>";
                 $pdo->exec("ALTER TABLE vouchers ADD COLUMN paid_to_received_from VARCHAR(255) NULL");
                 echo "<div class='success'>✓ Added `paid_to_received_from`</div>";
            } elseif (stripos($col['Type'], 'varchar') === false && stripos($col['Type'], 'text') === false) {
                 echo "<p>Fixing `paid_to_received_from` column type (Current: {$col['Type']})...</p>";
                 $pdo->exec("ALTER TABLE vouchers MODIFY paid_to_received_from VARCHAR(255) NULL");
                 echo "<div class='success'>✓ Converted `paid_to_received_from` to VARCHAR(255)</div>";
            } else {
                echo "<p>✓ `paid_to_received_from` is correct type</p>";
            }

            // Check amount_in_words type
             $col = $pdo->query("SHOW FIELDS FROM vouchers LIKE 'amount_in_words'")->fetch();
             if (!$col) {
                 echo "<p>Adding missing `amount_in_words` column...</p>";
                 $pdo->exec("ALTER TABLE vouchers ADD COLUMN amount_in_words VARCHAR(255) NULL");
                 echo "<div class='success'>✓ Added `amount_in_words`</div>";
             } elseif (stripos($col['Type'], 'varchar') === false && stripos($col['Type'], 'text') === false) {
                 echo "<p>Fixing `amount_in_words` column type (Current: {$col['Type']})...</p>";
                 $pdo->exec("ALTER TABLE vouchers MODIFY amount_in_words VARCHAR(255) NULL");
                 echo "<div class='success'>✓ Converted `amount_in_words` to VARCHAR(255)</div>";
            } else {
                 echo "<p>✓ `amount_in_words` is correct type</p>";
            }
        } else {
             // SQLite ALTER COLUMN is limited, usually requires recreation. 
             // Since we are likely on MySQL in production (based on error), we focus on MySQL fix.
             echo "<p>SQLite detected - schema updates are manual.</p>";
        }
    } catch (PDOException $e) {
        $errors[] = "Schema fix failed: " . $e->getMessage();
        echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 11: Vehicle Expenses Schema
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 11: Vehicle Expenses Schema</h2>";
    try {
        if ($driver === 'mysql') {
            $col = $pdo->query("SHOW FIELDS FROM vehicle_expenses LIKE 'payment_method'")->fetch();
            if (!$col) {
                 echo "<p>Adding `payment_method` column to `vehicle_expenses`...</p>";
                 $pdo->exec("ALTER TABLE vehicle_expenses ADD COLUMN payment_method VARCHAR(100) DEFAULT 'company_cash'");
                 echo "<div class='success'>✓ Added `payment_method` to vehicle_expenses</div>";
            } else {
                 echo "<p>✓ `payment_method` exists in vehicle_expenses</p>";
            }
        } else {
             // SQLite check
            $cols = $pdo->query("PRAGMA table_info(vehicle_expenses)")->fetchAll(PDO::FETCH_ASSOC);
            $hasCol = false;
            foreach ($cols as $c) {
                if ($c['name'] === 'payment_method') { $hasCol = true; break; }
            }
            if (!$hasCol) {
                $pdo->exec("ALTER TABLE vehicle_expenses ADD COLUMN payment_method TEXT DEFAULT 'company_cash'");
                echo "<div class='success'>✓ Added `payment_method` to vehicle_expenses</div>";
            } else {
                echo "<p>✓ `payment_method` exists in vehicle_expenses</p>";
            }
        }
    } catch (PDOException $e) {
        $errors[] = "Vehicle Expense schema fix failed: " . $e->getMessage();
        echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 12: Renewal Payments Schema
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 12: Renewal Payments Schema</h2>";
    try {
        if ($driver === 'mysql') {
            $col = $pdo->query("SHOW FIELDS FROM renewal_payments LIKE 'payment_method'")->fetch();
            if (!$col) {
                 echo "<p>Adding `payment_method` column to `renewal_payments`...</p>";
                 $pdo->exec("ALTER TABLE renewal_payments ADD COLUMN payment_method VARCHAR(50) DEFAULT 'Cheque'");
                 echo "<div class='success'>✓ Added `payment_method` to renewal_payments</div>";
            } else {
                 echo "<p>✓ `payment_method` exists in renewal_payments</p>";
            }
        } else {
             // SQLite check
            $cols = $pdo->query("PRAGMA table_info(renewal_payments)")->fetchAll(PDO::FETCH_ASSOC);
            $hasCol = false;
            foreach ($cols as $c) {
                if ($c['name'] === 'payment_method') { $hasCol = true; break; }
            }
            if (!$hasCol) {
                // Check if table exists first
                $tableExists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='renewal_payments'")->fetch();
                if ($tableExists) {
                    $pdo->exec("ALTER TABLE renewal_payments ADD COLUMN payment_method TEXT DEFAULT 'Cheque'");
                    echo "<div class='success'>✓ Added `payment_method` to renewal_payments</div>";
                } else {
                    echo "<div class='error'>✗ renewal_payments table missing</div>";
                }
            } else {
                echo "<p>✓ `payment_method` exists in renewal_payments</p>";
            }
        }
    } catch (PDOException $e) {
        $errors[] = "Renewal Payments schema fix failed: " . $e->getMessage();
        echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 13: Login Activity Table
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 13: Login Activity Table</h2>";
    try {
        $loginTableSQL = $driver === 'mysql' ? "
            CREATE TABLE IF NOT EXISTS login_activity (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                user_type VARCHAR(20),
                username VARCHAR(100),
                login_time DATETIME,
                ip_address VARCHAR(45),
                user_agent VARCHAR(255),
                status VARCHAR(20),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        " : "
            CREATE TABLE IF NOT EXISTS login_activity (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                user_type TEXT,
                username TEXT,
                login_time TEXT,
                ip_address TEXT,
                user_agent TEXT,
                status TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ";

        // Check if table exists
        if ($driver === 'mysql') {
            $exists = $pdo->query("SHOW TABLES LIKE 'login_activity'")->fetch();
        } else {
            $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='login_activity'")->fetch();
        }

        if (!$exists) {
            $pdo->exec($loginTableSQL);
            echo "<div class='success'>✓ Created login_activity table</div>";
            $successes[] = "Created login_activity table";
        } else {
            echo "<p>✓ login_activity table already exists</p>";
        }

    } catch (PDOException $e) {
        $errors[] = "Login Activity table fix failed: " . $e->getMessage();
        echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 14: Payroll History Table
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 14: Payroll History Table</h2>";
    try {
        $payrollTableSQL = $driver === 'mysql' ? "
            CREATE TABLE IF NOT EXISTS payroll_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                month INT NOT NULL,
                year INT NOT NULL,
                total_hours DECIMAL(10,2) DEFAULT 0,
                hourly_rate DECIMAL(10,2) DEFAULT 0,
                basic_salary DECIMAL(10,2) DEFAULT 0,
                total_allowance DECIMAL(10,2) DEFAULT 0,
                total_deduction DECIMAL(10,2) DEFAULT 0,
                net_salary DECIMAL(10,2) NOT NULL,
                status VARCHAR(50) DEFAULT 'Processed',
                processed_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY emp_month_year (employee_id, month, year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        " : "
            CREATE TABLE IF NOT EXISTS payroll_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL,
                month INTEGER NOT NULL,
                year INTEGER NOT NULL,
                total_hours REAL DEFAULT 0,
                hourly_rate REAL DEFAULT 0,
                basic_salary REAL DEFAULT 0,
                total_allowance REAL DEFAULT 0,
                total_deduction REAL DEFAULT 0,
                net_salary REAL NOT NULL,
                status TEXT DEFAULT 'Processed',
                processed_date TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(employee_id, month, year)
            )
        ";

        if ($driver === 'mysql') {
            $exists = $pdo->query("SHOW TABLES LIKE 'payroll_history'")->fetch();
        } else {
            $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='payroll_history'")->fetch();
        }

        if (!$exists) {
            $pdo->exec($payrollTableSQL);
            echo "<div class='success'>✓ Created payroll_history table</div>";
            $successes[] = "Created payroll_history table";
        } else {
            echo "<p>✓ payroll_history table already exists</p>";
        }
    } catch (PDOException $e) {
        $errors[] = "Payroll History table fix failed: " . $e->getMessage();
        echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 15: Salary Payments & Advance Payments
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 15: Salary Payments & Advance Payments</h2>";
    try {
        // Create salary_payments
        $sqlSalary = $driver === 'mysql' ? "
             CREATE TABLE IF NOT EXISTS salary_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                payment_date DATE NOT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                payment_method VARCHAR(50) NOT NULL,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        " : "
             CREATE TABLE IF NOT EXISTS salary_payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL,
                payment_date DATE NOT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                payment_method VARCHAR(50) NOT NULL,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
            )
        ";
        $pdo->exec($sqlSalary);
        echo "<div class='success'>✓ salary_payments table check/create done</div>";
        
        // Add payment_method to advance_payments
        $table = 'advance_payments';
        $col = 'payment_method';
        $colExists = false;
        if ($driver === 'mysql') {
            $check = $pdo->query("SHOW COLUMNS FROM $table LIKE '$col'");
            $colExists = ($check->rowCount() > 0);
        } else {
            $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $c) {
                if ($c['name'] === $col) {
                    $colExists = true;
                    break;
                }
            }
        }
        if (!$colExists) {
             $pdo->exec("ALTER TABLE $table ADD COLUMN $col VARCHAR(50)");
             echo "<div class='success'>✓ Added $col to $table</div>";
             $successes[] = "Added $col to $table";
        } else {
             echo "<p>✓ $col already exists in $table</p>";
        }

    } catch (PDOException $e) {
        $errors[] = "Fix 15 Error: " . $e->getMessage();
        echo "<div class='error'>✗ Fix 15 Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 16: Employee Payment Method
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 16: Employee Payment Method Column</h2>";
    try {
        $table = 'employees';
        $col = 'payment_method';
        $colExists = false;
        
        if ($driver === 'mysql') {
            $check = $pdo->query("SHOW COLUMNS FROM $table LIKE '$col'");
            $colExists = ($check->rowCount() > 0);
        } else {
            $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $c) {
                if ($c['name'] === $col) {
                    $colExists = true;
                    break;
                }
            }
        }
        
        if (!$colExists) {
             $pdo->exec("ALTER TABLE $table ADD COLUMN $col VARCHAR(50) DEFAULT 'company_bank'");
             echo "<div class='success'>✓ Added $col to $table</div>";
             $successes[] = "Added $col to $table";
        } else {
             echo "<p>✓ $col already exists in $table</p>";
        }

    } catch (PDOException $e) {
        $errors[] = "Fix 16 Error: " . $e->getMessage();
        echo "<div class='error'>✗ Fix 16 Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 17: Payments Table - Card ID Column
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 17: Payments Table - Card ID Column</h2>";
    try {
        $table = 'payments';
        $col = 'card_id';
        $colExists = false;
        
        if ($driver === 'mysql') {
            $check = $pdo->query("SHOW COLUMNS FROM $table LIKE '$col'");
            $colExists = ($check->rowCount() > 0);
        } else {
            $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $c) {
                if ($c['name'] === $col) {
                    $colExists = true;
                    break;
                }
            }
        }
        
        if (!$colExists) {
             if ($driver === 'mysql') {
                 $pdo->exec("ALTER TABLE $table ADD COLUMN $col INT NULL AFTER payment_method");
                 // Try to add foreign key, but don't fail if credit_cards table doesn't exist
                 try {
                     $pdo->exec("ALTER TABLE $table ADD FOREIGN KEY (card_id) REFERENCES credit_cards(id) ON DELETE SET NULL");
                     echo "<div class='success'>✓ Added $col to $table with foreign key</div>";
                 } catch (PDOException $e) {
                     echo "<div class='success'>✓ Added $col to $table (foreign key skipped)</div>";
                 }
             } else {
                 $pdo->exec("ALTER TABLE $table ADD COLUMN $col INTEGER NULL");
                 echo "<div class='success'>✓ Added $col to $table</div>";
             }
             $successes[] = "Added $col to $table";
        } else {
             echo "<p>✓ $col already exists in $table</p>";
        }

    } catch (PDOException $e) {
        $errors[] = "Fix 17 Error: " . $e->getMessage();
        echo "<div class='error'>✗ Fix 17 Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    echo "</div>";

    // ========================================
    // FIX 17: Vouchers Table - Reference Column
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 17: Vouchers Table - Reference Column</h2>";
    try {
        $table = 'vouchers';
        $col = 'reference';
        $colExists = false;
        
        if ($driver === 'mysql') {
            $check = $pdo->query("SHOW COLUMNS FROM $table LIKE '$col'");
            $colExists = ($check->rowCount() > 0);
        } else {
            $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $c) {
                if ($c['name'] === $col) {
                    $colExists = true;
                    break;
                }
            }
        }
        
        if (!$colExists) {
             $pdo->exec("ALTER TABLE $table ADD COLUMN $col VARCHAR(100) DEFAULT NULL");
             echo "<div class='success'>✓ Added $col to $table</div>";
             $successes[] = "Added $col to $table";
        } else {
             echo "<p>✓ $col already exists in $table</p>";
        }

    } catch (PDOException $e) {
        $errors[] = "Fix 17 Error: " . $e->getMessage();
        echo "<div class='error'>✗ Fix 17 Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 17.6: Vehicle Expenses - Paid By Column Type
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 17.6: Vehicle Expenses - Paid By Column Type</h2>";
    try {
        if ($driver === 'mysql') {
            // Check if paid_by column exists and its type
            $col = $pdo->query("SHOW FIELDS FROM vehicle_expenses LIKE 'paid_by'")->fetch();
            if ($col) {
                // Check if it's the wrong type (DECIMAL instead of VARCHAR)
                if (stripos($col['Type'], 'decimal') !== false || stripos($col['Type'], 'int') !== false) {
                    echo "<p>Fixing `paid_by` column type (Current: {$col['Type']})...</p>";
                    $pdo->exec("ALTER TABLE vehicle_expenses MODIFY paid_by VARCHAR(255) NULL");
                    echo "<div class='success'>✓ Converted `paid_by` to VARCHAR(255)</div>";
                    $successes[] = "Fixed paid_by column type in vehicle_expenses";
                } else {
                    echo "<p>✓ `paid_by` is correct type</p>";
                }
            } else {
                echo "<p>Adding missing `paid_by` column...</p>";
                $pdo->exec("ALTER TABLE vehicle_expenses ADD COLUMN paid_by VARCHAR(255) NULL");
                echo "<div class='success'>✓ Added `paid_by` column</div>";
            }
        } else {
            // SQLite - check if column exists
            $cols = $pdo->query("PRAGMA table_info(vehicle_expenses)")->fetchAll(PDO::FETCH_ASSOC);
            $hasCol = false;
            foreach ($cols as $c) {
                if ($c['name'] === 'paid_by') {
                    $hasCol = true;
                    break;
                }
            }
            if (!$hasCol) {
                $pdo->exec("ALTER TABLE vehicle_expenses ADD COLUMN paid_by TEXT NULL");
                echo "<div class='success'>✓ Added `paid_by` column</div>";
            } else {
                echo "<p>✓ `paid_by` column exists</p>";
            }
        }
    } catch (PDOException $e) {
        $errors[] = "Fix 17.6 Error: " . $e->getMessage();
        echo "<div class='error'>✗ Fix 17.6 Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // FIX 18: Backfill Missing GL Vouchers
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 18: Backfill Missing GL Vouchers</h2>";
    try {
        // We need to check if payments exist without vouchers
        // Logic adapted from our backfill script
        
        // 1. Get all payments
        $payments = $pdo->query("SELECT p.id, p.invoice_id, p.amount, p.date, p.payment_method, p.notes, 
                                        i.client_id, c.name as client_name 
                                 FROM payments p 
                                 JOIN invoices i ON p.invoice_id = i.id 
                                 LEFT JOIN clients c ON i.client_id = c.id 
                                 ORDER BY p.id ASC")->fetchAll();
        
        $count = 0;
        $created = 0;
        $skipped = 0;

        foreach ($payments as $payment) {
            $count++;
            $payment_id = $payment['id'];
            $ref = "PAY-{$payment_id}";
            
            // 2. Check existence
            $stmt = $pdo->prepare("SELECT id FROM vouchers WHERE reference = ?");
            $stmt->execute([$ref]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $skipped++;
                continue;
            }

            // 3. Determine Accounts (Simplified logic for fix script)
            $debit_account = '';
            $pm = $payment['payment_method'];
            if (stripos($pm, 'company_cash') !== false || stripos($pm, 'cash') !== false) {
                 $debit_account = 'Cash';
            } else {
                 $debit_account = 'Bank - Company Account'; // Default fallback
            }
            $credit_account = 'Sales Revenue';

            // 4. Generate Voucher No
            // We can't use helper function easily if not included, so inline it
            $stmtNum = $pdo->query("SELECT MAX(CAST(SUBSTR(voucher_no, 2) AS UNSIGNED)) as max_num FROM vouchers WHERE voucher_no LIKE 'V%'");
            $resNum = $stmtNum->fetch();
            $next_num = ($resNum['max_num'] ?? 0) + 1;
            $voucher_no = 'V' . str_pad($next_num, 4, '0', STR_PAD_LEFT);

            $amount = $payment['amount'];
            $desc = "Invoice Payment - INV#{$payment['invoice_id']} - {$payment['client_name']}";
            if (!empty($payment['notes'])) $desc .= " - " . $payment['notes'];

            // 5. Insert
            $stmtins = $pdo->prepare("INSERT INTO vouchers (voucher_no, voucher_date, paid_to_received_from, amount, description, status, voucher_type, reference) VALUES (?, ?, ?, ?, ?, 'posted', 'receipt', ?)");
            $stmtins->execute([$voucher_no, $payment['date'], $payment['client_name'], $amount, $desc, $ref]);
            $voucher_id = $pdo->lastInsertId();

            // Entries
            $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, ?, 0, ?)")
                ->execute([$voucher_id, $debit_account, $amount, $desc]);
            
            $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, 0, ?, ?)")
                ->execute([$voucher_id, $credit_account, $amount, $desc]);

            $created++;
            echo "<p>Created Voucher $voucher_no for Payment #$payment_id</p>";
        }
        
        if ($created > 0) {
            echo "<div class='success'>✓ Generated $created missing vouchers (Skipped $skipped existing)</div>";
            $successes[] = "Backfilled $created GL vouchers";
        } else {
            echo "<p>✓ All payments already have vouchers</p>";
        }

    } catch (Exception $e) {
         $errors[] = "Fix 18 Error: " . $e->getMessage();
         echo "<div class='error'>✗ Fix 18 Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    echo "</div>";

    // ========================================
    // FIX 19: Credit Card Transactions - Payment Method Column
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 19: Credit Card Transactions - Payment Method Column</h2>";
    try {
        $table = 'credit_card_transactions';
        $col = 'payment_method';
        $colExists = false;
        
        if ($driver === 'mysql') {
            $check = $pdo->query("SHOW COLUMNS FROM $table LIKE '$col'");
            $colExists = ($check->rowCount() > 0);
        } else {
            $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $c) {
                if ($c['name'] === $col) {
                    $colExists = true;
                    break;
                }
            }
        }
        
        if (!$colExists) {
             $pdo->exec("ALTER TABLE $table ADD COLUMN $col VARCHAR(50) DEFAULT NULL");
             echo "<div class='success'>✓ Added $col to $table</div>";
             $successes[] = "Added $col to $table";
        } else {
             echo "<p>✓ $col already exists in $table</p>";
        }

    } catch (PDOException $e) {
        $errors[] = "Fix 19 Error: " . $e->getMessage();
        echo "<div class='error'>✗ Fix 19 Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    echo "</div>";

    // ========================================
    // FIX 20: Backfill Missing Payroll GL Vouchers
    // ========================================
    echo "<div class='section'>";
    echo "<h2>Fix 20: Backfill Missing Payroll GL Vouchers</h2>";
    try {
        $created = 0;
        $skipped = 0;

        // 1. Backfill Advance Payments
        $advances = $pdo->query("SELECT ap.*, e.name as employee_name 
                                 FROM advance_payments ap 
                                 JOIN employees e ON ap.employee_id = e.id 
                                 ORDER BY ap.id ASC")->fetchAll();
        
        foreach ($advances as $adv) {
            $ref = "ADV-PAY-{$adv['id']}";
            $existing = $pdo->prepare("SELECT id FROM vouchers WHERE reference = ?");
            $existing->execute([$ref]);
            
            if ($existing->fetch()) {
                $skipped++;
                continue;
            }

            // Simple inline creation logic
            $credit_account = ($adv['payment_method'] === 'company_cash') ? 'Cash' : 'Bank – Company Account';
            $debit_account = 'Labor'; 

            $stmtNum = $pdo->query("SELECT MAX(CAST(SUBSTR(voucher_no, 2) AS UNSIGNED)) as max_num FROM vouchers WHERE voucher_no LIKE 'V%'");
            $resNum = $stmtNum->fetch();
            $next_num = ($resNum['max_num'] ?? 0) + 1;
            $voucher_no = 'V' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
            
            $desc = "Advance Payment - {$adv['employee_name']}" . (!empty($adv['reason']) ? " - " . $adv['reason'] : "");

            $stmt = $pdo->prepare("INSERT INTO vouchers (voucher_no, voucher_date, paid_to_received_from, amount, description, status, voucher_type, reference) VALUES (?, ?, ?, ?, ?, 'posted', 'payment', ?)");
            $stmt->execute([$voucher_no, $adv['payment_date'], $adv['employee_name'], $adv['amount'], $desc, $ref]);
            $voucher_id = $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, ?, 0, ?)")->execute([$voucher_id, $debit_account, $adv['amount'], $desc]);
            $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, 0, ?, ?)")->execute([$voucher_id, $credit_account, $adv['amount'], $desc]);
            
            $created++;
            echo "<p>Created Voucher $voucher_no for Advance Payment #{$adv['id']}</p>";
        }

        // 2. Backfill Salary Payments
        $salaries = $pdo->query("SELECT sp.*, e.name as employee_name 
                                 FROM salary_payments sp 
                                 JOIN employees e ON sp.employee_id = e.id 
                                 ORDER BY sp.id ASC")->fetchAll();
        
        foreach ($salaries as $sal) {
            $ref = "SAL-PAY-{$sal['id']}";
            $existing = $pdo->prepare("SELECT id FROM vouchers WHERE reference = ?");
            $existing->execute([$ref]);
            
            if ($existing->fetch()) {
                $skipped++;
                continue;
            }

            $credit_account = ($sal['payment_method'] === 'company_cash') ? 'Cash' : 'Bank – Company Account';
            $debit_account = 'Labor';

            $stmtNum = $pdo->query("SELECT MAX(CAST(SUBSTR(voucher_no, 2) AS UNSIGNED)) as max_num FROM vouchers WHERE voucher_no LIKE 'V%'");
            $resNum = $stmtNum->fetch();
            $next_num = ($resNum['max_num'] ?? 0) + 1;
            $voucher_no = 'V' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
            
            $desc = "Salary Payment - {$sal['employee_name']} - " . date('M Y', strtotime($sal['payment_date'])) . (!empty($sal['notes']) ? " - " . $sal['notes'] : "");

            $stmt = $pdo->prepare("INSERT INTO vouchers (voucher_no, voucher_date, paid_to_received_from, amount, description, status, voucher_type, reference) VALUES (?, ?, ?, ?, ?, 'posted', 'payment', ?)");
            $stmt->execute([$voucher_no, $sal['payment_date'], $sal['employee_name'], $sal['amount'], $desc, $ref]);
            $voucher_id = $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, ?, 0, ?)")->execute([$voucher_id, $debit_account, $sal['amount'], $desc]);
            $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, 0, ?, ?)")->execute([$voucher_id, $credit_account, $sal['amount'], $desc]);
            
            $created++;
            echo "<p>Created Voucher $voucher_no for Salary Payment #{$sal['id']}</p>";
        }

        if ($created > 0) {
            echo "<div class='success'>✓ Generated $created payroll vouchers (Skipped $skipped existing)</div>";
            $successes[] = "Backfilled $created payroll vouchers";
        } else {
            echo "<p>✓ All payroll payments already have vouchers</p>";
        }

    } catch (Exception $e) {
        $errors[] = "Fix 20 Error: " . $e->getMessage();
        echo "<div class='error'>✗ Fix 20 Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    echo "</div>";

    // ========================================
    // SUMMARY
    // ========================================
    echo "<div class='section'>";
    echo "<h2>📊 Summary</h2>";

    if (!empty($successes)) {
        echo "<div class='success'>";
        echo "<strong>✓ Successful Operations (" . count($successes) . "):</strong><ul>";
        foreach ($successes as $success) {
            echo "<li>$success</li>";
        }
        echo "</ul></div>";
    }

    if (!empty($errors)) {
        echo "<div class='error'>";
        echo "<strong>✗ Errors (" . count($errors) . "):</strong><ul>";
        foreach ($errors as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul></div>";
    }

    if (empty($errors)) {
        echo "<div class='success'>";
        echo "<h3>✅ All Fixes Applied Successfully!</h3>";
        echo "<p>Your database is now ready. You can safely delete this fix.php file.</p>";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<h3>⚠ Some Issues Require Attention</h3>";
        echo "<p>Please review the errors above and fix them manually.</p>";
        echo "</div>";
    }

    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>Fatal Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "
    <hr>
    <p style='text-align: center; color: #666; margin-top: 30px;'>
        <strong>Important:</strong> After successful completion, delete this fix.php file for security.<br>
        <small>Script completed at: " . date('Y-m-d H:i:s') . "</small>
    </p>
</body>
</html>";
?>