<?php
require_once __DIR__ . '/../includes/db.php';

echo "Current columns in employees table:\n";
echo "=====================================\n";
$cols = $pdo->query('PRAGMA table_info(employees)')->fetchAll();
foreach ($cols as $c) {
    echo $c['name'] . " (" . $c['type'] . ")\n";
}
