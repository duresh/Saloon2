<?php
// notifications.php - Staff Notifications Management
session_start();
require_once '../../includes/dbcon.php';

// Check if user is logged in and has staff/admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$staff_name = $_SESSION['fName'] ?? 'Staff';
$role = $_SESSION['role'] ?? 'staff';

// Get staff details and notification data
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
    $staff_stmt->execute([$user_id]);
    $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        die("No staff record found for user_id: " . $user_id);
    }
    
    $staff_db_id = $staff['id'] ?? 0;
    $staff_name = $staff['fName'] ?? $staff_name;
    
    // Get unread notifications count
    $notif_count_query = "
        SELECT COUNT(*) as count FROM staff_notifications 
        WHERE staff_id = ? AND is_read = 0
    ";
    $notif_count_stmt = $pdo->prepare($notif_count_query);
    $notif_count_stmt->execute([$staff_db_id]);
    $unread_count = $notif_count_stmt->fetch()['count'] ?? 0;
    
    // Get all notifications with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $records_per_page = 10;
    $offset = ($page - 1) * $records_per_page;
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM staff_notifications WHERE staff_id = ?";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute([$staff_db_id]);
    $total_records = $count_stmt->fetch()['total'] ?? 0;
    $total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 1;
    
    // Get paginated notifications - FIXED: Use named parameters with proper binding
    $notifications_query = "
        SELECT * FROM staff_notifications 
        WHERE staff_id = :staff_id 
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset
    ";
    $notifications_stmt = $pdo->prepare($notifications_query);
    
    // Bind parameters with proper types
    $notifications_stmt->bindValue(':staff_id', $staff_db_id, PDO::PARAM_INT);
    $notifications_stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $notifications_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $notifications_stmt->execute();
    $notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    try {
        $pdo = getPDOConnection();
        
        // Mark single notification as read
        if ($action === 'mark_read') {
            $notification_id = intval($_POST['notification_id'] ?? 0);
            
            if ($notification_id) {
                $update_stmt = $pdo->prepare("
                    UPDATE staff_notifications 
                    SET is_read = 1 
                    WHERE id = ? AND staff_id = ?
                ");
                $update_stmt->execute([$notification_id, $staff_db_id]);
                echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Notification ID required']);
            }
            exit;
        }
        
        // Mark all as read
        if ($action === 'mark_all_read') {
            $update_stmt = $pdo->prepare("
                UPDATE staff_notifications 
                SET is_read = 1 
                WHERE staff_id = ? AND is_read = 0
            ");
            $update_stmt->execute([$staff_db_id]);
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
            exit;
        }
        
        // Delete notification
        if ($action === 'delete') {
            $notification_id = intval($_POST['notification_id'] ?? 0);
            
            if ($notification_id) {
                $delete_stmt = $pdo->prepare("
                    DELETE FROM staff_notifications 
                    WHERE id = ? AND staff_id = ?
                ");
                $delete_stmt->execute([$notification_id, $staff_db_id]);
                echo json_encode(['success' => true, 'message' => 'Notification deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Notification ID required']);
            }
            exit;
        }
        
        // Delete all read notifications
        if ($action === 'delete_all_read') {
            $delete_stmt = $pdo->prepare("
                DELETE FROM staff_notifications 
                WHERE staff_id = ? AND is_read = 1
            ");
            $delete_stmt->execute([$staff_db_id]);
            echo json_encode(['success' => true, 'message' => 'All read notifications deleted']);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Include staff header
include 'header/header-staff.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4 col-6">
                    <div class="card" style="border-left: 4px solid #6f42c1;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $total_records; ?></h5>
                            <small class="text-muted">Total Notifications</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="card" style="border-left: 4px solid #ffc107;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $unread_count; ?></h5>
                            <small class="text-muted">Unread</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="card" style="border-left: 4px solid #28a745;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $total_records - $unread_count; ?></h5>
                            <small class="text-muted">Read</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mb-3">
                <?php if ($unread_count > 0): ?>
                <button class="btn btn-success btn-sm" onclick="markAllRead()">
                    <i class="fas fa-check-double me-1"></i> Mark All as Read
                </button>
                <?php endif; ?>
                <?php if ($total_records > 0): ?>
                <button class="btn btn-danger btn-sm" onclick="clearAll()">
                    <i class="fas fa-trash me-1"></i> Clear All
                </button>
                <?php endif; ?>
            </div>

            <!-- Notifications List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i>All Notifications</h5>
                    <span class="badge bg-primary float-end"><?php echo $total_records; ?> Total</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bell-slash fa-4x text-muted mb-3 d-block"></i>
                        <h5>No Notifications</h5>
                        <p class="text-muted">You're all caught up! No notifications to display.</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): 
                            $is_unread = $notification['is_read'] == 0;
                            $icon_class = 'info';
                            $icon = 'fa-bell';
                            
                            // Determine icon based on notification type
                            if (strpos($notification['type'] ?? '', 'order') !== false) {
                                $icon_class = 'info';
                                $icon = 'fa-shopping-cart';
                            } elseif (strpos($notification['type'] ?? '', 'appointment') !== false) {
                                $icon_class = 'success';
                                $icon = 'fa-calendar-check';
                            } elseif (strpos($notification['type'] ?? '', 'payment') !== false) {
                                $icon_class = 'warning';
                                $icon = 'fa-credit-card';
                            } elseif (strpos($notification['type'] ?? '', 'cancelled') !== false) {
                                $icon_class = 'danger';
                                $icon = 'fa-times-circle';
                            }
                        ?>
                        <div class="border-bottom p-3 <?php echo $is_unread ? 'bg-light' : ''; ?>" style="<?php echo $is_unread ? 'border-left: 3px solid #6f42c1;' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="d-flex">
                                    <div class="me-3" style="width: 40px; height: 40px; border-radius: 50%; background: <?php echo $is_unread ? 'rgba(111, 66, 193, 0.1)' : '#f8f9fa'; ?>; display: flex; align-items: center; justify-content: center; color: <?php echo $is_unread ? '#6f42c1' : '#6c757d'; ?>;">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($notification['title'] ?? 'Notification'); ?></strong>
                                            <?php if ($is_unread): ?>
                                            <span class="badge bg-warning text-dark ms-2">New</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="mb-1 text-muted" style="font-size: 14px;"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="d-flex gap-1">
                                    <?php if ($is_unread): ?>
                                    <button class="btn btn-sm btn-outline-success" onclick="markRead(<?php echo $notification['id']; ?>)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> notifications
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=1"><i class="fas fa-angle-double-left"></i></a>
                                </li>
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>"><i class="fas fa-angle-left"></i></a>
                                </li>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>"><i class="fas fa-angle-right"></i></a>
                                </li>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?>"><i class="fas fa-angle-double-right"></i></a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Mark single notification as read
function markRead(notificationId) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 
            action: 'mark_read', 
            notification_id: notificationId 
        })
    })
    .then(function(response) { return response.json(); })
    .then(function(response) {
        if (response.success) { 
            location.reload(); 
        } else {
            Swal.fire('Error', response.message || 'Could not mark as read', 'error');
        }
    })
    .catch(function(error) {
        Swal.fire('Error', 'Could not mark as read', 'error');
    });
}

// Mark all as read
function markAllRead() {
    Swal.fire({
        title: 'Mark All as Read?',
        text: 'This will mark all unread notifications as read.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, mark all',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#28a745'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'mark_all_read' })
            })
            .then(function(response) { return response.json(); })
            .then(function(response) {
                if (response.success) { 
                    location.reload(); 
                } else {
                    Swal.fire('Error', response.message || 'Could not mark all as read', 'error');
                }
            })
            .catch(function(error) {
                Swal.fire('Error', 'Could not mark all as read', 'error');
            });
        }
    });
}

