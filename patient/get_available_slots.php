<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db-connection/db_conn.php';

$doctorId = intval($_GET['doctor_id'] ?? 0);
$date = trim($_GET['date'] ?? '');

if ($doctorId <= 0 || empty($date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

// Fetch doctor shift settings
$stmt = $conn->prepare('SELECT shift_start, shift_end, slot_duration, available_time FROM tbl_doctor WHERE doctor_id = ? LIMIT 1');
$stmt->bind_param('i', $doctorId);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doctor) {
    echo json_encode(['success' => false, 'message' => 'Doctor not found.']);
    exit;
}

$shiftStartStr = !empty($doctor['shift_start']) ? $doctor['shift_start'] : '09:00:00';
$shiftEndStr = !empty($doctor['shift_end']) ? $doctor['shift_end'] : '17:00:00';
$slotDuration = !empty($doctor['slot_duration']) ? intval($doctor['slot_duration']) : 30;

$startTime = strtotime($date . ' ' . $shiftStartStr);
$endTime = strtotime($date . ' ' . $shiftEndStr);

if (!$startTime || !$endTime || $startTime >= $endTime) {
    // Fallback to default 9 AM to 5 PM
    $startTime = strtotime($date . ' 09:00:00');
    $endTime = strtotime($date . ' 17:00:00');
}

// Fetch existing booked slots for this doctor on this date
$bookedStmt = $conn->prepare("SELECT appointment_time FROM tbl_appointment WHERE doctor_id = ? AND appointment_date = ? AND status IN ('Pending', 'Confirmed')");
$bookedStmt->bind_param('is', $doctorId, $date);
$bookedStmt->execute();
$res = $bookedStmt->get_result();

$bookedTimes = [];
while ($row = $res->fetch_assoc()) {
    // Standardize time string format (e.g., 09:00 AM)
    $t = strtotime($date . ' ' . $row['appointment_time']);
    if ($t) {
        $bookedTimes[] = date('g:i A', $t);
    }
}
$bookedStmt->close();

// Generate all possible slots
$slots = [];
$current = $startTime;
$now = time();

while ($current < $endTime) {
    $slotTimeFormatted = date('g:i A', $current);
    
    // Check if slot is already in the past (if date is today)
    $isPast = ($current < $now);
    
    // Check if slot is booked
    $isBooked = in_array($slotTimeFormatted, $bookedTimes);
    
    $slots[] = [
        'time' => $slotTimeFormatted,
        'available' => !$isBooked && !$isPast,
        'reason' => $isBooked ? 'Booked' : ($isPast ? 'Past' : 'Available')
    ];
    
    $current += ($slotDuration * 60);
}

echo json_encode(['success' => true, 'slots' => $slots]);
