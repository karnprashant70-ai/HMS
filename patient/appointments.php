<?php
ob_start();
session_start();
if (empty($_SESSION['patient_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/../db-connection/db_conn.php';

$patientId = intval($_SESSION['patient_id']);
$patientName = $_SESSION['patient_name'] ?? 'Patient';

// Fetch patient data for sidebar
$stmt = $conn->prepare('SELECT * FROM tbl_patient WHERE patient_id = ? LIMIT 1');
$stmt->bind_param('i', $patientId);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc() ?: [];
$stmt->close();

// Get initials for avatar fallback
$initials = '';
if (!empty($patient['first_name'])) $initials .= strtoupper($patient['first_name'][0]);
if (!empty($patient['last_name'])) $initials .= strtoupper($patient['last_name'][0]);
if (empty($initials)) $initials = 'PT';
$profilePhoto = !empty($patient['profile_photo']) ? '../uploads/patients/' . $patient['profile_photo'] : '';

$errors = [];
$successMessage = '';
$submittedAction = '';

// Handle Appointment Book/Update/Delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_appointment'])) {
        $submittedAction = 'update_appointment';
        $appointment_id = intval($_POST['appointment_id'] ?? 0);
        $doctor_id = intval($_POST['doctor_id'] ?? 0);
        $department_id = intval($_POST['department_id'] ?? 0);
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $appointment_type = trim($_POST['appointment_type'] ?? '');

        // Validation
        if ($appointment_id <= 0) $errors[] = 'Invalid appointment transaction.';
        if ($doctor_id <= 0) $errors['doctor_id'] = 'Please select a doctor.';
        if ($department_id <= 0) $errors['department_id'] = 'Please select a department.';
        if (empty($appointment_date)) $errors['appointment_date'] = 'Appointment date is required.';
        if (empty($appointment_time)) {
            $errors['appointment_time'] = 'Appointment time is required.';
        } else {
            $parsedTime = strtotime($appointment_time);
            if ($parsedTime) {
                $appointment_time = date('H:i:s', $parsedTime);
            }
        }
        if (empty($appointment_type)) $errors['appointment_type'] = 'Appointment type is required.';

        if (empty($errors)) {
            // Verify ownership
            $checkOwner = $conn->prepare('SELECT patient_id FROM tbl_appointment WHERE appointment_id = ? LIMIT 1');
            $checkOwner->bind_param('i', $appointment_id);
            $checkOwner->execute();
            $ownerRes = $checkOwner->get_result()->fetch_assoc();
            $checkOwner->close();

            if ($ownerRes && intval($ownerRes['patient_id']) === $patientId) {
                // Get consultation fee
                $feeStmt = $conn->prepare('SELECT consultation_fee FROM tbl_doctor WHERE doctor_id = ? LIMIT 1');
                $feeStmt->bind_param('i', $doctor_id);
                $feeStmt->execute();
                $feeRes = $feeStmt->get_result()->fetch_assoc();
                $consultation_fee = $feeRes ? floatval($feeRes['consultation_fee']) : 0.00;
                $feeStmt->close();

                // Update with reschedule note for doctor and revert status to Pending
                $rescheduleNote = 'Patient requested reschedule on ' . date('M d, Y \a\t h:i A');
                $pendingStatus = 'Pending';
                $updateStmt = $conn->prepare('UPDATE tbl_appointment SET doctor_id = ?, department_id = ?, appointment_date = ?, appointment_time = ?, appointment_type = ?, consultation_fee = ?, reschedule_note = ?, status = ? WHERE appointment_id = ?');
                $updateStmt->bind_param('iisssdssi', $doctor_id, $department_id, $appointment_date, $appointment_time, $appointment_type, $consultation_fee, $rescheduleNote, $pendingStatus, $appointment_id);
                if ($updateStmt->execute()) {
                    $_SESSION['appt_success'] = 'Appointment rescheduled successfully! It is now Pending doctor confirmation.';
                    header('Location: appointments.php');
                    exit;
                } else {
                    $errors[] = 'Failed to update appointment. Please try again.';
                }
                $updateStmt->close();
            } else {
                $errors[] = 'Permission denied.';
            }
        }
    }

    if (isset($_POST['delete_appointment'])) {
        $submittedAction = 'delete_appointment';
        $appointment_id = intval($_POST['appointment_id'] ?? 0);
        if ($appointment_id > 0) {
            // Verify ownership
            $checkOwner = $conn->prepare('SELECT patient_id FROM tbl_appointment WHERE appointment_id = ? LIMIT 1');
            $checkOwner->bind_param('i', $appointment_id);
            $checkOwner->execute();
            $ownerRes = $checkOwner->get_result()->fetch_assoc();
            $checkOwner->close();

            if ($ownerRes && intval($ownerRes['patient_id']) === $patientId) {
                $deleteStmt = $conn->prepare('DELETE FROM tbl_appointment WHERE appointment_id = ?');
                $deleteStmt->bind_param('i', $appointment_id);
                if ($deleteStmt->execute()) {
                    $_SESSION['appt_success'] = 'Appointment canceled successfully.';
                    header('Location: appointments.php');
                    exit;
                } else {
                    $errors[] = 'Failed to cancel appointment.';
                }
                $deleteStmt->close();
            } else {
                $errors[] = 'Permission denied.';
            }
        }
    }
}

if (!empty($_SESSION['appt_success'])) {
    $successMessage = $_SESSION['appt_success'];
    unset($_SESSION['appt_success']);
}

// Fetch all departments
$depts = [];
$deptRes = $conn->query('SELECT * FROM tbl_department ORDER BY department_name ASC');
if ($deptRes) {
    while ($r = $deptRes->fetch_assoc()) {
        $depts[] = $r;
    }
}

// Fetch all available doctors for dynamic filtering
$docs = [];
$docRes = $conn->query('SELECT doctor_id, first_name, middle_name, last_name, department, consultation_fee, available_time FROM tbl_doctor WHERE status = "Available" ORDER BY first_name ASC');
if ($docRes) {
    while ($r = $docRes->fetch_assoc()) {
        $docs[] = $r;
    }
}

// Map department names to IDs for easier referencing on JS side
$deptNameToIdMap = [];
foreach ($depts as $d) {
    $deptNameToIdMap[$d['department_name']] = $d['department_id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments | Medi-Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-appointments.css?v=<?php echo time(); ?>">
</head>
<body>

    <div class="bg-pattern"></div>

    <div class="dashboard-layout">

        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- ===== MAIN CONTENT ===== -->
        <main class="main-content">
            <header class="top-header">
                <div class="top-header-left">
                    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">☰</button>
                    <div>
                        <h1>Manage Appointments</h1>
                        <p>Schedule, view, or reschedule your health visits</p>
                    </div>
                </div>
            </header>

            <div class="dashboard-content">
                <?php if (!empty($errors) && $submittedAction === 'delete_appointment'): ?>
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

                <div class="appt-header-actions">
                    <h2 style="font-size: 1.25rem; font-weight: 700;">Your Scheduled Visits</h2>
                    <a href="book_appointment.php" class="btn-book" style="text-decoration: none;">
                        <span>➕</span> Book New Appointment
                    </a>
                </div>

                <?php
                $stmt = $conn->prepare("SELECT a.*, d.first_name, d.middle_name, d.last_name, dept.department_name 
                                        FROM tbl_appointment a
                                        JOIN tbl_doctor d ON a.doctor_id = d.doctor_id
                                        JOIN tbl_department dept ON a.department_id = dept.department_id
                                        WHERE a.patient_id = ?
                                        ORDER BY a.appointment_date DESC, a.appointment_time DESC");
                $stmt->bind_param('i', $patientId);
                $stmt->execute();
                $result = $stmt->get_result();
                $count = $result ? $result->num_rows : 0;
                $stmt->close();
                ?>

                <div class="appointment-table-card" style="margin-bottom: 24px;">
                    <div class="card-header" style="margin-bottom: 16px;">
                        <h3 class="card-title">All Appointments</h3>
                        <span class="card-badge"><?php echo $count; ?> record<?php echo $count !== 1 ? 's' : ''; ?></span>
                    </div>
                    <table class="admin-table" style="margin-top: 0;">
                        <thead>
                            <tr>
                                <th>Appt ID</th>
                                <th>Doctor</th>
                                <th>Department</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Fee</th>
                                <th>Status</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($count > 0): 
                                $serial_no = 1;
                                while ($row = $result->fetch_assoc()):
                                    $docName = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
                                    $status = $row['status'];
                                    $statusLower = strtolower($status);
                            ?>
                                <tr>
                                    <td><?php echo $serial_no++; ?></td>
                                    <td><strong>Dr. <?php echo htmlspecialchars($docName); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['department_name']); ?></td>
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
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($status === 'Pending' || $status === 'Confirmed'): ?>
                                            <div class="dropdown-action-wrapper">
                                                <button type="button" class="dropdown-action-trigger" onclick="toggleDropdown(this)">
                                                    Actions <span class="arrow-icon">▼</span>
                                                </button>
                                                <div class="dropdown-action-menu">
                                                    <button type="button"
                                                            class="dropdown-action-item item-reschedule"
                                                            data-appt-id="<?php echo (int)$row['appointment_id']; ?>"
                                                            data-dept-id="<?php echo (int)$row['department_id']; ?>"
                                                            data-doctor-id="<?php echo (int)$row['doctor_id']; ?>"
                                                            data-date="<?php echo htmlspecialchars($row['appointment_date']); ?>"
                                                            data-time="<?php echo htmlspecialchars($row['appointment_time']); ?>"
                                                            data-type="<?php echo htmlspecialchars($row['appointment_type']); ?>"
                                                            onclick="openEditModal(this)">📅 Reschedule</button>
                                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                                        <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                                                        <button type="submit" name="delete_appointment" value="1" class="dropdown-action-item item-cancel">✕ Cancel</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php elseif ($status === 'Completed'): ?>
                                            <a href="view_prescription.php?appointment_id=<?php echo (int)$row['appointment_id']; ?>" class="btn-auth btn-auth-secondary" style="padding: 4px 10px; font-size: 0.78rem; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                                💊 View Rx
                                            </a>
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
                                    <td colspan="9" class="no-records">No appointments found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Chronological Medical History Timeline -->
                <?php
                // Fetch complete chronological medical history timeline records for currently logged-in patient
                $timelineSql = "SELECT a.*, 
                                       d.first_name AS doc_fname, d.middle_name AS doc_mname, d.last_name AS doc_lname, d.specialization AS doc_spec,
                                       dept.department_name,
                                       pr.prescription_id, pr.medications, pr.instructions AS rx_instructions,
                                       fup.follow_up_id, fup.follow_up_date, fup.follow_up_reason, fup.status AS fup_status
                                FROM tbl_appointment a
                                JOIN tbl_doctor d ON a.doctor_id = d.doctor_id
                                JOIN tbl_department dept ON a.department_id = dept.department_id
                                LEFT JOIN tbl_prescription pr ON a.appointment_id = pr.appointment_id
                                LEFT JOIN tbl_follow_up fup ON a.appointment_id = fup.appointment_id
                                WHERE a.patient_id = ?
                                ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.appointment_id DESC";
                $tStmt = $conn->prepare($timelineSql);
                $tStmt->bind_param('i', $patientId);
                $tStmt->execute();
                $timelineRes = $tStmt->get_result();
                $timelineRecords = [];
                if ($timelineRes) {
                    while ($tRow = $timelineRes->fetch_assoc()) {
                        $timelineRecords[] = $tRow;
                    }
                }
                $tStmt->close();
                $totalHistoryCount = count($timelineRecords);
                ?>
                <div class="card" id="timeline" style="margin-top: 30px;">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 class="card-title">⏳ Timeline / Medical History</h3>
                            <p style="font-size: 0.82rem; color: var(--text-secondary); margin-top: 2px;">Your complete healthcare journey in chronological order</p>
                        </div>
                        <span class="card-badge"><?php echo $totalHistoryCount; ?> record<?php echo $totalHistoryCount !== 1 ? 's' : ''; ?></span>
                    </div>

                    <?php if ($totalHistoryCount > 0): ?>
                        <div class="patient-timeline">
                            <?php foreach ($timelineRecords as $item): 
                                $docName = trim('Dr. ' . $item['doc_fname'] . ' ' . $item['doc_mname'] . ' ' . $item['doc_lname']);
                                $statusLower = strtolower($item['status']);
                                $dateFormatted = date('d F Y', strtotime($item['appointment_date']));
                                $timeFormatted = date('h:i A', strtotime($item['appointment_time']));
                            ?>
                                <div class="patient-timeline-item">
                                    <div class="patient-timeline-dot <?php echo $statusLower; ?>"></div>
                                    <div class="patient-timeline-card">
                                        <div class="timeline-date-title"><?php echo $dateFormatted; ?></div>
                                        <div class="timeline-doc-dept">
                                            <?php echo htmlspecialchars($docName); ?> • <span style="color: var(--accent);"><?php echo htmlspecialchars($item['department_name']); ?></span>
                                        </div>
                                        <div class="timeline-meta-bar">
                                            <span>🕒 <?php echo $timeFormatted; ?></span>
                                            <span>•</span>
                                            <span class="appt-badge <?php echo strtolower($item['appointment_type']) === 'online' ? 'online' : 'in-person'; ?>">
                                                <?php echo htmlspecialchars($item['appointment_type']); ?>
                                            </span>
                                            <span>•</span>
                                            <span class="appt-badge status-badge <?php echo $statusLower; ?>">
                                                <?php echo htmlspecialchars($item['status']); ?>
                                            </span>
                                        </div>

                                        <!-- Consultation / Medical Report Section -->
                                        <?php if (!empty($item['report'])): ?>
                                            <div class="timeline-section-block">
                                                <div class="timeline-section-title">📄 Consultation / Medical Report</div>
                                                <div class="timeline-section-content">
                                                    <?php echo nl2br(htmlspecialchars($item['report'])); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Investigation Section -->
                                        <?php if (!empty($item['investigation'])): ?>
                                            <div class="timeline-section-block">
                                                <div class="timeline-section-title">🔬 Investigation & Tests</div>
                                                <div class="timeline-section-content">
                                                    <?php echo nl2br(htmlspecialchars($item['investigation'])); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Prescription Section -->
                                        <?php if (!empty($item['medications']) || !empty($item['prescription_id'])): ?>
                                            <div class="timeline-section-block">
                                                <div class="timeline-section-title">💊 Prescription / Medication</div>
                                                <div class="timeline-section-content">
                                                    <?php if (!empty($item['medications'])): ?>
                                                        <div style="font-weight: 600; margin-bottom: 4px;">Medications:</div>
                                                        <div><?php echo nl2br(htmlspecialchars($item['medications'])); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['rx_instructions'])): ?>
                                                        <div style="font-weight: 600; margin-top: 8px; margin-bottom: 4px;">Instructions:</div>
                                                        <div><?php echo nl2br(htmlspecialchars($item['rx_instructions'])); ?></div>
                                                    <?php endif; ?>
                                                    <div style="margin-top: 10px;">
                                                        <a href="view_prescription.php?appointment_id=<?php echo (int)$item['appointment_id']; ?>" class="btn-auth btn-auth-secondary" style="padding: 6px 12px; font-size: 0.78rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                                            📄 View Full Prescription
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Follow Up Section -->
                                        <?php 
                                        $hasFup = !empty($item['follow_up_date']) || !empty($item['follow_up_reason']) || !empty($item['follow_up']);
                                        if ($hasFup): 
                                            $fupDate = !empty($item['follow_up_date']) ? date('d F Y', strtotime($item['follow_up_date'])) : 'Scheduled';
                                            $fupReason = !empty($item['follow_up_reason']) ? $item['follow_up_reason'] : $item['follow_up'];
                                            $fupStatus = !empty($item['fup_status']) ? $item['fup_status'] : 'Pending';
                                            $fupStatusLower = strtolower($fupStatus);
                                        ?>
                                            <div class="timeline-section-block">
                                                <div class="timeline-section-title">🔄 Follow Up</div>
                                                <div class="timeline-followup-card">
                                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                                        <strong style="color: var(--text-primary); font-size: 0.88rem;"><?php echo $fupDate; ?></strong>
                                                        <span class="appt-badge status-badge <?php echo $fupStatusLower; ?>">
                                                            Status: <?php echo htmlspecialchars($fupStatus); ?>
                                                        </span>
                                                    </div>
                                                    <?php if (!empty($fupReason)): ?>
                                                        <div style="font-size: 0.84rem; color: var(--text-secondary);">
                                                            <strong>Reason:</strong> <?php echo htmlspecialchars($fupReason); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="padding: 30px; text-align: center; color: var(--text-secondary); font-size: 0.9rem;">
                            No medical history or appointment timeline records found.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- ===== EDIT APPOINTMENT MODAL ===== -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📅 Reschedule Appointment</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="update_appointment" value="1">
                <input type="hidden" id="edit_appt_id" name="appointment_id">
                
                <?php 
                $generalUpdateErrors = array_filter($errors, function($key) {
                    return is_numeric($key);
                }, ARRAY_FILTER_USE_KEY);
                if (!empty($generalUpdateErrors) && $submittedAction === 'update_appointment'): 
                ?>
                    <div class="hms-error-box">
                        <ul>
                            <?php foreach ($generalUpdateErrors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label" for="edit_dept">Department *</label>
                    <?php if (isset($errors['department_id'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['department_id']); ?></div><?php endif; ?>
                    <select id="edit_dept" name="department_id" class="form-input" onchange="filterDoctors('edit')" required>
                        <option value="" disabled>Choose Department</option>
                        <?php foreach ($depts as $d): ?>
                            <option value="<?php echo $d['department_id']; ?>"><?php echo htmlspecialchars($d['department_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_doc">Doctor *</label>
                    <?php if (isset($errors['doctor_id'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['doctor_id']); ?></div><?php endif; ?>
                    <select id="edit_doc" name="doctor_id" class="form-input" onchange="updateFee('edit')" required disabled>
                        <option value="" disabled>Choose Doctor</option>
                    </select>
                </div>

                <div class="form-group" id="edit_schedule_info" style="display:none; background:rgba(0,0,0,0.02); padding:10px 14px; border-radius:6px; border:1px solid var(--border-glass); margin-bottom:15px; font-size:0.82rem; color:var(--text-secondary);">
                    📅 Doctor Schedule: <span id="edit_schedule_text" style="color:var(--accent); font-weight:600;"></span>
                </div>

                <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label class="form-label" for="edit_date">Date *</label>
                        <?php if (isset($errors['appointment_date'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['appointment_date']); ?></div><?php endif; ?>
                        <input type="date" id="edit_date" name="appointment_date" class="form-input" min="<?php echo date('Y-m-d'); ?>" onchange="loadSlots('edit')" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Selected Time *</label>
                        <?php if (isset($errors['appointment_time'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['appointment_time']); ?></div><?php endif; ?>
                        <input type="text" id="edit_time_display" class="form-input" placeholder="Click a slot below" readonly required>
                        <input type="hidden" id="edit_time" name="appointment_time" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 10px;">
                    <label class="form-label">Available Time Slots</label>
                    <div id="edit_slots_container" style="display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; min-height:42px; align-items:center;">
                        <span style="font-size:0.85rem; color:var(--text-secondary);">Select a doctor and date to view available time slots.</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Appointment Type *</label>
                    <?php if (isset($errors['appointment_type'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['appointment_type']); ?></div><?php endif; ?>
                    <select id="edit_type" name="appointment_type" class="form-input" required>
                        <option value="Physical">Physical (In-Person)</option>
                        <option value="Online">Online Consultation</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Consultation Fee</label>
                    <div class="fee-display" id="edit_fee">Rs. 0.00</div>
                </div>

                <div style="display:flex; gap:12px; margin-top:24px;">
                    <button type="button" class="btn-auth btn-auth-secondary" style="flex:1;" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-auth btn-auth-primary" style="flex:1;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== JS LOGIC ===== -->
    <script>
        // Store doctors list locally as JSON
        const doctorsData = <?php echo json_encode($docs); ?>;
        const deptNameToIdMap = <?php echo json_encode($deptNameToIdMap); ?>;



        // Modal triggers
        function openEditModal(button) {
            const apptData = button.dataset || {};

            document.getElementById('edit_appt_id').value = apptData.apptId || '';
            document.getElementById('edit_dept').value = apptData.deptId || '';

            // Re-filter doctors for edit modal
            filterDoctors('edit');

            const doctorSelect = document.getElementById('edit_doc');
            doctorSelect.value = apptData.doctorId || '';
            document.getElementById('edit_date').value = apptData.date || '';

            // Format time correctly to 24h for time input (e.g., '14:30:00' to '14:30')
            let timeStr = apptData.time || '';
            if (timeStr.length > 5) {
                timeStr = timeStr.substring(0, 5);
            }
            document.getElementById('edit_time').value = timeStr;
            document.getElementById('edit_type').value = apptData.type || 'Physical';

            updateFee('edit');
            document.getElementById('editModal').classList.add('show');
        }
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
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

        // Dropdown interactive filtering
        function filterDoctors(prefix) {
            const deptSelect = document.getElementById(`${prefix}_dept`);
            const docSelect = document.getElementById(`${prefix}_doc`);
            const selectedDeptId = parseInt(deptSelect.value);

            // Enable doctor select
            docSelect.disabled = false;
            docSelect.innerHTML = '<option value="" disabled selected>Choose Doctor</option>';

            // Find matching doctors
            const filteredDocs = doctorsData.filter(d => {
                const mapId = parseInt(deptNameToIdMap[d.department]);
                return mapId === selectedDeptId;
            });

            if (filteredDocs.length === 0) {
                docSelect.innerHTML = '<option value="" disabled>No available doctors in this department</option>';
            } else {
                filteredDocs.forEach(d => {
                    const docName = `Dr. ${d.first_name} ${d.middle_name || ''} ${d.last_name}`;
                    docSelect.innerHTML += `<option value="${d.doctor_id}">${docName}</option>`;
                });
            }

            // Reset fee display
            document.getElementById(`${prefix}_fee`).textContent = 'Rs. 0.00';
            document.getElementById(`${prefix}_schedule_info`).style.display = 'none';
        }

        function updateFee(prefix) {
            const docSelect = document.getElementById(`${prefix}_doc`);
            const selectedDocId = parseInt(docSelect.value);
            const doctorObj = doctorsData.find(d => parseInt(d.doctor_id) === selectedDocId);

            if (doctorObj) {
                const feeFormatted = parseFloat(doctorObj.consultation_fee).toFixed(2);
                document.getElementById(`${prefix}_fee`).textContent = `Rs. ${feeFormatted}`;
                
                // Show doctor schedule if set
                if (doctorObj.available_time) {
                    document.getElementById(`${prefix}_schedule_text`).textContent = doctorObj.available_time;
                    document.getElementById(`${prefix}_schedule_info`).style.display = 'block';
                } else {
                    document.getElementById(`${prefix}_schedule_info`).style.display = 'none';
                }
                loadSlots(prefix);
            } else {
                document.getElementById(`${prefix}_fee`).textContent = 'Rs. 0.00';
                document.getElementById(`${prefix}_schedule_info`).style.display = 'none';
            }
        }

        function loadSlots(prefix = 'edit') {
            const docSelect = document.getElementById(`${prefix}_doc`);
            const dateInput = document.getElementById(`${prefix}_date`);
            const container = document.getElementById(`${prefix}_slots_container`);
            const timeInput = document.getElementById(`${prefix}_time`);
            const timeDisplay = document.getElementById(`${prefix}_time_display`);

            if (!docSelect || !dateInput || !container) return;

            const docId = docSelect.value;
            const dateVal = dateInput.value;

            if (!docId || !dateVal) {
                container.innerHTML = '<span style="font-size:0.85rem; color:var(--text-secondary);">Select a doctor and date to view available time slots.</span>';
                return;
            }

            container.innerHTML = '<span style="font-size:0.85rem; color:var(--accent);">⏳ Loading available slots...</span>';

            fetch(`get_available_slots.php?doctor_id=${docId}&date=${dateVal}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success || !data.slots || data.slots.length === 0) {
                        container.innerHTML = '<span style="font-size:0.85rem; color:#e74c3c;">No available slots for this date.</span>';
                        return;
                    }

                    container.innerHTML = '';
                    data.slots.forEach(s => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.textContent = s.time;
                        btn.style.padding = '6px 14px';
                        btn.style.borderRadius = '20px';
                        btn.style.fontSize = '0.82rem';
                        btn.style.fontWeight = '600';
                        btn.style.cursor = s.available ? 'pointer' : 'not-allowed';
                        btn.style.border = '1px solid';
                        btn.style.transition = 'all 0.2s ease';

                        if (s.available) {
                            btn.style.backgroundColor = 'rgba(0, 184, 148, 0.1)';
                            btn.style.borderColor = 'rgba(0, 184, 148, 0.4)';
                            btn.style.color = 'var(--accent)';

                            btn.onclick = () => {
                                container.querySelectorAll('button').forEach(b => {
                                    if (!b.disabled) {
                                        b.style.backgroundColor = 'rgba(0, 184, 148, 0.1)';
                                        b.style.color = 'var(--accent)';
                                    }
                                });
                                btn.style.backgroundColor = 'var(--accent)';
                                btn.style.color = '#ffffff';

                                timeInput.value = s.time;
                                if (timeDisplay) timeDisplay.value = s.time;
                            };
                        } else {
                            btn.disabled = true;
                            btn.style.backgroundColor = 'rgba(0, 0, 0, 0.05)';
                            btn.style.borderColor = 'rgba(0, 0, 0, 0.1)';
                            btn.style.color = 'var(--text-muted, #aaa)';
                            btn.title = s.reason === 'Booked' ? 'Already Booked' : 'Past Time';
                        }

                        container.appendChild(btn);
                    });
                })
                .catch(err => {
                    container.innerHTML = '<span style="font-size:0.85rem; color:#e74c3c;">Failed to load slots.</span>';
                });
        }
    </script>
</body>
</html>
