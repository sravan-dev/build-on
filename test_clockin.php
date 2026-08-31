<?php
/**
 * Test clock-in API - DELETE AFTER USE
 */
header('Content-Type: application/json');
require_once 'includes/db.php';

try {
    $result = [];

    // Get first employee with password
    $stmt = $pdo->query("SELECT id, name, emp_id FROM employees WHERE password IS NOT NULL AND password != '' LIMIT 1");
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        echo json_encode(['error' => 'No employee with password found']);
        exit;
    }

    $result['employee'] = $employee;
    $date = date('Y-m-d');
    $time = date('H:i:s');
    $result['date'] = $date;
    $result['time'] = $time;

    // Check existing attendance for today
    $stmt = $pdo->prepare("SELECT * FROM daily_attendance WHERE employee_id = ? AND attendance_date = ? LIMIT 1");
    $stmt->execute([$employee['id'], $date]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    $result['existing_record'] = $existing ?: 'None';

    if ($existing && $existing['in_time']) {
        $result['status'] = 'Already clocked in today';
    } else {
        // Try to insert/update
        if ($existing) {
            $stmt = $pdo->prepare("UPDATE daily_attendance SET in_time = ? WHERE id = ?");
            $res = $stmt->execute([$time, $existing['id']]);
            $result['action'] = 'Updated existing record';
        } else {
            $stmt = $pdo->prepare("INSERT INTO daily_attendance (employee_id, attendance_date, in_time, status) VALUES (?, ?, ?, 'present')");
            $res = $stmt->execute([$employee['id'], $date, $time]);
            $result['action'] = 'Inserted new record';
        }
        $result['success'] = $res;

        // Verify
        $stmt = $pdo->prepare("SELECT * FROM daily_attendance WHERE employee_id = ? AND attendance_date = ?");
        $stmt->execute([$employee['id'], $date]);
        $result['new_record'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
