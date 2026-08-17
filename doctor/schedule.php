<?php
ob_start();
session_start();
if (empty($_SESSION['doctor_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/../db-connection/db_conn.php';

$doctorId = intval($_SESSION['doctor_id']);
$doctorName = $_SESSION['doctor_name'] ?? 'Doctor';

// Handle POST Form Submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_schedule') {
        $shiftStart = trim($_POST['shift_start'] ?? '09:00');
        $shiftEnd = trim($_POST['shift_end'] ?? '17:00');
        $slotDuration = intval($_POST['slot_duration'] ?? 30);
        $availableTime = trim($_POST['available_time'] ?? '');
        $status = trim($_POST['status'] ?? 'Available');

        if (strtotime($shiftStart) >= strtotime($shiftEnd)) {
            $message = 'Shift start time must be earlier than shift end time.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare('UPDATE tbl_doctor SET shift_start = ?, shift_end = ?, slot_duration = ?, available_time = ?, status = ? WHERE doctor_id = ?');
            $stmt->bind_param('ssissi', $shiftStart, $shiftEnd, $slotDuration, $availableTime, $status, $doctorId);
            if ($stmt->execute()) {
                $message = 'Working hours & schedule settings updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Database error updating schedule settings.';
                $messageType = 'error';
            }
            $stmt->close();
        }
    } elseif ($action === 'quick_status') {
        $newStatus = trim($_POST['new_status'] ?? 'Available');
        $allowedStatuses = ['Available', 'Unavailable', 'On Leave'];
        if (in_array($newStatus, $allowedStatuses)) {
            $stmt = $conn->prepare('UPDATE tbl_doctor SET status = ? WHERE doctor_id = ?');
            $stmt->bind_param('si', $newStatus, $doctorId);
            if ($stmt->execute()) {
                $message = "Availability status updated to '$newStatus'.";
                $messageType = 'success';
            }
            $stmt->close();
        }
    }
}

// Fetch Doctor Data
$stmt = $conn->prepare('SELECT * FROM tbl_doctor WHERE doctor_id = ? LIMIT 1');
$stmt->bind_param('i', $doctorId);
$stmt->execute();
$doctorRes = $stmt->get_result();
$doctor = $doctorRes->fetch_assoc() ?: [];
$stmt->close();

$department = 'General';
if (!empty($doctor['department_id'])) {
    $deptStmt = $conn->prepare('SELECT department_name FROM tbl_department WHERE department_id = ? LIMIT 1');
    $deptStmt->bind_param('i', $doctor['department_id']);
    $deptStmt->execute();
    $deptRes = $deptStmt->get_result();
    if ($dRow = $deptRes->fetch_assoc()) {
        $department = $dRow['department_name'];
    }
    $deptStmt->close();
}

$initials = '';
if (!empty($doctor['first_name'])) $initials .= strtoupper($doctor['first_name'][0]);
if (!empty($doctor['last_name'])) $initials .= strtoupper($doctor['last_name'][0]);
if (empty($initials)) $initials = 'DR';
$profilePhoto = !empty($doctor['profile_photo']) ? '../uploads/doctors/' . $doctor['profile_photo'] : '';

// Selected Date for Agenda View
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedDateFormatted = date('l, F j, Y', strtotime($selectedDate));

// Fetch Appointments for Selected Date
$apptSql = "SELECT a.*, p.first_name AS pat_fname, p.middle_name AS pat_mname, p.last_name AS pat_lname, p.gender, p.age, p.phone_number
            FROM tbl_appointment a
            JOIN tbl_patient p ON a.patient_id = p.patient_id
            WHERE a.doctor_id = ? AND a.appointment_date = ?
            ORDER BY a.appointment_time ASC";
$aStmt = $conn->prepare($apptSql);
$aStmt->bind_param('is', $doctorId, $selectedDate);
$aStmt->execute();
$aRes = $aStmt->get_result();
$dateAppointments = [];
while ($aRow = $aRes->fetch_assoc()) {
    $formattedTime = date('H:i', strtotime($aRow['appointment_time']));
    $dateAppointments[$formattedTime][] = $aRow;
}
$aStmt->close();

// Generate Time Slots based on Shift Start, Shift End, and Slot Duration
$shiftStartSec = strtotime($doctor['shift_start'] ?? '09:00');
$shiftEndSec = strtotime($doctor['shift_end'] ?? '17:00');
$durationSec = max(15, intval($doctor['slot_duration'] ?? 30)) * 60;

$generatedSlots = [];
if ($shiftStartSec < $shiftEndSec) {
    for ($curr = $shiftStartSec; $curr < $shiftEndSec; $curr += $durationSec) {
        $slotKey = date('H:i', $curr);
        $slotDisplay = date('h:i A', $curr) . ' - ' . date('h:i A', $curr + $durationSec);
        
        // Find matched appointment
        $matchedAppts = $dateAppointments[$slotKey] ?? [];
        
        $generatedSlots[] = [
            'time_key' => $slotKey,
            'display' => $slotDisplay,
            'appointments' => $matchedAppts
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule & Working Hours | Dr. <?php echo htmlspecialchars($doctorName); ?> | Medi-Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-appointments.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-schedule.css?v=<?php echo time(); ?>">
</head>
<body>

    <div class="bg-pattern"></div>

    <div class="dashboard-layout">

        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- ===== MAIN CONTENT ===== -->
        <main class="main-content">

            <!-- Top Header Bar -->
            <header class="top-header">
                <div class="top-header-left">
                    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">☰</button>
                    <div>
                        <?php include __DIR__ . '/../includes/breadcrumb.php'; ?>
                    </div>
                </div>
                <div class="top-header-right">
                    <button class="header-icon-btn" title="Notifications">
                        <i class="fi fi-rr-bell"></i>
                    </button>
                    <a href="profile.php" class="header-profile">
                        <div class="header-profile-avatar">
                            <?php if ($profilePhoto): ?>
                                <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Avatar">
                            <?php else: ?>
                                <?php echo $initials; ?>
                            <?php endif; ?>
                        </div>
                        <span class="header-profile-name">Dr. <?php echo htmlspecialchars(explode(' ', $doctorName)[0]); ?></span>
                    </a>
                </div>
            </header>

            <div class="dashboard-content">

                <?php if (!empty($message)): ?>
                    <div class="toast-popup show" style="margin-bottom: 20px; <?php echo $messageType === 'error' ? 'background: linear-gradient(135deg, #FF6B6B, #FF8FA3);' : ''; ?>">
                        <div class="toast-icon">
                            <i class="fi fi-rr-<?php echo $messageType === 'success' ? 'check-circle' : 'cross-circle'; ?>"></i>
                        </div>
                        <p><?php echo htmlspecialchars($message); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Status Banner & Quick Switch -->
                <div class="schedule-status-banner">
                    <div class="status-banner-info">
                        <h2>
                            <i class="fi fi-rr-clock"></i> Current Availability Status: 
                            <span style="color: var(--primary);"><?php echo htmlspecialchars($doctor['status'] ?? 'Available'); ?></span>
                        </h2>
                        <p>Shift: <?php echo date('h:i A', strtotime($doctor['shift_start'] ?? '09:00')); ?> – <?php echo date('h:i A', strtotime($doctor['shift_end'] ?? '17:00')); ?> (<?php echo intval($doctor['slot_duration'] ?? 30); ?> min slots)</p>
                    </div>
                    <div class="status-pills-group">
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="action" value="quick_status">
                            <input type="hidden" name="new_status" value="Available">
                            <button type="submit" class="status-pill-btn available <?php echo ($doctor['status'] ?? '') === 'Available' ? 'active' : ''; ?>">
                                ● Available
                            </button>
                        </form>
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="action" value="quick_status">
                            <input type="hidden" name="new_status" value="Unavailable">
                            <button type="submit" class="status-pill-btn unavailable <?php echo ($doctor['status'] ?? '') === 'Unavailable' ? 'active' : ''; ?>">
                                ● Unavailable
                            </button>
                        </form>
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="action" value="quick_status">
                            <input type="hidden" name="new_status" value="On Leave">
                            <button type="submit" class="status-pill-btn on-leave <?php echo ($doctor['status'] ?? '') === 'On Leave' ? 'active' : ''; ?>">
                                ● On Leave
                            </button>
                        </form>
                    </div>
                </div>

                <div class="schedule-grid">

                    <!-- Left Column: Shift & Schedule Form Settings -->
                    <div class="schedule-card">
                        <div class="schedule-card-header">
                            <h3><i class="fi fi-rr-settings"></i> Shift & Slot Settings</h3>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_schedule">

                            <div class="form-group" style="margin-bottom: 16px;">
                                <label class="form-label" for="shift_start">Shift Start Time</label>
                                <input type="time" id="shift_start" name="shift_start" class="form-input" value="<?php echo htmlspecialchars($doctor['shift_start'] ?? '09:00'); ?>" required>
                            </div>

                            <div class="form-group" style="margin-bottom: 16px;">
                                <label class="form-label" for="shift_end">Shift End Time</label>
                                <input type="time" id="shift_end" name="shift_end" class="form-input" value="<?php echo htmlspecialchars($doctor['shift_end'] ?? '17:00'); ?>" required>
                            </div>

                            <div class="form-group" style="margin-bottom: 16px;">
                                <label class="form-label" for="slot_duration">Consultation Slot Duration</label>
                                <select id="slot_duration" name="slot_duration" class="form-input" required>
                                    <option value="15" <?php echo ($doctor['slot_duration'] ?? 30) == 15 ? 'selected' : ''; ?>>15 Minutes</option>
                                    <option value="20" <?php echo ($doctor['slot_duration'] ?? 30) == 20 ? 'selected' : ''; ?>>20 Minutes</option>
                                    <option value="30" <?php echo ($doctor['slot_duration'] ?? 30) == 30 ? 'selected' : ''; ?>>30 Minutes</option>
                                    <option value="45" <?php echo ($doctor['slot_duration'] ?? 30) == 45 ? 'selected' : ''; ?>>45 Minutes</option>
                                    <option value="60" <?php echo ($doctor['slot_duration'] ?? 30) == 60 ? 'selected' : ''; ?>>60 Minutes</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 16px;">
                                <label class="form-label" for="available_time">Working Days / Notes</label>
                                <input type="text" id="available_time" name="available_time" class="form-input" value="<?php echo htmlspecialchars($doctor['available_time'] ?? ''); ?>" placeholder="e.g. Mon - Fri (Morning & Evening)">
                            </div>

                            <div class="form-group" style="margin-bottom: 24px;">
                                <label class="form-label" for="status">Overall Status</label>
                                <select id="status" name="status" class="form-input" required>
                                    <option value="Available" <?php echo ($doctor['status'] ?? '') === 'Available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="Unavailable" <?php echo ($doctor['status'] ?? '') === 'Unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                                    <option value="On Leave" <?php echo ($doctor['status'] ?? '') === 'On Leave' ? 'selected' : ''; ?>>On Leave</option>
                                </select>
                            </div>

                            <button type="submit" class="btn-reschedule" style="width: 100%; display: flex; justify-content: center; align-items: center; gap: 8px;">
                                <i class="fi fi-rr-disk"></i> Save Schedule Settings
                            </button>
                        </form>
                    </div>

                    <!-- Right Column: Interactive Daily Time-Slot Agenda -->
                    <div class="schedule-card">
                        <div class="schedule-card-header">
                            <h3><i class="fi fi-rr-calendar"></i> Daily Agenda Grid</h3>
                            <span style="font-size: 0.82rem; font-weight: 600; color: var(--text-secondary);"><?php echo count($generatedSlots); ?> Slots Generated</span>
                        </div>

                        <!-- Date Filter Bar -->
                        <div class="date-selector-bar">
                            <span style="font-weight: 600; font-size: 0.88rem; color: var(--text-primary);"><i class="fi fi-rr-calendar-clock"></i> <?php echo $selectedDateFormatted; ?></span>
                            <form method="GET" action="">
                                <input type="date" name="date" class="form-input" value="<?php echo htmlspecialchars($selectedDate); ?>" onchange="this.form.submit()" style="padding: 4px 10px; font-size: 0.82rem;">
                            </form>
                        </div>

                        <!-- Time Slot List -->
                        <div class="time-slot-list">
                            <?php if (!empty($generatedSlots)): ?>
                                <?php foreach ($generatedSlots as $slot): ?>
                                    <div class="time-slot-row">
                                        <div class="time-slot-time">
                                            <i class="fi fi-rr-clock"></i> <?php echo date('h:i A', strtotime($slot['time_key'])); ?>
                                        </div>
                                        <div class="time-slot-content">
                                            <?php if (!empty($slot['appointments'])): ?>
                                                <?php foreach ($slot['appointments'] as $appt): 
                                                    $patName = trim($appt['pat_fname'] . ' ' . $appt['pat_mname'] . ' ' . $appt['pat_lname']);
                                                    $patInitials = strtoupper(($appt['pat_fname'][0] ?? 'P') . ($appt['pat_lname'][0] ?? 'T'));
                                                    $statusLower = strtolower($appt['status']);
                                                ?>
                                                    <div class="slot-patient-info">
                                                        <div class="slot-patient-avatar"><?php echo $patInitials; ?></div>
                                                        <div>
                                                            <div class="slot-patient-name"><?php echo htmlspecialchars($patName); ?></div>
                                                            <div class="slot-patient-meta">
                                                                <span><?php echo htmlspecialchars($appt['gender'] ?? 'N/A'); ?>, <?php echo htmlspecialchars($appt['age'] ?? 'N/A'); ?> yrs</span>
                                                                <span>•</span>
                                                                <span><?php echo htmlspecialchars($appt['appointment_type']); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <span class="slot-badge-booked">
                                                        <i class="fi fi-rr-user"></i> <?php echo htmlspecialchars($appt['status']); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span style="font-size: 0.83rem; color: var(--text-muted);">No booking</span>
                                                <span class="slot-badge-open">
                                                    <i class="fi fi-rr-check"></i> Open
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                    <p>No valid shift timings set. Please configure your shift start and end times.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>

                </div>

            </div>

        </main>

    </div>

</body>
</html>
