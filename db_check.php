<?php
/**
 * Diagnostic script to see database structure
 * DELETE IMMEDIATELY AFTER USE
 */

header('Content-Type: application/json');
require_once 'includes/db.php';

try {
    $result = [];

    // Get all columns from employees table (MySQL)
    $cols = $pdo->query("SHOW COLUMNS FROM employees")->fetchAll(PDO::FETCH_ASSOC);
    $result['columns'] = array_column($cols, 'Field');

    // Get first 5 employees (only non-sensitive fields)
    $employees = $pdo->query("SELECT * FROM employees LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);

    // Show sample data structure (hide actual values for sensitive fields)
    $sample = [];
    if (!empty($employees)) {
        foreach ($employees[0] as $key => $value) {
            // Show key and type, not actual value for security
            $sample[$key] = gettype($value) . ' (length: ' . strlen($value ?? '') . ')';
        }
    }
    $result['sample_structure'] = $sample;

    // Show first 3 employee names/IDs only
    $result['employees'] = [];
    foreach ($employees as $emp) {
        $result['employees'][] = [
            'id' => $emp['id'] ?? null,
            'name' => $emp['name'] ?? $emp['employee_name'] ?? $emp['full_name'] ?? 'N/A'
        ];
    }

    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
