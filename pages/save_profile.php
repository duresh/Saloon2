<?php
session_start();
require_once '../includes/dbcon.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login']);
    exit;
}

$user_id = $_SESSION['user_id'];
$phone = $_POST['phone'] ?? '';
$shipping_address = $_POST['shipping_address'] ?? '';

// Validate
if (empty($phone) && empty($shipping_address)) {
    echo json_encode(['success' => false, 'message' => 'No data to save']);
    exit;
}

try {
    $pdo = getPDOConnection();
    
    // Check if user profile exists
    $stmt = $pdo->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing profile
        $sql = "UPDATE user_profiles SET ";
        $params = [];
        $updates = [];
        
        if (!empty($phone)) {
            $updates[] = "phone = ?";
            $params[] = $phone;
        }
        if (!empty($shipping_address)) {
            $updates[] = "shipping_address = ?";
            $params[] = $shipping_address;
        }
        
        $sql .= implode(", ", $updates) . " WHERE user_id = ?";
        $params[] = $user_id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        // Insert new profile
        $stmt = $pdo->prepare("
            INSERT INTO user_profiles (user_id, phone, shipping_address) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user_id, $phone, $shipping_address]);
    }
    
    // Also update reg table contact number
    if (!empty($phone)) {
        $stmt = $pdo->prepare("UPDATE reg SET contactNo = ? WHERE regID = ?");
        $stmt->execute([$phone, $user_id]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Save profile error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error saving profile: ' . $e->getMessage()
    ]);
}
?>