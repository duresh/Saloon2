<?php
session_start();
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'Test endpoint is working',
    'session' => isset($_SESSION['logged_in']) ? 'Logged in' : 'Not logged in',
    'role' => $_SESSION['role'] ?? 'No role'
]);
?>