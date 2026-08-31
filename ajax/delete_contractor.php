<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

include_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_contractor') {
    try {
        $contractor_id = intval($_POST['contractor_id']);
        
        // Check if contractor is used in any subcontracts
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM subcontracts WHERE contractor_id = ?");
        $stmt->execute([$contractor_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Cannot delete contractor. It is being used in ' . $result['count'] . ' subcontract(s).'
            ]);
            exit;
        }
        
        // Delete contractor
        $stmt = $pdo->prepare("DELETE FROM contractors WHERE id = ?");
        $stmt->execute([$contractor_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Contractor deleted successfully'
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
