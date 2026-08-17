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

// Fetch doctor data for sidebar
$stmt = $conn->prepare('SELECT * FROM tbl_doctor WHERE doctor_id = ? LIMIT 1');
$stmt->bind_param('i', $doctorId);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc() ?: [];
$stmt->close();

// Get initials for avatar fallback
$initials = '';
if (!empty($doctor['first_name'])) $initials .= strtoupper($doctor['first_name'][0]);
if (!empty($doctor['last_name'])) $initials .= strtoupper($doctor['last_name'][0]);
if (empty($initials)) $initials = 'DR';
$profilePhoto = !empty($doctor['profile_photo']) ? '../uploads/doctors/' . $doctor['profile_photo'] : '';
$department = $doctor['department'] ?? 'General';

$errors = [];
$successMessage = '';

// Handle Reschedule by Doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_appointment'])) {
    $appointment_id = intval($_POST['appointment_id'] ?? 0);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';

    // Validation
    if ($appointment_id <= 0) $errors[] = 'Invalid appointment.';
    if (empty($appointment_date)) $errors[] = 'New appointment date is required.';
    if (empty($appointment_time)) {
        $errors[] = 'New appointment time is required.';
    } else {
        $parsedTime = strtotime($appointment_time);
        if ($parsedTime) {
            $appointment_time = date('H:i:s', $parsedTime);
        }
    }

    if (empty($errors)) {
        // Verify this appointment belongs to this doctor
        $checkStmt = $conn->prepare('SELECT doctor_id FROM tbl_appointment WHERE appointment_id = ? LIMIT 1');
        $checkStmt->bind_param('i', $appointment_id);
        $checkStmt->execute();
        $checkRes = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($checkRes && intval($checkRes['doctor_id']) === $doctorId) {
            $updateStmt = $conn->prepare('UPDATE tbl_appointment SET appointment_date = ?, appointment_time = ? WHERE appointment_id = ?');
            $updateStmt->bind_param('ssi', $appointment_date, $appointment_time, $appointment_id);
            if ($updateStmt->execute()) {
                $_SESSION['appt_success'] = 'Appointment time updated successfully.';
                header('Location: appointments.php');
                exit;
            } else {
                $errors[] = 'Failed to reschedule appointment.';
            }
            $updateStmt->close();
        } else {
            $errors[] = 'Access denied or invalid appointment.';
        }
    }
}

// Handle appointment action by Doctor (confirm or decline)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $appointment_id = intval($_POST['appointment_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $validActions = ['confirm', 'cancel', 'acknowledge_reschedule'];

    if ($appointment_id <= 0 || !in_array($action, $validActions, true)) {
        $errors[] = 'Invalid appointment action.';
    } else {
        $checkStmt = $conn->prepare('SELECT doctor_id, status FROM tbl_appointment WHERE appointment_id = ? LIMIT 1');
        $checkStmt->bind_param('i', $appointment_id);
        $checkStmt->execute();
        $checkRes = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($checkRes && intval($checkRes['doctor_id']) === $doctorId) {
            if ($action === 'acknowledge_reschedule') {
                // Clear the reschedule note
                $ackStmt = $conn->prepare('UPDATE tbl_appointment SET reschedule_note = NULL WHERE appointment_id = ?');
                $ackStmt->bind_param('i', $appointment_id);
                if ($ackStmt->execute()) {
                    $_SESSION['appt_success'] = 'Reschedule notification acknowledged.';
                    header('Location: appointments.php');
                    exit;
                } else {
                    $errors[] = 'Failed to acknowledge reschedule.';
                }
                $ackStmt->close();
            } else {
                $newStatus = $action === 'confirm' ? 'Confirmed' : 'Cancelled';
                if ($checkRes['status'] === $newStatus) {
                    $errors[] = 'Appointment is already ' . strtolower($newStatus) . '.';
                } else {
                    $statusStmt = $conn->prepare('UPDATE tbl_appointment SET status = ?, reschedule_note = NULL WHERE appointment_id = ?');
                    $statusStmt->bind_param('si', $newStatus, $appointment_id);
                    if ($statusStmt->execute()) {
                        $_SESSION['appt_success'] = 'Appointment ' . ($action === 'confirm' ? 'confirmed' : 'declined') . ' successfully.';
                        header('Location: appointments.php');
                        exit;
                    } else {
                        $errors[] = 'Failed to update appointment status.';
                    }
                    $statusStmt->close();
                }
            }
        } else {
            $errors[] = 'Access denied or invalid appointment.';
        }
    }
}

