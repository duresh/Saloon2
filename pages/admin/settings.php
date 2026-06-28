<?php
ob_start();
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../user/dashboard.php');
    exit();
}

// Include database connection
require_once '../../includes/dbcon.php';
require_once '../../includes/helpers.php';

$error = '';
$success = '';

// Setting sections
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

try {
    $pdo = getPDOConnection();
    
    // Create settings table if not exists
    $create_table = "
        CREATE TABLE IF NOT EXISTS `system_settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `setting_key` varchar(100) NOT NULL,
            `setting_value` text DEFAULT NULL,
            `setting_type` enum('text','number','boolean','json','file') DEFAULT 'text',
            `category` varchar(50) DEFAULT 'general',
            `description` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_key` (`setting_key`),
            KEY `category` (`category`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $pdo->exec($create_table);
    
    // Insert default settings if not exists
    $default_settings = [
        // General Settings
        ['site_name', 'Salon Elegance', 'text', 'general', 'Salon name displayed throughout the system'],
        ['site_logo', '', 'file', 'general', 'Site logo URL'],
        ['timezone', 'Asia/Colombo', 'text', 'general', 'System timezone'],
        ['date_format', 'Y-m-d', 'text', 'general', 'Date format for display'],
        ['time_format', 'H:i', 'text', 'general', 'Time format for display'],
        
        // Business Hours
        ['business_open_time', '09:00', 'text', 'hours', 'Business opening time'],
        ['business_close_time', '20:00', 'text', 'hours', 'Business closing time'],
        ['time_slot_interval', '30', 'number', 'hours', 'Time slot interval in minutes'],
        ['max_appointments_per_slot', '10', 'number', 'hours', 'Maximum appointments per time slot'],
        ['working_days', '1,2,3,4,5,6', 'text', 'hours', 'Working days (0=Sun,1=Mon,...6=Sat)'],
        
        // Booking Settings
        ['advance_booking_days', '60', 'number', 'booking', 'Maximum days in advance for booking'],
        ['min_cancel_hours', '24', 'number', 'booking', 'Minimum hours before appointment to cancel'],
        ['max_reschedule_count', '3', 'number', 'booking', 'Maximum reschedule attempts allowed'],
        ['deposit_percentage', '0', 'number', 'booking', 'Deposit percentage required for booking'],
        
        // Notification Settings
        ['booking_confirmation_sms', '1', 'boolean', 'notifications', 'Send SMS on booking confirmation'],
        ['booking_confirmation_email', '1', 'boolean', 'notifications', 'Send Email on booking confirmation'],
        ['reminder_hours', '24', 'number', 'notifications', 'Hours before appointment to send reminder'],
        ['admin_notification_email', '', 'text', 'notifications', 'Admin email for notifications'],
        
        // Payment Settings
        ['payment_methods', 'cash,card,online', 'text', 'payment', 'Available payment methods'],
        ['tax_rate', '0', 'number', 'payment', 'Tax rate percentage'],
        ['currency_symbol', 'Rs', 'text', 'payment', 'Currency symbol'],
        
        // SEO & Other
        ['meta_description', 'Premier salon offering professional beauty and grooming services', 'text', 'seo', 'Site meta description'],
        ['contact_email', 'info@salonelegance.com', 'text', 'contact', 'Contact email address'],
        ['contact_phone', '+94 71 805 9219', 'text', 'contact', 'Contact phone number'],
        ['contact_address', 'Colombo, Sri Lanka', 'text', 'contact', 'Business address']
    ];
    
    foreach ($default_settings as $setting) {
        $check = $pdo->prepare("SELECT id FROM system_settings WHERE setting_key = ?");
        $check->execute([$setting[0]]);
        if (!$check->fetch()) {
            $insert = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description) VALUES (?, ?, ?, ?, ?)");
            $insert->execute([$setting[0], $setting[1], $setting[2], $setting[3], $setting[4]]);
        }
    }
    
    // Handle general settings update
    if (isset($_POST['save_general'])) {
        $updates = [
            'site_name' => $_POST['site_name'] ?? 'Salon Elegance',
            'timezone' => $_POST['timezone'] ?? 'Asia/Colombo',
            'date_format' => $_POST['date_format'] ?? 'Y-m-d',
            'time_format' => $_POST['time_format'] ?? 'H:i'
        ];
        
        foreach ($updates as $key => $value) {
            $update = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $update->execute([$value, $key]);
        }
        
        $success = 'General settings updated successfully!';
    }
    
    // Handle business hours update
    if (isset($_POST['save_hours'])) {
        $updates = [
            'business_open_time' => $_POST['business_open_time'] ?? '09:00',
            'business_close_time' => $_POST['business_close_time'] ?? '20:00',
            'time_slot_interval' => $_POST['time_slot_interval'] ?? '30',
            'max_appointments_per_slot' => $_POST['max_appointments_per_slot'] ?? '10',
            'working_days' => isset($_POST['working_days']) ? implode(',', $_POST['working_days']) : '1,2,3,4,5,6'
        ];
        
        foreach ($updates as $key => $value) {
            $update = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $update->execute([$value, $key]);
        }
        
        $success = 'Business hours updated successfully!';
    }
    
    // Handle booking settings update
    if (isset($_POST['save_booking'])) {
        $updates = [
            'advance_booking_days' => $_POST['advance_booking_days'] ?? '60',
            'min_cancel_hours' => $_POST['min_cancel_hours'] ?? '24',
            'max_reschedule_count' => $_POST['max_reschedule_count'] ?? '3',
            'deposit_percentage' => $_POST['deposit_percentage'] ?? '0'
        ];
        
        foreach ($updates as $key => $value) {
            $update = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $update->execute([$value, $key]);
        }
        
        $success = 'Booking settings updated successfully!';
    }
    
    // Handle notification settings update
    if (isset($_POST['save_notifications'])) {
        $updates = [
            'booking_confirmation_sms' => isset($_POST['booking_confirmation_sms']) ? '1' : '0',
            'booking_confirmation_email' => isset($_POST['booking_confirmation_email']) ? '1' : '0',
            'reminder_hours' => $_POST['reminder_hours'] ?? '24',
            'admin_notification_email' => $_POST['admin_notification_email'] ?? ''
        ];
        
        foreach ($updates as $key => $value) {
            $update = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $update->execute([$value, $key]);
        }
        
        $success = 'Notification settings updated successfully!';
    }
    
    // Handle payment settings update
    if (isset($_POST['save_payment'])) {
        $updates = [
            'payment_methods' => isset($_POST['payment_methods']) ? implode(',', $_POST['payment_methods']) : 'cash',
            'tax_rate' => $_POST['tax_rate'] ?? '0',
            'currency_symbol' => $_POST['currency_symbol'] ?? 'Rs'
        ];
        
        foreach ($updates as $key => $value) {
            $update = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $update->execute([$value, $key]);
        }
        
        $success = 'Payment settings updated successfully!';
    }
    
    // Handle contact settings update
    if (isset($_POST['save_contact'])) {
        $updates = [
            'contact_email' => $_POST['contact_email'] ?? '',
            'contact_phone' => $_POST['contact_phone'] ?? '',
            'contact_address' => $_POST['contact_address'] ?? ''
        ];
        
        foreach ($updates as $key => $value) {
            $update = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $update->execute([$value, $key]);
        }
        
        $success = 'Contact settings updated successfully!';
    }
    
    // Get all settings for display
    $settings_query = "SELECT * FROM system_settings ORDER BY category, setting_key";
    $settings_stmt = $pdo->query($settings_query);
    $all_settings = $settings_stmt->fetchAll();
    
    // Organize settings by category
    $settings_by_category = [];
    foreach ($all_settings as $setting) {
        $settings_by_category[$setting['category']][$setting['setting_key']] = $setting;
    }
    
} catch (PDOException $e) {
    error_log('Settings page error: ' . $e->getMessage());
    $error = 'Unable to load settings. Please try again later.';
}

