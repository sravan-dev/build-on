<?php
require_once 'includes/db.php';

echo "=== DATABASE TABLES ===\n\n";

// Get all tables
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "TABLE: $table\n";
    echo str_repeat("-", 50) . "\n";

    // Get columns for each table
    $columns = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        $nullable = $col['notnull'] == 0 ? 'NULL' : 'NOT NULL';
        $default = $col['dflt_value'] ? " DEFAULT {$col['dflt_value']}" : '';
        $pk = $col['pk'] ? ' PRIMARY KEY' : '';
        echo "  - {$col['name']} ({$col['type']}) $nullable$default$pk\n";
    }

    echo "\n";
}

echo "\n=== TOTAL TABLES: " . count($tables) . " ===\n";
