<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Salon Elegance</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="../../css/sweetalert2.min.css">
    
    <style>
        :root {
            --primary-color: #6f42c1;
            --secondary-color: #fd7e14;
            --light-bg: #f8f9fa;
            --dark-bg: #343a40;
            --text-color: #333;
            --text-light: #6c757d;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-color);
        }
        
        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, var(--primary-color) 0%, #5a32a0 100%);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .sidebar .logo {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar .logo h3 {
            margin: 0;
            color: white;
        }
        
        .sidebar .logo p {
            color: rgba(255,255,255,0.7);
            margin: 5px 0 0 0;
            font-size: 14px;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link i {
            width: 25px;
            text-align: center;
            margin-right: 10px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Header */
        .dashboard-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-left: 5px solid var(--primary-color);
        }
        
        .welcome-text h1 {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            border-top: 4px solid var(--primary-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .stat-card .icon-1 { background: rgba(111, 66, 193, 0.1); color: var(--primary-color); }
        .stat-card .icon-2 { background: rgba(253, 126, 20, 0.1); color: var(--secondary-color); }
        .stat-card .icon-3 { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .stat-card .icon-4 { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
            color: var(--dark-bg);
        }
        
        .stat-card .label {
            color: var(--text-light);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Table Styling */
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .table-container h4 {
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        /* Status Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #d4edda; color: #155724; }
        .badge-completed { background: #d1ecf1; color: #0c5460; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        
        /* User Info */
        .user-info {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
            margin: 20px 15px;
        }
        
        .user-info .user-avatar {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 20px;
            font-weight: bold;
            margin-right: 15px;
        }
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .action-card {
            text-align: center;
            padding: 20px;
            border-radius: 12px;
            transition: all 0.3s;
            cursor: pointer;
            border: 2px solid transparent;
            margin-bottom: 15px;
        }
        
        .action-card:hover {
            background: #f8f9fa;
            border-color: var(--primary-color);
            transform: scale(1.05);
        }
        
        .action-card i {
            font-size: 2rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            background: var(--primary-color);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            align-items: center;
            justify-content: center;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1050;
        }
        
        @media (max-width: 768px) {
            .mobile-toggle {
                display: flex;
            }
            .main-content {
                padding-top: 70px;
            }
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
    </style>
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="logo">
            <h3><i class="fas fa-cut"></i> Salon Elegance</h3>
            <p>User Dashboard</p>
        </div>
        
        <ul class="nav flex-column mt-4">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="appointments.php">
                    <i class="fas fa-calendar-alt"></i> My Appointments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="book-appointment.php">
                    <i class="fas fa-plus-circle"></i> Book Appointment
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="orders.php">
                    <i class="fas fa-plus-circle"></i> My Orders
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="services.php">
                    <i class="fas fa-spa"></i> Services
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="profile.php">
                    <i class="fas fa-user-cog"></i> My Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="history.php">
                    <i class="fas fa-history"></i> History
                </a>
            </li>
            <li class="nav-item mt-5">
                <a class="nav-link text-danger" href="dashboard.php?logout=true">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
        
        <div class="user-info">
            <div class="d-flex align-items-center">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['fName'], 0, 1)); ?>
                </div>
                <div>
                    <h6 class="mb-0" style="color: white;"><?php echo htmlspecialchars($user['fName']); ?></h6>
                    <small style="color: rgba(255,255,255,0.7);">
                        <?php echo $user['role'] ? ucfirst($user['role']) : 'Customer'; ?>
                    </small>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="welcome-text">
                        <h1>Welcome back, <?php echo htmlspecialchars($user['fName']); ?>!</h1>
                        <p class="lead mb-0">Manage your salon appointments and services</p>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-primary me-2" onclick="bookAppointment()">
                            <i class="fas fa-plus me-2"></i> Book Now
                        </button>
                        <button class="btn btn-outline-secondary" onclick="refreshDashboard()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>