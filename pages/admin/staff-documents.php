<?php
// staff-documents.php - Staff Documents Management
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
    $notif_count_query = "SELECT COUNT(*) as count FROM staff_notifications WHERE staff_id = ? AND is_read = 0";
    $notif_count_stmt = $pdo->prepare($notif_count_query);
    $notif_count_stmt->execute([$staff_db_id]);
    $unread_count = $notif_count_stmt->fetch()['count'] ?? 0;
    
    // Get documents with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $records_per_page = 15;
    $offset = ($page - 1) * $records_per_page;
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM staff_documents WHERE staff_id = ?";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute([$staff_db_id]);
    $total_records = $count_stmt->fetch()['total'] ?? 0;
    $total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 1;
    
    // Get paginated documents - FIXED: Use bindValue with PDO::PARAM_INT
    $documents_query = "
        SELECT * FROM staff_documents 
        WHERE staff_id = ? 
        ORDER BY uploaded_at DESC 
        LIMIT ? OFFSET ?
    ";
    $documents_stmt = $pdo->prepare($documents_query);
    $documents_stmt->bindValue(1, $staff_db_id, PDO::PARAM_INT);
    $documents_stmt->bindValue(2, $records_per_page, PDO::PARAM_INT);
    $documents_stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $documents_stmt->execute();
    $documents = $documents_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    try {
        $pdo = getPDOConnection();
        
        // Upload document
        if ($action === 'upload_document') {
            $document_type = $_POST['document_type'] ?? '';
            $document_name = $_POST['document_name'] ?? '';
            
            if (empty($document_type) || empty($document_name)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                exit;
            }
            
            // Handle file upload
            if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/staff_documents/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));
                $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
                
                if (!in_array($file_extension, $allowed_types)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type']);
                    exit;
                }
                
                if ($_FILES['document_file']['size'] > 10 * 1024 * 1024) {
                    echo json_encode(['success' => false, 'message' => 'File too large. Max 10MB']);
                    exit;
                }
                
                $new_filename = 'staff_' . $staff_db_id . '_' . time() . '.' . $file_extension;
                $target_file = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['document_file']['tmp_name'], $target_file)) {
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO staff_documents (staff_id, document_type, document_name, file_path) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $insert_stmt->execute([$staff_db_id, $document_type, $document_name, $new_filename]);
                    
                    echo json_encode(['success' => true, 'message' => 'Document uploaded successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Please select a file']);
            }
            exit;
        }
        
        // Delete document
        if ($action === 'delete_document') {
            $document_id = intval($_POST['document_id'] ?? 0);
            
            // Get file path
            $get_stmt = $pdo->prepare("SELECT file_path FROM staff_documents WHERE id = ? AND staff_id = ?");
            $get_stmt->execute([$document_id, $staff_db_id]);
            $doc = $get_stmt->fetch();
            
            if ($doc) {
                $upload_dir = '../../uploads/staff_documents/';
                if (file_exists($upload_dir . $doc['file_path'])) {
                    unlink($upload_dir . $doc['file_path']);
                }
            }
            
            $delete_stmt = $pdo->prepare("DELETE FROM staff_documents WHERE id = ? AND staff_id = ?");
            $delete_stmt->execute([$document_id, $staff_db_id]);
            
            echo json_encode(['success' => true, 'message' => 'Document deleted successfully']);
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

<style>
.document-item {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 10px;
    background: #f8f9fa;
    border-left: 4px solid #6f42c1;
}
.document-item .doc-name { font-weight: 600; }
.document-item .doc-type { font-size: 12px; padding: 3px 10px; border-radius: 20px; }
</style>

<!-- Page Content -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="dashboard-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="welcome-text">
                            <h1><i class="fas fa-file-alt me-2"></i>My Documents</h1>
                            <p class="lead mb-0">Manage your professional documents and certificates</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-light text-dark p-2 me-2">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($staff_name); ?>
                        </span>
                        <button class="btn btn-primary btn-sm" onclick="showUploadModal()">
                            <i class="fas fa-upload me-1"></i>Upload Document
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4 col-6">
                    <div class="card" style="border-left: 4px solid #6f42c1;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $total_records; ?></h5>
                            <small class="text-muted">Total Documents</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="card" style="border-left: 4px solid #17a2b8;">
                        <div class="card-body">
                            <h5 class="card-title mb-0">
                                <?php 
                                $certificates = array_filter($documents, function($d) { return $d['document_type'] == 'certificate'; });
                                echo count($certificates);
                                ?>
                            </h5>
                            <small class="text-muted">Certificates</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="card" style="border-left: 4px solid #ffc107;">
                        <div class="card-body">
                            <h5 class="card-title mb-0">
                                <?php 
                                $others = array_filter($documents, function($d) { return $d['document_type'] != 'certificate'; });
                                echo count($others);
                                ?>
                            </h5>
                            <small class="text-muted">Other Documents</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Documents</h5>
                    <span class="badge bg-primary float-end"><?php echo $total_records; ?> Records</span>
                </div>
                <div class="card-body">
                    <?php if (empty($documents)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-alt fa-4x text-muted mb-3 d-block"></i>
                        <h5>No Documents</h5>
                        <p class="text-muted">Upload your professional documents and certificates.</p>
                        <button class="btn btn-primary" onclick="showUploadModal()">
                            <i class="fas fa-upload me-2"></i>Upload Document
                        </button>
                    </div>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                        <div class="document-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="doc-name">
                                        <i class="fas fa-<?php echo $doc['document_type'] == 'certificate' ? 'certificate' : 'file'; ?> me-2"></i>
                                        <?php echo htmlspecialchars($doc['document_name']); ?>
                                    </div>
                                    <div class="mt-1">
                                        <span class="badge bg-<?php echo $doc['document_type'] == 'certificate' ? 'success' : 'info'; ?>">
                                            <?php echo ucfirst($doc['document_type']); ?>
                                        </span>
                                        <span class="text-muted ms-2">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <a href="../../uploads/staff_documents/<?php echo $doc['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-info me-1">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button class="btn btn-sm btn-danger" onclick="deleteDocument(<?php echo $doc['id']; ?>)">
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
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> documents
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

<!-- Upload Document Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-upload me-2"></i>Upload Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="documentForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Document Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="document_type" id="docType">
                            <option value="certificate">Certificate</option>
                            <option value="license">License</option>
                            <option value="certification">Certification</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Document Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="document_name" id="docName" placeholder="e.g., Hair Styling Certificate">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="document_file" id="docFile" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt">
                        <small class="text-muted">Max size: 10MB. Allowed: PDF, DOC, DOCX, JPG, PNG, TXT</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="uploadDocument()">
                    <i class="fas fa-upload me-2"></i>Upload
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Show upload modal
function showUploadModal() {
    document.getElementById('docType').value = 'certificate';
    document.getElementById('docName').value = '';
    document.getElementById('docFile').value = '';
    $('#uploadModal').modal('show');
}

// Upload document
function uploadDocument() {
    const form = document.getElementById('documentForm');
    const formData = new FormData(form);
    
    // Validate
    if (!formData.get('document_name')) {
        Swal.fire('Error', 'Please enter a document name', 'error');
        return;
    }
    if (!formData.get('document_file') || formData.get('document_file').size === 0) {
        Swal.fire('Error', 'Please select a file', 'error');
        return;
    }
    
    formData.append('action', 'upload_document');
    
    Swal.fire({
        title: 'Uploading...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(response => {
        Swal.close();
        $('#uploadModal').modal('hide');
        if (response.success) {
            Swal.fire('Success!', response.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', response.message || 'Failed to upload document', 'error');
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire('Error', 'Failed to upload document', 'error');
    });
}

// Delete document
function deleteDocument(docId) {
    Swal.fire({
        title: 'Delete Document?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'delete_document',
                    document_id: docId
                })
            })
            .then(response => response.json())
            .then(response => {
                Swal.close();
                if (response.success) {
                    Swal.fire('Deleted!', response.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', response.message || 'Failed to delete document', 'error');
                }
            })
            .catch(error => {
                Swal.close();
                Swal.fire('Error', 'Failed to delete document', 'error');
            });
        }
    });
}

// Show notifications function for bell icon
function showNotifications() {
    Swal.fire({
        title: '<i class="fas fa-bell me-2"></i> Notifications',
        html: `
            <div class="text-start">
                <p><strong>Total:</strong> <?php echo $total_records; ?></p>
                <p><strong>Unread:</strong> <?php echo $unread_count ?? 0; ?></p>
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