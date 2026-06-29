<?php
// orders.php - User Orders Page
ob_start();
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Check if user is admin (redirect to admin dashboard)
if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    header('Location: ../admin/dashboard.php');
    exit();
}

// Check if user is staff member (redirect to staff-dashboard)
if (isset($_SESSION['role']) && $_SESSION['role'] == 'staff') {
    header('Location: ../admin/staff-dashboard.php');
    exit();
}

// Include database connection
require_once '../../includes/dbcon.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

try {
    $pdo = getPDOConnection();
    
    // Get user details
    $user_query = "SELECT * FROM reg WHERE regID = ? AND cStatus = 1";
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
    
    // Get user orders with item count
    $orders_query = "
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
        FROM orders o 
        WHERE o.user_id = ? 
        ORDER BY o.ordered_at DESC
    ";
    $orders_stmt = $pdo->prepare($orders_query);
    $orders_stmt->execute([$user_id]);
    $orders = $orders_stmt->fetchAll();
    
    // Get order statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(grand_total) as total_spent
        FROM orders 
        WHERE user_id = ?
    ";
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([$user_id]);
    $stats = $stats_stmt->fetch();
    
} catch (PDOException $e) {
    error_log('Orders page error: ' . $e->getMessage());
    $error = 'Unable to load orders. Please try again later.';
    $orders = [];
    $stats = [
        'total_orders' => 0,
        'pending' => 0,
        'confirmed' => 0,
        'processing' => 0,
        'shipped' => 0,
        'delivered' => 0,
        'cancelled' => 0,
        'total_spent' => 0
    ];
}

function getStatusProgress($status) {
    $steps = [
        'pending' => 10,
        'confirmed' => 30,
        'processing' => 50,
        'shipped' => 75,
        'delivered' => 100,
        'cancelled' => 0
    ];
    return $steps[$status] ?? 0;
}

function getStatusLabel($status) {
    $labels = [
        'pending' => 'Order Received - Awaiting Confirmation',
        'confirmed' => 'Order Confirmed - Being Processed',
        'processing' => 'Items Being Prepared',
        'shipped' => 'Order Shipped - On the Way!',
        'delivered' => '✅ Delivered Successfully',
        'cancelled' => '❌ Cancelled'
    ];
    return $labels[$status] ?? $status;
}

function getStatusBadge($status) {
    $colors = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'processing' => 'primary',
        'shipped' => 'secondary',
        'delivered' => 'success',
        'cancelled' => 'danger'
    ];
    $color = $colors[$status] ?? 'secondary';
    return "<span class='badge bg-{$color}'>" . ucfirst($status) . "</span>";
}

include 'header/header.php';
?>

<style>
/* Orders Page Styles */
.orders-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 18px 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
    border-left: 4px solid var(--primary-color);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.stat-card .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.stat-card .stat-details {
    flex: 1;
    min-width: 0;
}

.stat-card .stat-value {
    font-size: 20px;
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

/* Order Card */
.order-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
    overflow: hidden;
}

