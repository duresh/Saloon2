<?php
// profile.php - User Profile Management
ob_start();
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';
$staff_name = $_SESSION['fName'] ?? 'User';

// Include database connection
require_once '../../includes/dbcon.php';
require_once '../../includes/helpers.php';

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
    
    // Get additional profile details
    $profile_query = "SELECT * FROM user_profiles WHERE user_id = ?";
    $profile_stmt = $pdo->prepare($profile_query);
    $profile_stmt->execute([$user_id]);
    $profile = $profile_stmt->fetch();
    
    // If profile doesn't exist, create it
    if (!$profile) {
        $create_profile = "INSERT INTO user_profiles (user_id, notification_email, notification_sms, created_at) VALUES (?, 1, 0, NOW())";
        $create_stmt = $pdo->prepare($create_profile);
        $create_stmt->execute([$user_id]);
        
        $profile_stmt->execute([$user_id]);
        $profile = $profile_stmt->fetch();
    }
    
    // Set notification values
    $notification_email = $profile['notification_email'] ?? 1;
    $notification_sms = $profile['notification_sms'] ?? 0;
    
    // Get staff ID if staff role
    $staff_db_id = 0;
    if ($user_role == 'staff') {
        $staff_query = "SELECT id FROM staff WHERE user_id = ?";
        $staff_stmt = $pdo->prepare($staff_query);
        $staff_stmt->execute([$user_id]);
        $staff_data = $staff_stmt->fetch();
        if ($staff_data) {
            $staff_db_id = $staff_data['id'];
        }
    }
    
    // Get unread notifications count for staff
    $unread_count = 0;
    if ($user_role == 'staff' && $staff_db_id > 0) {
        $notif_count_query = "SELECT COUNT(*) as count FROM staff_notifications WHERE staff_id = ? AND is_read = 0";
        $notif_count_stmt = $pdo->prepare($notif_count_query);
        $notif_count_stmt->execute([$staff_db_id]);
        $unread_count = $notif_count_stmt->fetch()['count'] ?? 0;
    }
    
    // Create profile directory if not exists
    $upload_dir = '../../uploads/profile/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Handle profile photo upload
    if (isset($_POST['upload_photo'])) {
        $target_dir = $upload_dir;
        $file = $_FILES['profile_photo'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_extension, $allowed_types)) {
                $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;
                
                if ($file['size'] <= 5 * 1024 * 1024) {
                    if (move_uploaded_file($file['tmp_name'], $target_file)) {
                        if (!empty($profile['profile_image']) && file_exists($target_dir . $profile['profile_image'])) {
                            unlink($target_dir . $profile['profile_image']);
                        }
                        
                        $update_photo = "UPDATE user_profiles SET profile_image = ?, updated_at = NOW() WHERE user_id = ?";
                        $stmt = $pdo->prepare($update_photo);
                        $stmt->execute([$new_filename, $user_id]);
                        
                        $success = 'Profile photo updated successfully!';
                        $profile_stmt->execute([$user_id]);
                        $profile = $profile_stmt->fetch();
                    } else {
                        $error = 'Failed to upload image. Please try again.';
                    }
                } else {
                    $error = 'File size too large. Maximum 5MB allowed.';
                }
            } else {
                $error = 'Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.';
            }
        } else {
            $error = 'Please select a file to upload.';
        }
    }
    
    // Handle profile update
    if (isset($_POST['update_profile'])) {
        $fName = trim($_POST['fName']);
        $email = trim($_POST['email']);
        $contactNo = trim($_POST['contactNo']);
        $address = trim($_POST['address'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        $valid = true;
        
        if (empty($fName)) {
            $error = 'Name is required';
            $valid = false;
        }
        
        if (empty($email)) {
            $error = 'Email is required';
            $valid = false;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
            $valid = false;
        }
        
        if (!empty($contactNo) && !preg_match('/^[0-9]{10}$/', $contactNo)) {
            $error = 'Contact number must be 10 digits';
            $valid = false;
        }
        
        if ($valid) {
            try {
                $check_email = "SELECT regID FROM reg WHERE email = ? AND regID != ?";
                $check_stmt = $pdo->prepare($check_email);
                $check_stmt->execute([$email, $user_id]);
                
                if ($check_stmt->rowCount() > 0) {
                    $error = 'Email already exists for another user';
                } else {
                    $update_reg = "UPDATE reg SET fName = ?, email = ?, contactNo = ?, modifyDate = NOW() WHERE regID = ?";
                    $stmt = $pdo->prepare($update_reg);
                    $stmt->execute([$fName, $email, $contactNo, $user_id]);
                    
                    $update_profile = "UPDATE user_profiles SET address = ?, bio = ?, updated_at = NOW() WHERE user_id = ?";
                    $stmt = $pdo->prepare($update_profile);
                    $stmt->execute([$address, $bio, $user_id]);
                    
                    $success = 'Profile updated successfully!';
                    
                    $user_stmt->execute([$user_id]);
                    $user = $user_stmt->fetch();
                    $profile_stmt->execute([$user_id]);
                    $profile = $profile_stmt->fetch();
                }
            } catch (PDOException $e) {
                error_log('Profile update error: ' . $e->getMessage());
                $error = 'Failed to update profile. Please try again.';
            }
        }
    }
    
    // Handle notification settings update
    if (isset($_POST['update_notifications'])) {
        $email_notify = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notify = isset($_POST['sms_notifications']) ? 1 : 0;
        
        try {
            $update_notify = "UPDATE user_profiles SET notification_email = ?, notification_sms = ?, updated_at = NOW() WHERE user_id = ?";
            $stmt = $pdo->prepare($update_notify);
            $stmt->execute([$email_notify, $sms_notify, $user_id]);
            
            $success = 'Notification settings updated successfully!';
            
            $profile_stmt->execute([$user_id]);
            $profile = $profile_stmt->fetch();
            $notification_email = $profile['notification_email'] ?? 1;
            $notification_sms = $profile['notification_sms'] ?? 0;
        } catch (PDOException $e) {
            error_log('Notification update error: ' . $e->getMessage());
            $error = 'Failed to update notification settings.';
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $valid = true;
        
        if (empty($current_password)) {
            $error = 'Current password is required';
            $valid = false;
        }
        
        if (empty($new_password)) {
            $error = 'New password is required';
            $valid = false;
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters';
            $valid = false;
        }
        
        if ($new_password != $confirm_password) {
            $error = 'New passwords do not match';
            $valid = false;
        }
        
        if ($valid) {
            if (password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_pass = "UPDATE reg SET password = ?, modifyDate = NOW(), password_changed = 1 WHERE regID = ?";
                $stmt = $pdo->prepare($update_pass);
                
                if ($stmt->execute([$hashed_password, $user_id])) {
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Failed to change password. Please try again.';
                }
            } else {
                $error = 'Current password is incorrect';
            }
        }
    }
    
    // Handle delete account
    if (isset($_POST['delete_account']) && isset($_POST['confirm_delete'])) {
        $confirm_text = $_POST['confirm_delete'];
        
        if ($confirm_text === 'DELETE') {
            try {
                $check_appointments = "SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND appointment_date >= CURDATE() AND status IN ('pending', 'confirmed')";
                $check_stmt = $pdo->prepare($check_appointments);
                $check_stmt->execute([$user_id]);
                $appointments = $check_stmt->fetch();
                
                if ($appointments['count'] > 0) {
                    $error = 'You have upcoming appointments. Please cancel them before deleting your account.';
                } else {
                    $delete_user = "UPDATE reg SET cStatus = 0, modifyDate = NOW() WHERE regID = ?";
                    $stmt = $pdo->prepare($delete_user);
                    $stmt->execute([$user_id]);
                    
                    session_destroy();
                    header('Location: ../login.php?deleted=1');
                    exit();
                }
            } catch (PDOException $e) {
                error_log('Account deletion error: ' . $e->getMessage());
                $error = 'Failed to delete account. Please try again.';
            }
        } else {
            $error = 'Please type DELETE to confirm account deletion';
        }
    }
    
} catch (PDOException $e) {
    error_log('Profile page error: ' . $e->getMessage());
    $error = 'Unable to load profile. Please try again later.';
}

// Determine redirect based on role
if ($user_role == 'admin') {
    $dashboard_link = '../admin/dashboard.php';
    $header_file = 'header/header-admin.php';
} elseif ($user_role == 'staff') {
    $dashboard_link = '../admin/staff-dashboard.php';
    $header_file = 'header/header-staff.php';
} else {
    $dashboard_link = '../users/dashboard.php';
    $header_file = 'header/headerBooking.php';
}

$profile_image_path = !empty($profile['profile_image']) ? '../../uploads/profile/' . $profile['profile_image'] : '../../assets/images/default-avatar.png';
$is_admin = $user_role == 'admin';

// Include appropriate header
include $header_file;
?>

<style>
/* Profile Page Styles */
.profile-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Profile Photo */
.profile-photo-container {
    text-align: center;
    margin-bottom: 20px;
}

.profile-photo {
    position: relative;
    width: 150px;
    height: 150px;
    margin: 0 auto;
    border-radius: 50%;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.profile-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.photo-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all 0.3s;
    cursor: pointer;
    color: white;
}

.photo-overlay i {
    font-size: 24px;
    margin-bottom: 5px;
}

.photo-overlay span {
    font-size: 12px;
}

.profile-photo:hover .photo-overlay {
    opacity: 1;
}

/* Profile Info */
.profile-info h3 {
    margin-bottom: 15px;
    color: #333;
}

.profile-info .text-muted {
    font-size: 14px;
    margin-bottom: 8px;
}

/* Profile Stats */
.profile-stats {
    display: flex;
    justify-content: space-around;
    text-align: center;
    margin-top: 15px;
}

.stat-item {
    flex: 1;
}

.stat-value {
    font-size: 22px;
    font-weight: 700;
    color: #6f42c1;
}

.stat-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
}

/* Profile Cards */
.profile-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 25px;
    border: none;
}

