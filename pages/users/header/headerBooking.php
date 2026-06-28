<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Salon Elegance</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="../../css/sweetalert2.min.css">
    
    <!-- Flatpickr CSS for date picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
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
                transition: margin-left 0.3s ease;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
                transition: margin-left 0.3s ease;
            }
            .main-content.active {
                margin-left: 250px;
            }
        }
        
        /* Header */
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-left: 5px solid var(--primary-color);
        }
        
        .page-header h1 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .page-header .lead {
            color: var(--text-light);
            margin-bottom: 0;
        }
        
        /* Booking Card */
        .booking-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-top: 4px solid var(--primary-color);
        }
        
        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            font-weight: 700;
            margin-right: 15px;
            font-size: 1.2rem;
        }
        
        .section-header h3 {
            color: var(--dark-bg);
            font-weight: 700;
            margin: 0;
            font-size: 1.5rem;
        }
        
        /* Service Cards */
        .service-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .service-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(111, 66, 193, 0.1);
        }
        
        .service-card.selected {
            border-color: var(--primary-color);
            background: linear-gradient(to right, rgba(111, 66, 193, 0.05), transparent);
            box-shadow: 0 5px 15px rgba(111, 66, 193, 0.15);
        }
        
        .service-card.selected::after {
            content: '✓ Selected';
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--primary-color);
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .service-card .service-category {
            display: inline-block;
            background: rgba(111, 66, 193, 0.1);
            color: var(--primary-color);
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .service-card .service-title {
            font-weight: 700;
            color: var(--dark-bg);
            margin-bottom: 8px;
            font-size: 1.1rem;
        }
        
        .service-card .service-description {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .service-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        
        .service-duration {
            color: var(--text-light);
            font-size: 0.85rem;
        }
        
        .service-duration i {
            color: var(--primary-color);
            margin-right: 5px;
        }
        
        .service-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        /* Date Time Selection */
        .datetime-selector {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 2px solid #e9ecef;
        }
        
        .date-picker-container {
            position: relative;
        }
        
        .flatpickr-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: white;
            cursor: pointer;
        }
        
        .flatpickr-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(111, 66, 193, 0.1);
            outline: none;
        }
        
        /* Time Slots */
        .time-slots-container {
            margin-top: 15px;
        }
        
        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 15px;
            max-height: 300px;
            overflow-y: auto;
            padding: 10px;
        }
        
        .time-slot {
            padding: 10px 5px;
            text-align: center;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .time-slot:hover {
            border-color: var(--primary-color);
            background: rgba(111, 66, 193, 0.05);
        }
        
        .time-slot.selected {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: scale(1.05);
        }
        
        .time-slot.booked {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        /* Notes Section */
        .notes-container textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s;
            resize: vertical;
            min-height: 100px;
        }
        
        .notes-container textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(111, 66, 193, 0.1);
            outline: none;
        }
        
        /* Summary Sidebar */
        .summary-sidebar {
            position: sticky;
            top: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 25px;
            border: 1px solid #e9ecef;
            height: fit-content;
        }
        
        .summary-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .summary-header h4 {
            color: var(--primary-color);
            font-weight: 700;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .summary-label {
            color: var(--text-light);
            font-weight: 500;
        }
        
        .summary-value {
            font-weight: 600;
            color: var(--text-color);
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }
        
        .summary-total {
            background: var(--primary-color);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .summary-total .value {
            font-size: 1.4rem;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-reset {
            background: white;
            color: var(--text-color);
            border: 2px solid #e9ecef;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-reset:hover {
            background: #f8f9fa;
            border-color: var(--text-light);
        }
        
        .btn-book {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(111, 66, 193, 0.2);
            flex: 1;
        }
        
        .btn-book:hover:not(:disabled) {
            background: #5a32a0;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(111, 66, 193, 0.3);
        }
        
        .btn-book:disabled {
            background: #adb5bd;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* Tips Section */
        .tips-section {
            background: linear-gradient(135deg, #6f42c1 0%, #5a32a0 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-top: 25px;
        }
        
        .tips-section h5 {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tips-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .tips-list li {
            padding: 6px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tips-list li i {
            color: #4cc9f0;
        }
        
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
            .time-slots-grid {
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            }
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-light);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 15px;
        }
        
        /* Alert Styling */
        .alert-custom {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #dc3545, #e83e8c);
            color: white;
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
            width: 40px;
            height: 40px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Success Animation */
        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .success-pulse {
            animation: successPulse 0.5s ease-in-out;
        }
        
        /* Category Badges */
        .category-badge {
            display: inline-block;
            padding: 5px 15px;
            background: rgba(111, 66, 193, 0.1);
            color: var(--primary-color);
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        /* Form Labels */
        .form-label {
            font-weight: 600;
            color: var(--dark-bg);
            margin-bottom: 10px;
        }
        
        /* Scrollbar Styling */
        .time-slots-grid::-webkit-scrollbar {
            width: 8px;
        }
        
        .time-slots-grid::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .time-slots-grid::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }
        
        .time-slots-grid::-webkit-scrollbar-thumb:hover {
            background: #5a32a0;
        }
    </style>
</head>
<body>
<?php
// Get current page filename
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
            <p>User Dashboard</p>
        </div>
        
        <ul class="nav flex-column mt-4">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'appointments.php' || $current_page == 'my-appointments.php') ? 'active' : ''; ?>" href="appointments.php">
                    <i class="fas fa-calendar-alt"></i> My Appointments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'book-appointment.php') ? 'active' : ''; ?>" href="book-appointment.php">
                    <i class="fas fa-plus-circle"></i> Book Appointment
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'services.php') ? 'active' : ''; ?>" href="services.php">
                    <i class="fas fa-spa"></i> Services
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" href="profile.php">
                    <i class="fas fa-user-cog"></i> My Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'history.php') ? 'active' : ''; ?>" href="history.php">
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
                    <?php echo isset($user) ? strtoupper(substr($user['fName'], 0, 1)) : 'U'; ?>
                </div>
                <div>
                    <h6 class="mb-0" style="color: white;"><?php echo isset($user) ? htmlspecialchars($user['fName']) : 'User'; ?></h6>
                    <small style="color: rgba(255,255,255,0.7);">
                        <?php echo isset($user) && isset($user['role']) ? ucfirst($user['role']) : 'Customer'; ?>
                    </small>
                </div>
            </div>
        </div>
    </nav>