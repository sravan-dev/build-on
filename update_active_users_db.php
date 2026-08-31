<?php
require_once 'includes/db.php';

function addColumnIfNotExists($pdo, $table, $column, $type) {
    try {
        $stmt = $pdo->query("PRAGMA table_info($table)");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array($column, $columns)) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $type");
            echo "Added $column to $table\n";
        } else {
            echo "Column $column already exists in $table\n";
        }
    } catch (Exception $e) {
        echo "Error updating $table: " . $e->getMessage() . "\n";
    }
}

addColumnIfNotExists($pdo, 'users', 'last_active', 'DATETIME');
addColumnIfNotExists($pdo, 'employees', 'last_active', 'DATETIME');

echo "Database update complete.\n";