include 'header/header-admin.php';
?>

<!-- Page Header -->
<div class="dashboard-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1><i class="fas fa-cog me-2" style="color: #6f42c1;"></i> System Settings</h1>
            <p class="lead mb-0">Configure your salon management system</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-success" onclick="refreshPage()">
                <i class="fas fa-sync-alt me-2"></i>Refresh
            </button>
        </div>
    </div>
</div>

<!-- Settings Tabs -->
<div class="filter-card">
    <div class="filter-header">
        <ul class="nav nav-tabs settings-tabs">
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab == 'general' ? 'active' : ''; ?>" href="?tab=general">
                    <i class="fas fa-globe me-2"></i>General
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab == 'hours' ? 'active' : ''; ?>" href="?tab=hours">
                    <i class="fas fa-clock me-2"></i>Business Hours
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab == 'booking' ? 'active' : ''; ?>" href="?tab=booking">
                    <i class="fas fa-calendar-check me-2"></i>Booking
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab == 'notifications' ? 'active' : ''; ?>" href="?tab=notifications">
                    <i class="fas fa-bell me-2"></i>Notifications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab == 'payment' ? 'active' : ''; ?>" href="?tab=payment">
                    <i class="fas fa-credit-card me-2"></i>Payment
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab == 'contact' ? 'active' : ''; ?>" href="?tab=contact">
                    <i class="fas fa-address-card me-2"></i>Contact
                </a>
            </li>
        </ul>
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

