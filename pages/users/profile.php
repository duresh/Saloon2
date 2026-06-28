<?php
ob_start();
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Include database connection
require_once '../../includes/dbcon.php';
require_once '../../includes/helpers.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$profile_image = '';

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
    
    // Get additional profile details if exists
    $profile_query = "SELECT * FROM user_profiles WHERE user_id = ?";
    $profile_stmt = $pdo->prepare($profile_query);
    $profile_stmt->execute([$user_id]);
    $profile = $profile_stmt->fetch();
    
    // If profile doesn't exist, create it
    if (!$profile) {
        $create_profile = "INSERT INTO user_profiles (user_id, notification_email, notification_sms, created_at) VALUES (?, 1, 0, NOW())";
        $create_stmt = $pdo->prepare($create_profile);
        $create_stmt->execute([$user_id]);
        
        // Refresh profile data
        $profile_stmt->execute([$user_id]);
        $profile = $profile_stmt->fetch();
    }
    
    // Set notification values with defaults
    $notification_email = $profile['notification_email'] ?? 1;
    $notification_sms = $profile['notification_sms'] ?? 0;
    
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
                        
                        if ($profile) {
                            $update_photo = "UPDATE user_profiles SET profile_image = ?, updated_at = NOW() WHERE user_id = ?";
                            $stmt = $pdo->prepare($update_photo);
                            $stmt->execute([$new_filename, $user_id]);
                        } else {
                            $insert_photo = "INSERT INTO user_profiles (user_id, profile_image, created_at) VALUES (?, ?, NOW())";
                            $stmt = $pdo->prepare($insert_photo);
                            $stmt->execute([$user_id, $new_filename]);
                        }
                        
                        $success = 'Profile photo updated successfully!';
                        $profile_stmt->execute([$user_id]);
                        $profile = $profile_stmt->fetch();
                        $notification_email = $profile['notification_email'] ?? 1;
                        $notification_sms = $profile['notification_sms'] ?? 0;
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
                    
                    if ($profile) {
                        $update_profile = "UPDATE user_profiles SET address = ?, bio = ?, updated_at = NOW() WHERE user_id = ?";
                        $stmt = $pdo->prepare($update_profile);
                        $stmt->execute([$address, $bio, $user_id]);
                    } else {
                        $insert_profile = "INSERT INTO user_profiles (user_id, address, bio, created_at) VALUES (?, ?, ?, NOW())";
                        $stmt = $pdo->prepare($insert_profile);
                        $stmt->execute([$user_id, $address, $bio]);
                    }
                    
                    $success = 'Profile updated successfully!';
                    
                    $user_stmt->execute([$user_id]);
                    $user = $user_stmt->fetch();
                    $profile_stmt->execute([$user_id]);
                    $profile = $profile_stmt->fetch();
                    $notification_email = $profile['notification_email'] ?? 1;
                    $notification_sms = $profile['notification_sms'] ?? 0;
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
            
            // Refresh values
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
                $update_pass = "UPDATE reg SET password = ?, modifyDate = NOW() WHERE regID = ?";
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

// Determine if user is admin for navigation
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
$profile_image_path = !empty($profile['profile_image']) ? '../../uploads/profile/' . $profile['profile_image'] : '../../assets/images/default-avatar.png';

