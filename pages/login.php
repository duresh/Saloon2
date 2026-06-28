<?php
ob_start();
session_start();

require_once '../includes/dbcon.php';

$error = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '../index.php';

// If already logged in, redirect based on role
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['user_role'])) {
        switch ($_SESSION['user_role']) {
            case 'admin':
                header('Location: admin/dashboard.php');
                break;
            case 'staff':
                header('Location: admin/staff-dashboard.php');
                break;
            default:
                // Customers go to root homepage
                header('Location: ../index.php');
                break;
        }
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password';
    } else {
        try {
            $pdo = getPDOConnection();
            
            // Get user from reg table
            $query = "SELECT * FROM reg WHERE email = ? AND cStatus = 1";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $user['regID'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['fName'] . ' ' . $user['lName'];
                $_SESSION['user_fname'] = $user['fName'];
                $_SESSION['user_lname'] = $user['lName'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_contact'] = $user['contactNo'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['fName'] = $user['fName'];
                $_SESSION['lName'] = $user['lName'];
                
                // Check if password needs to be changed (for staff/admin only)
                if (($user['role'] === 'staff' || $user['role'] === 'admin') && $user['password_changed'] == 0) {
                    $_SESSION['temp_user_id'] = $user['regID'];
                    $_SESSION['temp_user_name'] = $user['fName'];
                    header('Location: force-password-change.php');
                    exit();
                }
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header('Location: admin/dashboard.php');
                        break;
                    case 'staff':
                        header('Location: admin/staff-dashboard.php');
                        break;
                    default:
                        // Customers go to root homepage (not pages/index.php)
                        header('Location: ../index.php');
                        break;
                }
                exit();
            } else {
                $error = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'Login failed. Please try again.';
        }
    }
}

// Get redirect URL from query string - FIXED to use root index.php
$redirectUrl = isset($_GET['redirect']) ? $_GET['redirect'] : '../index.php';
// If redirect contains pages/index.php, change to root index.php
if (strpos($redirectUrl, 'pages/index.php') !== false) {
    $redirectUrl = '../index.php';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Salon Elegance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body {
            background: linear-gradient(135deg, #fff5ec 0%, #fae6d4 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: white;
            border-radius: 30px;
            padding: 40px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.1);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo .logo-icon {
            font-size: 3rem;
            color: #b85c1a;
        }
        .login-logo h2 {
            font-weight: 800;
            background: linear-gradient(135deg, #b85c1a, #e09d5e);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-top: 10px;
        }
        .form-control {
            border-radius: 12px;
            padding: 12px 15px;
            border: 1px solid #e0d0c0;
        }
        .form-control:focus {
            border-color: #b85c1a;
            box-shadow: 0 0 0 0.2rem rgba(184,92,26,0.25);
        }
        .btn-login {
            background: #b85c1a;
            border: none;
            padding: 12px;
            border-radius: 40px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: #9b4512;
            transform: translateY(-2px);
        }
        .input-group-text {
            background: transparent;
            border-right: none;
            color: #b85c1a;
        }
        .input-group .form-control {
            border-left: none;
        }
        .user-type-badge {
            background: #f5ede5;
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.8rem;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <i class="fas fa-cut logo-icon"></i>
            <h2>Salon Elegance</h2>
            <p class="text-muted">Welcome back! Please login to your account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="redirect" value="<?php echo $redirectUrl; ?>">
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" class="form-control" name="email" placeholder="Email Address" required>
                </div>
            </div>
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-login text-white">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>
        </form>
        
        <div class="user-type-badge text-center">
            <small class="text-muted">
                <i class="fas fa-user me-1"></i> Customer | 
                <i class="fas fa-user-tie me-1"></i> Staff | 
                <i class="fas fa-user-shield me-1"></i> Admin
            </small>
        </div>
        
        <div class="text-center mt-4">
            <p class="text-muted">Don't have an account? <a href="register.php?redirect=<?php echo urlencode($redirectUrl); ?>" class="text-decoration-none fw-bold" style="color:#b85c1a;">Register Here</a></p>
        </div>
        
        <div class="text-center mt-3">
            <a href="../index.php" class="text-decoration-none text-muted">
                <i class="fas fa-arrow-left me-1"></i>Back to Home
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>