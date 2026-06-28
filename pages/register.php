<?php
session_start();

// Initialize variables
$errors = [];
$success = false;
$fullName = $email = $phone = $captchaInput = '';

// Generate CAPTCHA if not exists
if (!isset($_SESSION['captcha'])) {
    generateCaptcha();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $fullName = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $captchaInput = trim($_POST['captchaInput'] ?? '');
    $acceptTerms = isset($_POST['acceptTerms']);
    
    // Validate form data
    // Validate full name
    if (empty($fullName)) {
        $errors['fullName'] = 'Full name is required';
    } elseif (strlen($fullName) < 3) {
        $errors['fullName'] = 'Full name must be at least 3 characters';
    }
    
    // Validate email
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    // Validate phone
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required';
    } elseif (strlen($cleanPhone) !== 10) {
        $errors['phone'] = 'Please enter a valid 10-digit phone number';
    }
    
    // Validate password
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors['password'] = 'Password must contain at least one uppercase letter';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors['password'] = 'Password must contain at least one lowercase letter';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must contain at least one number';
    }
    
    // Validate confirm password
    if (empty($confirmPassword)) {
        $errors['confirmPassword'] = 'Please confirm your password';
    } elseif ($password !== $confirmPassword) {
        $errors['confirmPassword'] = 'Passwords do not match';
    }
    
    // Validate CAPTCHA - FIXED: Use case-insensitive comparison
    if (empty($captchaInput)) {
        $errors['captchaInput'] = 'CAPTCHA is required';
    } elseif (!isset($_SESSION['captcha']) || strtoupper($captchaInput) !== strtoupper($_SESSION['captcha'])) {
        $errors['captchaInput'] = 'CAPTCHA code is incorrect. Please try again.';
    }
    
    // Validate terms
    if (!$acceptTerms) {
        $errors['acceptTerms'] = 'You must accept the terms and conditions';
    }
    
    // If no errors, process registration
    if (empty($errors)) {
        // Generate password hash
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
    
        // Include the database connection
        require_once '../includes/dbcon.php';

        try {
            $pdo = getPDOConnection(); // Using function approach
            
            $stmt = $pdo->prepare('INSERT INTO reg (fName, email, contactNo, password,role,cStatus) VALUES (?, ?, ?, ?  , "user",1)');
            $stmt->execute([$fullName, $email, $cleanPhone, $hashedPassword]);
            
            $success = true;
        } catch (PDOException $e) {
            $errors['database'] = 'Registration failed. Please try again later.';
        }
        
        
        // For demo, simulate success
        $success = true;
        
        // Clear form data
        $fullName = $email = $phone = $captchaInput = '';
        
        // Generate new CAPTCHA for next registration
        generateCaptcha();
    }
}

// Function to generate CAPTCHA - FIXED: Return the generated CAPTCHA
function generateCaptcha() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Removed lowercase for consistency
    $captcha = '';
    for ($i = 0; $i < 5; $i++) {
        $captcha .= $chars[rand(0, strlen($chars) - 1)];
    }
    $_SESSION['captcha'] = $captcha; // Already uppercase
    return $captcha;
}

