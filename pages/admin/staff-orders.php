<?php
// staff-orders.php - Staff Order Management
session_start();
require_once '../../includes/dbcon.php';

// Check if user is logged in and has staff/admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../login.php');
    exit;
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['fName'] ?? 'Staff';

// Get staff details
try {
    $pdo = getPDOConnection();
    
    // Get staff information
    $staff_query = "
        SELECT s.*, r.fName, r.lName, r.email, r.contactNo 
        FROM staff s 
        JOIN reg r ON s.user_id = r.regID 
        WHERE s.user_id = ?
    ";
    $staff_stmt = $pdo->prepare($staff_query);
    $staff_stmt->execute([$staff_id]);
    $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        $staff_db_id = 0;
    } else {
        $staff_db_id = $staff['id'] ?? 0;
    }
    
    // Get unread notifications count
    $notif_count_query = "
        SELECT COUNT(*) as count FROM staff_notifications 
        WHERE staff_id = ? AND is_read = 0
    ";
    $notif_count_stmt = $pdo->prepare($notif_count_query);
    $notif_count_stmt->execute([$staff_db_id]);
    $unread_count = $notif_count_stmt->fetch()['count'] ?? 0;
    
    // Get notifications for staff
    $notif_query = "
        SELECT * FROM staff_notifications 
        WHERE staff_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ";
    $notif_stmt = $pdo->prepare($notif_query);
    $notif_stmt->execute([$staff_db_id]);
    $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Staff orders error: " . $e->getMessage());
    $staff = null;
    $staff_db_id = 0;
    $unread_count = 0;
    $notifications = [];
}

