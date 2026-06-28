<?php
ob_start();
session_start();

// Check if user is in temporary session
if (!isset($_SESSION['temp_user_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../includes/dbcon.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please enter new password';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        try {
            $pdo = getPDOConnection();
            $user_id = $_SESSION['temp_user_id'];
            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $query = "UPDATE reg SET password = ?, password_changed = 1, modifyDate = NOW() WHERE regID = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$hashed_password, $user_id]);
            
            // Set session variables
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = 'staff';
            
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['temp_user_name']);
            
            header('Location: admin/dashboard.php');
            exit();
        } catch (PDOException $e) {
            error_log('Password change error: ' . $e->getMessage());
            $error = 'Failed to change password. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Salon Elegance</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .change-password-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
        }
        .btn-update {
            background: #6f42c1;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
        }
        .btn-update:hover {
            background: #5a32a0;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
            transition: all 0.3s;
        }
    </style>
</head>
<body>
    <div class="change-password-card">
        <div class="text-center mb-4">
            <i class="fas fa-key fa-3x" style="color: #6f42c1;"></i>
            <h2 class="mt-2">Change Your Password</h2>
            <p class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['temp_user_name'] ?? 'Staff'); ?>!</p>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                You are required to change your default password before continuing.
            </div>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="passwordForm">
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" name="new_password" id="newPassword" required minlength="6">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('newPassword')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="password-strength" id="passwordStrength"></div>
                <small class="text-muted">Minimum 6 characters</small>
            </div>
            
            <div class="mb-4">
                <label class="form-label">Confirm New Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                    <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmPassword')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div id="passwordMatch" class="small mt-1"></div>
            </div>
            
            <div class="alert alert-info small">
                <i class="fas fa-info-circle me-2"></i>
                Password requirements:
                <ul class="mb-0 mt-1">
                    <li>Minimum 6 characters</li>
                    <li>Use a mix of letters and numbers for better security</li>
                </ul>
            </div>
            
            <button type="submit" class="btn btn-update text-white">Update Password & Continue</button>
        </form>
    </div>
    
    <script>
        document.getElementById('newPassword').addEventListener('keyup', function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);
            updateStrengthIndicator(strength);
        });
        
        document.getElementById('confirmPassword').addEventListener('keyup', function() {
            const newPass = document.getElementById('newPassword').value;
            const confirmPass = this.value;
            checkMatch(newPass, confirmPass);
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
                indicator.style.background = colors[strength - 1];
                indicator.style.width = widths[strength - 1];
                indicator.innerHTML = `<span class="small text-muted">${texts[strength - 1]}</span>`;
            } else {
                indicator.style.background = '#e9ecef';
                indicator.style.width = '0%';
                indicator.innerHTML = '';
            }
        }
        
        function checkMatch(newPass, confirmPass) {
            const matchDiv = document.getElementById('passwordMatch');
            if (confirmPass.length === 0) {
                matchDiv.innerHTML = '';
                return;
            }
            if (newPass === confirmPass) {
                matchDiv.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Passwords match</span>';
            } else {
                matchDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
            }
        }
        
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const type = field.type === 'password' ? 'text' : 'password';
            field.type = type;
        }
        
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
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
    </script>
</body>
</html>