<!-- General Settings Tab -->
<?php if ($active_tab == 'general'): ?>
<div class="table-card">
    <div class="table-header">
        <h5><i class="fas fa-globe me-2"></i> General Settings</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="" class="settings-form">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Site Name</label>
                    <input type="text" class="form-control" name="site_name" 
                           value="<?php echo htmlspecialchars($settings_by_category['general']['site_name']['setting_value'] ?? 'Salon Elegance'); ?>">
                    <small class="text-muted">Name displayed throughout the system</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Time Zone</label>
                    <select class="form-select" name="timezone">
                        <option value="Asia/Colombo" <?php echo ($settings_by_category['general']['timezone']['setting_value'] ?? '') == 'Asia/Colombo' ? 'selected' : ''; ?>>Asia/Colombo</option>
                        <option value="Asia/Kolkata" <?php echo ($settings_by_category['general']['timezone']['setting_value'] ?? '') == 'Asia/Kolkata' ? 'selected' : ''; ?>>Asia/Kolkata</option>
                        <option value="Asia/Dubai" <?php echo ($settings_by_category['general']['timezone']['setting_value'] ?? '') == 'Asia/Dubai' ? 'selected' : ''; ?>>Asia/Dubai</option>
                        <option value="Asia/Singapore" <?php echo ($settings_by_category['general']['timezone']['setting_value'] ?? '') == 'Asia/Singapore' ? 'selected' : ''; ?>>Asia/Singapore</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Date Format</label>
                    <select class="form-select" name="date_format">
                        <option value="Y-m-d" <?php echo ($settings_by_category['general']['date_format']['setting_value'] ?? '') == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                        <option value="d-m-Y" <?php echo ($settings_by_category['general']['date_format']['setting_value'] ?? '') == 'd-m-Y' ? 'selected' : ''; ?>>DD-MM-YYYY</option>
                        <option value="m/d/Y" <?php echo ($settings_by_category['general']['date_format']['setting_value'] ?? '') == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                        <option value="d/m/Y" <?php echo ($settings_by_category['general']['date_format']['setting_value'] ?? '') == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Time Format</label>
                    <select class="form-select" name="time_format">
                        <option value="H:i" <?php echo ($settings_by_category['general']['time_format']['setting_value'] ?? '') == 'H:i' ? 'selected' : ''; ?>>24-Hour (14:30)</option>
                        <option value="h:i A" <?php echo ($settings_by_category['general']['time_format']['setting_value'] ?? '') == 'h:i A' ? 'selected' : ''; ?>>12-Hour (02:30 PM)</option>
                    </select>
                </div>
            </div>
            
            <div class="text-end">
                <button type="submit" name="save_general" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Business Hours Tab -->
<?php if ($active_tab == 'hours'): 
    $working_days = explode(',', $settings_by_category['hours']['working_days']['setting_value'] ?? '1,2,3,4,5,6');
?>
<div class="table-card">
    <div class="table-header">
        <h5><i class="fas fa-clock me-2"></i> Business Hours</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="" class="settings-form">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Opening Time</label>
                    <input type="time" class="form-control" name="business_open_time" 
                           value="<?php echo htmlspecialchars($settings_by_category['hours']['business_open_time']['setting_value'] ?? '09:00'); ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Closing Time</label>
                    <input type="time" class="form-control" name="business_close_time" 
                           value="<?php echo htmlspecialchars($settings_by_category['hours']['business_close_time']['setting_value'] ?? '20:00'); ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Time Slot Interval (minutes)</label>
                    <input type="number" class="form-control" name="time_slot_interval" 
                           value="<?php echo htmlspecialchars($settings_by_category['hours']['time_slot_interval']['setting_value'] ?? '30'); ?>" min="15" max="60" step="15">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Max Appointments Per Slot</label>
                    <input type="number" class="form-control" name="max_appointments_per_slot" 
                           value="<?php echo htmlspecialchars($settings_by_category['hours']['max_appointments_per_slot']['setting_value'] ?? '10'); ?>" min="1" max="50">
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">Working Days</label>
                    <div class="row">
                        <?php
                        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        foreach ($days as $index => $day):
                            $checked = in_array($index, $working_days);
                        ?>
                        <div class="col-md-3 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="working_days[]" value="<?php echo $index; ?>" id="day_<?php echo $index; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="day_<?php echo $index; ?>">
                                    <?php echo $day; ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="text-end">
                <button type="submit" name="save_hours" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Booking Settings Tab -->
