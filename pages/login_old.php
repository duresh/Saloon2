<?php 
ob_start();
session_start();
session_destroy();
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salon Elegance | Admin Login</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="../css/sweetalert2.min.css">
    
    <style>
        :root {
            --primary-color: #6f42c1;
            --secondary-color: #fd7e14;
            --dark-bg: #121826;
            --card-bg: #1e293b;
            --text-color: #f8f9fa;
            --text-muted: #94a3b8;
            --border-color: #334155;
        }
        
        body {
            background-color: var(--dark-bg);
            color: var(--text-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: linear-gradient(rgba(18, 24, 38, 0.9), rgba(18, 24, 38, 0.9)), 
                              url('https://images.unsplash.com/photo-1560066984-138dadb4c035?ixlib=rb-4.0.3&auto=format&fit=crop&w=1367&q=80');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        
        .login-card {
            background-color: var(--card-bg);
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-color), #8a63d2);
            padding: 2.5rem 1.5rem 1.5rem;
            text-align: center;
            position: relative;
        }
        
        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        
        .login-logo-icon {
            width: 60px;
            height: 60px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .login-logo-icon i {
            font-size: 1.8rem;
            color: white;
        }
        
        .login-logo-text h2 {
            color: white;
            font-weight: 700;
            margin: 0;
            text-align: left;
        }
        
        .login-logo-text p {
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
            font-size: 0.9rem;
            text-align: left;
        }
        
        .login-body {
            padding: 2rem 1.5rem 1.5rem;
        }
        
        .form-label {
            color: var(--text-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            background-color: rgba(255, 255, 255, 0.08);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(111, 66, 193, 0.25);
            color: var(--text-color);
        }
        
        .input-group-text {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), #8a63d2);
            border: none;
            color: white;
            padding: 0.75rem;
            font-weight: 600;
            border-radius: 0.5rem;
            width: 100%;
            transition: all 0.3s;
            margin-top: 1rem;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #5e32a6, var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(111, 66, 193, 0.4);
        }
        
        .login-footer {
            padding: 1.5rem;
            text-align: center;
            border-top: 1px solid var(--border-color);
            background-color: rgba(0, 0, 0, 0.1);
        }
        
        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .login-footer a:hover {
            color: #8a63d2;
            text-decoration: underline;
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-check-label {
            color: var(--text-muted);
        }
        
        .error-message {
            color: #ff6b6b;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }
        
        .password-toggle {
            cursor: pointer;
            color: var(--text-muted);
            transition: color 0.3s;
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
        }
        
        .login-features {
            display: flex;
            justify-content: space-around;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .feature-item {
            text-align: center;
            padding: 0 0.5rem;
        }
        
        .feature-icon {
            width: 40px;
            height: 40px;
            background-color: rgba(111, 66, 193, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            color: var(--primary-color);
        }
        
        .feature-text {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        @media (max-width: 576px) {
            .login-container {
                padding: 15px;
            }
            
            .login-header {
                padding: 2rem 1rem 1.5rem;
            }
            
            .login-body {
                padding: 1.5rem 1rem 1rem;
            }
            
            .remember-forgot {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .remember-forgot .form-check {
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Login Header -->
            <div class="login-header">
                <div class="login-logo">
                    <div class="login-logo-icon">
                        <i class="fas fa-scissors"></i>
                    </div>
                    <div class="login-logo-text">
                        <h2>Salon Elegance</h2>
                        <p>Admin Dashboard Login</p>
                    </div>
                </div>
            </div>
            
            <!-- Login Body -->
            <div class="login-body">
                <form method="post" action="" name="login" id="loginfrm">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username or Email</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username or email" required>
                        </div>
                        <div class="error-message" id="usernameError">Please enter a valid username or email</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                            <span class="input-group-text password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <div class="error-message" id="passwordError">Password must be at least 6 characters</div>
                    </div>
                    
                    <div class="remember-forgot">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rememberMe" name="rememberMe">
                            <label class="form-check-label" for="rememberMe">
                                Remember me
                            </label>
                        </div>
                        <a href="#" id="forgotPassword">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-login" id="loginButton" name="Login">
                        <i class="fas fa-sign-in-alt me-2"></i> Login to Dashboard
                    </button>
                    
                    
                </form>
                
                
            </div>
            
            <!-- Login Footer -->
            <div class="login-footer">
                <small>
                    &copy; 2026 Salon Elegance Admin Dashboard. 
                    <a href="#" class="ms-1">Privacy Policy</a> | 
                    <a href="register.php" class="ms-1">Register Here</a>
                </small>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="../js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="../js/jquery-3.7.1.min.js"></script>

<?php 
if(isset($_POST['Login'])){
    if(!empty($_POST['rememberMe'])){            
            setcookie('user',$_POST['username'],time() + 3600);
            setcookie('pass',$_POST['password'],time() + 3600);
        }
        else{
            setcookie('user',"");
            setcookie('pass',"");
        }
    // pass textbox data to variables
   $_user = trim($_POST['username'] ?? '');
   $_pass = $_POST['password'] ?? '';
   
   
   // encrypt password
   // Generate password hash
        $hashedPassword = password_hash($_pass, PASSWORD_DEFAULT);
// Include the database connection
        require_once '../includes/dbcon.php';
        try {
        $pdo = getPDOConnection();
        
        // IMPORTANT: Never store passwords in plain text!
        // First, get the user with email
        $sql = "SELECT * FROM reg WHERE email = ? AND cStatus = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_user,1]);
        $row = $stmt->fetch();
        
        if ($row) {
            // Verify password (assuming you're using password_hash)
            if (password_verify($_pass, $row['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $row['regID']; // Store user ID
                $_SESSION['user'] = $row['fName'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['logged_in'] = true;
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                // Redirect to dashboard
                header('Location: users/dashboard');
                exit();
            } else {
                $errors[] = 'Invalid email or password';
            }
        } else {
            $errors[] = 'Invalid email or password, or account not active';
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        $errors[] = 'Login failed. Please try again.';
    }
    }
?>

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
        });
</script>
    
</body>
</html>