<?php
header('Content-Type: application/json');
require_once '../includes/dbcon.php';

try {
    $pdo = getPDOConnection();
    $stmt = $pdo->query("
        SELECT id, name, description, price, category, rating, image_url as image, offer_tag as offerTag, stock_quantity
        FROM products 
        WHERE status = 'active'
        ORDER BY id
    ");
    $products = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'products' => $products]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>