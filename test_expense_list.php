<?php
// Test expense_list.php query with error display
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db.php';

echo "Testing expense_list.php query...\n\n";

// Simulate the query from expense_list.php
$project_filter = $_GET['project'] ?? '';
$type_filter = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_conditions = [];
$params = [];

if ($project_filter) {
    $where_conditions[] = "e.project_id = ?";
    $params[] = $project_filter;
}

if ($type_filter) {
    $where_conditions[] = "e.expense_type = ?";
    $params[] = $type_filter;
}

if ($date_from) {
    $where_conditions[] = "e.date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "e.date <= ?";
    $params[] = $date_to;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

echo "WHERE clause: '$where_clause'\n";
echo "Parameters: " . json_encode($params) . "\n\n";

try {
    $sql = "SELECT e.*, p.name as project_name 
            FROM expenses e 
            LEFT JOIN projects p ON e.project_id = p.id 
            $where_clause 
            ORDER BY e.date DESC, e.id DESC";

    echo "SQL Query:\n$sql\n\n";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Results found: " . count($expenses) . "\n\n";

    if (count($expenses) > 0) {
        echo "Sample data:\n";
        foreach ($expenses as $idx => $exp) {
            echo "  Record " . ($idx + 1) . ": ID={$exp['id']}, Amount={$exp['amount']}, Desc={$exp['description']}\n";
        }
    } else {
        echo "No records returned!\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
