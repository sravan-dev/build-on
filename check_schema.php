<?php
include_once 'includes/db.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM vouchers");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    print_r($columns);
    
    // Also check if 'type' column exists because I used it too
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
