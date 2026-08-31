<?php
/**
 * Labour Cost Tracking System - Database Migration
 * 
 * This script adds all necessary fields for labour cost tracking:
 * - Employee salary and rate fields
 * - Attendance project tracking and cost calculation
 * 
 * Run this on both local and live servers
 */

require_once __DIR__ . '/../includes/db.php';

echo "=== Labour Cost Tracking - Database Migration ===\n\n";

$errors = [];
$successes = [];

try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "Database Driver: $driver\n\n";

    // ========================================
    // STEP 1: Update Employees Table
    // ========================================
    echo "STEP 1: Updating Employees Table...\n";

    if ($driver === 'mysql') {
        // Check if columns exist
        $columns = $pdo->query("SHOW COLUMNS FROM employees")->fetchAll(PDO::FETCH_COLUMN);

        // Add basic_salary
        if (!in_array('basic_salary', $columns)) {
            echo "  - Adding basic_salary column...\n";
            $pdo->exec("ALTER TABLE employees ADD COLUMN basic_salary DECIMAL(10,2) DEFAULT 0");
            $successes[] = "Added basic_salary column";
        } else {
            echo "  ✓ basic_salary already exists\n";
        }

        // Add employee_charge
        if (!in_array('employee_charge', $columns)) {
            echo "  - Adding employee_charge column...\n";
            $pdo->exec("ALTER TABLE employees ADD COLUMN employee_charge DECIMAL(10,2) DEFAULT 0 COMMENT 'Monthly cost (visa, insurance, accommodation)'");
            $successes[] = "Added employee_charge column";
        } else {
            echo "  ✓ employee_charge already exists\n";
        }

        // Note: We'll calculate rates in PHP instead of generated columns for better compatibility
        echo "  ✓ Employees table updated\n\n";

    } else {
        // SQLite
        $columns = $pdo->query("PRAGMA table_info(employees)")->fetchAll(PDO::FETCH_COLUMN, 1);

        if (!in_array('basic_salary', $columns)) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN basic_salary REAL DEFAULT 0");
            $successes[] = "Added basic_salary column (SQLite)";
        }

        if (!in_array('employee_charge', $columns)) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN employee_charge REAL DEFAULT 0");
            $successes[] = "Added employee_charge column (SQLite)";
        }
    }

    // ========================================
    // STEP 2: Update Daily Attendance Table
    // ========================================
    echo "STEP 2: Updating Daily Attendance Table...\n";

    if ($driver === 'mysql') {
        $columns = $pdo->query("SHOW COLUMNS FROM daily_attendance")->fetchAll(PDO::FETCH_COLUMN);

        // Add project_id
        if (!in_array('project_id', $columns)) {
            echo "  - Adding project_id column...\n";
            $pdo->exec("ALTER TABLE daily_attendance ADD COLUMN project_id INT AFTER employee_id");
            $pdo->exec("ALTER TABLE daily_attendance ADD FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE SET NULL");
            $successes[] = "Added project_id column";
        } else {
            echo "  ✓ project_id already exists\n";
        }

        // Add total_hours
        if (!in_array('total_hours', $columns)) {
            echo "  - Adding total_hours column...\n";
            $pdo->exec("ALTER TABLE daily_attendance ADD COLUMN total_hours DECIMAL(5,2) DEFAULT 0");
            $successes[] = "Added total_hours column";
        } else {
            echo "  ✓ total_hours already exists\n";
        }

        // Add overtime_hours
        if (!in_array('overtime_hours', $columns)) {
            echo "  - Adding overtime_hours column...\n";
            $pdo->exec("ALTER TABLE daily_attendance ADD COLUMN overtime_hours DECIMAL(5,2) DEFAULT 0");
            $successes[] = "Added overtime_hours column";
        } else {
            echo "  ✓ overtime_hours already exists\n";
        }

        // Add labour_cost
        if (!in_array('labour_cost', $columns)) {
            echo "  - Adding labour_cost column...\n";
            $pdo->exec("ALTER TABLE daily_attendance ADD COLUMN labour_cost DECIMAL(10,2) DEFAULT 0");
            $successes[] = "Added labour_cost column";
        } else {
            echo "  ✓ labour_cost already exists\n";
        }

        // Add approved_by
        if (!in_array('approved_by', $columns)) {
            echo "  - Adding approved_by column...\n";
            $pdo->exec("ALTER TABLE daily_attendance ADD COLUMN approved_by VARCHAR(100)");
            $successes[] = "Added approved_by column";
        } else {
            echo "  ✓ approved_by already exists\n";
        }

        // Add work_site
        if (!in_array('work_site', $columns)) {
            echo "  - Adding work_site column...\n";
            $pdo->exec("ALTER TABLE daily_attendance ADD COLUMN work_site VARCHAR(100)");
            $successes[] = "Added work_site column";
        } else {
            echo "  ✓ work_site already exists\n";
        }

        echo "  ✓ Daily attendance table updated\n\n";

    } else {
        // SQLite
        $columns = $pdo->query("PRAGMA table_info(daily_attendance)")->fetchAll(PDO::FETCH_COLUMN, 1);

        if (!in_array('project_id', $columns)) {
            $pdo->exec("ALTER TABLE daily_attendance ADD COLUMN project_id INTEGER");
        }
        if (!in_array('total_hours', $columns)) {
            $pdo->exec("ALTER TABLE daily_attendance ADD COLUMN total_hours REAL DEFAULT 0");
        }
        if (!in_array('overtime_hours', $columns)) {
            $pdo->exec("ALTER TABLE daily_attendance ADD COLUMN overtime_hours REAL DEFAULT 0");
        }
        if (!in_array('labour_cost', $columns)) {
            $pdo->exec("ALTER TABLE daily_attendance ADD COLUMN labour_cost REAL DEFAULT 0");
        }
        if (!in_array('approved_by', $columns)) {
            $pdo->exec("ALTER TABLE daily_attendance ADD COLUMN approved_by TEXT");
        }
    }

    // ========================================
    // STEP 3: Verification
    // ========================================
    echo "STEP 3: Verification...\n";

    // Verify employees table
    $empCount = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    echo "  ✓ Employees table: $empCount records\n";

    // Verify attendance table
    $attCount = $pdo->query("SELECT COUNT(*) FROM daily_attendance")->fetchColumn();
    echo "  ✓ Daily attendance table: $attCount records\n";

    echo "\n=== Migration Completed Successfully ===\n";
    echo "Total changes: " . count($successes) . "\n";
    foreach ($successes as $success) {
        echo "  ✓ $success\n";
    }

    if (count($errors) > 0) {
        echo "\nWarnings:\n";
        foreach ($errors as $error) {
            echo "  ⚠ $error\n";
        }
    }

    echo "\nNext Steps:\n";
    echo "1. Update employees.php to add salary fields\n";
    echo "2. Update attendance.php to add project tracking\n";
    echo "3. Update projects.php to include labour costs in profit calculation\n";

} catch (PDOException $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