<?php if ($active_tab == 'booking'): ?>
<div class="table-card">
    <div class="table-header">
        <h5><i class="fas fa-calendar-check me-2"></i> Booking Settings</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="" class="settings-form">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Advance Booking Days</label>
                    <input type="number" class="form-control" name="advance_booking_days" 
                           value="<?php echo htmlspecialchars($settings_by_category['booking']['advance_booking_days']['setting_value'] ?? '60'); ?>" min="1" max="365">
                    <small class="text-muted">Maximum days in advance customers can book</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Minimum Cancel Hours</label>
                    <input type="number" class="form-control" name="min_cancel_hours" 
                           value="<?php echo htmlspecialchars($settings_by_category['booking']['min_cancel_hours']['setting_value'] ?? '24'); ?>" min="1" max="168">
                    <small class="text-muted">Hours before appointment to cancel without penalty</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Max Reschedule Count</label>
                    <input type="number" class="form-control" name="max_reschedule_count" 
                           value="<?php echo htmlspecialchars($settings_by_category['booking']['max_reschedule_count']['setting_value'] ?? '3'); ?>" min="0" max="10">
                    <small class="text-muted">Maximum number of times an appointment can be rescheduled</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Deposit Percentage</label>
                    <input type="number" class="form-control" name="deposit_percentage" 
                           value="<?php echo htmlspecialchars($settings_by_category['booking']['deposit_percentage']['setting_value'] ?? '0'); ?>" min="0" max="100">
                    <small class="text-muted">Percentage to charge as deposit for booking (0 = no deposit)</small>
                </div>
            </div>
            
            <div class="text-end">
                <button type="submit" name="save_booking" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Notification Settings Tab -->