.profile-card .card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    border-radius: 12px 12px 0 0;
    padding: 15px 20px;
}

.profile-card .card-header h4 {
    margin: 0;
    font-weight: 600;
    color: #333;
}

.profile-card .card-body {
    padding: 25px;
}

/* Settings Items */
.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid #e9ecef;
}

.setting-item:last-child {
    border-bottom: none;
}

.setting-info {
    flex: 1;
}

.setting-info strong {
    display: block;
    margin-bottom: 5px;
}

/* Danger Zone */
.danger-zone {
    background: #fff5f5;
    border: 1px solid #f8d7da;
    border-radius: 12px;
    padding: 20px;
}

.danger-zone h5 {
    margin-bottom: 10px;
}

/* Password Strength */
.password-strength {
    margin-top: 5px;
}

/* Dashboard Header */
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

.welcome-text h1 .fa-user-circle {
    color: #6f42c1;
}

/* Form Controls */
.form-control:focus {
    border-color: #6f42c1;
    box-shadow: 0 0 0 3px rgba(111, 66, 193, 0.1);
}

.form-switch .form-check-input:checked {
    background-color: #6f42c1;
    border-color: #6f42c1;
}

.btn-primary {
    background: #6f42c1;
    border-color: #6f42c1;
}

.btn-primary:hover {
    background: #5a32a0;
    border-color: #5a32a0;
}

