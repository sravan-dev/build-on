<?php
require_once 'includes/db.php';

$stmt = $pdo->query('PRAGMA table_info(clients)');
echo "Clients table columns:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['name'] . "\n";
}
