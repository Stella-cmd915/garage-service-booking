<?php
header('Content-Type: application/json');

require_once '../config/database.php';

$customer_id = $_GET['customer_id'] ?? '';

if (empty($customer_id) || !is_numeric($customer_id)) {
    echo json_encode(['error' => 'Invalid customer ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, brand, model, serial_number 
        FROM vehicles 
        WHERE customer_id = ? 
        ORDER BY brand, model
    ");
    $stmt->execute([$customer_id]);
    $vehicles = $stmt->fetchAll();
    
    echo json_encode(['vehicles' => $vehicles]);
} catch (Exception $e) {
    error_log("Error loading vehicles: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>