<?php if ($active_tab == 'notifications'): ?>
<div class="table-card">
    <div class="table-header">
        <h5><i class="fas fa-bell me-2"></i> Notification Settings</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="" class="settings-form">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="booking_confirmation_sms" id="booking_confirmation_sms" 
                               <?php echo ($settings_by_category['notifications']['booking_confirmation_sms']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="booking_confirmation_sms">Send SMS on Booking Confirmation</label>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="booking_confirmation_email" id="booking_confirmation_email" 
                               <?php echo ($settings_by_category['notifications']['booking_confirmation_email']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="booking_confirmation_email">Send Email on Booking Confirmation</label>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Reminder Hours Before Appointment</label>
                    <input type="number" class="form-control" name="reminder_hours" 
                           value="<?php echo htmlspecialchars($settings_by_category['notifications']['reminder_hours']['setting_value'] ?? '24'); ?>" min="1" max="168">
                    <small class="text-muted">Hours before appointment to send reminder notifications</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Admin Notification Email</label>
                    <input type="email" class="form-control" name="admin_notification_email" 
                           value="<?php echo htmlspecialchars($settings_by_category['notifications']['admin_notification_email']['setting_value'] ?? ''); ?>">
                    <small class="text-muted">Email address for admin notifications</small>
                </div>
            </div>
            
            <div class="text-end">
                <button type="submit" name="save_notifications" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Payment Settings Tab -->
<?php if ($active_tab == 'payment'): 
    $payment_methods = explode(',', $settings_by_category['payment']['payment_methods']['setting_value'] ?? 'cash,card,online');
?>
<div class="table-card">
    <div class="table-header">
        <h5><i class="fas fa-credit-card me-2"></i> Payment Settings</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="" class="settings-form">
            <div class="row">
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">Payment Methods</label>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="payment_methods[]" value="cash" id="payment_cash" <?php echo in_array('cash', $payment_methods) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="payment_cash">Cash</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="payment_methods[]" value="card" id="payment_card" <?php echo in_array('card', $payment_methods) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="payment_card">Credit/Debit Card</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="payment_methods[]" value="online" id="payment_online" <?php echo in_array('online', $payment_methods) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="payment_online">Online Payment</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Tax Rate (%)</label>
                    <input type="number" class="form-control" name="tax_rate" 
                           value="<?php echo htmlspecialchars($settings_by_category['payment']['tax_rate']['setting_value'] ?? '0'); ?>" min="0" max="100" step="0.01">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Currency Symbol</label>
                    <select class="form-select" name="currency_symbol">
                        <option value="Rs" <?php echo ($settings_by_category['payment']['currency_symbol']['setting_value'] ?? 'Rs') == 'Rs' ? 'selected' : ''; ?>>Rs (Sri Lankan Rupee)</option>
                        <option value="$" <?php echo ($settings_by_category['payment']['currency_symbol']['setting_value'] ?? 'Rs') == '$' ? 'selected' : ''; ?>>$ (US Dollar)</option>
                        <option value="€" <?php echo ($settings_by_category['payment']['currency_symbol']['setting_value'] ?? 'Rs') == '€' ? 'selected' : ''; ?>>€ (Euro)</option>
                        <option value="£" <?php echo ($settings_by_category['payment']['currency_symbol']['setting_value'] ?? 'Rs') == '£' ? 'selected' : ''; ?>>£ (Pound Sterling)</option>
                    </select>
                </div>
            </div>
            
            <div class="text-end">
                <button type="submit" name="save_payment" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Contact Settings Tab -->
<?php if ($active_tab == 'contact'): ?>
<div class="table-card">
    <div class="table-header">
        <h5><i class="fas fa-address-card me-2"></i> Contact Settings</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="" class="settings-form">
            <div class="row">
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">Contact Email</label>
                    <input type="email" class="form-control" name="contact_email" 
                           value="<?php echo htmlspecialchars($settings_by_category['contact']['contact_email']['setting_value'] ?? ''); ?>">
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">Contact Phone</label>
                    <input type="text" class="form-control" name="contact_phone" 
                           value="<?php echo htmlspecialchars($settings_by_category['contact']['contact_phone']['setting_value'] ?? ''); ?>">
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">Address</label>
                    <textarea class="form-control" name="contact_address" rows="3"><?php echo htmlspecialchars($settings_by_category['contact']['contact_address']['setting_value'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="text-end">
                <button type="submit" name="save_contact" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include 'footer/footer.php'; ?>

<script>
function refreshPage() {
    location.reload();
}

$(document).ready(function() {
    // Initialize tooltips
    $('[title]').tooltip({ boundary: 'window' });
});
</script>

<style>
/* Dashboard Header */
.dashboard-header {
    background: white;
    border-radius: 16px;
    padding: 25px 30px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border-left: 5px solid #6f42c1;
}

/* Filter Card (for tabs) */
.filter-card {
    background: white;
    border-radius: 16px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}

.filter-header {
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

/* Settings Tabs */
.settings-tabs {
    margin-bottom: 0;
    border-bottom: none;
}

.settings-tabs .nav-link {
    border: none;
    color: #6c757d;
    padding: 8px 20px;
    font-weight: 500;
    transition: all 0.3s;
}

.settings-tabs .nav-link:hover {
    color: #6f42c1;
    background: transparent;
}

.settings-tabs .nav-link.active {
    color: #6f42c1;
    border-bottom: 2px solid #6f42c1;
    background: transparent;
}

.settings-tabs .nav-link i {
    margin-right: 8px;
}

/* Table Card */
.table-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    overflow: hidden;
    margin-bottom: 25px;
}

.table-header {
    padding: 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
}

.table-header h5 {
    margin: 0;
    color: #333;
    font-weight: 600;
}

.card-body {
    padding: 25px;
}

/* Settings Form */
.settings-form .form-label {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.settings-form .form-control,
.settings-form .form-select {
    border-radius: 8px;
    border: 1px solid #e9ecef;
    padding: 10px 15px;
}

.settings-form .form-control:focus,
.settings-form .form-select:focus {
    border-color: #6f42c1;
    box-shadow: 0 0 0 3px rgba(111, 66, 193, 0.1);
}

.settings-form .form-check-input:checked {
    background-color: #6f42c1;
    border-color: #6f42c1;
}

.settings-form .form-check-label {
    margin-left: 8px;
}

.settings-form small {
    display: block;
    margin-top: 5px;
    color: #6c757d;
}

/* Alert Custom */
.alert-custom {
    border-radius: 12px;
    border: none;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    padding: 15px 20px;
    margin-bottom: 25px;
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
    .dashboard-header {
        padding: 20px;
    }
    
    .dashboard-header h1 {
        font-size: 24px;
    }
    
    .table-header {
        padding: 15px;
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .card-body {
        padding: 15px;
    }
    
    .settings-tabs .nav-link {
        padding: 6px 12px;
        font-size: 13px;
    }
}
</style>