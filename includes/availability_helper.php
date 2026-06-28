<?php
/**
 * Check if a time slot is available for a specific staff member
 */
function isStaffAvailableForSlot($pdo, $staff_id, $date, $time, $duration, $exclude_appointment_id = 0) {
    $start_time = strtotime($time);
    $end_time = $start_time + ($duration * 60);
    $end_time_str = date('H:i:s', $end_time);
    
    // Check for conflicting appointments for this staff member
    $query = "
        SELECT COUNT(*) as conflict_count
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.staff_id = ?
        AND a.appointment_date = ?
        AND a.id != ?
        AND a.status IN ('confirmed', 'pending')
        AND (
            (a.appointment_time <= ? AND ADDTIME(a.appointment_time, SEC_TO_TIME(s.duration * 60)) > ?)
            OR
            (? < ADDTIME(a.appointment_time, SEC_TO_TIME(s.duration * 60)) AND ? >= a.appointment_time)
        )
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        $staff_id, 
        $date, 
        $exclude_appointment_id, 
        $time, 
        $time, 
        $end_time_str, 
        $end_time_str
    ]);
    
    $result = $stmt->fetch();
    return $result['conflict_count'] == 0;
}

/**
 * Get all available staff for a specific time slot
 */
function getAvailableStaffForSlot($pdo, $date, $time, $duration, $exclude_appointment_id = 0) {
    $start_time = strtotime($time);
    $end_time = $start_time + ($duration * 60);
    $end_time_str = date('H:i:s', $end_time);
    
    // Get all active staff
    $staff_query = "
        SELECT s.*, r.fName as staff_name, r.email, r.contactNo,
               COUNT(DISTINCT a.id) as total_appointments_today,
               MAX(CASE WHEN a.appointment_date = ? THEN 1 ELSE 0 END) as has_appointment_today
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        LEFT JOIN appointments a ON s.id = a.staff_id 
            AND a.appointment_date = ?
            AND a.status IN ('confirmed', 'pending')
        WHERE r.cStatus = 1
        GROUP BY s.id
    ";
    
    $staff_stmt = $pdo->prepare($staff_query);
    $staff_stmt->execute([$date, $date]);
    $all_staff = $staff_stmt->fetchAll();
    
    $available_staff = [];
    
    foreach ($all_staff as $staff) {
        // Check if staff has any conflict at this time
        $conflict_query = "
            SELECT COUNT(*) as conflict_count
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            WHERE a.staff_id = ?
            AND a.appointment_date = ?
            AND a.id != ?
            AND a.status IN ('confirmed', 'pending')
            AND (
                (a.appointment_time <= ? AND ADDTIME(a.appointment_time, SEC_TO_TIME(s.duration * 60)) > ?)
                OR
                (? < ADDTIME(a.appointment_time, SEC_TO_TIME(s.duration * 60)) AND ? >= a.appointment_time)
            )
        ";
        
        $conflict_stmt = $pdo->prepare($conflict_query);
        $conflict_stmt->execute([
            $staff['id'],
            $date,
            $exclude_appointment_id,
            $time,
            $time,
            $end_time_str,
            $end_time_str
        ]);
        
        $conflict = $conflict_stmt->fetch();
        
        if ($conflict['conflict_count'] == 0) {
            $staff['available'] = true;
            $available_staff[] = $staff;
        }
    }
    
    return $available_staff;
}

/**
 * Check if a time slot has reached maximum capacity
 */
function isTimeSlotAtCapacity($pdo, $date, $time, $max_appointments = 10) {
    $query = "
        SELECT COUNT(*) as appointment_count
        FROM appointments
        WHERE appointment_date = ?
        AND appointment_time = ?
        AND status IN ('pending', 'confirmed')
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$date, $time]);
    $result = $stmt->fetch();
    
    return $result['appointment_count'] >= $max_appointments;
}

/**
 * Get available time slots for a specific date (considering staff availability)
 */
function getAvailableTimeSlots($pdo, $date, $max_appointments_per_slot = 10) {
    $time_slots = [];
    $start_time = strtotime('09:00');
    $end_time = strtotime('20:00');
    $interval = 30 * 60;
    
    for ($time = $start_time; $time <= $end_time; $time += $interval) {
        $time_str = date('H:i', $time);
        
        // Check if this time slot is at capacity
        if (isTimeSlotAtCapacity($pdo, $date, $time_str, $max_appointments_per_slot)) {
            $time_slots[] = [
                'time' => $time_str,
                'available' => false,
                'reason' => 'Maximum capacity reached',
                'available_staff' => 0
            ];
            continue;
        }
        
        // Get available staff for this time slot
        $available_staff = getAvailableStaffForSlot($pdo, $date, $time_str, 30); // Default 30 min duration
        $has_available_staff = count($available_staff) > 0;
        
        $time_slots[] = [
            'time' => $time_str,
            'available' => $has_available_staff,
            'reason' => $has_available_staff ? 'Available' : 'No staff available',
            'available_staff' => count($available_staff)
        ];
    }
    
    return $time_slots;
}

/**
 * Get staff schedule for a specific date
 */
function getStaffScheduleForDate($pdo, $date) {
    $query = "
        SELECT 
            s.id as staff_id,
            r.fName as staff_name,
            s.specialization,
            a.appointment_time as slot_time,
            a.status,
            a.id as appointment_id,
            serv.name as service_name,
            u.fName as customer_name
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        LEFT JOIN appointments a ON s.id = a.staff_id 
            AND a.appointment_date = ?
            AND a.status IN ('confirmed', 'pending')
        LEFT JOIN services serv ON a.service_id = serv.id
        LEFT JOIN reg u ON a.user_id = u.regID
        WHERE r.cStatus = 1
        ORDER BY s.id, a.appointment_time
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$date]);
    
    $schedule = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($schedule[$row['staff_id']])) {
            $schedule[$row['staff_id']] = [
                'staff_name' => $row['staff_name'],
                'specialization' => $row['specialization'],
                'appointments' => []
            ];
        }
        
        if ($row['slot_time']) {
            $schedule[$row['staff_id']]['appointments'][] = [
                'time' => $row['slot_time'],
                'status' => $row['status'],
                'appointment_id' => $row['appointment_id'],
                'service' => $row['service_name'],
                'customer' => $row['customer_name']
            ];
        }
    }
    
    return $schedule;
}
?>