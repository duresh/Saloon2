<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Salon Elegance</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="../../css/sweetalert2.min.css">
    
    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/main.min.css" rel="stylesheet">
    
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-color);
            overflow-x: hidden;
        }
        
        /* Sidebar */
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
        
        .sidebar .logo {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.2);
        }
        
        .sidebar .logo h3 {
            margin: 0;
            color: white;
            font-weight: 700;
        }
        
        .sidebar .logo p {
            color: rgba(255,255,255,0.6);
            margin: 8px 0 0 0;
            font-size: 12px;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 25px;
            margin: 3px 15px;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(111, 66, 193, 0.3);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link i {
            width: 30px;
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        /* Staff Info */
        .staff-info {
            background: linear-gradient(135deg, rgba(111, 66, 193, 0.2), rgba(0,0,0,0.3));
            padding: 20px;
            margin: 20px 15px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .staff-avatar {
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
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px 30px;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        /* Top Navbar */
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
        
        .page-title h4 {
            margin: 0;
            color: var(--dark-bg);
            font-weight: 600;
        }
        
        .notification-bell {
            position: relative;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.3s;
        }
        
        .notification-bell:hover {
            background: #f8f9fa;
        }
        
        .notification-bell i {
            font-size: 1.3rem;
            color: var(--text-light);
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
        }
        
        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            background: var(--primary-color);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1050;
            box-shadow: 0 5px 15px rgba(111, 66, 193, 0.3);
            cursor: pointer;
            align-items: center;
            justify-content: center;
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
    </style>
</head>
<body>
<?php
// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);
?>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="logo">
            <h3><i class="fas fa-cut"></i> Salon Elegance</h3>
            <p>Staff Panel</p>
        </div>
        
        <ul class="nav flex-column mt-4">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'staff-dashboard.php') ? 'active' : ''; ?>" href="staff-dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'my-schedule.php') ? 'active' : ''; ?>" href="my-schedule.php">
                    <i class="fas fa-calendar-alt"></i> My Schedule
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'staff-history.php') ? 'active' : ''; ?>" href="staff-history.php">
                    <i class="fas fa-history"></i> Appointment History
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'availability.php') ? 'active' : ''; ?>" href="availability.php">
                    <i class="fas fa-clock"></i> Availability
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="showNotifications()">
                    <i class="fas fa-bell"></i> Notifications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="staff-orders.php">
                    <i class="fa fa-star" aria-hidden="true"></i> Orders
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="staff-ratings.php">
                    <i class="fa fa-star" aria-hidden="true"></i> Staff Ratings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="profile.php">
                    <i class="fas fa-user-circle"></i> My Profile
                </a>
            </li>
            <li class="nav-item mt-5">
                <a class="nav-link text-danger" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
        
        <div class="staff-info">
            <div class="d-flex align-items-center">
                <div class="staff-avatar">
                    <?php echo isset($staff['staff_name']) ? strtoupper(substr($staff['staff_name'], 0, 1)) : 'S'; ?>
                </div>
                <div>
                    <h6 class="mb-0" style="color: white;"><?php echo isset($staff['staff_name']) ? htmlspecialchars($staff['staff_name']) : 'Staff'; ?></h6>
                    <small style="color: rgba(255,255,255,0.7);">
                        <?php echo isset($staff['specialization']) ? htmlspecialchars($staff['specialization']) : 'Staff'; ?>
                    </small>
                </div>
            </div>
        </div>
        <!-- Add after staff info -->
<div class="staff-rating-summary mt-2 text-center">
    <?php
    // Get rating summary for this staff
    $rating_summary = getStaffRatingSummary($pdo, $staff_id);
    ?>
    <div class="rating-stars-display">
        <?php for($i = 1; $i <= 5; $i++): ?>
            <i class="fas fa-star <?php echo $i <= floor($rating_summary['average_rating'] ?? 0) ? 'text-warning' : 'text-muted'; ?>" style="font-size: 12px;"></i>
        <?php endfor; ?>
        <span class="ms-1 small">(<?php echo number_format($rating_summary['average_rating'] ?? 0, 1); ?>)</span>
    </div>
    <small class="text-muted"><?php echo $rating_summary['total_ratings'] ?? 0; ?> reviews</small>
</div>
    </nav>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="page-title">
                <h4><?php echo ucfirst(str_replace('-', ' ', basename($_SERVER['PHP_SELF'], '.php'))); ?></h4>
            </div>
            <div class="top-nav-actions">
                <div class="notification-bell" onclick="showNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if (isset($unread_count) && $unread_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-spinner"></div>
            <div class="mt-3 text-primary fw-bold">Loading...</div>
        </div>

        <!-- NO ALERT DIVS HERE - They are removed completely -->