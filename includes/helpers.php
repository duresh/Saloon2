<?php
// includes/helpers.php

/**
 * Convert timestamp to "time ago" format
 */
function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 10) {
        return "Just Now";
    } else if ($seconds <= 60) {
        return "Just Now";
    } else if ($minutes <= 60) {
        return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    } else if ($hours <= 24) {
        return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    } else if ($days <= 7) {
        return ($days == 1) ? "yesterday" : "$days days ago";
    } else if ($weeks <= 4.3) {
        return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    } else if ($months <= 12) {
        return ($months == 1) ? "1 month ago" : "$months months ago";
    } else {
        return ($years == 1) ? "1 year ago" : "$years years ago";
    }
}

/**
 * Format date in a readable format
 */
function formatDate($date, $format = 'M d, Y') {
    if (empty($date)) return 'N/A';
    return date($format, strtotime($date));
}

/**
 * Format date with time
 */
function formatDateTime($date) {
    if (empty($date)) return 'N/A';
    return date('M d, Y h:i A', strtotime($date));
}

/**
 * Format currency
 */
function formatMoney($amount) {
    return 'Rs: ' . number_format($amount, 2);
}

/**
 * Get status badge class
 */
function getStatusClass($status) {
    $classes = [
        'pending' => 'badge-pending',
        'confirmed' => 'badge-confirmed',
        'completed' => 'badge-completed',
        'cancelled' => 'badge-cancelled',
        'active' => 'badge-active',
        'inactive' => 'badge-inactive'
    ];
    
    return isset($classes[$status]) ? $classes[$status] : 'badge-secondary';
}

/**
 * Truncate text
 */
function truncateText($text, $length = 50) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function getStaffRatingSummary($pdo, $staff_id) {
    $query = "
        SELECT 
            COUNT(*) as total_ratings,
            ROUND(AVG(rating), 1) as average_rating
        FROM staff_ratings
        WHERE staff_id = ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$staff_id]);
    return $stmt->fetch();
}


?>