// Handle save medical records (Report, Investigation, Follow Up) for Timeline Appointments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_medical_records'])) {
    $appointment_id = intval($_POST['appointment_id'] ?? 0);
    $submittedApptId = $appointment_id;
    $report = trim($_POST['report'] ?? '');
    $investigation = trim($_POST['investigation'] ?? '');
    $follow_up_required = $_POST['follow_up_required'] ?? 'no';
    $follow_up_date = $_POST['follow_up_date'] ?? '';
    $follow_up_reason = trim($_POST['follow_up_reason'] ?? '');

    $fieldErrors = [];

    // Validation
    if ($appointment_id <= 0) {
        $errors[] = 'Invalid appointment.';
    }
    if (empty($report)) {
        $reportError = 'Report details are required.';
        $fieldErrors[] = $reportError;
    }
    if (empty($investigation)) {
        $investigationError = 'Investigation details are required.';
        $fieldErrors[] = $investigationError;
    }
    if ($follow_up_required === 'yes') {
        $min_fup_date = date('Y-m-d', strtotime('+1 day'));
        if (empty($follow_up_date) || $follow_up_date < $min_fup_date) {
            $fupDateError = 'Please select a valid date.';
            $fieldErrors[] = $fupDateError;
        }
        if (empty($follow_up_reason)) {
            $fupReasonError = 'Follow-up reason is required.';
            $fieldErrors[] = $fupReasonError;
        }
    }

    if (empty($fieldErrors) && empty($errors)) {
        // Verify this appointment belongs to this doctor and get patient_id
        $checkStmt = $conn->prepare('SELECT doctor_id, patient_id FROM tbl_appointment WHERE appointment_id = ? LIMIT 1');
        $checkStmt->bind_param('i', $appointment_id);
        $checkStmt->execute();
        $checkRes = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($checkRes && intval($checkRes['doctor_id']) === $doctorId) {
            $patient_id = intval($checkRes['patient_id']);
            $conn->begin_transaction();

            try {
                // Update existing appointment: save report, investigation, and follow_up text (if required, save date & reason, else NULL)
                $follow_up_text = ($follow_up_required === 'yes') ? "Required on " . $follow_up_date . " - " . $follow_up_reason : NULL;

                $updateStmt = $conn->prepare('UPDATE tbl_appointment SET report = ?, investigation = ?, follow_up = ?, status = ? WHERE appointment_id = ?');
                $status = 'Completed';
                $updateStmt->bind_param('ssssi', $report, $investigation, $follow_up_text, $status, $appointment_id);
                $updateStmt->execute();
                $updateStmt->close();

                // If follow-up required, insert into tbl_follow_up
                if ($follow_up_required === 'yes') {
                    $insertFUP = $conn->prepare('INSERT INTO tbl_follow_up (appointment_id, patient_id, doctor_id, follow_up_date, follow_up_reason, status) VALUES (?, ?, ?, ?, ?, ?)');
                    $fupStatus = 'Pending';
                    $insertFUP->bind_param('iiisss', $appointment_id, $patient_id, $doctorId, $follow_up_date, $follow_up_reason, $fupStatus);
                    $insertFUP->execute();
                    $insertFUP->close();
                }

                // Insert into tbl_prescription if medications are provided
                if (!empty($medications)) {
                    $insertRx = $conn->prepare('INSERT INTO tbl_prescription (appointment_id, patient_id, doctor_id, medications, instructions) VALUES (?, ?, ?, ?, ?)');
                    $insertRx->bind_param('iiiss', $appointment_id, $patient_id, $doctorId, $medications, $instructions);
                    $insertRx->execute();
                    $insertRx->close();
                }

                $conn->commit();
                $_SESSION['appt_success'] = 'Medical records and follow-up saved successfully.';
                header('Location: appointments.php');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Failed to save medical records: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'Access denied or invalid appointment.';
        }
    }
}

