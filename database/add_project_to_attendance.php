<?php
require_once dirname(__DIR__) . '/includes/db.php';

try {
    // Add project_id column to attendance table
    $pdo->exec("ALTER TABLE attendance ADD COLUMN project_id INTEGER");
    
    // Add foreign key constraint
    $pdo->exec("CREATE INDEX idx_attendance_project ON attendance(project_id)");
    
    echo "Successfully added project_id column to attendance table.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
