<?php

require_once dirname(__DIR__) . '/includes/db.php';

$sql = file_get_contents(__DIR__ . '/schema.sql');

try {

    $pdo->exec($sql);

    echo "Database initialized successfully.";

} catch (PDOException $e) {

    die("Error initializing database: " . $e->getMessage());

}