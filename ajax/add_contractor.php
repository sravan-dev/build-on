<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

include_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_contractor') {
    try {
        $stmt = $pdo->prepare("INSERT INTO contractors (company_name, phone_number, email, address, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['company_name'],
            $_POST['phone_number'] ?? null,
            $_POST['email'] ?? null,
            $_POST['address'] ?? null,
            $_POST['notes'] ?? null
        ]);
        
        $contractor_id = $pdo->lastInsertId();
        
        // Fetch the newly created contractor
        $stmt = $pdo->prepare("SELECT id, company_name, phone_number FROM contractors WHERE id = ?");
        $stmt->execute([$contractor_id]);
        $contractor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Contractor added successfully',
            'contractor' => $contractor
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
