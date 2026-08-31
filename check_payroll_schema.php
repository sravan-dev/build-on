<?php
include_once 'includes/db.php';

echo "<h2>Table Schema Check</h2>";

function check_table($pdo, $table) {
    echo "<h3>Table: $table</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($cols as $col) {
            echo "<tr>";
            foreach ($col as $k => $v) {
                echo "<td>$v</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}

check_table($pdo, 'salary_payments');
check_table($pdo, 'advance_payments');
?>
