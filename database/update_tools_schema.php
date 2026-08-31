<?php
include_once __DIR__ . '/../includes/db.php';

echo "Creating tools and inventory tables...\n";

// Tools Master Table
$sql_tools = "CREATE TABLE IF NOT EXISTS tools (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    category TEXT,
    serial_number TEXT,
    purchase_date TEXT,
    supplier TEXT,
    cost REAL DEFAULT 0,
    warranty_expiry TEXT,
    status TEXT DEFAULT 'in_store', -- in_store, issued, damaged, lost, scrapped
    assigned_to INTEGER, -- Current holder (Employee ID)
    image_path TEXT,
    notes TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(assigned_to) REFERENCES employees(id)
)";

// Tool Assignment History Table
$sql_assignments = "CREATE TABLE IF NOT EXISTS tool_assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tool_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    assigned_date TEXT DEFAULT CURRENT_TIMESTAMP,
    returned_date TEXT, -- NULL if currently assigned
    notes TEXT,
    condition_on_issue TEXT,
    condition_on_return TEXT,
    FOREIGN KEY(tool_id) REFERENCES tools(id),
    FOREIGN KEY(employee_id) REFERENCES employees(id)
)";

try {
    $pdo->exec($sql_tools);
    echo "Created table: tools\n";
    $pdo->exec($sql_assignments);
    echo "Created table: tool_assignments\n";
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage() . "\n";
}

echo "Database update complete.\n";
?>