// Handle order status updates via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $order_id = intval($_POST['order_id'] ?? 0);
    $reason = $_POST['reason'] ?? '';
    $staff_notes = $_POST['staff_notes'] ?? '';
    
    // Handle notification actions
    if ($action === 'mark_notification_read') {
        $notification_id = intval($_POST['notification_id'] ?? 0);
        
        try {
            $pdo = getPDOConnection();
            
            if ($notification_id) {
                $update_stmt = $pdo->prepare("
                    UPDATE staff_notifications 
                    SET is_read = 1 
                    WHERE id = ? AND staff_id = ?
                ");
                $update_stmt->execute([$notification_id, $staff_db_id]);
                echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
            } else {
                // Mark all as read
                $update_stmt = $pdo->prepare("
                    UPDATE staff_notifications 
                    SET is_read = 1 
                    WHERE staff_id = ? AND is_read = 0
                ");
                $update_stmt->execute([$staff_db_id]);
                echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if (!$order_id) {
        echo json_encode(['success' => false, 'message' => 'Order ID required']);
        exit;
    }
    
    try {
        $pdo = getPDOConnection();
        $pdo->beginTransaction();
        
        // Get current order status and user_id
        $stmt = $pdo->prepare("SELECT status, user_id, order_number, payment_status FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }
        
        $old_status = $order['status'];
        $old_payment_status = $order['payment_status'];
        $user_id = $order['user_id'];
        $order_number = $order['order_number'];
        
        switch ($action) {
            case 'approve':
                $new_status = 'confirmed';
                $title = 'Order Confirmed';
                $message = "Your order #{$order_number} has been confirmed by our staff. We are preparing your items.";
                $notification_type = 'order_confirmed';
                break;
                
            case 'processing':
                $new_status = 'processing';
                $title = 'Order Being Processed';
                $message = "Your order #{$order_number} is now being processed and prepared for shipping.";
                $notification_type = 'order_processing';
                break;
                
            case 'ship':
                $new_status = 'shipped';
                $title = 'Order Shipped';
                $message = "Great news! Your order #{$order_number} has been shipped and is on its way to you.";
                $notification_type = 'order_shipped';
                break;
                
            case 'deliver':
                $new_status = 'delivered';
                $title = 'Order Delivered';
                $message = "Your order #{$order_number} has been delivered successfully. Thank you for shopping with us!";
                $notification_type = 'order_delivered';
                break;
                
            case 'mark_paid':
                $new_status = $order['status'];
                $new_payment_status = 'paid';
                $title = 'Payment Confirmed';
                $message = "Payment for order #{$order_number} has been confirmed. Thank you!";
                $notification_type = 'payment_confirmed';
                break;
                
            case 'reject':
                $new_status = 'cancelled';
                $title = 'Order Cancelled';
                $message = "We regret to inform you that your order #{$order_number} has been cancelled. Reason: " . ($reason ?: 'Not specified');
                $notification_type = 'order_cancelled';
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit;
        }
        
        // Update order
        $updateFields = [];
        $params = [];
        
        if (isset($new_status)) {
            $updateFields[] = "status = ?";
            $params[] = $new_status;
        }
        
        if (isset($new_payment_status)) {
            $updateFields[] = "payment_status = ?";
            $params[] = $new_payment_status;
        }
        
        if ($action === 'deliver') {
            $updateFields[] = "delivered_at = NOW()";
        }
        if ($action === 'approve') {
            $updateFields[] = "approved_at = NOW()";
        }
        if ($staff_notes) {
            $updateFields[] = "staff_notes = ?";
            $params[] = $staff_notes;
        }
        
        $params[] = $order_id;
        $query = "UPDATE orders SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        // Log status change
        if (isset($new_status) && $new_status !== $old_status) {
            $stmt = $pdo->prepare("
                INSERT INTO order_status_logs (order_id, old_status, new_status, changed_by, reason) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$order_id, $old_status, $new_status, $staff_id, $reason ?: 'Staff action']);
        }
        
        // Handle stock for rejection
        if ($action === 'reject' && $old_status !== 'cancelled') {
            $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as $item) {
                if ($item['product_id']) {
                    $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                    $stmt->execute([$item['quantity'], $item['product_id']]);
                }
            }
        }
        
        // Send notification
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'customer_notifications'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO customer_notifications (user_id, order_id, type, title, message) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $order_id, $notification_type, $title, $message]);
            }
        } catch (Exception $e) {
            error_log("Notification insert error: " . $e->getMessage());
        }
        
        // Log staff action
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'staff_action_logs'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO staff_action_logs (staff_id, order_id, action, details) 
                    VALUES (?, ?, ?, ?)
                ");
                $action_details = "Order #{$order_number} ";
                if (isset($new_status) && $new_status !== $old_status) {
                    $action_details .= "status changed from {$old_status} to {$new_status}";
                }
                if (isset($new_payment_status) && $new_payment_status !== $old_payment_status) {
                    $action_details .= " | Payment status changed from {$old_payment_status} to {$new_payment_status}";
                }
                if ($reason) $action_details .= ". Reason: " . $reason;
                $stmt->execute([$staff_id, $order_id, $action, $action_details]);
            }
        } catch (Exception $e) {
            error_log("Staff action log error: " . $e->getMessage());
        }
        
        $pdo->commit();
        
        $response_message = "Order #{$order_number} has been ";
        if ($action === 'reject') {
            $response_message .= 'rejected';
        } elseif ($action === 'mark_paid') {
            $response_message .= 'marked as paid';
        } else {
            $response_message .= 'updated';
        }
        
        echo json_encode([
            'success' => true,
            'message' => $response_message,
            'new_status' => $new_status ?? $old_status,
            'new_payment_status' => $new_payment_status ?? $old_payment_status
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Staff order action error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Get filters
$status_filter = $_GET['status'] ?? 'pending';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(o.ordered_at) = ?";
    $params[] = $date_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(o.order_number LIKE ? OR r.fName LIKE ? OR r.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    $pdo = getPDOConnection();
    
    // Get orders with customer info
    $query = "
        SELECT o.*, r.fName as customer_name, r.email, r.contactNo,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
        FROM orders o 
        JOIN reg r ON o.user_id = r.regID 
        {$where_clause}
        ORDER BY 
            CASE 
                WHEN o.status = 'pending' THEN 1
                WHEN o.status = 'confirmed' THEN 2
                WHEN o.status = 'processing' THEN 3
                WHEN o.status = 'shipped' THEN 4
                WHEN o.status = 'delivered' THEN 5
                WHEN o.status = 'cancelled' THEN 6
            END,
            o.ordered_at ASC
    ";
    
    $stmt = $pdo->prepare($query);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get order statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_count,
            SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped_count,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
        FROM orders
    ";
    $stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Staff orders error: " . $e->getMessage());
    $orders = [];
    $stats = [];
}

function getStatusBadge($status) {
    $badges = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'processing' => 'primary',
        'shipped' => 'secondary',
        'delivered' => 'success',
        'cancelled' => 'danger'
    ];
    $color = $badges[$status] ?? 'secondary';
    return "<span class='badge bg-{$color}'>" . ucfirst($status) . "</span>";
}

function getAvailableActions($status, $payment_status) {
    $actions = [
        'pending' => ['approve' => 'Approve', 'reject' => 'Reject'],
        'confirmed' => ['processing' => 'Start Processing', 'reject' => 'Cancel'],
        'processing' => ['ship' => 'Mark as Shipped', 'reject' => 'Cancel'],
        'shipped' => ['deliver' => 'Mark as Delivered'],
        'delivered' => []
    ];
    
    $result = $actions[$status] ?? [];
    
    if ($status === 'delivered' && $payment_status !== 'paid') {
        $result['mark_paid'] = 'Mark as Paid';
    }
    
    return $result;
}

// Include staff header
include 'header/header-staff.php';
?>

<style>
/* Orders Page Styles */
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 15px 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
    cursor: pointer;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.stat-card .stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.stat-card .stat-details {
    flex: 1;
    min-width: 0;
}

.stat-card .stat-value {
    font-size: 22px;
    font-weight: 700;
    line-height: 1.2;
    color: #333;
}

.stat-card .stat-label {
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-top: 2px;
}

.stat-card.pending { border-left-color: #ffc107; }
.stat-card.confirmed { border-left-color: #0dcaf0; }
.stat-card.processing { border-left-color: #0d6efd; }
.stat-card.shipped { border-left-color: #6c757d; }
.stat-card.delivered { border-left-color: #198754; }
.stat-card.cancelled { border-left-color: #dc3545; }

.filter-card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border-radius: 12px;
}

.filter-card .card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    border-radius: 12px 12px 0 0;
    padding: 15px 20px;
}

.order-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.order-card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.order-card.pending { border-left-color: #ffc107; }
.order-card.confirmed { border-left-color: #0dcaf0; }
.order-card.processing { border-left-color: #0d6efd; }
.order-card.shipped { border-left-color: #6c757d; }
.order-card.delivered { border-left-color: #198754; }
.order-card.cancelled { border-left-color: #dc3545; }

.customer-info {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 10px;
}

.customer-info p {
    margin-bottom: 4px;
    font-size: 14px;
}

.action-btn {
    border-radius: 25px;
    padding: 6px 16px;
    margin: 3px;
    font-weight: 500;
    font-size: 13px;
}

.payment-status-badge {
    font-size: 12px;
    padding: 4px 12px;
    border-radius: 20px;
}

.dashboard-header {
    background: white;
    border-radius: 12px;
    padding: 20px 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border-left: 5px solid #6f42c1;
}

.welcome-text h1 {
    color: #6f42c1;
    font-weight: 600;
}

.table-card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border-radius: 12px;
    overflow: hidden;
}

.table-card .card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 15px 20px;
}

.table-card .table {
    margin-bottom: 0;
}

.table-card .table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
    font-size: 13px;
    border-bottom: 2px solid #e9ecef;
    padding: 12px 15px;
    white-space: nowrap;
}

.table-card .table td {
    padding: 12px 15px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
}

/* Notification Modal Styles */
.notification-modal .swal2-popup {
    padding: 0;
}
.notification-modal .swal2-html-container {
    padding: 0;
    margin: 0;
}
.notifications-container {
    max-height: 450px;
    overflow: hidden;
}
.notifications-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}
.notification-count {
    font-size: 14px;
    color: #6c757d;
}
.mark-all-read {
    font-size: 13px;
    color: #6f42c1;
    text-decoration: none;
    cursor: pointer;
    background: none;
    border: none;
}
.mark-all-read:hover {
    text-decoration: underline;
}
.notifications-list {
    max-height: 360px;
    overflow-y: auto;
    padding: 5px 0;
}
.notification-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px;
    border-bottom: 1px solid #f8f9fa;
    transition: background 0.2s;
}
.notification-item:hover {
    background: #f8f9fa;
}
.notification-item.unread {
    background: #f0e8ff;
    border-left: 3px solid #6f42c1;
}
.notification-item.unread:hover {
    background: #e8d8ff;
}
.notification-content {
    flex: 1;
    margin-right: 10px;
}
.notification-title {
    font-weight: 600;
    color: #333;
    font-size: 14px;
}
.notification-message {
    color: #6c757d;
    font-size: 13px;
    margin-top: 2px;
}
.notification-time {
    color: #adb5bd;
    font-size: 11px;
    margin-top: 4px;
}
.btn-mark-read {
    background: none;
    border: none;
    color: #28a745;
    cursor: pointer;
    padding: 5px 8px;
    border-radius: 50%;
    transition: all 0.2s;
}
.btn-mark-read:hover {
    background: #d4edda;
}
.notification-empty {
    text-align: center;
    padding: 40px 20px;
}
.notification-empty i {
    opacity: 0.5;
}
.notification-empty p {
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .stat-card {
        padding: 12px 15px;
    }
    .stat-card .stat-value {
        font-size: 18px;
    }
    .stat-card .stat-icon {
        width: 38px;
        height: 38px;
        font-size: 16px;
    }
}
</style>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Header -->
    <div class="dashboard-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="welcome-text">
                    <h1><i class="fas fa-clipboard-list me-2"></i>Order Management</h1>
                    <p class="lead mb-0">Manage and process customer orders</p>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <span class="badge bg-light text-dark p-2 me-2">
                    <i class="fas fa-user me-2"></i>
                    <?php echo htmlspecialchars($staff_name); ?>
                </span>
                <a href="staff-dashboard.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-2 col-4">
            <div class="stat-card pending" onclick="filterOrders('pending')">
                <div class="stat-icon" style="background: rgba(255, 193, 7, 0.12); color: #ffc107;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['pending_count'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="stat-card confirmed" onclick="filterOrders('confirmed')">
                <div class="stat-icon" style="background: rgba(13, 202, 240, 0.12); color: #0dcaf0;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['confirmed_count'] ?? 0; ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="stat-card processing" onclick="filterOrders('processing')">
                <div class="stat-icon" style="background: rgba(13, 110, 253, 0.12); color: #0d6efd;">
                    <i class="fas fa-cog"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['processing_count'] ?? 0; ?></div>
                    <div class="stat-label">Processing</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="stat-card shipped" onclick="filterOrders('shipped')">
                <div class="stat-icon" style="background: rgba(108, 117, 125, 0.12); color: #6c757d;">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['shipped_count'] ?? 0; ?></div>
                    <div class="stat-label">Shipped</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="stat-card delivered" onclick="filterOrders('delivered')">
                <div class="stat-icon" style="background: rgba(25, 135, 84, 0.12); color: #198754;">
                    <i class="fas fa-check-double"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['delivered_count'] ?? 0; ?></div>
                    <div class="stat-label">Delivered</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="stat-card cancelled" onclick="filterOrders('cancelled')">
                <div class="stat-icon" style="background: rgba(220, 53, 69, 0.12); color: #dc3545;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['cancelled_count'] ?? 0; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card filter-card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Orders</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Status</label>
                    <select name="status" class="form-select" id="statusFilter" onchange="this.form.submit()">
                        <option value="all">All Orders</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Search</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Order #, Customer, Email..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="staff-orders.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Notification Area -->
    <div id="notificationArea"></div>

    <!-- Orders List -->
    <div id="ordersList">
        <?php if (empty($orders)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-inbox fa-4x text-muted mb-3 d-block"></i>
                <h5>No orders found</h5>
                <p class="text-muted">Orders will appear here when customers place them</p>
            </div>
        </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
            <div class="order-card <?php echo $order['status']; ?>" id="order-<?php echo $order['id']; ?>">
                <div class="row">
                    <div class="col-md-3">
                        <h5 class="mb-1">
                            <i class="fas fa-hashtag text-muted"></i>
                            <?php echo htmlspecialchars($order['order_number']); ?>
                        </h5>
                        <div class="customer-info mt-2">
                            <p class="mb-1">
                                <i class="fas fa-user text-primary"></i>
                                <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                            </p>
                            <p class="mb-1">
                                <i class="fas fa-envelope text-muted"></i>
                                <?php echo htmlspecialchars($order['email']); ?>
                            </p>
                            <p class="mb-0">
                                <i class="fas fa-phone text-muted"></i>
                                <?php echo htmlspecialchars($order['contactNo'] ?? 'N/A'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <p class="mb-1">
                            <i class="fas fa-calendar text-muted"></i>
                            <?php echo date('M d, Y', strtotime($order['ordered_at'])); ?>
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-clock text-muted"></i>
                            <?php echo date('h:i A', strtotime($order['ordered_at'])); ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-box text-muted"></i>
                            <?php echo $order['items_count']; ?> items
                        </p>
                    </div>
                    
                    <div class="col-md-2">
                        <p class="mb-1">
                            <strong>Total:</strong>
                            <span class="text-primary fw-bold">LKR <?php echo number_format($order['grand_total'], 2); ?></span>
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-credit-card text-muted"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?>
                        </p>
                        <p class="mb-0">
                            <span class="payment-status-badge <?php echo $order['payment_status'] === 'paid' ? 'bg-success text-white' : 'bg-warning text-dark'; ?>">
                                <i class="fas fa-<?php echo $order['payment_status'] === 'paid' ? 'check' : 'clock'; ?> me-1"></i>
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </p>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="mb-2">
                            <?php echo getStatusBadge($order['status']); ?>
                        </div>
                        <?php if ($order['staff_notes']): ?>
                            <small class="text-muted d-block">
                                <i class="fas fa-sticky-note"></i>
                                <?php echo htmlspecialchars(substr($order['staff_notes'], 0, 50)); ?>
                            </small>
                        <?php endif; ?>
                        <?php if ($order['approved_at']): ?>
                            <small class="text-muted d-block">
                                <i class="fas fa-check-circle text-success"></i>
                                Approved: <?php echo date('M d, H:i', strtotime($order['approved_at'])); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="d-flex flex-wrap justify-content-end">
                            <?php $actions = getAvailableActions($order['status'], $order['payment_status']); ?>
                            <?php foreach ($actions as $action_key => $action_label): ?>
                                <button class="btn btn-sm action-btn btn-<?php 
                                    echo $action_key === 'reject' ? 'danger' : 
                                         ($action_key === 'approve' ? 'success' : 
                                         ($action_key === 'processing' ? 'primary' : 
                                         ($action_key === 'ship' ? 'info' : 
                                         ($action_key === 'mark_paid' ? 'success' : 'secondary')))); 
                                ?> order-action" 
                                        data-id="<?php echo $order['id']; ?>"
                                        data-action="<?php echo $action_key; ?>"
                                        data-order="<?php echo htmlspecialchars($order['order_number']); ?>">
                                    <i class="fas fa-<?php 
                                        echo $action_key === 'reject' ? 'times' : 
                                             ($action_key === 'approve' ? 'check' : 
                                             ($action_key === 'processing' ? 'cog' : 
                                             ($action_key === 'ship' ? 'truck' : 
                                             ($action_key === 'mark_paid' ? 'money-bill' : 'check-double')))); 
                                    ?> me-1"></i>
                                    <?php echo $action_label; ?>
                                </button>
                            <?php endforeach; ?>
                            
                            <button class="btn btn-sm btn-outline-secondary action-btn view-details mt-1" 
                                    data-id="<?php echo $order['id']; ?>">
                                <i class="fas fa-eye me-1"></i> Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Store notifications data
var notifications = <?php echo json_encode($notifications); ?>;
var unreadCount = <?php echo $unread_count; ?>;

$(document).ready(function() {
    // Handle order actions
    $('.order-action').on('click', function() {
        const orderId = $(this).data('id');
        const action = $(this).data('action');
        const orderNumber = $(this).data('order');
        let actionText = '';
        let confirmText = '';
        let inputHtml = '';
        let iconColor = '';
        
        switch(action) {
            case 'approve':
                actionText = 'approve';
                confirmText = 'Approve this order and notify customer?';
                iconColor = '#198754';
                break;
            case 'processing':
                actionText = 'start processing';
                confirmText = 'Mark order as processing?';
                iconColor = '#0d6efd';
                break;
            case 'ship':
                actionText = 'mark as shipped';
                confirmText = 'Mark order as shipped? Customer will be notified.';
                iconColor = '#0dcaf0';
                break;
            case 'deliver':
                actionText = 'mark as delivered';
                confirmText = 'Mark order as delivered? Customer will be notified.';
                iconColor = '#198754';
                break;
            case 'mark_paid':
                actionText = 'mark as paid';
                confirmText = 'Mark order payment as paid? This will confirm the payment.';
                iconColor = '#198754';
                break;
            case 'reject':
                actionText = 'reject';
                confirmText = 'Reject this order? Customer will be notified.';
                iconColor = '#dc3545';
                inputHtml = `
                    <div class="mb-3">
                        <label class="form-label">Reason for rejection:</label>
                        <textarea id="rejectReason" class="form-control" placeholder="Enter reason..."></textarea>
                    </div>
                `;
                break;
        }
        
        Swal.fire({
            title: `Confirm Action`,
            html: `
                <p>Are you sure you want to <strong>${actionText}</strong> order <strong>#${orderNumber}</strong>?</p>
                <p class="text-muted small">${confirmText}</p>
                ${inputHtml}
                <div class="mt-2">
                    <label class="form-label">Staff notes (optional):</label>
                    <textarea id="staffNotes" class="form-control" placeholder="Add internal notes..."></textarea>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: iconColor,
            confirmButtonText: `Yes, ${actionText}`,
            cancelButtonText: 'Cancel',
            preConfirm: () => {
                return {
                    reason: document.getElementById('rejectReason')?.value || '',
                    staff_notes: document.getElementById('staffNotes')?.value || ''
                };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const data = result.value || {};
                processOrderAction(orderId, action, data.reason, data.staff_notes);
            }
        });
    });
    
    // View order details
    $('.view-details').on('click', function() {
        const orderId = $(this).data('id');
        viewOrderDetails(orderId);
    });
    
    // Filter by status (from stat cards)
    window.filterOrders = function(status) {
        $('#statusFilter').val(status);
        $('form').submit();
    };
});

function processOrderAction(orderId, action, reason, staffNotes) {
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: {
            action: action,
            order_id: orderId,
            reason: reason || '',
            staff_notes: staffNotes || ''
        },
        dataType: 'json',
        beforeSend: function() {
            Swal.fire({
                title: 'Processing...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        },
        success: function(response) {
            Swal.close();
            
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    html: `
                        ${response.message}
                        <br><br>
                        <i class="fas fa-bell text-primary"></i> 
                        <span class="text-success">Customer notification has been sent!</span>
                    `,
                    timer: 3000,
                    timerProgressBar: true
                });
                
                setTimeout(() => {
                    location.reload();
                }, 1500);
                
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message || 'Could not process order'
                });
            }
        },
        error: function(xhr) {
            Swal.close();
            let errorMsg = 'Could not process order';
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.message) errorMsg = response.message;
            } catch (e) {}
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMsg
            });
        }
    });
}

function viewOrderDetails(orderId) {
    const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
    
    $('#orderDetailsContent').html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading order details...</p>
        </div>
    `);
    
    modal.show();
    
    $.ajax({
        url: 'order_details.php',
        method: 'GET',
        data: { id: orderId, staff: 1 },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                const data = response.data;
                let html = `
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6><i class="fas fa-info-circle"></i> Order Information</h6>
                            <p><strong>Order #:</strong> ${data.order_number}</p>
                            <p><strong>Status:</strong> <span class="badge bg-${data.status === 'pending' ? 'warning' : data.status === 'delivered' ? 'success' : data.status === 'cancelled' ? 'danger' : 'info'}">${data.status}</span></p>
                            <p><strong>Payment Status:</strong> <span class="badge bg-${data.payment_status === 'paid' ? 'success' : 'warning'}">${data.payment_status}</span></p>
                            <p><strong>Date:</strong> ${new Date(data.ordered_at).toLocaleString()}</p>
                            <p><strong>Payment:</strong> ${data.payment_method.replace('_', ' ')}</p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-user"></i> Customer Information</h6>
                            <p><strong>Name:</strong> ${data.customer_name || 'N/A'}</p>
                            <p><strong>Email:</strong> ${data.email || 'N/A'}</p>
                            <p><strong>Phone:</strong> ${data.phone || 'N/A'}</p>
                            <p><strong>Address:</strong><br>${data.shipping_address || 'N/A'}</p>
                        </div>
                    </div>
                    <hr>
                    <h6><i class="fas fa-box"></i> Order Items</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                if (data.items && data.items.length > 0) {
                    data.items.forEach(item => {
                        html += `
                            <tr>
                                <td>${item.product_name}</td>
                                <td class="text-end">LKR ${Number(item.price).toFixed(2)}</td>
                                <td class="text-center">${item.quantity}</td>
                                <td class="text-end">LKR ${Number(item.subtotal).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                }
                
                html += `
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                    <td class="text-end">LKR ${Number(data.total_amount).toFixed(2)}</td>
                                </tr>
                                ${data.discount_amount > 0 ? `
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Discount:</strong></td>
                                    <td class="text-end text-success">-LKR ${Number(data.discount_amount).toFixed(2)}</td>
                                </tr>
                                ` : ''}
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Tax:</strong></td>
                                    <td class="text-end">LKR ${Number(data.tax_amount).toFixed(2)}</td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Grand Total:</strong></td>
                                    <td class="text-end"><strong>LKR ${Number(data.grand_total).toFixed(2)}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    ${data.staff_notes ? `
                    <div class="alert alert-info">
                        <strong><i class="fas fa-sticky-note"></i> Staff Notes:</strong>
                        ${data.staff_notes}
                    </div>
                    ` : ''}
                    <hr>
                    <h6><i class="fas fa-clock-history"></i> Status Timeline</h6>
                    <div class="status-timeline">
                `;
                
                if (data.logs && data.logs.length > 0) {
                    data.logs.forEach((log, index) => {
                        const isLast = index === data.logs.length - 1;
                        const logDate = new Date(log.changed_at).toLocaleString();
                        
                        html += `
                            <div class="d-flex mb-2">
                                <div class="me-3">
                                    <i class="fas fa-${isLast ? 'check-circle text-success' : 'circle text-secondary'}"></i>
                                </div>
                                <div>
                                    <strong>${log.new_status.replace('_', ' ').toUpperCase()}</strong>
                                    <br>
                                    <small class="text-muted">${logDate}</small>
                                    ${log.reason ? `<br><small class="text-muted">Reason: ${log.reason}</small>` : ''}
                                    ${log.old_status ? `<br><small class="text-muted">Previous: ${log.old_status}</small>` : ''}
                                </div>
                            </div>
                        `;
                    });
                }
                
                html += `
                    </div>
                `;
                
                $('#orderDetailsContent').html(html);
            } else {
                $('#orderDetailsContent').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> 
                        ${response.message || 'Could not load order details'}
                    </div>
                `);
            }
        },
        error: function() {
            $('#orderDetailsContent').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Could not load order details
                </div>
            `);
        }
    });
}

// Show notifications function
function showNotifications() {
    var notificationsHtml = '';
    
    if (notifications && notifications.length > 0) {
        notificationsHtml = notifications.map(function(notif) {
            var isRead = parseInt(notif.is_read) === 1;
            var timeAgo = getTimeAgo(notif.created_at);
            return `
                <div class="notification-item ${isRead ? 'read' : 'unread'}" data-id="${notif.id}">
                    <div class="notification-content">
                        <div class="notification-title">${notif.title || 'Notification'}</div>
                        <div class="notification-message">${notif.message}</div>
                        <div class="notification-time">${timeAgo}</div>
                    </div>
                    ${!isRead ? `<button class="btn-mark-read" onclick="markNotificationRead(${notif.id})"><i class="fas fa-check"></i></button>` : ''}
                </div>
            `;
        }).join('');
    } else {
        notificationsHtml = `
            <div class="notification-empty">
                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                <p class="text-muted">No notifications</p>
            </div>
        `;
    }
    
    Swal.fire({
        title: '<i class="fas fa-bell me-2"></i> Notifications',
        html: `
            <div class="notifications-container">
                <div class="notifications-header">
                    <span class="notification-count">${unreadCount} unread</span>
                    ${unreadCount > 0 ? `<button class="mark-all-read" onclick="markAllNotificationsRead()">Mark all as read</button>` : ''}
                </div>
                <div class="notifications-list">
                    ${notificationsHtml}
                </div>
            </div>
        `,
        showCloseButton: true,
        confirmButtonText: 'Close',
        confirmButtonColor: '#6f42c1',
        width: '450px',
        customClass: {
            popup: 'notification-modal',
            htmlContainer: 'p-0'
        }
    });
}

function getTimeAgo(dateString) {
    var date = new Date(dateString);
    var now = new Date();
    var diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
    if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
    return date.toLocaleDateString();
}

function markNotificationRead(notificationId) {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'mark_notification_read',
            notification_id: notificationId
        })
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(response) {
        if (response.success) {
            unreadCount--;
            var badge = document.querySelector('.notification-badge');
            if (badge) {
                if (unreadCount > 0) {
                    badge.textContent = unreadCount;
                } else {
                    badge.style.display = 'none';
                }
            }
            // Refresh notifications
            location.reload();
        }
    })
    .catch(function(error) {
        console.error('Error marking notification as read:', error);
    });
}

function markAllNotificationsRead() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'mark_notification_read',
            notification_id: 0
        })
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(response) {
        if (response.success) {
            unreadCount = 0;
            var badge = document.querySelector('.notification-badge');
            if (badge) {
                badge.style.display = 'none';
            }
            location.reload();
        }
    })
    .catch(function(error) {
        console.error('Error marking all notifications as read:', error);
    });
}

// Auto-refresh for new orders (every 30 seconds)
setInterval(function() {
    $.ajax({
        url: 'check_new_orders.php',
        method: 'GET',
        data: { last_check: $('#lastCheck').val() || 0 },
        dataType: 'json',
        success: function(response) {
            if (response.new_orders && response.new_orders.length > 0) {
                let message = '';
                response.new_orders.forEach(function(order) {
                    message += `<div class="alert alert-warning alert-dismissible fade show">
                        <i class="fas fa-bell me-2"></i>
                        <strong>New Order!</strong> #${order.order_number} from ${order.customer_name}
                        <button class="btn btn-sm btn-success ms-2" onclick="location.reload()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>`;
                });
                $('#notificationArea').html(message);
            }
            $('#lastCheck').val(response.timestamp || Date.now());
        }
    });
}, 30000);
</script>

<input type="hidden" id="lastCheck" value="<?php echo time(); ?>">
</body>
</html>