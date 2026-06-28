<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id'])) {
    require_once '../includes/dbcon.php';
    
    try {
        $pdo = getPDOConnection();
        $stmt = $pdo->prepare("SELECT regID, fName, lName, email, contactNo, role FROM reg WHERE regID = ? AND cStatus = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo json_encode([
                'logged_in' => true,
                'user' => [
                    'regID' => $user['regID'],
                    'fName' => $user['fName'],
                    'lName' => $user['lName'],
                    'name' => $user['fName'] . ' ' . $user['lName'],
                    'email' => $user['email'],
                    'contactNo' => $user['contactNo'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            echo json_encode(['logged_in' => false]);
        }
    } catch (Exception $e) {
        echo json_encode(['logged_in' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['logged_in' => false]);
}
?>