include 'header/headerBooking.php';
?>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <div class="profile-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-user-circle me-2"></i> My Profile</h1>
                    <p class="lead mb-0">Manage your personal information and account settings</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="<?php echo $is_admin ? '../admin/dashboard.php' : 'dashboard.php'; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Left Column - Profile Photo & Quick Info -->
            <div class="col-lg-4">
                <div class="profile-card">
                    <div class="profile-photo-container">
                        <div class="profile-photo">
                            <img src="<?php echo $profile_image_path; ?>" alt="Profile Photo" id="profilePhoto">
                            <div class="photo-overlay" onclick="$('#photoUpload').click()">
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
                        <span class="badge <?php echo $user['role'] == 'admin' ? 'bg-danger' : 'bg-primary'; ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </div>
                    
                    <hr>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo getAppointmentCount($pdo, $user_id); ?></div>
                            <div class="stat-label">Total Appointments</div>
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
                
                <!-- Profile Photo Upload Form -->
                <form method="POST" enctype="multipart/form-data" id="photoUploadForm" class="mt-3">
                    <input type="file" name="profile_photo" id="photoUpload" accept="image/*" style="display: none;" onchange="uploadPhoto()">
                    <input type="hidden" name="upload_photo" value="1">
                </form>
            </div>
            
            <!-- Right Column - Profile Settings -->
            <div class="col-lg-8">
                <!-- Personal Information -->
                <div class="profile-card">
                    <h4><i class="fas fa-user-edit me-2"></i> Personal Information</h4>
                    <form method="POST" action="" class="mt-3">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="fName" value="<?php echo htmlspecialchars($user['fName']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address *</label>
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
                
                <!-- Notification Settings -->
<div class="profile-card">
    <h4><i class="fas fa-bell me-2"></i> Notification Settings</h4>
    <div class="mt-3">
        <div class="setting-item">
            <div class="setting-info">
                <strong>Email Notifications</strong>
                <p class="text-muted small">Receive email notifications about your appointments</p>
            </div>
            <div class="setting-control">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="emailNotifications" 
                           data-type="email" <?php echo ($notification_email == 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="emailNotifications">Email Notifications</label>
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
                    <label class="form-check-label" for="smsNotifications">SMS Alerts</label>
                </div>
            </div>
        </div>
    </div>
</div>
                
                <!-- Change Password -->
<div class="profile-card">
    <h4><i class="fas fa-key me-2"></i> Change Password</h4>
    <form method="POST" action="" class="mt-3" id="changePasswordForm">
        <div class="row">
            <div class="col-12 mb-3">
                <label class="form-label">Current Password *</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" name="current_password" id="currentPassword" required>
                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="currentPassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small class="text-muted">Enter your current password to verify your identity</small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">New Password *</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                    <input type="password" class="form-control" name="new_password" id="newPassword" required minlength="6">
                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="newPassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="password-strength mt-2" id="passwordStrength"></div>
                <small class="text-muted">Minimum 6 characters. Use a mix of letters, numbers, and symbols for better security.</small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Confirm New Password *</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                    <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required>
                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirmPassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="password-match-status mt-2" id="passwordMatchStatus"></div>
            </div>
        </div>
        <div class="text-end">
            <button type="button" class="btn btn-warning" id="changePasswordBtn">
                <i class="fas fa-key me-2"></i>Change Password
            </button>
        </div>
    </form>
</div>
                
                <!-- Account Settings & Danger Zone -->
                <div class="profile-card">
                    <h4><i class="fas fa-cog me-2"></i> Account Settings</h4>
                    
                    <div class="danger-zone">
                        <h5 class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i> Danger Zone</h5>
                        <p class="text-muted small">Once you delete your account, there is no going back. Please be certain.</p>
                        <button type="button" class="btn btn-danger" onclick="showDeleteConfirmation()">
                            <i class="fas fa-trash-alt me-2"></i>Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Confirmation Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Delete Account</h5>
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

<?php include 'footer/footer.php'; ?>

<script>
// Profile photo upload
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
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'File Too Large',
                text: 'Maximum file size is 5MB.',
                confirmButtonColor: '#6f42c1'
            });
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

// Show delete confirmation modal
function showDeleteConfirmation() {
    $('#deleteAccountModal').modal('show');
}

// Delete account
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
// AUTO-SAVE NOTIFICATION SETTINGS
// ============================================

