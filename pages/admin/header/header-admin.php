<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name ?? 'Salon Elegance'; ?> - Admin Dashboard</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="../../css/sweetalert2.min.css">
    
    <!-- Chart.js for admin charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #6f42c1;
            --secondary-color: #fd7e14;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-bg: #f4f6f9;
            --dark-bg: #343a40;
            --text-color: #333;
            --text-light: #6c757d;
            --sidebar-width: 280px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-color);
            overflow-x: hidden;
        }
        
        /* Sidebar - Admin specific gradient */
        .sidebar {
            background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            box-shadow: 3px 0 15px rgba(0,0,0,0.2);
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.3s;
        }
        
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .sidebar .logo {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.2);
        }
        
        .sidebar .logo h3 {
            margin: 0;
            color: white;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .sidebar .logo p {
            color: rgba(255,255,255,0.6);
            margin: 8px 0 0 0;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .sidebar .nav-section {
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar .nav-section-title {
            padding: 10px 25px;
            color: rgba(255,255,255,0.5);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 25px;
            margin: 3px 15px;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            font-weight: 500;
            text-decoration: none;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: linear-gradient(90deg, rgba(111, 66, 193, 0.3), rgba(111, 66, 193, 0.1));
            color: white;
            transform: translateX(5px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        .sidebar .nav-link i {
            width: 30px;
            text-align: center;
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .sidebar .nav-link .badge {
            margin-left: auto;
            background: var(--warning-color);
            color: var(--dark-bg);
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
        }
        
        .sidebar .nav-link .badge.danger {
            background: var(--danger-color);
            color: white;
        }
        
        .sidebar .nav-link .badge.success {
            background: var(--success-color);
            color: white;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px 30px;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        /* Admin Info in Sidebar */
        .admin-info {
            background: linear-gradient(135deg, rgba(111, 66, 193, 0.2), rgba(0,0,0,0.3));
            padding: 20px;
            margin: 20px 15px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .admin-info .admin-avatar {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, var(--primary-color), #5a32a0);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            margin-right: 15px;
            border: 3px solid rgba(255,255,255,0.2);
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        
        .admin-info .admin-details h6 {
            margin: 0;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }
        
        .admin-info .admin-details .admin-role {
            color: var(--warning-color);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .admin-info .admin-details .admin-status {
            color: rgba(255,255,255,0.6);
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 5px;
        }
        
        .admin-info .admin-details .admin-status i {
            font-size: 8px;
            color: #2ecc71;
        }
        
        /* Top Navigation Bar */
        .top-navbar {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .page-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-title h4 {
            margin: 0;
            color: var(--dark-bg);
            font-weight: 600;
        }
        
        .page-title .breadcrumb {
            margin: 0;
            background: none;
            padding: 0;
        }
        
        .page-title .breadcrumb-item {
            font-size: 13px;
        }
        
        .page-title .breadcrumb-item a {
            color: var(--text-light);
            text-decoration: none;
        }
        
        .page-title .breadcrumb-item.active {
            color: var(--primary-color);
        }
        
        .top-nav-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .notification-icon {
            position: relative;
            cursor: pointer;
        }
        
        .notification-icon i {
            font-size: 1.3rem;
            color: var(--text-light);
        }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            font-size: 10px;
            padding: 3px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }
        
        .admin-profile-dropdown {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .admin-profile-dropdown:hover {
            background: #f8f9fa;
        }
        
        .admin-profile-dropdown img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .admin-profile-dropdown .avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #5a32a0);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }
        
        .admin-profile-dropdown .admin-name {
            font-weight: 600;
            color: var(--dark-bg);
        }
        
        .admin-profile-dropdown .admin-name small {
            display: block;
            font-weight: normal;
            color: var(--text-light);
            font-size: 11px;
        }
        
        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            background: linear-gradient(135deg, var(--primary-color), #5a32a0);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            align-items: center;
            justify-content: center;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1050;
            box-shadow: 0 5px 15px rgba(111, 66, 193, 0.3);
            cursor: pointer;
        }
        
        /* Stats Cards - Admin Style */
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            border-left: 4px solid transparent;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-card.primary { border-left-color: var(--primary-color); }
        .stat-card.success { border-left-color: var(--success-color); }
        .stat-card.warning { border-left-color: var(--warning-color); }
        .stat-card.danger { border-left-color: var(--danger-color); }
        .stat-card.info { border-left-color: var(--info-color); }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .stat-card.primary .stat-icon { background: rgba(111, 66, 193, 0.1); color: var(--primary-color); }
        .stat-card.success .stat-icon { background: rgba(40, 167, 69, 0.1); color: var(--success-color); }
        .stat-card.warning .stat-icon { background: rgba(255, 193, 7, 0.1); color: var(--warning-color); }
        .stat-card.danger .stat-icon { background: rgba(220, 53, 69, 0.1); color: var(--danger-color); }
        .stat-card.info .stat-icon { background: rgba(23, 162, 184, 0.1); color: var(--info-color); }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark-bg);
            line-height: 1.2;
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-trend {
            font-size: 12px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .stat-trend.up { color: var(--success-color); }
        .stat-trend.down { color: var(--danger-color); }
        
        /* Dashboard Header */
        .dashboard-header {
            background: white;
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border-left: 5px solid #6f42c1;
        }
        
        .dashboard-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        
        /* Alert Custom */
        .alert-custom {
            border-radius: 12px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
        }
        
        @media (max-width: 992px) {
            .sidebar {
                margin-left: calc(var(--sidebar-width) * -1);
            }
            .sidebar.active {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
            }
            .main-content.active {
                margin-left: var(--sidebar-width);
            }
            .mobile-toggle {
                display: flex;
            }
        }
        
        @media (max-width: 768px) {
            .mobile-toggle {
                display: flex;
            }
            .main-content {
                padding: 70px 15px 15px;
            }
            .top-navbar {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            .top-nav-actions {
                width: 100%;
                justify-content: flex-end;
            }
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .dashboard-header {
                padding: 20px;
            }
            .dashboard-header h1 {
                font-size: 24px;
            }
        }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            display: none;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<?php
// Get current page filename for active menu
$current_page = basename($_SERVER['PHP_SELF']);

// Get notification count (you'll need to implement this)
$notification_count = 0;
$pending_count = 0;

// Safely get admin name with fallback
$admin_name = 'Admin';
$admin_initial = 'A';

// Check if admin variable exists and has the required data
if (isset($admin) && is_array($admin) && isset($admin['fName'])) {
    $admin_name = htmlspecialchars($admin['fName']);
    $admin_initial = strtoupper(substr($admin['fName'], 0, 1));
} elseif (isset($admin) && is_object($admin) && isset($admin->fName)) {
    $admin_name = htmlspecialchars($admin->fName);
    $admin_initial = strtoupper(substr($admin->fName, 0, 1));
}

// Load system settings from database if not already loaded
if (!isset($site_name) && isset($pdo)) {
    try {
        $settings_query = "SELECT setting_key, setting_value FROM system_settings";
        $settings_stmt = $pdo->query($settings_query);
        $settings_data = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $site_name = $settings_data['site_name'] ?? 'Salon Elegance';
        $contact_email = $settings_data['contact_email'] ?? 'info@salonelegance.com';
        $contact_phone = $settings_data['contact_phone'] ?? '+94 71 805 9219';
        $contact_address = $settings_data['contact_address'] ?? 'Colombo, Sri Lanka';
        $currency_symbol = $settings_data['currency_symbol'] ?? 'Rs';
        $date_format = $settings_data['date_format'] ?? 'Y-m-d';
        $time_format = $settings_data['time_format'] ?? 'H:i';
        $business_open_time = $settings_data['business_open_time'] ?? '09:00';
        $business_close_time = $settings_data['business_close_time'] ?? '20:00';
    } catch (PDOException $e) {
        // Fallback defaults
        $site_name = 'Salon Elegance';
        $contact_email = 'info@salonelegance.com';
        $contact_phone = '+94 71 805 9219';
        $contact_address = 'Colombo, Sri Lanka';
        $currency_symbol = 'Rs';
        $date_format = 'Y-m-d';
        $time_format = 'H:i';
        $business_open_time = '09:00';
        $business_close_time = '20:00';
    }
} elseif (!isset($site_name)) {
    // Fallback defaults if no database connection
    $site_name = 'Salon Elegance';
    $contact_email = 'info@salonelegance.com';
    $contact_phone = '+94 71 805 9219';
    $contact_address = 'Colombo, Sri Lanka';
    $currency_symbol = 'Rs';
    $date_format = 'Y-m-d';
    $time_format = 'H:i';
    $business_open_time = '09:00';
    $business_close_time = '20:00';
}

// Set timezone
if (isset($settings_data['timezone']) && $settings_data['timezone']) {
    date_default_timezone_set($settings_data['timezone']);
}
?>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="logo">
            <h3><i class="fas fa-cut"></i> <?php echo htmlspecialchars($site_name); ?></h3>
            <p>Admin Panel</p>
        </div>
        
        <!-- Main Navigation -->
        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'admin-dashboard.php' || $current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (in_array($current_page, ['manage-appointments.php', 'appointment-details.php'])) ? 'active' : ''; ?>" href="manage-appointments.php">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Appointments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'manage-services.php') ? 'active' : ''; ?>" href="manage-services.php">
                        <i class="fas fa-spa"></i>
                        <span>Services</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'manage-staff.php') ? 'active' : ''; ?>" href="manage-staff.php">
                        <i class="fas fa-users-cog"></i>
                        <span>Staff</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'manage-users.php') ? 'active' : ''; ?>" href="manage-users.php">
                        <i class="fas fa-users"></i>
                        <span>Customers</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Management Section -->
        <div class="nav-section">
            <div class="nav-section-title">Management</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'admin-staff-ratings.php') ? 'active' : ''; ?>" href="admin-staff-ratings.php">
                        <i class="fas fa-star"></i>
                        <span>Staff Ratings</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Settings Section -->
        <div class="nav-section">
            <div class="nav-section-title">Settings</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" href="settings.php">
                        <i class="fas fa-cog"></i>
                        <span>General Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" href="profile.php">
                        <i class="fas fa-user-cog"></i>
                        <span>My Profile</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Admin Info -->
        <div class="admin-info">
            <div class="d-flex align-items-center">
                <div class="admin-avatar">
                    <?php echo $admin_initial; ?>
                </div>
                <div class="admin-details">
                    <h6><?php echo $admin_name; ?></h6>
                    <div class="admin-role">Administrator</div>
                    <div class="admin-status">
                        <i class="fas fa-circle"></i> Online
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Logout Link -->
        <ul class="nav flex-column mb-4">
            <li class="nav-item">
                <a class="nav-link text-danger" href="dashboard.php?logout=true">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navigation Bar -->
        <div class="top-navbar">
            <div class="page-title">
                <h4>
                    <?php
                    // Set page title based on current page
                    $page_titles = [
                        'dashboard.php' => 'Dashboard',
                        'admin-dashboard.php' => 'Dashboard',
                        'manage-appointments.php' => 'Manage Appointments',
                        'manage-services.php' => 'Manage Services',
                        'manage-staff.php' => 'Manage Staff',
                        'manage-users.php' => 'Manage Customers',
                        'reports.php' => 'Reports',
                        'admin-staff-ratings.php' => 'Staff Ratings',
                        'settings.php' => 'Settings',
                        'profile.php' => 'My Profile'
                    ];
                    $page_title = isset($page_titles[$current_page]) ? $page_titles[$current_page] : 'Admin Panel';
                    echo $page_title;
                    ?>
                </h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo $page_title; ?></li>
                    </ol>
                </nav>
            </div>
            
            <div class="top-nav-actions">
                <div class="admin-profile-dropdown" onclick="toggleAdminMenu()">
                    <div class="avatar-placeholder">
                        <?php echo $admin_initial; ?>
                    </div>
                    <div class="admin-name">
                        <?php echo $admin_name; ?>
                        <small>Administrator</small>
                    </div>
                    <i class="fas fa-chevron-down" style="font-size: 12px; color: var(--text-light);"></i>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-spinner"></div>
            <div class="mt-3 text-primary fw-bold">Loading...</div>
        </div>

        <script>
            // Toggle sidebar on mobile
            $(document).ready(function() {
                $('#sidebarToggle').click(function(e) {
                    e.preventDefault();
                    $('#sidebar').toggleClass('active');
                    $('#mainContent').toggleClass('active');
                });

                // Close sidebar when clicking outside on mobile
                $(document).click(function(event) {
                    if ($(window).width() <= 992) {
                        if (!$(event.target).closest('#sidebar').length && 
                            !$(event.target).is('#sidebarToggle') &&
                            $('#sidebar').hasClass('active')) {
                            $('#sidebar').removeClass('active');
                            $('#mainContent').removeClass('active');
                        }
                    }
                });
            });

            // Admin profile dropdown
            function toggleAdminMenu() {
                Swal.fire({
                    title: 'Admin Menu',
                    html: `
                        <div class="list-group">
                            <a href="profile.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-user me-2"></i> My Profile
                            </a>
                            <a href="settings.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-cog me-2"></i> Settings
                            </a>
                            <hr class="my-2">
                            <a href="dashboard.php?logout=true" class="list-group-item list-group-item-action text-danger">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </div>
                    `,
                    showConfirmButton: false,
                    showCloseButton: true
                });
            }

            // Loading functions
            function showLoading() {
                $('#loadingOverlay').fadeIn();
            }

            function hideLoading() {
                $('#loadingOverlay').fadeOut();
            }

            // Format date based on system settings
            function formatDate(dateStr, includeTime = false) {
                if (!dateStr) return 'N/A';
                const date = new Date(dateStr);
                const dateFormat = '<?php echo $date_format; ?>';
                const timeFormat = '<?php echo $time_format; ?>';
                
                if (includeTime) {
                    return date.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
                return date.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric' 
                });
            }

            // Format currency based on system settings
            function formatCurrency(amount) {
                return '<?php echo $currency_symbol; ?>: ' + parseFloat(amount).toFixed(2);
            }

            // Escape HTML to prevent XSS
            function escapeHtml(unsafe) {
                if (!unsafe) return '';
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            // Get business hours
            const businessOpenTime = '<?php echo $business_open_time; ?>';
            const businessCloseTime = '<?php echo $business_close_time; ?>';
            
            // Contact information
            const contactEmail = '<?php echo $contact_email; ?>';
            const contactPhone = '<?php echo $contact_phone; ?>';
            const contactAddress = '<?php echo addslashes($contact_address); ?>';
        </script>