if (!empty($_SESSION['appt_success'])) {
    $successMessage = $_SESSION['appt_success'];
    unset($_SESSION['appt_success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Appointments | Dr. <?php echo htmlspecialchars($doctorName); ?> | Medi-Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-appointments.css?v=<?php echo time(); ?>">
</head>
<body>

    <div class="bg-pattern"></div>
    <div class="dashboard-layout">
        <!-- Shared Sidebar Component -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- ===== MAIN CONTENT ===== -->
        <main class="main-content">
            <header class="top-header">
                <div class="top-header-left">
                    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">☰</button>
                    <div>
                        <?php include __DIR__ . '/../includes/breadcrumb.php'; ?>
                    </div>
                </div>
            </header>

                <div class="dashboard-content">
                    <?php if (!empty($errors)): ?>
                        <div class="hms-error-box">
                            <ul>
                                <?php foreach ($errors as $e): ?>
                                    <li><?php echo htmlspecialchars($e); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($successMessage)): ?>
                        <div class="toast-popup show" style="top: 24px; right: 24px;" id="successAlert">
                            <div class="toast-icon">✅</div>
                            <p><?php echo htmlspecialchars($successMessage); ?></p>
                        </div>
                        <script>
                            setTimeout(function() {
                                document.getElementById('successAlert').classList.remove('show');
                            }, 3000);
                        </script>
                    <?php endif; ?>

                    <?php
                    $todayDate = date('Y-m-d');

                    // Fetch Normal Appointments (Everything EXCEPT today's confirmed/completed appointments)
                    $stmt = $conn->prepare("SELECT a.*, p.first_name, p.middle_name, p.last_name, p.date_of_birth, p.gender, p.phone_number, p.profile_photo 
                                            FROM tbl_appointment a
                                            JOIN tbl_patient p ON a.patient_id = p.patient_id
                                            WHERE a.doctor_id = ?
                                              AND NOT (a.appointment_date = ? AND a.status IN ('Confirmed', 'Completed'))
                                            ORDER BY a.appointment_date DESC, a.appointment_time DESC");
                    $stmt->bind_param('is', $doctorId, $todayDate);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $count = $result ? $result->num_rows : 0;
                    $stmt->close();

                    // Fetch Timeline Appointments (Confirmed/Completed AND appointment date is exactly today)
                    $stmtTimeline = $conn->prepare("SELECT a.*, p.first_name, p.middle_name, p.last_name, p.date_of_birth, p.gender, p.phone_number, p.profile_photo 
                                                    FROM tbl_appointment a
                                                    JOIN tbl_patient p ON a.patient_id = p.patient_id
                                                    WHERE a.doctor_id = ?
                                                      AND a.appointment_date = ?
                                                      AND a.status IN ('Confirmed', 'Completed')
                                                    ORDER BY a.appointment_date DESC, a.appointment_time DESC");
                    $stmtTimeline->bind_param('is', $doctorId, $todayDate);
                    $stmtTimeline->execute();
                    $resultTimeline = $stmtTimeline->get_result();
                    $countTimeline = $resultTimeline ? $resultTimeline->num_rows : 0;
                    $stmtTimeline->close();
                    ?>

                    <!-- Tabs Navigation -->
                    <div class="appt-tabs">
                        <button type="button" class="appt-tab-btn active" data-tab="scheduled" onclick="switchTab('scheduled')">
                            📅 Scheduled Appointments (<?php echo $count; ?>)
                        </button>
                        <button type="button" class="appt-tab-btn" data-tab="timeline" onclick="switchTab('timeline')">
                            🕒 Timeline Appointments (<?php echo $countTimeline; ?>)
                        </button>
                    </div>

                    <!-- Scheduled Appointments Tab Content -->
                    <div id="scheduled-tab" class="tab-content active">
                        <div class="appointment-table-card" style="margin-bottom: 24px;">
                        <div class="card-header" style="margin-bottom: 16px;">
                            <h3 class="card-title">All Appointments</h3>
                            <span class="card-badge"><?php echo $count; ?> record<?php echo $count !== 1 ? 's' : ''; ?></span>
                        </div>
                        <table class="admin-table" style="margin-top: 0;">
                            <thead>
                                <tr>
                                    <th>S.N.</th>
                                    <th>Patient Name</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Type</th>
                                    <th>Consultation Fee</th>
                                    <th>Status</th>
                                    <th style="text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($count > 0): 
                                    $serial_no = 1;
                                    while ($row = $result->fetch_assoc()):
                                        $patName = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
                                        $status = $row['status'];
                                        $statusLower = strtolower($status);
                                        $rescheduleNote = $row['reschedule_note'] ?? null;
                                ?>
                                    <tr>
                                        <td><?php echo $serial_no++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($patName); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></td>
                                        <td><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></td>
                                        <td>
                                            <span class="appt-badge <?php echo strtolower($row['appointment_type']) === 'online' ? 'online' : 'in-person'; ?>">
                                                <?php echo htmlspecialchars($row['appointment_type']); ?>
                                            </span>
                                        </td>
                                        <td><span style="color: var(--accent); font-weight:600;">Rs. <?php echo number_format($row['consultation_fee'], 2); ?></span></td>
                                        <td>
                                            <span class="appt-badge status-badge <?php echo $statusLower; ?>">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                            <?php if (!empty($rescheduleNote)): ?>
                                                <div class="reschedule-alert" style="margin-top: 6px;">
                                                    <span class="reschedule-alert-text"><i class="fi fi-rr-refresh"></i> <?php echo htmlspecialchars($rescheduleNote); ?></span>
                                                    <form method="POST" action="" style="display:inline; margin-left: 6px;">
                                                        <input type="hidden" name="action" value="acknowledge_reschedule">
                                                        <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                                                        <button type="submit" class="reschedule-ack-btn" title="Dismiss">✓ OK</button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php if ($status === 'Pending' || $status === 'Confirmed'): ?>
                                                <div class="dropdown-action-wrapper">
                                                    <button type="button" class="dropdown-action-trigger" onclick="toggleDropdown(this)">
                                                        Actions <span class="arrow-icon">▼</span>
                                                    </button>
                                                    <div class="dropdown-action-menu">
                                                        <?php if ($status === 'Pending'): ?>
                                                            <form method="POST" action="">
                                                                <input type="hidden" name="action" value="confirm">
                                                                <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                                                                <button type="submit" class="dropdown-action-item item-confirm"><i class="fi fi-rr-check"></i> Confirm</button>
                                                            </form>
                                                            <form method="POST" action="">
                                                                <input type="hidden" name="action" value="cancel">
                                                                <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                                                                <button type="submit" class="dropdown-action-item item-decline"><i class="fi fi-rr-cross"></i> Decline</button>
                                                            </form>
                                                            <div class="dropdown-divider"></div>
                                                        <?php endif; ?>
                                                        <button type="button" class="dropdown-action-item item-reschedule" onclick='openRescheduleModal(<?php echo json_encode($row); ?>, "<?php echo htmlspecialchars($patName); ?>")'>
                                                            <i class="fi fi-rr-clock"></i> Reschedule
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size: 0.82rem;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php 
                                    endwhile; 
                                else: 
                                ?>
                                    <tr>
                                        <td colspan="8" class="no-records">No appointments found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    </div> <!-- End of scheduled-tab -->

                    <!-- Timeline Appointments Tab Content -->
                    <div id="timeline-tab" class="tab-content">
                        <!-- ===== TIMELINE APPOINTMENTS SUBSECTION ===== -->
                        <div class="timeline-section" style="margin-bottom: 40px;">
                        <div class="timeline-header" style="margin-bottom: 20px;">
                            <h3 class="card-title" style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary);"><i class="fi fi-rr-calendar"></i> Timeline Appointments</h3>
                            <span class="card-badge" style="background: var(--primary); color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;"><?php echo $countTimeline; ?> record<?php echo $countTimeline !== 1 ? 's' : ''; ?></span>
                        </div>

                        <?php if ($countTimeline > 0): ?>
                            <div class="timeline-container">
                                <?php 
                                while ($tRow = $resultTimeline->fetch_assoc()):
                                    $tPatName = trim($tRow['first_name'] . ' ' . $tRow['middle_name'] . ' ' . $tRow['last_name']);
                                    $tInitials = strtoupper(($tRow['first_name'][0] ?? 'P') . ($tRow['last_name'][0] ?? 'T'));
                                    $tPhoto = !empty($tRow['profile_photo']) ? '../uploads/patients/' . $tRow['profile_photo'] : '';
                                    $tDateFormatted = date('l, M j, Y', strtotime($tRow['appointment_date']));
                                    $tTimeFormatted = date('h:i A', strtotime($tRow['appointment_time']));

                                    $tGender = !empty($tRow['gender']) ? $tRow['gender'] : '';
                                    $tPhone = !empty($tRow['phone_number']) ? $tRow['phone_number'] : '';
                                    $tAge = !empty($tRow['date_of_birth']) ? date_diff(date_create($tRow['date_of_birth']), date_create('today'))->y : null;
                                ?>
                                    <div class="timeline-item">
                                        <div class="timeline-dot"></div>
                                        <div class="timeline-card">
                                            <div class="timeline-card-header">
                                                <div class="timeline-patient">
                                                    <div class="patient-avatar-sm">
                                                        <?php if ($tPhoto): ?>
                                                            <img src="<?php echo htmlspecialchars($tPhoto); ?>" alt="Avatar">
                                                        <?php else: ?>
                                                            <?php echo $tInitials; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div class="patient-name-sm"><?php echo htmlspecialchars($tPatName); ?></div>
                                                        <div class="patient-meta-sm">
                                                            <?php 
                                                            $tMetaParts = [];
                                                            if (!empty($tGender)) {
                                                                $tMetaParts[] = htmlspecialchars($tGender);
                                                            }
                                                            if ($tAge !== null && $tAge >= 0) {
                                                                $tMetaParts[] = $tAge . ' yrs';
                                                            }
                                                            $tMetaStr = implode(', ', $tMetaParts);
                                                            if (!empty($tMetaStr)) {
                                                                echo '<span>' . $tMetaStr . '</span>';
                                                            }
                                                            if (!empty($tPhone)) {
                                                                if (!empty($tMetaStr)) echo ' <span>•</span> ';
                                                                echo '<span>' . htmlspecialchars($tPhone) . '</span>';
                                                            }
                                                            if (empty($tMetaStr) && empty($tPhone)) {
                                                                echo '<span>Patient</span>';
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div style="text-align: right;">
                                                    <div class="timeline-date"><?php echo $tDateFormatted; ?></div>
                                                    <div class="timeline-time"><?php echo $tTimeFormatted; ?> • <?php echo htmlspecialchars($tRow['appointment_type']); ?></div>
                                                </div>
                                            </div>

                                            <div class="timeline-card-body">
                                                <form method="POST" action="" novalidate>
                                                    <input type="hidden" name="save_medical_records" value="1">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $tRow['appointment_id']; ?>">

                                                    <div class="form-row-grid">
                                                        <div class="timeline-textarea-group">
                                                            <label for="report_<?php echo $tRow['appointment_id']; ?>">Consultation / Medical Report <span style="color: #ef4444;">*</span></label>
                                                            <?php if (!empty($reportError) && $submittedApptId === (int)$tRow['appointment_id']): ?>
                                                                <div style="color: #ef4444; font-size: 0.8rem; font-weight: 600; margin-top: 2px; margin-bottom: 2px;">• <?php echo htmlspecialchars($reportError); ?></div>
                                                            <?php endif; ?>
                                                            <textarea id="report_<?php echo $tRow['appointment_id']; ?>" name="report" class="timeline-textarea" placeholder="Enter patient diagnosis, symptoms, and examination notes..."></textarea>
                                                        </div>
                                                        <div class="timeline-textarea-group">
                                                            <label for="investigation_<?php echo $tRow['appointment_id']; ?>">Investigation <span style="color: #ef4444;">*</span></label>
                                                            <?php if (!empty($investigationError) && $submittedApptId === (int)$tRow['appointment_id']): ?>
                                                                <div style="color: #ef4444; font-size: 0.8rem; font-weight: 600; margin-top: 2px; margin-bottom: 2px;">• <?php echo htmlspecialchars($investigationError); ?></div>
                                                            <?php endif; ?>
                                                            <textarea id="investigation_<?php echo $tRow['appointment_id']; ?>" name="investigation" class="timeline-textarea" placeholder="Enter requested lab tests or diagnostic investigations..."></textarea>
                                                        </div>
                                                        <div class="timeline-textarea-group" style="grid-column: span 2;">
                                                            <label for="follow_up_required_<?php echo $tRow['appointment_id']; ?>">Follow Up Required?</label>
                                                            <select id="follow_up_required_<?php echo $tRow['appointment_id']; ?>" name="follow_up_required" class="timeline-textarea" style="padding: 10px 12px; height: auto;" onchange="toggleFollowUpFields(<?php echo $tRow['appointment_id']; ?>)">
                                                                <option value="no">No</option>
                                                                <option value="yes">Yes</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div id="follow_up_details_<?php echo $tRow['appointment_id']; ?>" class="form-row-grid" style="display: none; margin-top: 16px; border-top: 1px dashed var(--border-glass); padding-top: 16px;">
                                                        <div class="timeline-textarea-group">
                                                            <label for="follow_up_date_<?php echo $tRow['appointment_id']; ?>">Follow-up Date <span style="color: #ef4444;">*</span></label>
                                                            <?php if (!empty($fupDateError) && $submittedApptId === (int)$tRow['appointment_id']): ?>
                                                                <div style="color: #ef4444; font-size: 0.78rem; font-weight: 600; margin-top: 2px; margin-bottom: 2px;">• <?php echo htmlspecialchars($fupDateError); ?></div>
                                                            <?php endif; ?>
                                                            <input type="date" id="follow_up_date_<?php echo $tRow['appointment_id']; ?>" name="follow_up_date" class="timeline-textarea" style="padding: 10px 12px;">
                                                        </div>
                                                        <div class="timeline-textarea-group" style="grid-column: span 2;">
                                                            <label for="follow_up_reason_<?php echo $tRow['appointment_id']; ?>">Follow-up Reason <span style="color: #ef4444;">*</span></label>
                                                            <?php if (!empty($fupReasonError) && $submittedApptId === (int)$tRow['appointment_id']): ?>
                                                                <div style="color: #ef4444; font-size: 0.78rem; font-weight: 600; margin-top: 2px; margin-bottom: 2px;">• <?php echo htmlspecialchars($fupReasonError); ?></div>
                                                            <?php endif; ?>
                                                            <textarea id="follow_up_reason_<?php echo $tRow['appointment_id']; ?>" name="follow_up_reason" class="timeline-textarea" placeholder="Enter follow-up reason and medical instructions..."></textarea>
                                                        </div>
                                                    </div>

                                                    <div style="display: flex; justify-content: flex-end;">
                                                        <button type="submit" class="btn-timeline-save">
                                                            <i class="fi fi-rr-disk"></i> Save Medical Records & Complete
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="appointment-table-card" style="padding: 32px; text-align: center; color: var(--text-secondary);">
                                <p style="font-size: 0.95rem; font-weight: 500;">No confirmed appointments scheduled for today or past days.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    </div> <!-- End of timeline-tab -->
            </div>
        </main>
    </div>

    <!-- ===== RESCHEDULE MODAL ===== -->
    <div class="modal" id="rescheduleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🕒 Change Appointment Time</h3>
                <button class="modal-close" onclick="closeRescheduleModal()">&times;</button>
            </div>
            <form method="POST" action="" novalidate>
                <input type="hidden" name="reschedule_appointment" value="1">
                <input type="hidden" id="edit_appt_id" name="appointment_id">
                
                <div style="background:rgba(0,0,0,0.02); border: 1px solid var(--border-glass); padding: 14px; border-radius: 8px; margin-bottom: 20px; font-size: 0.88rem;">
                    <p style="color:var(--text-secondary);">Patient: <strong id="patient_name_text" style="color:var(--text-primary);"></strong></p>
                    <p style="color:var(--text-secondary); margin-top:6px;">Your Schedule: <strong style="color:var(--accent);"><?php echo htmlspecialchars($doctor['available_time'] ?: 'Not configured'); ?></strong></p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_date">New Date *</label>
                    <input type="date" id="edit_date" name="appointment_date" class="form-input" min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_time">New Time *</label>
                    <input type="time" id="edit_time" name="appointment_time" class="form-input">
                </div>

                <div style="display:flex; gap:12px; margin-top:24px;">
                    <button type="button" class="btn-auth btn-auth-secondary" style="flex:1;" onclick="closeRescheduleModal()">Cancel</button>
                    <button type="submit" class="btn-auth btn-auth-primary" style="flex:1;">Update Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== JS LOGIC ===== -->
    <script>


        // Reschedule modal logic
        function openRescheduleModal(apptData, patientName) {
            document.getElementById('edit_appt_id').value = apptData.appointment_id;
            document.getElementById('patient_name_text').textContent = patientName;
            document.getElementById('edit_date').value = apptData.appointment_date;
            
            let timeStr = apptData.appointment_time;
            if (timeStr.length > 5) {
                timeStr = timeStr.substring(0, 5);
            }
            document.getElementById('edit_time').value = timeStr;
            
            document.getElementById('rescheduleModal').classList.add('show');
        }
        
        function closeRescheduleModal() {
            document.getElementById('rescheduleModal').classList.remove('show');
        }

        function toggleDropdown(trigger) {
            const wrapper = trigger.parentElement;
            const isOpen = wrapper.classList.contains('open');
            
            document.querySelectorAll('.dropdown-action-wrapper.open').forEach(w => {
                w.classList.remove('open');
            });
            
            if (!isOpen) {
                wrapper.classList.add('open');
            }
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-action-wrapper')) {
                document.querySelectorAll('.dropdown-action-wrapper.open').forEach(w => {
                    w.classList.remove('open');
                });
            }
        });

        // Tab switching logic
        function switchTab(tabId) {
            document.querySelectorAll('.appt-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            // Find matching button and content
            const activeBtn = document.querySelector(`.appt-tab-btn[data-tab="${tabId}"]`);
            if (activeBtn) activeBtn.classList.add('active');

            const activeContent = document.getElementById(`${tabId}-tab`);
            if (activeContent) activeContent.classList.add('active');

            localStorage.setItem('activeApptTab', tabId);
        }

        // Toggle Follow-up fields dynamically based on selection
        function toggleFollowUpFields(apptId) {
            const selectEl = document.getElementById('follow_up_required_' + apptId);
            const detailsContainer = document.getElementById('follow_up_details_' + apptId);

            if (selectEl && selectEl.value === 'yes') {
                if (detailsContainer) detailsContainer.style.display = 'grid';
            } else {
                if (detailsContainer) detailsContainer.style.display = 'none';
            }
        }

        // On page load, restore active tab if exists
        document.addEventListener('DOMContentLoaded', () => {
            const activeTab = localStorage.getItem('activeApptTab') || 'scheduled';
            switchTab(activeTab);
        });
    </script>
</body>
</html>