$(document).ready(function() {
    // Load initial notification states from PHP
    const emailNotify = <?php echo $notification_email; ?>;
    const smsNotify = <?php echo $notification_sms; ?>;
    
    $('#emailNotifications').prop('checked', emailNotify === 1);
    $('#smsNotifications').prop('checked', smsNotify === 1);
    
    // Auto-save on toggle change
    $('#emailNotifications, #smsNotifications').change(function() {
        const type = $(this).data('type');
        const isChecked = $(this).is(':checked');
        const status = isChecked ? 'enabled' : 'disabled';
        
        // Show temporary loading state on the toggle
        const $toggle = $(this);
        $toggle.prop('disabled', true);
        
        // Add a small loading indicator
        const $parent = $toggle.closest('.setting-control');
        const $loadingIcon = $('<i class="fas fa-spinner fa-spin ms-2" style="color: #6f42c1;"></i>');
        $parent.append($loadingIcon);
        
        // Make AJAX request to save
        $.ajax({
            url: 'ajax/save-notification-settings.php',
            method: 'POST',
            data: {
                type: type,
                status: status
            },
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                // Remove loading indicator
                $loadingIcon.remove();
                $toggle.prop('disabled', false);
                
                if (response.success) {
                    // Show success toast
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer);
                            toast.addEventListener('mouseleave', Swal.resumeTimer);
                        }
                    });
                    
                    Toast.fire({
                        icon: 'success',
                        title: response.message || `${type.toUpperCase()} ${status}`
                    });
                    
                    // Update the data attribute for consistency
                    $toggle.attr('data-status', status);
                    
                } else {
                    // Revert the toggle if save failed
                    $toggle.prop('checked', !isChecked);
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Update Failed',
                        text: response.message || 'Failed to save notification settings',
                        confirmButtonColor: '#6f42c1',
                        toast: true,
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
            },
            error: function(xhr, status, error) {
                // Remove loading indicator
                $loadingIcon.remove();
                $toggle.prop('disabled', false);
                
                // Revert the toggle
                $toggle.prop('checked', !isChecked);
                
                console.error('AJAX Error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                
                let errorMsg = 'Failed to save settings. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: errorMsg,
                    confirmButtonColor: '#6f42c1',
                    toast: true,
                    timer: 3000,
                    showConfirmButton: false
                });
            }
        });
    });
});

// Profile photo upload
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
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'File Too Large',
                text: 'Maximum file size is 5MB.',
                confirmButtonColor: '#6f42c1'
            });
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

// Show delete confirmation modal
function showDeleteConfirmation() {
    $('#deleteAccountModal').modal('show');
}

// Delete account
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