/* Responsive */
@media (max-width: 768px) {
    .profile-card .card-body {
        padding: 20px;
    }
    
    .profile-photo {
        width: 120px;
        height: 120px;
    }
    
    .setting-item {
        flex-direction: column;
        text-align: center;
    }
    
    .setting-control {
        margin-top: 10px;
    }
    
    .profile-stats {
        flex-wrap: wrap;
    }
    
    .stat-item {
        min-width: 80px;
    }
    
    .dashboard-header {
        padding: 15px 20px;
    }
}
</style>

<!-- Page Content -->
<div class="container-fluid">
    <div class="profile-container">
        <!-- Page Header -->
        <div class="dashboard-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="welcome-text">
                        <h1><i class="fas fa-user-circle me-2"></i>My Profile</h1>
                        <p class="lead mb-0">Manage your personal information and account settings</p>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-light text-dark p-2 me-2">
                        <i class="fas fa-user me-2"></i>
                        <?php echo htmlspecialchars($staff_name); ?>
                    </span>
                    <a href="<?php echo $dashboard_link; ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Left Column - Profile Photo & Quick Info -->
            <div class="col-lg-4">
                <!-- Profile Photo Card -->
                <div class="card profile-card">
                    <div class="card-body">
                        <div class="profile-photo-container">
                            <div class="profile-photo">
                                <img src="<?php echo $profile_image_path; ?>" alt="Profile Photo" id="profilePhoto">
                                <div class="photo-overlay" onclick="document.getElementById('photoUpload').click()">
                                    <i class="fas fa-camera"></i>
                                    <span>Change Photo</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="profile-info text-center">
                            <h3><?php echo htmlspecialchars($user['fName']); ?></h3>
                            <p class="text-muted">
                                <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                            <p class="text-muted">
                                <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($user['contactNo'] ?: 'Not provided'); ?>
                            </p>
                            <p class="text-muted">
                                <i class="fas fa-calendar-alt me-1"></i> Member since <?php echo date('M Y', strtotime($user['regDate'])); ?>
                            </p>
                            <span class="badge <?php echo $is_admin ? 'bg-danger' : 'bg-primary'; ?>">
                                <?php echo ucfirst($user_role); ?>
                            </span>
                        </div>
                        
                        <hr>
                        
                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo getAppointmentCount($pdo, $user_id); ?></div>
                                <div class="stat-label">Total</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo getCompletedCount($pdo, $user_id); ?></div>
                                <div class="stat-label">Completed</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo getUpcomingCount($pdo, $user_id); ?></div>
                                <div class="stat-label">Upcoming</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Photo Upload Form -->
                <form method="POST" enctype="multipart/form-data" id="photoUploadForm">
                    <input type="file" name="profile_photo" id="photoUpload" accept="image/*" style="display: none;" onchange="uploadPhoto()">
                    <input type="hidden" name="upload_photo" value="1">
                </form>
            </div>
            
            <!-- Right Column - Profile Settings -->
            <div class="col-lg-8">
                <!-- Personal Information -->
                <div class="card profile-card">
                    <div class="card-header">
                        <h4><i class="fas fa-user-edit me-2"></i>Personal Information</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="mt-0">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="fName" value="<?php echo htmlspecialchars($user['fName']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="contactNo" value="<?php echo htmlspecialchars($user['contactNo']); ?>" placeholder="10-digit mobile number">
                                    <small class="text-muted">Enter 10-digit mobile number</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Member Since</label>
                                    <input type="text" class="form-control" value="<?php echo date('F d, Y', strtotime($user['regDate'])); ?>" disabled readonly>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Bio / About Me</label>
                                    <textarea class="form-control" name="bio" rows="3" placeholder="Tell us a little about yourself..."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Notification Settings -->
                <div class="card profile-card">
                    <div class="card-header">
                        <h4><i class="fas fa-bell me-2"></i>Notification Settings</h4>
                    </div>
                    <div class="card-body">
                        <div class="setting-item">
                            <div class="setting-info">
                                <strong>Email Notifications</strong>
                                <p class="text-muted small">Receive email notifications about your appointments</p>
                            </div>
                            <div class="setting-control">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="emailNotifications" 
                                           data-type="email" <?php echo ($notification_email == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="emailNotifications">Email</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <strong>SMS Alerts</strong>
                                <p class="text-muted small">Receive SMS alerts for appointment reminders</p>
                            </div>
                            <div class="setting-control">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="smsNotifications" 
                                           data-type="sms" <?php echo ($notification_sms == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="smsNotifications">SMS</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Change Password -->
                <div class="card profile-card">
                    <div class="card-header">
                        <h4><i class="fas fa-key me-2"></i>Change Password</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="mt-0" id="changePasswordForm">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="current_password" id="currentPassword" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="new_password" id="newPassword" required minlength="6">
                                    <div class="password-strength mt-1" id="passwordStrength"></div>
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required>
                                    <div id="passwordMatchMsg" class="small mt-1"></div>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="submit" name="change_password" class="btn btn-warning">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Danger Zone -->
                <?php if (!$is_admin): ?>
                <div class="card profile-card">
                    <div class="card-body">
                        <div class="danger-zone">
                            <h5 class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i> Danger Zone</h5>
                            <p class="text-muted small">Once you delete your account, there is no going back. Please be certain.</p>
                            <button type="button" class="btn btn-danger" onclick="showDeleteConfirmation()">
                                <i class="fas fa-trash-alt me-2"></i>Delete Account
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Confirmation Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Delete Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger"><strong>Warning!</strong> This action cannot be undone.</p>
                <p>Deleting your account will:</p>
                <ul>
                    <li>Remove all your personal information</li>
                    <li>Cancel all your upcoming appointments</li>
                    <li>Remove your appointment history</li>
                    <li>You will lose access to all services</li>
                </ul>
                <p class="mt-3">Please type <strong class="text-danger">DELETE</strong> to confirm:</p>
                <input type="text" class="form-control" id="deleteConfirm" placeholder="Type DELETE here">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="deleteAccount()">Delete Account</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Form (hidden) -->
<form method="POST" id="deleteAccountForm">
    <input type="hidden" name="delete_account" value="1">
    <input type="hidden" name="confirm_delete" id="confirmDeleteInput">
</form>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ============================================
// PROFILE PHOTO UPLOAD
// ============================================
function uploadPhoto() {
    const fileInput = document.getElementById('photoUpload');
    const file = fileInput.files[0];
    
    if (file) {
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid File Type',
                text: 'Only JPG, PNG, GIF, and WEBP files are allowed.',
                confirmButtonColor: '#6f42c1'
            });
            fileInput.value = '';
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'File Too Large',
                text: 'Maximum file size is 5MB.',
                confirmButtonColor: '#6f42c1'
            });
            fileInput.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            Swal.fire({
                title: 'Upload Profile Photo?',
                html: `
                    <div class="text-center">
                        <img src="${e.target.result}" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin-bottom: 15px;">
                        <p>Do you want to set this as your profile photo?</p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Yes, Upload',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#6f42c1'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('photoUploadForm').submit();
                }
            });
        };
        reader.readAsDataURL(file);
    }
}

// ============================================
// PASSWORD STRENGTH METER
// ============================================
$(document).ready(function() {
    $('#newPassword').on('keyup', function() {
        const password = $(this).val();
        const strength = checkPasswordStrength(password);
        updateStrengthIndicator(strength);
    });

    $('#confirmPassword').on('keyup', function() {
        const newPass = $('#newPassword').val();
        const confirmPass = $(this).val();
        checkMatch(newPass, confirmPass);
    });
});

function checkPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
    return Math.min(strength, 6);
}

function updateStrengthIndicator(strength) {
    const colors = ['#dc3545', '#ff6b6b', '#ffc107', '#ffc107', '#28a745', '#28a745'];
    const texts = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    const widths = ['10%', '25%', '40%', '55%', '70%', '85%'];
    
    const indicator = document.getElementById('passwordStrength');
    if (strength > 0) {
        indicator.innerHTML = `
            <div style="background: ${colors[strength-1]}; width: ${widths[strength-1]}; height: 4px; border-radius: 2px;"></div>
            <small class="text-muted">${texts[strength-1]}</small>
        `;
    } else {
        indicator.innerHTML = '';
    }
}

function checkMatch(newPass, confirmPass) {
    const matchDiv = document.getElementById('passwordMatchMsg');
    if (confirmPass.length === 0) {
        matchDiv.innerHTML = '';
        return false;
    }
    if (newPass === confirmPass) {
        matchDiv.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Passwords match</span>';
        return true;
    } else {
        matchDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
        return false;
    }
}

$('#changePasswordForm').on('submit', function(e) {
    const newPass = document.getElementById('newPassword').value;
    const confirmPass = document.getElementById('confirmPassword').value;
    
    if (newPass.length < 6) {
        e.preventDefault();
        Swal.fire('Error', 'Password must be at least 6 characters', 'error');
        return false;
    }
    
    if (newPass !== confirmPass) {
        e.preventDefault();
        Swal.fire('Error', 'Passwords do not match', 'error');
        return false;
    }
});

// ============================================
// NOTIFICATION SETTINGS - AUTO SAVE
// ============================================
$(document).ready(function() {
    $('#emailNotifications, #smsNotifications').change(function() {
        const type = $(this).data('type');
        const isChecked = $(this).is(':checked');
        const status = isChecked ? 'enabled' : 'disabled';
        
        const $toggle = $(this);
        $toggle.prop('disabled', true);
        
        const $parent = $toggle.closest('.setting-control');
        const $loadingIcon = $('<i class="fas fa-spinner fa-spin ms-2" style="color: #6f42c1;"></i>');
        $parent.append($loadingIcon);
        
        $.ajax({
            url: 'ajax/save-notification-settings.php',
            method: 'POST',
            data: { type: type, status: status },
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                $loadingIcon.remove();
                $toggle.prop('disabled', false);
                
                if (response.success) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: type.toUpperCase() + ' ' + status
                    });
                } else {
                    $toggle.prop('checked', !isChecked);
                    Swal.fire('Error', response.message || 'Failed to save settings', 'error');
                }
            },
            error: function() {
                $loadingIcon.remove();
                $toggle.prop('disabled', false);
                $toggle.prop('checked', !isChecked);
                Swal.fire('Error', 'Failed to save settings', 'error');
            }
        });
    });
});

// ============================================
// DELETE ACCOUNT
// ============================================
function showDeleteConfirmation() {
    document.getElementById('deleteConfirm').value = '';
    $('#deleteAccountModal').modal('show');
}

function deleteAccount() {
    const confirmText = document.getElementById('deleteConfirm').value;
    
    if (confirmText === 'DELETE') {
        Swal.fire({
            title: 'Are you absolutely sure?',
            text: 'This action cannot be undone. Your account will be permanently deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete my account',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('confirmDeleteInput').value = confirmText;
                document.getElementById('deleteAccountForm').submit();
            }
        });
    } else {
        Swal.fire({
            icon: 'error',
            title: 'Confirmation Failed',
            text: 'Please type DELETE to confirm account deletion.',
            confirmButtonColor: '#6f42c1'
        });
    }
}

// ============================================
// SHOW NOTIFICATIONS (for staff)
// ============================================
function showNotifications() {
    <?php if ($user_role == 'staff'): ?>
    Swal.fire({
        title: '<i class="fas fa-bell me-2"></i> Notifications',
        html: `
            <div class="text-start">
                <p><strong>Total:</strong> <?php echo $total_records ?? 0; ?></p>
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
    <?php else: ?>
    Swal.fire({
        icon: 'info',
        title: 'Notifications',
        text: 'No notifications available',
        confirmButtonColor: '#6f42c1'
    });
    <?php endif; ?>
}
</script>

<?php
// Helper functions
function getAppointmentCount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result ? $result['count'] : 0;
}

function getCompletedCount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result ? $result['count'] : 0;
}

function getUpcomingCount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND appointment_date >= CURDATE() AND status IN ('pending', 'confirmed')");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result ? $result['count'] : 0;
}
?>
</body>
</html>