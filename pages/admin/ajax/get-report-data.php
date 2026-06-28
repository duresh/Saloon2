<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../includes/dbcon.php';

$range = isset($_POST['range']) ? $_POST['range'] : 'month';
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');

// Set date range based on selection
if ($range != 'custom') {
    switch ($range) {
        case 'today':
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d');
            break;
        case 'week':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $end_date = date('Y-m-d');
            break;
        case 'month':
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-d');
            break;
        case 'year':
            $start_date = date('Y-01-01');
            $end_date = date('Y-m-d');
            break;
    }
}

try {
    $pdo = getPDOConnection();
    
    // ============================================
    // 1. METRICS (Total Appointments, Completed, Cancelled, Revenue)
    // ============================================
    $metrics_query = "
        SELECT 
            (SELECT COUNT(*) FROM appointments WHERE DATE(created_at) BETWEEN ? AND ?) as total_appointments,
            (SELECT COUNT(*) FROM appointments WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ?) as completed_count,
            (SELECT COUNT(*) FROM appointments WHERE status = 'cancelled' AND DATE(created_at) BETWEEN ? AND ?) as cancelled_count,
            (SELECT COALESCE(SUM(s.price), 0) 
             FROM appointments a 
             JOIN services s ON a.service_id = s.id 
             WHERE a.status = 'completed' AND DATE(a.created_at) BETWEEN ? AND ?) as total_revenue
    ";
    $metrics_stmt = $pdo->prepare($metrics_query);
    $metrics_stmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
    $metrics = $metrics_stmt->fetch(PDO::FETCH_ASSOC);
    
    // ============================================
    // 2. STATUS DATA (Pending, Confirmed, Completed, Cancelled counts)
    // ============================================
    $status_query = "
        SELECT 
            SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN a.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
        FROM appointments a
        WHERE DATE(a.created_at) BETWEEN ? AND ?
    ";
    $status_stmt = $pdo->prepare($status_query);
    $status_stmt->execute([$start_date, $end_date]);
    $status_data = $status_stmt->fetch(PDO::FETCH_ASSOC);
    
    // ============================================
    // 3. DAILY APPOINTMENTS (Date range filtered)
    // ============================================
    $daily_query = "
        SELECT 
            DATE(a.appointment_date) as date,
            COUNT(*) as total,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments a
        WHERE a.appointment_date IS NOT NULL
        AND a.appointment_date BETWEEN ? AND ?
        GROUP BY DATE(a.appointment_date)
        ORDER BY date ASC
    ";
    $daily_stmt = $pdo->prepare($daily_query);
    $daily_stmt->execute([$start_date, $end_date]);
    $daily_data = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // 4. POPULAR SERVICES (Date range filtered)
    // ============================================
    $popular_query = "
        SELECT 
            s.name,
            s.category,
            COUNT(a.id) as booking_count,
            COALESCE(SUM(s.price), 0) as total_revenue
        FROM services s
        LEFT JOIN appointments a ON s.id = a.service_id AND a.status = 'completed' AND DATE(a.created_at) BETWEEN ? AND ?
        GROUP BY s.id
        ORDER BY booking_count DESC
        LIMIT 10
    ";
    $popular_stmt = $pdo->prepare($popular_query);
    $popular_stmt->execute([$start_date, $end_date]);
    $popular_services = $popular_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // 5. CATEGORY REVENUE (Date range filtered)
    // ============================================
    $category_revenue_query = "
        SELECT 
            s.category,
            COUNT(a.id) as total_bookings,
            COALESCE(SUM(s.price), 0) as total_revenue,
            COUNT(DISTINCT a.user_id) as unique_customers
        FROM services s
        LEFT JOIN appointments a ON s.id = a.service_id AND a.status = 'completed' AND DATE(a.created_at) BETWEEN ? AND ?
        WHERE s.category IS NOT NULL AND s.category != ''
        GROUP BY s.category
        ORDER BY total_bookings DESC
    ";
    $category_revenue_stmt = $pdo->prepare($category_revenue_query);
    $category_revenue_stmt->execute([$start_date, $end_date]);
    $category_revenue = $category_revenue_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // 6. STAFF PERFORMANCE (Date range filtered)
    // ============================================
    $staff_query = "
        SELECT 
            r.fName as staff_name,
            st.specialization,
            COUNT(a.id) as total_appointments,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
            SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
            ROUND(AVG(sr.rating), 1) as avg_rating,
            COALESCE(SUM(CASE WHEN a.status = 'completed' THEN srv.price ELSE 0 END), 0) as total_revenue
        FROM staff st
        JOIN reg r ON st.user_id = r.regID
        LEFT JOIN appointments a ON st.id = a.staff_id AND DATE(a.created_at) BETWEEN ? AND ?
        LEFT JOIN services srv ON a.service_id = srv.id
        LEFT JOIN staff_ratings sr ON st.id = sr.staff_id
        WHERE r.cStatus = 1
        GROUP BY st.id
        ORDER BY completed_appointments DESC
        LIMIT 10
    ";
    $staff_stmt = $pdo->prepare($staff_query);
    $staff_stmt->execute([$start_date, $end_date]);
    $staff_performance = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // 7. MONTHLY TRENDS (Last 12 months or date range)
    // ============================================
    $monthly_query = "
        SELECT 
            DATE_FORMAT(a.appointment_date, '%Y-%m') as month,
            COUNT(*) as total_appointments,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
            COALESCE(SUM(s.price), 0) as revenue
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.status = 'completed'
        AND a.appointment_date IS NOT NULL
        AND a.appointment_date BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(a.appointment_date, '%Y-%m')
        ORDER BY month ASC
    ";
    $monthly_stmt = $pdo->prepare($monthly_query);
    $monthly_stmt->execute([$start_date, $end_date]);
    $monthly_trends = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // 8. HOURLY DISTRIBUTION (Date range filtered)
    // ============================================
    $hourly_query = "
        SELECT 
            HOUR(a.appointment_time) as hour,
            COUNT(*) as bookings
        FROM appointments a
        WHERE a.appointment_time IS NOT NULL
        AND DATE(a.appointment_date) BETWEEN ? AND ?
        GROUP BY HOUR(a.appointment_time)
        ORDER BY hour ASC
    ";
    $hourly_stmt = $pdo->prepare($hourly_query);
    $hourly_stmt->execute([$start_date, $end_date]);
    $hourly_data = $hourly_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fill in missing hours with zero bookings
    $hourly_data_complete = [];
    for ($i = 0; $i <= 23; $i++) {
        $found = false;
        foreach ($hourly_data as $hour) {
            if ($hour['hour'] == $i) {
                $hourly_data_complete[] = $hour;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $hourly_data_complete[] = ['hour' => $i, 'bookings' => 0];
        }
    }
    $hourly_data = $hourly_data_complete;
    
    // ============================================
    // 9. CUSTOMER RETENTION (Overall - not date filtered for meaningful analysis)
    // ============================================
    $retention_query = "
        SELECT 
            CASE 
                WHEN total_visits = 1 THEN 'New Customers'
                WHEN total_visits = 2 THEN '2 Visits'
                WHEN total_visits BETWEEN 3 AND 5 THEN '3-5 Visits'
                ELSE '5+ Visits'
            END as customer_type,
            COUNT(*) as customer_count
        FROM (
            SELECT user_id, COUNT(*) as total_visits
            FROM appointments
            WHERE status = 'completed'
            GROUP BY user_id
        ) as visit_counts
        GROUP BY customer_type
        ORDER BY 
            CASE 
                WHEN customer_type = 'New Customers' THEN 1
                WHEN customer_type = '2 Visits' THEN 2
                WHEN customer_type = '3-5 Visits' THEN 3
                ELSE 4
            END
    ";
    $retention_stmt = $pdo->query($retention_query);
    $retention_data = $retention_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // 10. CATEGORY PERFORMANCE (Date range filtered)
    // ============================================
    $category_perf_query = "
        SELECT 
            s.category,
            COUNT(a.id) as total_bookings,
            COALESCE(SUM(s.price), 0) as total_revenue,
            COUNT(DISTINCT a.user_id) as unique_customers
        FROM services s
        LEFT JOIN appointments a ON s.id = a.service_id AND a.status = 'completed' AND DATE(a.created_at) BETWEEN ? AND ?
        WHERE s.category IS NOT NULL AND s.category != ''
        GROUP BY s.category
        ORDER BY total_bookings DESC
    ";
    $category_perf_stmt = $pdo->prepare($category_perf_query);
    $category_perf_stmt->execute([$start_date, $end_date]);
    $category_performance = $category_perf_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // 11. ADDITIONAL: NEW CUSTOMERS COUNT (Date range filtered)
    // ============================================
    $new_customers_query = "
        SELECT COUNT(*) as count
        FROM reg
        WHERE role = 'user' AND cStatus = 1
        AND DATE(regDate) BETWEEN ? AND ?
    ";
    $new_customers_stmt = $pdo->prepare($new_customers_query);
    $new_customers_stmt->execute([$start_date, $end_date]);
    $new_customers = $new_customers_stmt->fetch(PDO::FETCH_ASSOC);
    $metrics['new_customers'] = $new_customers['count'] ?? 0;
    
    // ============================================
    // 12. ADDITIONAL: AVERAGE TRANSACTION VALUE (Date range filtered)
    // ============================================
    $avg_transaction_query = "
        SELECT COALESCE(AVG(s.price), 0) as avg_value
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.status = 'completed'
        AND DATE(a.created_at) BETWEEN ? AND ?
    ";
    $avg_transaction_stmt = $pdo->prepare($avg_transaction_query);
    $avg_transaction_stmt->execute([$start_date, $end_date]);
    $avg_transaction = $avg_transaction_stmt->fetch(PDO::FETCH_ASSOC);
    $metrics['avg_transaction_value'] = $avg_transaction['avg_value'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'date_range' => [
            'start' => $start_date,
            'end' => $end_date,
            'range' => $range
        ],
        'metrics' => $metrics,
        'status_data' => $status_data,
        'daily_data' => $daily_data,
        'popular_services' => $popular_services,
        'category_revenue' => $category_revenue,
        'staff_performance' => $staff_performance,
        'monthly_trends' => $monthly_trends,
        'hourly_data' => $hourly_data,
        'retention_data' => $retention_data,
        'category_performance' => $category_performance
    ]);
    
} catch (PDOException $e) {
    error_log('Get report data error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage(),
        'sql_error' => true
    ]);
} catch (Exception $e) {
    error_log('Get report data error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>