// Handle AJAX CAPTCHA refresh - FIXED: Proper JSON response
if (isset($_GET['refresh_captcha']) && $_GET['refresh_captcha'] == 'true') {
    $newCaptcha = generateCaptcha();
    header('Content-Type: application/json');
    echo json_encode(['captcha' => $newCaptcha]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Salon Elegance</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        :root {
            --primary-color: #9c27b0;
            --primary-dark: #7b1fa2;
            --secondary-color: #e91e63;
            --dark-bg: #121212;
            --card-bg: #1e1e1e;
            --card-bg-light: #252525;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --text-muted: #888888;
            --border-color: #333333;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --input-bg: #2d2d2d;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            /* height: 100vh; */
            /* display: flex; */
            /* align-items: center; */
            justify-content: center;
            /* background-color: var(--dark-bg);*/
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            /* background-image: radial-gradient(circle at 20% 30%, rgba(156, 39, 176, 0.1) 0%, transparent 20%),
                              radial-gradient(circle at 80% 80%, rgba(233, 30, 99, 0.1) 0%, transparent 20%);  */
        }
        
        .registration-container {
            width: 100%;
            max-width: 500px;
        }
        
        /* Header */
        .registration-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .salon-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        
        .logo-icon {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            box-shadow: 0 5px 15px rgba(156, 39, 176, 0.3);
        }
        
        .logo-icon i {
            font-size: 1.8rem;
            color: white;
        }
        
        .logo-text h1 {
            font-size: 2.2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.2rem;
        }
        
        .logo-text p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            letter-spacing: 1px;
        }
        
        /* Registration Card */
        .registration-card {
            background-color: var(--card-bg);
            border-radius: 1.2rem;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
        }
        
        .registration-title {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .registration-title h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        .registration-title p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }
        
        .required-star {
            color: var(--danger-color);
            margin-left: 3px;
        }
        
        .form-control {
            background-color: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.85rem 1rem;
            border-radius: 0.75rem;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .form-control:focus {
            background-color: var(--input-bg);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(156, 39, 176, 0.25);
            color: var(--text-primary);
        }
        
        .is-invalid {
            border-color: var(--danger-color) !important;
        }
        
        .is-valid {
            border-color: var(--success-color) !important;
        }
        
        .invalid-feedback {
            color: var(--danger-color);
            font-size: 0.85rem;
            margin-top: 0.4rem;
            display: block;
        }
        
        .input-group-text {
            background-color: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }
        
        /* Password Strength */
        .password-strength-container {
            margin-top: 0.5rem;
        }
        
        .strength-meter {
            height: 6px;
            background-color: var(--border-color);
            border-radius: 3px;
            margin-bottom: 6px;
            overflow: hidden;
        }
        
        .strength-meter-fill {
            height: 100%;
            width: 0%;
            border-radius: 3px;
            transition: width 0.3s, background-color 0.3s;
        }
        
        .strength-text {
            font-size: 0.85rem;
            display: flex;
            justify-content: space-between;
        }
        
        .strength-label {
            color: var(--text-secondary);
        }
        
        .strength-value {
            font-weight: 600;
        }
        
        .strength-weak {
            background-color: var(--danger-color);
            width: 25%;
        }
        
        .strength-fair {
            background-color: var(--warning-color);
            width: 50%;
        }
        
        .strength-good {
            background-color: #ff8c00;
            width: 75%;
        }
        
        .strength-strong {
            background-color: var(--success-color);
            width: 100%;
        }
        
        /* CAPTCHA Section */
        .captcha-container {
            background-color: var(--card-bg-light);
            border-radius: 0.75rem;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        
        .captcha-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .captcha-title {
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
        }
        
        .captcha-title i {
            margin-right: 8px;
            color: var(--primary-color);
        }
        
        .btn-refresh-captcha {
            background-color: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            outline: none;
        }
        
        .btn-refresh-captcha:hover {
            background-color: var(--primary-color);
            color: white;
            transform: rotate(90deg);
        }
        
        .captcha-display {
            background-color: #0f0f0f;
            color: #e0e0e0;
            font-size: 2rem;
            font-weight: bold;
            letter-spacing: 10px;
            padding: 1.2rem;
            text-align: center;
            border-radius: 0.75rem;
            margin-bottom: 1.2rem;
            font-family: 'Courier New', monospace;
            user-select: none;
            border: 1px solid var(--border-color);
        }
        
        /* Terms and Conditions */
        .terms-container {
            margin-bottom: 2rem;
        }
        
        .form-check {
            padding-left: 2rem;
        }
        
        .form-check-input {
            background-color: var(--input-bg);
            border: 1px solid var(--border-color);
            width: 1.2em;
            height: 1.2em;
            margin-left: -2rem;
            margin-top: 0.2em;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-check-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .form-check-label a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .form-check-label a:hover {
            text-decoration: underline;
        }
        
        /* Submit Button */
        .btn-register {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            padding: 1rem;
            font-weight: 600;
            border-radius: 0.75rem;
            width: 100%;
            transition: all 0.3s;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 1.5rem;
        }
        
        .btn-register:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(156, 39, 176, 0.4);
        }
        
        /* Login Link */
        .login-link-container {
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .login-link {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .login-link a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            margin-left: 5px;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        /* Password Toggle */
        .password-toggle {
            cursor: pointer;
            color: var(--text-secondary);
            transition: color 0.3s;
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            .registration-card {
                padding: 2rem 1.5rem;
            }
            
            .captcha-display {
                font-size: 1.7rem;
                letter-spacing: 8px;
                padding: 1rem;
            }
        }
        
        /* Loading Animation */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Success Alert */
        .alert-success {
            background-color: rgba(76, 175, 80, 0.1);
            border-color: rgba(76, 175, 80, 0.3);
            color: var(--success-color);
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <!-- Header -->
        <div class="registration-header">
            <div class="salon-logo">
                <div class="logo-icon">
                    <i class="fas fa-scissors"></i>
                </div>
                <div class="logo-text">
                    <h1>SalonElegance</h1>
                    <p>USER REGISTRATION</p>
                </div>
            </div>
        </div>
        
        <!-- Registration Card -->
        <div class="registration-card">
            <!-- Title -->
            <div class="registration-title">
                <h2>Create Account</h2>
                <p>Register to book appointments and enjoy exclusive benefits</p>
            </div>
            
            <!-- Success Message -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Registration successful!</strong> Your account has been created. You can now login.
                </div>
            <?php endif; ?>
            
            <!-- Registration Form -->
            <form method="POST" action="" id="registrationForm">
                <!-- Full Name -->
                <div class="form-group">
                    <label for="fullName" class="form-label">
                        <i class="fas fa-user me-2"></i> Full Name <span class="required-star">*</span>
                    </label>
                    <input type="text" class="form-control <?php echo isset($errors['fullName']) ? 'is-invalid' : ''; ?>" 
                           id="fullName" name="fullName" placeholder="Enter your full name" 
                           value="<?php echo htmlspecialchars($fullName); ?>" required>
                    <?php if (isset($errors['fullName'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['fullName']; ?></div>
                    <?php endif; ?>
                </div>
                
                <!-- Email -->
                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope me-2"></i> Email Address <span class="required-star">*</span>
                    </label>
                    <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                           id="email" name="email" placeholder="Enter your email address" 
                           value="<?php echo htmlspecialchars($email); ?>" required>
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>
                
                <!-- Contact Number -->
                <div class="form-group">
                    <label for="phone" class="form-label">
                        <i class="fas fa-phone me-2"></i> Contact Number <span class="required-star">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">+94</span>
                        <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                               id="phone" name="phone" placeholder="(123) 456-7890" 
                               value="<?php echo htmlspecialchars($phone); ?>" required>
                    </div>
                    <?php if (isset($errors['phone'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                    <?php endif; ?>
                </div>
                
                <!-- Password -->
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-key me-2"></i> Password <span class="required-star">*</span>
                    </label>
                    <div class="input-group">
                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                               id="password" name="password" placeholder="Create a strong password" required>
                        <span class="input-group-text password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    
                    <!-- Password Strength Meter -->
                    <div class="password-strength-container">
                        <div class="strength-meter">
                            <div class="strength-meter-fill" id="strengthMeter"></div>
                        </div>
                        <div class="strength-text">
                            <span class="strength-label">Password Strength:</span>
                            <span class="strength-value" id="strengthText">None</span>
                        </div>
                    </div>
                    
                    <?php if (isset($errors['password'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                    <?php endif; ?>
                </div>
                
                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="confirmPassword" class="form-label">
                        <i class="fas fa-check-double me-2"></i> Confirm Password <span class="required-star">*</span>
                    </label>
                    <div class="input-group">
                        <input type="password" class="form-control <?php echo isset($errors['confirmPassword']) ? 'is-invalid' : ''; ?>" 
                               id="confirmPassword" name="confirmPassword" placeholder="Re-enter your password" required>
                        <span class="input-group-text password-toggle" id="toggleConfirmPassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <?php if (isset($errors['confirmPassword'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['confirmPassword']; ?></div>
                    <?php endif; ?>
                </div>
                
                <!-- CAPTCHA -->
                <div class="captcha-container">
                    <div class="captcha-header">
                        <div class="captcha-title">
                            <i class="fas fa-robot"></i> Verify You're Human
                        </div>
                        <button type="button" class="btn-refresh-captcha" id="refreshCaptcha">
                            <i class="fas fa-redo"></i>
                        </button>
                    </div>
                    
                    <div class="captcha-display" id="captchaText"><?php echo isset($_SESSION['captcha']) ? $_SESSION['captcha'] : ''; ?></div>
                    
                    <div class="form-group mb-0">
                        <label for="captchaInput" class="form-label">Enter the code above</label>
                        <input type="text" class="form-control <?php echo isset($errors['captchaInput']) ? 'is-invalid' : ''; ?>" 
                               id="captchaInput" name="captchaInput" placeholder="Enter CAPTCHA code" maxlength="5" 
                               value="<?php echo htmlspecialchars($captchaInput); ?>" required>
                        <?php if (isset($errors['captchaInput'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['captchaInput']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Terms and Conditions -->
                <div class="terms-container">
                    <div class="form-check">
                        <input class="form-check-input <?php echo isset($errors['acceptTerms']) ? 'is-invalid' : ''; ?>" 
                               type="checkbox" id="acceptTerms" name="acceptTerms" value="1" 
                               <?php echo isset($_POST['acceptTerms']) ? 'checked' : ''; ?> required>
                        <label class="form-check-label" for="acceptTerms">
                            I agree to the <a href="#" onclick="showTerms(); return false;">Terms of Service</a> and <a href="#" onclick="showPrivacy(); return false;">Privacy Policy</a>
                        </label>
                        <?php if (isset($errors['acceptTerms'])): ?>
                            <div class="invalid-feedback" style="display: block;"><?php echo $errors['acceptTerms']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="btn btn-register" id="registerButton">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
                
                <!-- Login Link -->
                <div class="login-link-container">
                    <div class="login-link">
                        Already have an account?
                        <a href="login">Sign In</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Toggle password visibility
            $('#togglePassword').click(function() {
                const passwordInput = $('#password');
                const icon = $(this).find('i');
                
                if (passwordInput.attr('type') === 'password') {
                    passwordInput.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordInput.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            
            $('#toggleConfirmPassword').click(function() {
                const passwordInput = $('#confirmPassword');
                const icon = $(this).find('i');
                
                if (passwordInput.attr('type') === 'password') {
                    passwordInput.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordInput.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            
            // Phone number formatting
            $('#phone').on('input', function() {
                let phone = $(this).val().replace(/\D/g, '');
                
                if (phone.length > 10) {
                    phone = phone.substring(0, 10);
                }
                
                // Format as (123) 456-7890
                if (phone.length > 6) {
                    phone = '(' + phone.substring(0, 3) + ') ' + phone.substring(3, 6) + '-' + phone.substring(6);
                } else if (phone.length > 3) {
                    phone = '(' + phone.substring(0, 3) + ') ' + phone.substring(3);
                } else if (phone.length > 0) {
                    phone = '(' + phone;
                }
                
                $(this).val(phone);
            });
            
            // Password strength checker
            function checkPasswordStrength(password) {
                let strength = 0;
                
                // Check length
                if (password.length >= 8) strength += 1;
                
                // Check uppercase
                if (/[A-Z]/.test(password)) strength += 1;
                
                // Check lowercase
                if (/[a-z]/.test(password)) strength += 1;
                
                // Check number
                if (/[0-9]/.test(password)) strength += 1;
                
                // Check special character
                if (/[^A-Za-z0-9]/.test(password)) strength += 1;
                
                // Update strength meter
                const meter = $('#strengthMeter');
                const text = $('#strengthText');
                
                meter.removeClass('strength-weak strength-fair strength-good strength-strong');
                
                if (strength === 0) {
                    meter.css('width', '0%');
                    text.text('None').css('color', 'var(--text-secondary)');
                } else if (strength <= 2) {
                    meter.addClass('strength-weak');
                    text.text('Weak').css('color', 'var(--danger-color)');
                } else if (strength === 3) {
                    meter.addClass('strength-fair');
                    text.text('Fair').css('color', 'var(--warning-color)');
                } else if (strength === 4) {
                    meter.addClass('strength-good');
                    text.text('Good').css('color', '#ff8c00');
                } else {
                    meter.addClass('strength-strong');
                    text.text('Strong').css('color', 'var(--success-color)');
                }
                
                return strength >= 3; // At least fair strength
            }
            
            // Real-time password validation
            $('#password').on('input', function() {
                const password = $(this).val();
                const isStrongEnough = checkPasswordStrength(password);
                
                if (password.length > 0 && !isStrongEnough) {
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
                
                // Check password confirmation
                const confirmPassword = $('#confirmPassword').val();
                if (confirmPassword.length > 0) {
                    if (password !== confirmPassword) {
                        $('#confirmPassword').addClass('is-invalid');
                    } else {
                        $('#confirmPassword').removeClass('is-invalid');
                    }
                }
            });
            
            // Confirm password validation
            $('#confirmPassword').on('input', function() {
                const password = $('#password').val();
                const confirmPassword = $(this).val();
                
                if (confirmPassword.length > 0 && password !== confirmPassword) {
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
            
            // CAPTCHA refresh - FIXED: Proper AJAX call
            $('#refreshCaptcha').click(function() {
                const $btn = $(this);
                $btn.prop('disabled', true);
                
                $.ajax({
                    url: window.location.href,
                    type: 'GET',
                    data: { refresh_captcha: 'true' },
                    dataType: 'json',
                    success: function(response) {
                        if (response && response.captcha) {
                            $('#captchaText').text(response.captcha);
                            $('#captchaInput').val('').removeClass('is-invalid is-valid');
                            
                            // Add animation effect
                            $btn.css('transform', 'rotate(360deg)');
                            setTimeout(() => {
                                $btn.css('transform', '');
                            }, 300);
                        } else {
                            console.error('Invalid CAPTCHA response:', response);
                            alert('Error refreshing CAPTCHA. Please try again.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                        alert('Error refreshing CAPTCHA. Please try again.');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });
            
            // CAPTCHA input validation - FIXED: Case insensitive
            $('#captchaInput').on('input', function() {
                const input = $(this).val().toUpperCase();
                const currentCaptcha = $('#captchaText').text().toUpperCase();
                
                if (input.length === 5) {
                    if (input === currentCaptcha) {
                        $(this).removeClass('is-invalid').addClass('is-valid');
                    } else {
                        $(this).addClass('is-invalid').removeClass('is-valid');
                    }
                } else {
                    $(this).removeClass('is-invalid is-valid');
                }
            });
            
            // Form validation on blur
            $('#fullName').on('blur', function() {
                const value = $(this).val().trim();
                if (value.length >= 3) {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                } else if (value.length > 0) {
                    $(this).addClass('is-invalid').removeClass('is-valid');
                }
            });
            
            $('#email').on('blur', function() {
                const value = $(this).val().trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (emailRegex.test(value)) {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                } else if (value.length > 0) {
                    $(this).addClass('is-invalid').removeClass('is-valid');
                }
            });
            
            // Show SweetAlert on page load if there are errors
            <?php if (!empty($errors) && !$success): ?>
                setTimeout(function() {
                    Swal.fire({
                        title: 'Please fix the errors',
                        html: `
                            <div style="text-align: left;">
                                <p>There are errors in the form:</p>
                                <ul style="margin-bottom: 0;">
                                    <?php foreach($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        `,
                        icon: 'error',
                        confirmButtonColor: 'var(--danger-color)',
                        background: 'var(--card-bg)',
                        color: 'var(--text-primary)'
                    });
                }, 100);
            <?php endif; ?>
            
            // Show success message with SweetAlert if registered successfully
            <?php if ($success): ?>
                setTimeout(function() {
                    Swal.fire({
                        title: 'Registration Successful!',
                        html: `
                            <div style="text-align: left;">
                                <p>Your account has been created successfully!</p>
                                <div style="background-color: var(--card-bg-light); padding: 1rem; border-radius: 0.5rem; margin-top: 1rem;">
                                    <p style="margin-bottom: 0.5rem;"><strong>Welcome to SalonElegance!</strong></p>
                                    <p style="font-size: 0.9rem; margin-bottom: 0;">
                                        You can now login with your credentials.
                                    </p>
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: 'var(--primary-color)',
                        confirmButtonText: 'Continue to Login',
                        background: 'var(--card-bg)',
                        color: 'var(--text-primary)',
                        showCancelButton: false,
                        allowOutsideClick: false,
                        allowEscapeKey: false
}).then((result) => {
    if (result.isConfirmed) {
        // Redirect to login page
        window.location.href = 'login';
    }
                        
                    });
                }, 100);
            <?php endif; ?>
            
            // Form submission with validation - FIXED: CAPTCHA case handling
            $('#registrationForm').on('submit', function(e) {
                let isValid = true;
                
                // Validate all fields
                const fullName = $('#fullName').val().trim();
                if (fullName.length < 3) {
                    $('#fullName').addClass('is-invalid');
                    isValid = false;
                }
                
                const email = $('#email').val().trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    $('#email').addClass('is-invalid');
                    isValid = false;
                }
                
                const phone = $('#phone').val().replace(/\D/g, '');
                if (phone.length !== 10) {
                    $('#phone').addClass('is-invalid');
                    isValid = false;
                }
                
                const password = $('#password').val();
                if (!checkPasswordStrength(password) || password.length < 8) {
                    $('#password').addClass('is-invalid');
                    isValid = false;
                }
                
                const confirmPassword = $('#confirmPassword').val();
                if (password !== confirmPassword) {
                    $('#confirmPassword').addClass('is-invalid');
                    isValid = false;
                }
                
                // FIXED: Use case-insensitive comparison for CAPTCHA
                const captchaInput = $('#captchaInput').val().toUpperCase();
                const currentCaptcha = $('#captchaText').text().toUpperCase();
                if (captchaInput !== currentCaptcha) {
                    $('#captchaInput').addClass('is-invalid');
                    isValid = false;
                }
                
                if (!$('#acceptTerms').is(':checked')) {
                    $('#acceptTerms').addClass('is-invalid');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Please fix the errors',
                        text: 'There are validation errors in the form. Please check all fields.',
                        icon: 'error',
                        confirmButtonColor: 'var(--danger-color)',
                        background: 'var(--card-bg)',
                        color: 'var(--text-primary)'
                    });
                } else {
                    // Show loading state on submit button
                    $('#registerButton').html('<span class="spinner"></span> Creating Account...').prop('disabled', true);
                }
            });
            
            // Auto-advance to next input on Enter key
            $('input.form-control').keydown(function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const inputs = $('input.form-control');
                    const index = inputs.index(this) + 1;
                    if (index < inputs.length) {
                        inputs.eq(index).focus();
                    } else {
                        $('#registerButton').focus();
                    }
                }
            });
        });
        
        // Terms and Privacy modal functions
        function showTerms() {
            Swal.fire({
                title: 'Terms of Service',
                html: `
                    <div style="text-align: left; max-height: 300px; overflow-y: auto;">
                        <h5>1. Acceptance of Terms</h5>
                        <p>By registering with SalonElegance, you agree to these terms and conditions.</p>
                        
                        <h5>2. User Account</h5>
                        <p>You are responsible for maintaining the confidentiality of your account and password.</p>
                        
                        <h5>3. Booking Policy</h5>
                        <p>Appointments must be cancelled at least 24 hours in advance to avoid cancellation fees.</p>
                        
                        <h5>4. Privacy</h5>
                        <p>We respect your privacy and will protect your personal information.</p>
                        
                        <h5>5. Changes to Terms</h5>
                        <p>We reserve the right to modify these terms at any time.</p>
                    </div>
                `,
                width: 600,
                background: 'var(--card-bg)',
                color: 'var(--text-primary)',
                confirmButtonColor: 'var(--primary-color)'
            });
        }
        
        function showPrivacy() {
            Swal.fire({
                title: 'Privacy Policy',
                html: `
                    <div style="text-align: left; max-height: 300px; overflow-y: auto;">
                        <h5>1. Information We Collect</h5>
                        <p>We collect personal information you provide when registering and booking appointments.</p>
                        
                        <h5>2. How We Use Information</h5>
                        <p>We use your information to provide services, process appointments, and send notifications.</p>
                        
                        <h5>3. Data Protection</h5>
                        <p>We implement security measures to protect your personal information.</p>
                        
                        <h5>4. Third-Party Disclosure</h5>
                        <p>We do not sell or share your personal information with third parties without your consent.</p>
                        
                        <h5>5. Your Rights</h5>
                        <p>You have the right to access, correct, or delete your personal information.</p>
                    </div>
                `,
                width: 600,
                background: 'var(--card-bg)',
                color: 'var(--text-primary)',
                confirmButtonColor: 'var(--primary-color)'
            });
        }
    </script>
</body>
</html>