<?php
// Migration to create labour_payments table and update projects table
require_once dirname(__DIR__) . '/includes/db.php';

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

echo "Starting Labour Payment System Migration...\n\n";

// Create labour_payments table
try {
    if ($driver === 'mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS labour_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voucher_no VARCHAR(50) NOT NULL,
            labour_id INT NOT NULL,
            project_id INT NOT NULL,
            paid_amount DECIMAL(10,2) NOT NULL,
            payment_date DATE NOT NULL,
            payment_mode VARCHAR(50) DEFAULT 'cash',
            remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(labour_id) REFERENCES outside_labours(id) ON DELETE RESTRICT,
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS labour_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            voucher_no TEXT NOT NULL,
            labour_id INTEGER NOT NULL,
            project_id INTEGER NOT NULL,
            paid_amount REAL NOT NULL,
            payment_date TEXT NOT NULL,
            payment_mode TEXT DEFAULT 'cash',
            remarks TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(labour_id) REFERENCES outside_labours(id),
            FOREIGN KEY(project_id) REFERENCES projects(id)
        )");
    }
    echo "✓ Created labour_payments table\n";
} catch (Exception $e) {
    echo "labour_payments table may already exist: " . $e->getMessage() . "\n";
}

// Add total_project_amount column to projects table
try {
    if ($driver === 'mysql') {
        $check = $pdo->query("SHOW COLUMNS FROM projects LIKE 'total_project_amount'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN total_project_amount DECIMAL(12,2) DEFAULT 0");
            echo "✓ Added total_project_amount to projects\n";
        } else {
            echo "- total_project_amount already exists\n";
        }
    } else {
        $pdo->exec("ALTER TABLE projects ADD COLUMN total_project_amount REAL DEFAULT 0");
        echo "✓ Added total_project_amount to projects\n";
    }
} catch (Exception $e) {
    echo "total_project_amount may already exist\n";
}

// Add total_labour_cost column to projects table
try {
    if ($driver === 'mysql') {
        $check = $pdo->query("SHOW COLUMNS FROM projects LIKE 'total_labour_cost'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN total_labour_cost DECIMAL(12,2) DEFAULT 0");
            echo "✓ Added total_labour_cost to projects\n";
        } else {
            echo "- total_labour_cost already exists\n";
        }
    } else {
        $pdo->exec("ALTER TABLE projects ADD COLUMN total_labour_cost REAL DEFAULT 0");
        echo "✓ Added total_labour_cost to projects\n";
    }
} catch (Exception $e) {
    echo "total_labour_cost may already exist\n";
}

// Add working_days column to outside_labours table
try {
    if ($driver === 'mysql') {
        $check = $pdo->query("SHOW COLUMNS FROM outside_labours LIKE 'working_days'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE outside_labours ADD COLUMN working_days DECIMAL(5,1) DEFAULT 0");
            echo "✓ Added working_days to outside_labours\n";
        } else {
            echo "- working_days already exists\n";
        }
    } else {
        $pdo->exec("ALTER TABLE outside_labours ADD COLUMN working_days REAL DEFAULT 0");
        echo "✓ Added working_days to outside_labours\n";
    }
} catch (Exception $e) {
    echo "working_days may already exist\n";
}

echo "\n✓ Migration complete!\n";
