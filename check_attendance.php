<?php
/**
 * Check attendance table - DELETE AFTER USE
 */
header('Content-Type: application/json');
require_once 'includes/db.php';

try {
    $result = [];

    // Check if daily_attendance table exists
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM daily_attendance")->fetchAll(PDO::FETCH_ASSOC);
        $result['table_exists'] = true;
        $result['columns'] = array_column($cols, 'Field');
    } catch (Exception $e) {
        $result['table_exists'] = false;
        $result['error'] = $e->getMessage();

        // Create the table
        $result['creating_table'] = true;
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS daily_attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                attendance_date DATE NOT NULL,
                in_time TIME NULL,
                out_time TIME NULL,
                working_hours DECIMAL(5,2) NULL,
                status ENUM('present','absent','leave','half_day') DEFAULT 'present',
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_attendance (employee_id, attendance_date)
            )
        ");
        $result['table_created'] = true;
    }

    // Show recent attendance records
    $records = $pdo->query("SELECT * FROM daily_attendance ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $result['recent_records'] = $records;
    $result['total_count'] = $pdo->query("SELECT COUNT(*) FROM daily_attendance")->fetchColumn();

    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}