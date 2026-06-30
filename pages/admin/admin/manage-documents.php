<?php
// admin/manage-documents.php - Admin Manage All Staff Documents
session_start();
require_once '../../includes/dbcon.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin'])) {
    header('Location: ../login.php');
    exit;
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['fName'] ?? 'Admin';

try {
    $pdo = getPDOConnection();
    
    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $records_per_page = 20;
    $offset = ($page - 1) * $records_per_page;
    
    // Filter
    $staff_filter = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;
    $type_filter = isset($_GET['type']) ? $_GET['type'] : '';
    
    // Build query
    $query = "
        SELECT sd.*, s.user_id, r.fName as staff_name, r.lName as staff_lname
        FROM staff_documents sd
        JOIN staff s ON sd.staff_id = s.id
        JOIN reg r ON s.user_id = r.regID
        WHERE 1=1
    ";
    $count_query = "
        SELECT COUNT(*) as total
        FROM staff_documents sd
        JOIN staff s ON sd.staff_id = s.id
        JOIN reg r ON s.user_id = r.regID
        WHERE 1=1
    ";
    $params = [];
    
    if ($staff_filter > 0) {
        $query .= " AND sd.staff_id = ?";
        $count_query .= " AND sd.staff_id = ?";
        $params[] = $staff_filter;
    }
    
    if (!empty($type_filter)) {
        $query .= " AND sd.document_type = ?";
        $count_query .= " AND sd.document_type = ?";
        $params[] = $type_filter;
    }
    
    // Get total
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'] ?? 0;
    $total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 1;
    
    // Get data
    $query .= " ORDER BY sd.uploaded_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all staff for filter
    $staff_list = $pdo->query("
        SELECT s.id, r.fName, r.lName 
        FROM staff s 
        JOIN reg r ON s.user_id = r.regID 
        ORDER BY r.fName
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    // Get file path
    $stmt = $pdo->prepare("SELECT file_path FROM staff_documents WHERE id = ?");
    $stmt->execute([$delete_id]);
    $doc = $stmt->fetch();
    if ($doc) {
        $upload_dir = '../../uploads/staff_documents/';
        if (file_exists($upload_dir . $doc['file_path'])) {
            unlink($upload_dir . $doc['file_path']);
        }
    }
    $stmt = $pdo->prepare("DELETE FROM staff_documents WHERE id = ?");
    $stmt->execute([$delete_id]);
    header('Location: manage-documents.php?deleted=1');
    exit;
}

// Include admin header
include 'header/header-admin.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="dashboard-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="welcome-text">
                            <h1><i class="fas fa-file-alt me-2"></i>Manage Documents</h1>
                            <p class="lead mb-0">View and manage all staff documents</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-light text-dark p-2 me-2">
                            <i class="fas fa-user-shield me-2"></i>
                            <?php echo htmlspecialchars($admin_name); ?>
                        </span>
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filter -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Documents</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Staff Member</label>
                            <select name="staff_id" class="form-select">
                                <option value="0">All Staff</option>
                                <?php foreach ($staff_list as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>" <?php echo $staff_filter == $staff['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($staff['fName'] . ' ' . ($staff['lName'] ?? '')); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Document Type</label>
                            <select name="type" class="form-select">
                                <option value="">All Types</option>
                                <option value="certificate" <?php echo $type_filter == 'certificate' ? 'selected' : ''; ?>>Certificate</option>
                                <option value="license" <?php echo $type_filter == 'license' ? 'selected' : ''; ?>>License</option>
                                <option value="certification" <?php echo $type_filter == 'certification' ? 'selected' : ''; ?>>Certification</option>
                                <option value="other" <?php echo $type_filter == 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                            <a href="manage-documents.php" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i>Reset
                            </a>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <span class="badge bg-primary"><?php echo $total_records; ?> Records</span>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Documents List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Documents</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Staff</th>
                                    <th>Document</th>
                                    <th>Type</th>
                                    <th>Uploaded</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($documents)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <i class="fas fa-file-alt fa-3x text-muted mb-3 d-block"></i>
                                        <p class="text-muted">No documents found</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($doc['staff_name'] . ' ' . ($doc['staff_lname'] ?? '')); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['document_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $doc['document_type'] == 'certificate' ? 'success' : 'info'; ?>">
                                                <?php echo ucfirst($doc['document_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></td>
                                        <td>
                                            <a href="../../uploads/staff_documents/<?php echo $doc['file_path']; ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="manage-documents.php?delete=<?php echo $doc['id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Delete this document?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php
                                $query_params = [];
                                if ($staff_filter > 0) $query_params['staff_id'] = $staff_filter;
                                if (!empty($type_filter)) $query_params['type'] = $type_filter;
                                $query_string = http_build_query($query_params);
                                $base_url = '?' . ($query_string ? $query_string . '&' : '');
                                ?>
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $base_url; ?>page=1"><i class="fas fa-angle-double-left"></i></a>
                                </li>
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $page-1; ?>"><i class="fas fa-angle-left"></i></a>
                                </li>
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $page+1; ?>"><i class="fas fa-angle-right"></i></a>
                                </li>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $total_pages; ?>"><i class="fas fa-angle-double-right"></i></a>
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

<?php include 'footer/footer.php'; ?>
</body>
</html>