// Delete single notification
function deleteNotification(notificationId) {
    Swal.fire({
        title: 'Delete Notification?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ 
                    action: 'delete', 
                    notification_id: notificationId 
                })
            })
            .then(function(response) { return response.json(); })
            .then(function(response) {
                if (response.success) { 
                    location.reload(); 
                } else {
                    Swal.fire('Error', response.message || 'Could not delete', 'error');
                }
            })
            .catch(function(error) {
                Swal.fire('Error', 'Could not delete notification', 'error');
            });
        }
    });
}

// Clear all notifications
function clearAll() {
    Swal.fire({
        title: 'Clear All Notifications?',
        text: 'This will permanently delete all your notifications.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, clear all',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'delete_all_read' })
            })
            .then(function(response) { return response.json(); })
            .then(function(response) {
                if (response.success) { 
                    location.reload(); 
                } else {
                    Swal.fire('Error', response.message || 'Could not clear all', 'error');
                }
            })
            .catch(function(error) {
                Swal.fire('Error', 'Could not clear all notifications', 'error');
            });
        }
    });
}

// Notification function for bell icon
function showNotifications() {
    // This will be called from the header bell icon
    Swal.fire({
        title: '<i class="fas fa-bell me-2"></i> Notifications',
        html: `
            <div class="text-start">
                <p><strong>Total:</strong> <?php echo $total_records; ?></p>
                <p><strong>Unread:</strong> <?php echo $unread_count; ?></p>
                <p><strong>Read:</strong> <?php echo $total_records - $unread_count; ?></p>
                <hr>
                <p class="text-muted small">Click "View All" to see all notifications</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'View All',
        cancelButtonText: 'Close',
        confirmButtonColor: '#6f42c1'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'notifications.php';
        }
    });
}
</script>
</body>
</html>