.order-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.order-card.pending { border-left-color: #ffc107; }
.order-card.confirmed { border-left-color: #17a2b8; }
.order-card.processing { border-left-color: #0d6efd; }
.order-card.shipped { border-left-color: #6c757d; }
.order-card.delivered { border-left-color: #28a745; }
.order-card.cancelled { border-left-color: #dc3545; }

.order-card .card-body {
    padding: 20px;
}

.order-card .order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.order-card .order-number {
    font-weight: 700;
    color: #333;
    font-size: 16px;
}

.order-card .order-number small {
    font-weight: 400;
    color: #6c757d;
    font-size: 13px;
}

.order-card .order-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.order-card .detail-item {
    display: flex;
    flex-direction: column;
}

.order-card .detail-item .label {
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 3px;
}

.order-card .detail-item .value {
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.order-card .detail-item .value.price {
    color: #6f42c1;
}

.order-card .order-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.order-card .btn-sm {
    padding: 5px 12px;
    font-size: 12px;
    border-radius: 8px;
}

.order-card .progress-wrapper {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #f0f0f0;
}

.order-card .progress-bar-custom {
    height: 6px;
    border-radius: 3px;
    background: #e9ecef;
    width: 100%;
    overflow: hidden;
}

.order-card .progress-bar-custom .progress-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.5s ease;
}

.order-card .status-label {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.empty-state i {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-state h4 {
    color: #495057;
    margin-bottom: 10px;
}

.empty-state p {
    color: #6c757d;
    margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .stat-card {
        padding: 12px 15px;
    }
    
    .stat-card .stat-value {
        font-size: 16px;
    }
    
    .order-card .order-details {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .order-card .order-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .order-card .order-actions {
        width: 100%;
        justify-content: flex-start;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    
    .stat-card {
        padding: 10px;
    }
    
    .stat-card .stat-icon {
        width: 36px;
        height: 36px;
        font-size: 16px;
    }
    
    .stat-card .stat-value {
        font-size: 14px;
    }
    
    .stat-card .stat-label {
        font-size: 9px;
    }
}
</style>

<div class="container-fluid">
    <div class="orders-container">
        <!-- Page Header -->
        <div class="dashboard-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="welcome-text">
                        <h1><i class="fas fa-box me-2"></i>My Orders</h1>
                        <p class="lead mb-0">View and track all your orders</p>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-primary" onclick="location.href='../shop.php'">
                        <i class="fas fa-shopping-cart me-2"></i>Continue Shopping
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(111, 66, 193, 0.12); color: #6f42c1;">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['total_orders'] ?? 0; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(255, 193, 7, 0.12); color: #ffc107;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(23, 162, 184, 0.12); color: #17a2b8;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo ($stats['confirmed'] ?? 0) + ($stats['processing'] ?? 0) + ($stats['shipped'] ?? 0); ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(40, 167, 69, 0.12); color: #28a745;">
                    <i class="fas fa-check-double"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['delivered'] ?? 0; ?></div>
                    <div class="stat-label">Delivered</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(220, 53, 69, 0.12); color: #dc3545;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['cancelled'] ?? 0; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(255, 193, 7, 0.12); color: #ffc107;">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value">Rs: <?php echo number_format($stats['total_spent'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
            </div>
        </div>

        <!-- Orders List -->
        <?php if (!empty($orders)): ?>
            <?php foreach ($orders as $order): ?>
            <div class="order-card <?php echo $order['status']; ?>">
                <div class="card-body">
                    <div class="order-header">
                        <div>
                            <div class="order-number">
                                <?php echo htmlspecialchars($order['order_number']); ?>
                                <small><?php echo date('M d, Y H:i', strtotime($order['ordered_at'])); ?></small>
                            </div>
                        </div>
                        <div class="order-actions">
                            <?php echo getStatusBadge($order['status']); ?>
                            <button class="btn btn-sm btn-outline-info view-order" data-id="<?php echo $order['id']; ?>">
                                <i class="fas fa-eye"></i> Details
                            </button>
                            <?php if ($order['status'] === 'pending'): ?>
                            <button class="btn btn-sm btn-outline-danger cancel-order" data-id="<?php echo $order['id']; ?>">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="order-details">
                        <div class="detail-item">
                            <span class="label">Total Amount</span>
                            <span class="value price">Rs: <?php echo number_format($order['grand_total'], 2); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Items</span>
                            <span class="value"><?php echo $order['items_count']; ?> product(s)</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Payment Method</span>
                            <span class="value"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'N/A')); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Payment Status</span>
                            <span class="value">
                                <?php 
                                $payment_colors = [
                                    'paid' => 'success',
                                    'unpaid' => 'warning',
                                    'refunded' => 'info',
                                    'failed' => 'danger'
                                ];
                                $color = $payment_colors[$order['payment_status'] ?? 'unpaid'] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>">
                                    <?php echo ucfirst($order['payment_status'] ?? 'Unpaid'); ?>
                                </span>
                            </span>
                        </div>
                    </div>
                    
                    <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'delivered'): ?>
                    <div class="progress-wrapper">
                        <div class="progress-bar-custom">
                            <div class="progress-fill bg-<?php echo $order['status'] === 'delivered' ? 'success' : 'info'; ?>" 
                                 style="width: <?php echo getStatusProgress($order['status']); ?>%;">
                            </div>
                        </div>
                        <div class="status-label"><?php echo getStatusLabel($order['status']); ?></div>
                    </div>
                    <?php elseif ($order['status'] === 'delivered'): ?>
                    <div class="progress-wrapper">
                        <div class="status-label text-success fw-bold">✅ Order Delivered Successfully</div>
                    </div>
                    <?php elseif ($order['status'] === 'cancelled'): ?>
                    <div class="progress-wrapper">
                        <div class="status-label text-danger fw-bold">❌ Order Cancelled</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h4>No Orders Yet</h4>
                <p>You haven't placed any orders yet. Start shopping to see your orders here.</p>
                <button class="btn btn-primary" onclick="location.href='../shop.php'">
                    <i class="fas fa-shopping-cart me-2"></i>Start Shopping
                </button>
            </div>
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
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // View order details
    $('.view-order').click(function() {
        const orderId = $(this).data('id');
        const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
        
        // Show loading
        $('#orderDetailsContent').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `);
        modal.show();
        
        $.ajax({
            url: 'order_details.php',
            method: 'GET',
            data: { id: orderId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    let html = `
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6 class="fw-bold">Order Information</h6>
                                <p class="mb-1"><strong>Order #:</strong> ${data.order_number}</p>
                                <p class="mb-1"><strong>Status:</strong> <span class="badge bg-${data.status === 'pending' ? 'warning' : data.status === 'confirmed' ? 'info' : data.status === 'processing' ? 'primary' : data.status === 'shipped' ? 'secondary' : data.status === 'delivered' ? 'success' : 'danger'}">${data.status.charAt(0).toUpperCase() + data.status.slice(1)}</span></p>
                                <p class="mb-1"><strong>Date:</strong> ${new Date(data.ordered_at).toLocaleString()}</p>
                                <p class="mb-1"><strong>Payment Method:</strong> ${data.payment_method ? data.payment_method.replace('_', ' ').toUpperCase() : 'N/A'}</p>
                                <p class="mb-1"><strong>Payment Status:</strong> <span class="badge bg-${data.payment_status === 'paid' ? 'success' : data.payment_status === 'unpaid' ? 'warning' : data.payment_status === 'refunded' ? 'info' : 'danger'}">${data.payment_status ? data.payment_status.toUpperCase() : 'UNPAID'}</span></p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold">Shipping Information</h6>
                                <p class="mb-1"><strong>Address:</strong> ${data.shipping_address || 'N/A'}</p>
                                <p class="mb-1"><strong>Phone:</strong> ${data.phone || 'N/A'}</p>
                                ${data.notes ? `<p class="mb-1"><strong>Notes:</strong> ${data.notes}</p>` : ''}
                            </div>
                        </div>
                        <hr>
                        <h6 class="fw-bold">Order Items</h6>
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
                                    <td class="text-end">Rs: ${Number(item.price).toFixed(2)}</td>
                                    <td class="text-center">${item.quantity}</td>
                                    <td class="text-end">Rs: ${Number(item.subtotal).toFixed(2)}</td>
                                </tr>
                            `;
                        });
                    }
                    
                    html += `
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">Subtotal:</td>
                                        <td class="text-end">Rs: ${Number(data.total_amount).toFixed(2)}</td>
                                    </tr>
                                    ${data.discount_amount > 0 ? `
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">Discount:</td>
                                        <td class="text-end text-success">-Rs: ${Number(data.discount_amount).toFixed(2)}</td>
                                    </tr>
                                    ` : ''}
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">Tax:</td>
                                        <td class="text-end">Rs: ${Number(data.tax_amount).toFixed(2)}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">Grand Total:</td>
                                        <td class="text-end fw-bold text-primary">Rs: ${Number(data.grand_total).toFixed(2)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        ${data.logs && data.logs.length > 0 ? `
                        <hr>
                        <h6 class="fw-bold">Status Timeline</h6>
                        <div class="timeline-wrapper" style="max-height: 200px; overflow-y: auto;">
                            <ul class="list-unstyled mb-0">
                        ` : ''}
                        
                        ${data.logs ? data.logs.map(log => `
                            <li class="py-1">
                                <small>
                                    <i class="fas fa-clock text-muted me-1"></i>
                                    ${new Date(log.changed_at).toLocaleString()} - 
                                    ${log.old_status ? `<span class="text-muted">${log.old_status} → </span>` : ''}
                                    <strong>${log.new_status}</strong>
                                    ${log.reason ? `<span class="text-muted"> (${log.reason})</span>` : ''}
                                </small>
                            </li>
                        `).join('') : ''}
                        
                        ${data.logs && data.logs.length > 0 ? `
                            </ul>
                        </div>
                        ` : ''}
                    `;
                    
                    $('#orderDetailsContent').html(html);
                } else {
                    $('#orderDetailsContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>${response.message || 'Could not load order details'}
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                $('#orderDetailsContent').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>Failed to load order details. Please try again.
                    </div>
                `);
            }
        });
    });
    
    // Cancel order
    $('.cancel-order').click(function() {
        const orderId = $(this).data('id');
        
        Swal.fire({
            title: 'Cancel Order?',
            text: 'Are you sure you want to cancel this order?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, cancel order!',
            cancelButtonText: 'No, keep it',
            input: 'text',
            inputPlaceholder: 'Reason for cancellation (optional)',
            inputAttributes: {
                'aria-label': 'Reason for cancellation'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'cancel_order.php',
                    method: 'POST',
                    data: {
                        order_id: orderId,
                        reason: result.value || 'Cancelled by customer'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Cancelled!',
                                text: response.message || 'Order cancelled successfully',
                                confirmButtonColor: '#6f42c1'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Could not cancel order',
                                confirmButtonColor: '#6f42c1'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Could not cancel order. Please try again.',
                            confirmButtonColor: '#6f42c1'
                        });
                    }
                });
            }
        });
    });
});
</script>
</body>
</html>