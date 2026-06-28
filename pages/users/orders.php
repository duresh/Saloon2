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
    
    // Get user orders
    $stmt = $pdo->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
        FROM orders o 
        WHERE o.user_id = ? 
        ORDER BY o.ordered_at DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
    
    // Get user info
    $stmt = $pdo->prepare("SELECT fName, lName FROM reg WHERE regID = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
} catch (Exception $e) {
    $orders = [];
    $user = null;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Saloon Elegance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #fefaf5; }
        .btn-elegance { background: #b85c1a; color: white; border-radius: 40px; padding: 10px 28px; font-weight: 600; border: none; }
        .btn-elegance:hover { background: #9b4512; transform: translateY(-2px); }
        .order-card { border: none; border-radius: 1.25rem; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .order-card:hover { transform: translateY(-3px); }
        .progress-bar-custom { height: 8px; border-radius: 4px; background: #e9ecef; }
        .progress-bar-custom .progress-fill { height: 100%; border-radius: 4px; transition: width 0.5s; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-box-seam me-2"></i>My Orders</h2>
                    <a href="../shop.php" class="btn btn-elegance">
                        <i class="bi bi-cart-plus"></i> Continue Shopping
                    </a>
                </div>
                
                <?php if ($user): ?>
                <div class="card order-card mb-4">
                    <div class="card-body">
                        <h5>Welcome back, <?= htmlspecialchars($user['fName']) ?>!</h5>
                        <p class="text-muted mb-0">
                            Total Orders: <?= count($orders) ?> | 
                            Active Orders: <?= count(array_filter($orders, function($o) { return !in_array($o['status'], ['delivered', 'cancelled']); })) ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (count($orders) > 0): ?>
                    <?php foreach ($orders as $order): ?>
                    <div class="card order-card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Order #<?= $order['order_number'] ?></strong>
                                    <br>
                                    <small class="text-muted"><?= date('M d, Y H:i', strtotime($order['ordered_at'])) ?></small>
                                </div>
                                <div class="col-md-2">
                                    <strong>Total</strong>
                                    <br>
                                    <span class="text-primary">LKR <?= number_format($order['grand_total'], 2) ?></span>
                                </div>
                                <div class="col-md-2">
                                    <strong>Items</strong>
                                    <br>
                                    <span><?= $order['items_count'] ?></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Status</strong>
                                    <br>
                                    <?= getStatusBadge($order['status']) ?>
                                    <br>
                                    <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'delivered'): ?>
                                    <div class="progress-bar-custom mt-1" style="width: 100%;">
                                        <div class="progress-fill bg-<?= $order['status'] === 'delivered' ? 'success' : 'info' ?>" 
                                             style="width: <?= getStatusProgress($order['status']) ?>%;">
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <small class="text-muted"><?= getStatusLabel($order['status']) ?></small>
                                </div>
                                <div class="col-md-2 text-end">
                                    <button class="btn btn-sm btn-info view-order" data-id="<?= $order['id'] ?>">
                                        <i class="bi bi-eye"></i> Details
                                    </button>
                                    <?php if ($order['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-danger cancel-order mt-1" data-id="<?= $order['id'] ?>">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="card order-card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-box-seam display-1 text-muted"></i>
                        <h3 class="mt-3">No Orders Yet</h3>
                        <p class="text-muted">Start shopping to see your orders here</p>
                        <a href="../shop.php" class="btn btn-elegance">
                            <i class="bi bi-cart-plus"></i> Browse Products
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details</h5>
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
        // View order details
        $('.view-order').click(function() {
            const orderId = $(this).data('id');
            const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
            
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
                                    <h6>Order Information</h6>
                                    <p><strong>Order #:</strong> ${data.order_number}</p>
                                    <p><strong>Status:</strong> ${data.status}</p>
                                    <p><strong>Date:</strong> ${new Date(data.ordered_at).toLocaleString()}</p>
                                    <p><strong>Payment:</strong> ${data.payment_method} - ${data.payment_status}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Shipping Information</h6>
                                    <p><strong>Address:</strong> ${data.shipping_address || 'N/A'}</p>
                                    <p><strong>Phone:</strong> ${data.phone || 'N/A'}</p>
                                    ${data.notes ? `<p><strong>Notes:</strong> ${data.notes}</p>` : ''}
                                </div>
                            </div>
                            <h6>Order Items</h6>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Qty</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;
                        
                        data.items.forEach(item => {
                            html += `
                                <tr>
                                    <td>${item.product_name}</td>
                                    <td>LKR ${Number(item.price).toFixed(2)}</td>
                                    <td>${item.quantity}</td>
                                    <td>LKR ${Number(item.subtotal).toFixed(2)}</td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                </tbody>
                                <tfoot>
                                    <tr><td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                        <td>LKR ${Number(data.total_amount).toFixed(2)}</td></tr>
                                    ${data.discount_amount > 0 ? `
                                    <tr><td colspan="3" class="text-end"><strong>Discount:</strong></td>
                                        <td class="text-success">-LKR ${Number(data.discount_amount).toFixed(2)}</td></tr>
                                    ` : ''}
                                    <tr><td colspan="3" class="text-end"><strong>Tax:</strong></td>
                                        <td>LKR ${Number(data.tax_amount).toFixed(2)}</td></tr>
                                    <tr><td colspan="3" class="text-end"><strong>Grand Total:</strong></td>
                                        <td><strong>LKR ${Number(data.grand_total).toFixed(2)}</strong></td></tr>
                                </tfoot>
                            </table>
                            <h6>Status Timeline</h6>
                            <ul class="list-unstyled">
                        `;
                        
                        data.logs.forEach(log => {
                            html += `
                                <li>
                                    <small>
                                        ${new Date(log.changed_at).toLocaleString()} - 
                                        ${log.old_status ? log.old_status + ' → ' : ''}
                                        <strong>${log.new_status}</strong>
                                        ${log.reason ? ' (Reason: ' + log.reason + ')' : ''}
                                    </small>
                                </li>
                            `;
                        });
                        
                        html += `</ul>`;
                        
                        $('#orderDetailsContent').html(html);
                        modal.show();
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Could not load order details', 'error');
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
                confirmButtonText: 'Yes, cancel order!',
                input: 'text',
                inputPlaceholder: 'Reason for cancellation (optional)'
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
                                Swal.fire('Cancelled!', response.message, 'success')
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Could not cancel order', 'error');
                        }
                    });
                }
            });
        });
    });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>