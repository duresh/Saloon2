<?php
session_start();
require_once '../../includes/dbcon.php';

// Check if user is logged in and has staff/admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../login.php');
    exit;
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['fName'] ?? 'Staff';

// Handle order status updates via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $order_id = intval($_POST['order_id'] ?? 0);
    $reason = $_POST['reason'] ?? '';
    $staff_notes = $_POST['staff_notes'] ?? '';
    
    if (!$order_id) {
        echo json_encode(['success' => false, 'message' => 'Order ID required']);
        exit;
    }
    
    try {
        $pdo = getPDOConnection();
        $pdo->beginTransaction();
        
        // Get current order status and user_id
        $stmt = $pdo->prepare("SELECT status, user_id, order_number FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }
        
        $old_status = $order['status'];
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
        
        // Update order status
        $updateFields = "status = ?";
        $params = [$new_status];
        
        if ($action === 'deliver') {
            $updateFields .= ", delivered_at = NOW()";
        }
        if ($action === 'approve') {
            $updateFields .= ", approved_at = NOW()";
        }
        if ($staff_notes) {
            $updateFields .= ", staff_notes = ?";
            $params[] = $staff_notes;
        }
        
        $params[] = $order_id;
        $stmt = $pdo->prepare("UPDATE orders SET {$updateFields} WHERE id = ?");
        $stmt->execute($params);
        
        // Log status change
        $stmt = $pdo->prepare("
            INSERT INTO order_status_logs (order_id, old_status, new_status, changed_by, reason) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$order_id, $old_status, $new_status, $staff_id, $reason ?: 'Staff action']);
        
        // Handle stock for rejection (restore stock)
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
        
        // Check if notification table has the required columns
        try {
            // First, check if the table exists and has the right structure
            $stmt = $pdo->query("SHOW TABLES LIKE 'customer_notifications'");
            $tableExists = $stmt->rowCount() > 0;
            
            if ($tableExists) {
                // Check if title column exists
                $stmt = $pdo->query("SHOW COLUMNS FROM customer_notifications LIKE 'title'");
                $titleExists = $stmt->rowCount() > 0;
                
                if ($titleExists) {
                    // Insert notification with title
                    $stmt = $pdo->prepare("
                        INSERT INTO customer_notifications (user_id, order_id, type, title, message) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$user_id, $order_id, $notification_type, $title, $message]);
                } else {
                    // Insert notification without title (fallback)
                    $stmt = $pdo->prepare("
                        INSERT INTO customer_notifications (user_id, order_id, type, message) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$user_id, $order_id, $notification_type, $message]);
                }
            }
        } catch (Exception $e) {
            // Log error but don't stop the process
            error_log("Notification insert error: " . $e->getMessage());
        }
        
        // Log staff action (if table exists)
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'staff_action_logs'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO staff_action_logs (staff_id, order_id, action, details) 
                    VALUES (?, ?, ?, ?)
                ");
                $action_details = "Order #{$order_number} status changed from {$old_status} to {$new_status}";
                if ($reason) $action_details .= ". Reason: " . $reason;
                $stmt->execute([$staff_id, $order_id, $action, $action_details]);
            }
        } catch (Exception $e) {
            error_log("Staff action log error: " . $e->getMessage());
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Order #{$order_number} has been " . ($action === 'reject' ? 'rejected' : 'updated'),
            'new_status' => $new_status
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

function getAvailableActions($status) {
    $actions = [
        'pending' => ['approve' => 'Approve', 'reject' => 'Reject'],
        'confirmed' => ['processing' => 'Start Processing', 'reject' => 'Cancel'],
        'processing' => ['ship' => 'Mark as Shipped', 'reject' => 'Cancel'],
        'shipped' => ['deliver' => 'Mark as Delivered'],
        'delivered' => [],
        'cancelled' => []
    ];
    return $actions[$status] ?? [];
}

function getStatusClass($status) {
    $classes = [
        'pending' => 'table-warning',
        'confirmed' => 'table-info',
        'processing' => 'table-primary',
        'shipped' => 'table-secondary',
        'delivered' => 'table-success',
        'cancelled' => 'table-danger'
    ];
    return $classes[$status] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Order Management - Salon Elegance</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .page-header {
            background: linear-gradient(135deg, #6f42c1 0%, #5a32a0 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            border-radius: 0 0 20px 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            cursor: pointer;
            border-left: 4px solid #6f42c1;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: #6f42c1;
        }
        
        .stat-card .label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-card.pending { border-left-color: #ffc107; }
        .stat-card.confirmed { border-left-color: #0dcaf0; }
        .stat-card.processing { border-left-color: #0d6efd; }
        .stat-card.shipped { border-left-color: #6c757d; }
        .stat-card.delivered { border-left-color: #198754; }
        .stat-card.cancelled { border-left-color: #dc3545; }
        
        .order-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .order-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .order-card.pending { border-left: 4px solid #ffc107; }
        .order-card.confirmed { border-left: 4px solid #0dcaf0; }
        .order-card.processing { border-left: 4px solid #0d6efd; }
        .order-card.shipped { border-left: 4px solid #6c757d; }
        .order-card.delivered { border-left: 4px solid #198754; }
        .order-card.cancelled { border-left: 4px solid #dc3545; }
        
        .customer-info {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .action-btn {
            border-radius: 25px;
            padding: 8px 20px;
            margin: 3px;
            font-weight: 500;
        }
        
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .badge-status {
            font-size: 0.85rem;
            padding: 8px 16px;
            border-radius: 20px;
        }
        
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>

<div class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="fas fa-clipboard-list me-3"></i>Order Management</h1>
                <p class="mb-0 opacity-75">Manage and process customer orders</p>
            </div>
            <div class="col-md-4 text-end">
                <span class="badge bg-light text-dark p-3">
                    <i class="fas fa-user me-2"></i>
                    <?php echo htmlspecialchars($staff_name); ?>
                </span>
                <a href="staff-dashboard.php" class="btn btn-light ms-2">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-2 col-6">
            <div class="stat-card pending" onclick="filterOrders('pending')">
                <div class="number"><?php echo $stats['pending_count'] ?? 0; ?></div>
                <div class="label"><i class="fas fa-clock me-1"></i>Pending</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card confirmed" onclick="filterOrders('confirmed')">
                <div class="number"><?php echo $stats['confirmed_count'] ?? 0; ?></div>
                <div class="label"><i class="fas fa-check-circle me-1"></i>Confirmed</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card processing" onclick="filterOrders('processing')">
                <div class="number"><?php echo $stats['processing_count'] ?? 0; ?></div>
                <div class="label"><i class="fas fa-cog me-1"></i>Processing</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card shipped" onclick="filterOrders('shipped')">
                <div class="number"><?php echo $stats['shipped_count'] ?? 0; ?></div>
                <div class="label"><i class="fas fa-truck me-1"></i>Shipped</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card delivered" onclick="filterOrders('delivered')">
                <div class="number"><?php echo $stats['delivered_count'] ?? 0; ?></div>
                <div class="label"><i class="fas fa-check-double me-1"></i>Delivered</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card cancelled" onclick="filterOrders('cancelled')">
                <div class="number"><?php echo $stats['cancelled_count'] ?? 0; ?></div>
                <div class="label"><i class="fas fa-times-circle me-1"></i>Cancelled</div>
            </div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label"><i class="fas fa-filter me-1"></i>Status</label>
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
                <label class="form-label"><i class="fas fa-calendar me-1"></i>Date</label>
                <input type="date" name="date" class="form-control" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-4">
                <label class="form-label"><i class="fas fa-search me-1"></i>Search</label>
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
    
    <!-- Auto-refresh notification for new orders -->
    <div id="notificationArea"></div>
    
    <!-- Orders List -->
    <div id="ordersList">
        <?php if (empty($orders)): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox display-1 text-muted"></i>
                <h4 class="mt-3">No orders found</h4>
                <p class="text-muted">Orders will appear here when customers place them</p>
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
                            <span class="badge <?php echo $order['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning'; ?>">
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
                            <?php $actions = getAvailableActions($order['status']); ?>
                            <?php foreach ($actions as $action_key => $action_label): ?>
                                <button class="btn btn-sm action-btn btn-<?php 
                                    echo $action_key === 'reject' ? 'danger' : 
                                         ($action_key === 'approve' ? 'success' : 
                                         ($action_key === 'processing' ? 'primary' : 
                                         ($action_key === 'ship' ? 'info' : 'secondary'))); 
                                ?> order-action" 
                                        data-id="<?php echo $order['id']; ?>"
                                        data-action="<?php echo $action_key; ?>"
                                        data-order="<?php echo htmlspecialchars($order['order_number']); ?>">
                                    <i class="fas fa-<?php 
                                        echo $action_key === 'reject' ? 'times' : 
                                             ($action_key === 'approve' ? 'check' : 
                                             ($action_key === 'processing' ? 'cog' : 
                                             ($action_key === 'ship' ? 'truck' : 'check-double'))); 
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

<script>
$(document).ready(function() {
    // Handle order actions
    $('.order-action').on('click', function() {
        const orderId = $(this).data('id');
        const action = $(this).data('action');
        const orderNumber = $(this).data('order');
        let actionText = '';
        let confirmText = '';
        let inputHtml = '';
        
        switch(action) {
            case 'approve':
                actionText = 'approve';
                confirmText = 'Approve this order and notify customer?';
                break;
            case 'processing':
                actionText = 'start processing';
                confirmText = 'Mark order as processing?';
                break;
            case 'ship':
                actionText = 'mark as shipped';
                confirmText = 'Mark order as shipped? Customer will be notified.';
                break;
            case 'deliver':
                actionText = 'mark as delivered';
                confirmText = 'Mark order as delivered? Customer will be notified.';
                break;
            case 'reject':
                actionText = 'reject';
                confirmText = 'Reject this order? Customer will be notified.';
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
            confirmButtonColor: action === 'reject' ? '#dc3545' : '#198754',
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
        url: 'staff-orders.php',
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
                            <p><strong>Date:</strong> ${new Date(data.ordered_at).toLocaleString()}</p>
                            <p><strong>Payment:</strong> ${data.payment_method.replace('_', ' ')} - ${data.payment_status}</p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-user"></i> Customer Information</h6>
                            <p><strong>Name:</strong> ${data.customer_name}</p>
                            <p><strong>Email:</strong> ${data.email}</p>
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
                                    <td colspan="3" class="text-end"><strong>Tax (10%):</strong></td>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>