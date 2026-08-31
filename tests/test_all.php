<?php

include_once 'includes/db.php';

function test($description, $callback)
{
    echo "Testing: $description\n";
    $result = $callback();
    if ($result) {
        echo "Result: PASS\n\n";
    } else {
        echo "Result: FAIL\n\n";
    }
}

test('Clients CRUD', function () use ($pdo) {
    // Test Create
    $stmt = $pdo->prepare("INSERT INTO clients (name) VALUES (?)");
    $stmt->execute(['Test Client']);
    $id = $pdo->lastInsertId();
    if (!$id) return false;

    // Test Read
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    $client = $stmt->fetch();
    if ($client['name'] !== 'Test Client') return false;

    // Test Update
    $stmt = $pdo->prepare("UPDATE clients SET name = ? WHERE id = ?");
    $stmt->execute(['Test Client Updated', $id]);
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    $client = $stmt->fetch();
    if ($client['name'] !== 'Test Client Updated') return false;

    // Test Delete
    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetch()) return false;

    return true;
});


test('Vendors CRUD', function () use ($pdo) {
    // Test Create
    $stmt = $pdo->prepare("INSERT INTO vendors (name) VALUES (?)");
    $stmt->execute(['Test Vendor']);
    $id = $pdo->lastInsertId();
    if (!$id) return false;
    // Test Read
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$id]);
    $vendor = $stmt->fetch();
    if ($vendor['name'] !== 'Test Vendor') return false;
    // Test Update
    $stmt = $pdo->prepare("UPDATE vendors SET name = ? WHERE id = ?");
    $stmt->execute(['Test Vendor Updated', $id]);
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$id]);
    $vendor = $stmt->fetch();
    if ($vendor['name'] !== 'Test Vendor Updated') return false;
    // Test Delete
    $stmt = $pdo->prepare("DELETE FROM vendors WHERE id = ?");
    $stmt->execute([$id]);
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetch()) return false;
    return true;
});

test('Projects CRUD', function () use ($pdo) {
    // Test Create
    $stmt = $pdo->prepare("INSERT INTO projects (name) VALUES (?)");
    $stmt->execute(['Test Project']);
    $id = $pdo->lastInsertId();
    if (!$id) return false;
    // Test Read
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    if ($project['name'] !== 'Test Project') return false;
    // Test Update
    $stmt = $pdo->prepare("UPDATE projects SET name = ? WHERE id = ?");
    $stmt->execute(['Test Project Updated', $id]);
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    if ($project['name'] !== 'Test Project Updated') return false;
    // Test Delete
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetch()) return false;
    return true;
});


test('Employees CRUD', function () use ($pdo) {
    // Test Create
    $stmt = $pdo->prepare("INSERT INTO employees (name) VALUES (?)");
    $stmt->execute(['Test Employee']);
    $id = $pdo->lastInsertId();
    if (!$id) return false;
    // Test Read
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $employee = $stmt->fetch();
    if ($employee['name'] !== 'Test Employee') return false;
    // Test Update
    $stmt = $pdo->prepare("UPDATE employees SET name = ? WHERE id = ?");
    $stmt->execute(['Test Employee Updated', $id]);
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $employee = $stmt->fetch();
    if ($employee['name'] !== 'Test Employee Updated') return false;
    // Test Delete
    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetch()) return false;
    return true;
});

test('Outside Labours CRUD', function () use ($pdo) {
    // Test Create
    $stmt = $pdo->prepare("INSERT INTO outside_labours (name) VALUES (?)");
    $stmt->execute(['Test Labour']);
    $id = $pdo->lastInsertId();
    if (!$id) return false;
    // Test Read
    $stmt = $pdo->prepare("SELECT * FROM outside_labours WHERE id = ?");
    $stmt->execute([$id]);
    $labour = $stmt->fetch();
    if ($labour['name'] !== 'Test Labour') return false;
    // Test Update
    $stmt = $pdo->prepare("UPDATE outside_labours SET name = ? WHERE id = ?");
    $stmt->execute(['Test Labour Updated', $id]);
    $stmt = $pdo->prepare("SELECT * FROM outside_labours WHERE id = ?");
    $stmt->execute([$id]);
    $labour = $stmt->fetch();
    if ($labour['name'] !== 'Test Labour Updated') return false;
    // Test Delete
    $stmt = $pdo->prepare("DELETE FROM outside_labours WHERE id = ?");
    $stmt->execute([$id]);
    $stmt = $pdo->prepare("SELECT * FROM outside_labours WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetch()) return false;
    return true;
});


test('Vehicles CRUD', function () use ($pdo) {
    // Test Create
    $stmt = $pdo->prepare("INSERT INTO vehicles (name) VALUES (?)");
    $stmt->execute(['Test Vehicle']);
    $id = $pdo->lastInsertId();
    if (!$id) return false;

    // Test Read
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
    $stmt->execute([$id]);
    $vehicle = $stmt->fetch();
    if ($vehicle['name'] !== 'Test Vehicle') return false;

    // Test Update
    $stmt = $pdo->prepare("UPDATE vehicles SET name = ? WHERE id = ?");
    $stmt->execute(['Test Vehicle Updated', $id]);
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
    $stmt->execute([$id]);
    $vehicle = $stmt->fetch();
    if ($vehicle['name'] !== 'Test Vehicle Updated') return false;

    // Test Delete
    $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ?");
    $stmt->execute([$id]);
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetch()) return false;

    return true;
});