function showLoading() {
    Swal.fire({
        title: 'Saving...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

function hideLoading() {
    Swal.close();
}
// ============================================
// CHANGE PASSWORD VALIDATION WITH SWEETALERT
// ============================================

$(document).ready(function() {
    // Password strength meter
    $('#newPassword').on('keyup', function() {
        const password = $(this).val();
        const strength = checkPasswordStrength(password);
        updatePasswordStrengthUI(strength);
    });
    
    // Password match check
    $('#confirmPassword').on('keyup', function() {
        const newPassword = $('#newPassword').val();
        const confirmPassword = $(this).val();
        checkPasswordMatch(newPassword, confirmPassword);
    });
    
    // Toggle password visibility
    $('.toggle-password').click(function() {
        const target = $(this).data('target');
        const input = $('#' + target);
        const type = input.attr('type') === 'password' ? 'text' : 'password';
        input.attr('type', type);
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });
    
    // Change password button click
    $('#changePasswordBtn').click(function() {
        validateAndChangePassword();
    });
    
    // Allow Enter key submission
    $('#changePasswordForm input').keypress(function(e) {
        if (e.which === 13) {
            e.preventDefault();
            validateAndChangePassword();
        }
    });
});

// Check password strength
function checkPasswordStrength(password) {
    let strength = 0;
    
    if (password.length === 0) return 0;
    
    // Length check
    if (password.length >= 6) strength += 1;
    if (password.length >= 8) strength += 1;
    
    // Contains lowercase
    if (/[a-z]/.test(password)) strength += 1;
    
    // Contains uppercase
    if (/[A-Z]/.test(password)) strength += 1;
    
    // Contains numbers
    if (/[0-9]/.test(password)) strength += 1;
    
    // Contains special characters
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength += 1;
    
    // Max strength is 6
    return Math.min(strength, 6);
}

// Update password strength UI
function updatePasswordStrengthUI(strength) {
    const strengthMap = {
        0: { text: 'Very Weak', class: 'strength-0', width: '10%', color: '#dc3545' },
        1: { text: 'Weak', class: 'strength-1', width: '25%', color: '#ff6b6b' },
        2: { text: 'Fair', class: 'strength-2', width: '40%', color: '#ffc107' },
        3: { text: 'Good', class: 'strength-3', width: '55%', color: '#ffc107' },
        4: { text: 'Strong', class: 'strength-4', width: '70%', color: '#28a745' },
        5: { text: 'Very Strong', class: 'strength-5', width: '85%', color: '#28a745' },
        6: { text: 'Excellent', class: 'strength-6', width: '100%', color: '#28a745' }
    };
    
    const strengthInfo = strengthMap[strength];
    
    if (strength > 0) {
        const html = `
            <div class="strength-meter">
                <div class="strength-bar" style="width: ${strengthInfo.width}; background: ${strengthInfo.color};"></div>
            </div>
            <div class="strength-text ${strengthInfo.class}">${strengthInfo.text}</div>
        `;
        $('#passwordStrength').html(html);
    } else {
        $('#passwordStrength').html('');
    }
}

// Check password match
function checkPasswordMatch(newPassword, confirmPassword) {
    if (confirmPassword.length === 0) {
        $('#passwordMatchStatus').html('');
        return;
    }
    
    if (newPassword === confirmPassword) {
        $('#passwordMatchStatus').html(`
            <div class="match-success">
                <i class="fas fa-check-circle"></i> Passwords match
            </div>
        `);
        return true;
    } else {
        $('#passwordMatchStatus').html(`
            <div class="match-error">
                <i class="fas fa-times-circle"></i> Passwords do not match
            </div>
        `);
        return false;
    }
}

// Validate and change password
function validateAndChangePassword() {
    const currentPassword = $('#currentPassword').val();
    const newPassword = $('#newPassword').val();
    const confirmPassword = $('#confirmPassword').val();
    const errors = [];
    
    // Validate current password
    if (!currentPassword) {
        errors.push('Current password is required');
    }
    
    // Validate new password
    if (!newPassword) {
        errors.push('New password is required');
    } else if (newPassword.length < 6) {
        errors.push('New password must be at least 6 characters long');
    }
    
    // Check if new password is same as current (we'll check on server)
    
    // Validate password match
    if (newPassword !== confirmPassword) {
        errors.push('New passwords do not match');
    }
    
    // Additional password strength recommendation (not required, just warning)
    const strength = checkPasswordStrength(newPassword);
    let strengthWarning = '';
    if (newPassword && strength < 3) {
        strengthWarning = '<div class="alert alert-warning mt-2 small">⚠️ Consider using a stronger password for better security.</div>';
    }
    
    // If there are validation errors, show SweetAlert
    if (errors.length > 0) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            html: `
                <div class="text-start">
                    <p>Please fix the following errors:</p>
                    <ul class="mb-0">
                        ${errors.map(err => `<li>${err}</li>`).join('')}
                    </ul>
                </div>
            `,
            confirmButtonColor: '#6f42c1',
            confirmButtonText: 'OK'
        });
        return false;
    }
    
    // Show confirmation dialog with password strength
    Swal.fire({
        title: 'Change Password?',
        html: `
            <div class="text-start">
                <p>Are you sure you want to change your password?</p>
                <div class="alert alert-info mt-2">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Security Tips:</strong>
                    <ul class="mt-2 mb-0">
                        <li>Use a unique password not used elsewhere</li>
                        <li>Don't share your password with anyone</li>
                        <li>Consider using a password manager</li>
                    </ul>
                </div>
                ${strengthWarning}
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Change Password',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return new Promise((resolve, reject) => {
                // Show loading
                Swal.showLoading();
                
                // Submit via AJAX
                $.ajax({
                    url: 'ajax/change-password.php',
                    method: 'POST',
                    data: {
                        current_password: currentPassword,
                        new_password: newPassword,
                        confirm_password: confirmPassword
                    },
                    dataType: 'json',
                    timeout: 10000,
                    success: function(response) {
                        if (response.success) {
                            resolve(response);
                        } else {
                            reject(new Error(response.message || 'Failed to change password'));
                        }
                    },
                    error: function(xhr, status, error) {
                        let errorMsg = 'Connection error. Please try again.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        reject(new Error(errorMsg));
                    }
                });
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value.success) {
            Swal.fire({
                icon: 'success',
                title: 'Password Changed!',
                html: `
                    <div class="text-center">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <p>Your password has been changed successfully.</p>
                        <p class="small text-muted">Please use your new password for future logins.</p>
                    </div>
                `,
                confirmButtonColor: '#6f42c1',
                confirmButtonText: 'OK'
            }).then(() => {
                // Clear the form
                $('#currentPassword').val('');
                $('#newPassword').val('');
                $('#confirmPassword').val('');
                $('#passwordStrength').html('');
                $('#passwordMatchStatus').html('');
            });
        }
    }).catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Password Change Failed',
            text: error.message || 'An error occurred while changing your password',
            confirmButtonColor: '#6f42c1',
            confirmButtonText: 'Try Again'
        });
    });
}

// Add CSS for password strength meter
const style = document.createElement('style');
style.textContent = `
    .strength-meter {
        height: 4px;
        background: #e9ecef;
        border-radius: 2px;
        overflow: hidden;
        margin: 5px 0;
    }
    
    .strength-bar {
        height: 100%;
        transition: width 0.3s ease;
        border-radius: 2px;
    }
    
    .strength-text {
        font-size: 11px;
        margin-top: 3px;
    }
    
    .strength-text.strength-0 { color: #dc3545; }
    .strength-text.strength-1 { color: #ff6b6b; }
    .strength-text.strength-2 { color: #ffc107; }
    .strength-text.strength-3 { color: #ffc107; }
    .strength-text.strength-4 { color: #28a745; }
    .strength-text.strength-5 { color: #28a745; }
    .strength-text.strength-6 { color: #28a745; }
    
    .match-success {
        color: #28a745;
        font-size: 11px;
        margin-top: 3px;
    }
    
    .match-success i {
        margin-right: 3px;
    }
    
    .match-error {
        color: #dc3545;
        font-size: 11px;
        margin-top: 3px;
    }
    
    .match-error i {
        margin-right: 3px;
    }
    
    .toggle-password {
        cursor: pointer;
    }
    
    .toggle-password:hover {
        background: #e9ecef;
    }
`;
document.head.appendChild(style);

</script>


<style>
/* Profile Page Styles */
.profile-container {
    max-width: 1400px;
    margin: 0 auto;
}

.profile-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

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
    font-size: 24px;
    font-weight: 700;
    color: #6f42c1;
}

.stat-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
}

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

.danger-zone {
    background: #fff5f5;
    border: 1px solid #f8d7da;
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
}

.danger-zone h5 {
    margin-bottom: 10px;
}

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

@media (max-width: 768px) {
    .profile-card {
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
}
</style>

<?php
// Helper functions
function getAppointmentCount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch()['count'];
}

function getCompletedCount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    return $stmt->fetch()['count'];
}

function getUpcomingCount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND appointment_date >= CURDATE() AND status IN ('pending', 'confirmed')");
    $stmt->execute([$user_id]);
    return $stmt->fetch()['count'];
}
?>