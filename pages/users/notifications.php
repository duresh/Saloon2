<?php
session_start();
require_once '../../includes/dbcon.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = getPDOConnection();
    
    // Mark all as read
    if (isset($_GET['mark_read'])) {
        $stmt = $pdo->prepare("UPDATE customer_notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        header('Location: notifications.php');
        exit;
    }
    
    // Get notifications
    $stmt = $pdo->prepare("
        SELECT * FROM customer_notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count unread
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM customer_notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $notifications = [];
    $unread = ['count' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Salon Elegance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-bell me-2"></i>Notifications</h2>
            <div>
                <?php if ($unread['count'] > 0): ?>
                    <a href="?mark_read=1" class="btn btn-primary me-2">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </a>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
        
        <?php if (empty($notifications)): ?>
            <div class="text-center py-5">
                <i class="fas fa-bell-slash display-1 text-muted"></i>
                <h4 class="mt-3">No notifications</h4>
                <p class="text-muted">You'll see notifications here when your orders are updated</p>
            </div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($notifications as $notification): ?>
                    <div class="list-group-item <?php echo $notification['is_read'] ? '' : 'list-group-item-primary'; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                    <?php if (!$notification['is_read']): ?>
                                        <span class="badge bg-primary ms-2">New</span>
                                    <?php endif; ?>
                                </h6>
                                <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                <small class="text-muted">
                                    <i class="far fa-clock"></i>
                                    <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                </small>
                            </div>
                            <?php if ($notification['order_id']): ?>
                                <a href="orders.php#order-<?php echo $notification['order_id']; ?>" class="btn btn-sm btn-outline-primary">
                                    View Order
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>