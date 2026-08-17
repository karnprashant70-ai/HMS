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

    if (isset($_POST['submit_rating'])) {
        $submittedAction = 'submit_rating';
        $appointment_id = intval($_POST['appointment_id'] ?? 0);
        $rating_stars = intval($_POST['rating_stars'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($appointment_id <= 0) $errors[] = 'Invalid appointment.';
        if ($rating_stars < 1 || $rating_stars > 5) $errors[] = 'Please select a rating between 1 and 5 stars.';

        if (empty($errors)) {
            // Verify appointment is completed and belongs to patient
            $checkAppt = $conn->prepare("SELECT doctor_id, status FROM tbl_appointment WHERE appointment_id = ? AND patient_id = ? LIMIT 1");
            $checkAppt->bind_param('ii', $appointment_id, $patientId);
            $checkAppt->execute();
            $apptRes = $checkAppt->get_result()->fetch_assoc();
            $checkAppt->close();

            if ($apptRes && $apptRes['status'] === 'Completed') {
                $doctorId = intval($apptRes['doctor_id']);

                // Insert or Update Rating
                $rateStmt = $conn->prepare("INSERT INTO tbl_rating (appointment_id, patient_id, doctor_id, rating_stars, comment) 
                                           VALUES (?, ?, ?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE rating_stars = VALUES(rating_stars), comment = VALUES(comment)");
                $rateStmt->bind_param('iiiis', $appointment_id, $patientId, $doctorId, $rating_stars, $comment);
                if ($rateStmt->execute()) {
                    $rateStmt->close();

                    // Recalculate Average Rating for this Doctor
                    $avgStmt = $conn->prepare("SELECT AVG(rating_stars) AS avg_rating FROM tbl_rating WHERE doctor_id = ?");
                    $avgStmt->bind_param('i', $doctorId);
                    $avgStmt->execute();
                    $avgRes = $avgStmt->get_result()->fetch_assoc();
                    $newAvg = $avgRes && $avgRes['avg_rating'] !== null ? floatval($avgRes['avg_rating']) : 0.00;
                    $avgStmt->close();

                    // Update tbl_doctor.rating
                    $updateDocRating = $conn->prepare("UPDATE tbl_doctor SET rating = ? WHERE doctor_id = ?");
                    $updateDocRating->bind_param('di', $newAvg, $doctorId);
                    $updateDocRating->execute();
                    $updateDocRating->close();

                    $_SESSION['appt_success'] = 'Thank you for rating your doctor!';
                    header('Location: appointments.php');
                    exit;
                } else {
                    $errors[] = 'Failed to submit rating. Please try again.';
                    $rateStmt->close();
                }
            } else {
                $errors[] = 'You can only rate completed appointments.';
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
                        <?php include __DIR__ . '/includes/breadcrumb.php'; ?>
                        <h1>Manage Appointments</h1>
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
                        <span><i class="fi fi-rr-calendar-plus"></i></span> Book New Appointment
                    </a>
                </div>

                <?php
                $stmt = $conn->prepare("SELECT a.*, d.first_name, d.middle_name, d.last_name, dept.department_name, r.rating_id, r.rating_stars, r.comment 
                                        FROM tbl_appointment a
                                        JOIN tbl_doctor d ON a.doctor_id = d.doctor_id
                                        JOIN tbl_department dept ON a.department_id = dept.department_id
                                        LEFT JOIN tbl_rating r ON a.appointment_id = r.appointment_id
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
                                <th>S.N.</th>
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
                                                            onclick="openEditModal(this)"><i class="fi fi-rr-calendar-clock"></i> Reschedule</button>
                                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                                        <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                                                        <button type="submit" name="delete_appointment" value="1" class="dropdown-action-item item-cancel"><i class="fi fi-rr-cross-small"></i> Cancel</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php elseif ($status === 'Completed'): 
                                            $hasRated = !empty($row['rating_id']);
                                            $userStars = intval($row['rating_stars'] ?? 0);
                                            $userComment = $row['comment'] ?? '';
                                        ?>
                                            <div style="display: inline-flex; gap: 6px; align-items: center; justify-content: center;">
                                                <a href="view_prescription.php?appointment_id=<?php echo (int)$row['appointment_id']; ?>" class="btn-auth btn-auth-secondary" style="padding: 4px 10px; font-size: 0.78rem; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                                    <i class="fi fi-rr-medicine"></i> View Rx
                                                </a>
                                                <button type="button" class="btn-auth btn-auth-primary" style="padding: 4px 10px; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 4px; background: <?php echo $hasRated ? 'linear-gradient(135deg, #f59e0b, #d97706)' : 'linear-gradient(135deg, var(--primary), var(--primary-light))'; ?>; border: none;"
                                                        data-appt-id="<?php echo (int)$row['appointment_id']; ?>"
                                                        data-doc-name="Dr. <?php echo htmlspecialchars($docName); ?>"
                                                        data-stars="<?php echo $userStars; ?>"
                                                        data-comment="<?php echo htmlspecialchars($userComment); ?>"
                                                        onclick="openRatingModal(this)">
                                                    <i class="fi fi-rr-star"></i> <?php echo $hasRated ? '★ ' . $userStars . '/5' : 'Rate Doctor'; ?>
                                                </button>
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
                                    <td colspan="9" class="no-records">No appointments found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- ===== EDIT APPOINTMENT MODAL ===== -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fi fi-rr-calendar-clock"></i> Reschedule Appointment</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="" novalidate>
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
                    <select id="edit_dept" name="department_id" class="form-input" onchange="filterDoctors('edit')">
                        <option value="" disabled>Choose Department</option>
                        <?php foreach ($depts as $d): ?>
                            <option value="<?php echo $d['department_id']; ?>"><?php echo htmlspecialchars($d['department_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_doc">Doctor *</label>
                    <?php if (isset($errors['doctor_id'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['doctor_id']); ?></div><?php endif; ?>
                    <select id="edit_doc" name="doctor_id" class="form-input" onchange="updateFee('edit')" disabled>
                        <option value="" disabled>Choose Doctor</option>
                    </select>
                </div>

                <div class="form-group" id="edit_schedule_info" style="display:none; background:rgba(0,0,0,0.02); padding:10px 14px; border-radius:6px; border:1px solid var(--border-glass); margin-bottom:15px; font-size:0.82rem; color:var(--text-secondary);">
                    <i class="fi fi-rr-calendar"></i> Doctor Schedule: <span id="edit_schedule_text" style="color:var(--accent); font-weight:600;"></span>
                </div>

                <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label class="form-label" for="edit_date">Date *</label>
                        <?php if (isset($errors['appointment_date'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['appointment_date']); ?></div><?php endif; ?>
                        <input type="date" id="edit_date" name="appointment_date" class="form-input" min="<?php echo date('Y-m-d'); ?>" onchange="loadSlots('edit')">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Selected Time *</label>
                        <?php if (isset($errors['appointment_time'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['appointment_time']); ?></div><?php endif; ?>
                        <input type="text" id="edit_time_display" class="form-input" placeholder="Click a slot below" readonly>
                        <input type="hidden" id="edit_time" name="appointment_time">
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
                    <select id="edit_type" name="appointment_type" class="form-input">
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

    <!-- ===== RATE DOCTOR MODAL ===== -->
    <div class="modal" id="ratingModal">
        <div class="modal-content" style="max-width: 480px;">
            <div class="modal-header">
                <h3><i class="fi fi-rr-star"></i> Rate & Review Doctor</h3>
                <button class="modal-close" onclick="closeRatingModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="submit_rating" value="1">
                <input type="hidden" id="rating_appt_id" name="appointment_id">
                <input type="hidden" id="rating_stars_input" name="rating_stars" value="5">
                
                <div style="text-align: center; margin-bottom: 20px;">
                    <p style="font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 4px;">How was your consultation with</p>
                    <h4 id="rating_doc_name" style="font-size: 1.1rem; color: var(--primary); font-weight: 700;">Dr. Doctor Name</h4>
                </div>

                <div class="star-rating-container" id="starRatingContainer" style="display: flex; justify-content: center; gap: 12px; margin-bottom: 12px;">
                    <span class="star-rating-icon" data-value="1" style="font-size: 2.4rem; cursor: pointer; color: #f59e0b; transition: transform 0.15s ease; user-select: none;">★</span>
                    <span class="star-rating-icon" data-value="2" style="font-size: 2.4rem; cursor: pointer; color: #f59e0b; transition: transform 0.15s ease; user-select: none;">★</span>
                    <span class="star-rating-icon" data-value="3" style="font-size: 2.4rem; cursor: pointer; color: #f59e0b; transition: transform 0.15s ease; user-select: none;">★</span>
                    <span class="star-rating-icon" data-value="4" style="font-size: 2.4rem; cursor: pointer; color: #f59e0b; transition: transform 0.15s ease; user-select: none;">★</span>
                    <span class="star-rating-icon" data-value="5" style="font-size: 2.4rem; cursor: pointer; color: #f59e0b; transition: transform 0.15s ease; user-select: none;">★</span>
                </div>
                <div style="text-align: center; margin-bottom: 20px;">
                    <span id="starRatingText" style="font-size: 0.9rem; font-weight: 700; color: var(--accent);">5.0 - Excellent</span>
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-label" for="rating_comment">Your Review / Comment (Optional)</label>
                    <textarea class="form-control" id="rating_comment" name="comment" rows="4" placeholder="Share your experience with the doctor..." style="resize: vertical; font-family: inherit; width: 100%; box-sizing: border-box;"></textarea>
                </div>

                <div class="modal-footer" style="display: flex; gap: 12px;">
                    <button type="button" class="btn-auth btn-auth-secondary" style="flex: 1;" onclick="closeRatingModal()">Cancel</button>
                    <button type="submit" class="btn-auth btn-auth-primary" style="flex: 1;">Submit Rating</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const starLabels = {
            1: '1.0 - Poor',
            2: '2.0 - Fair',
            3: '3.0 - Good',
            4: '4.0 - Very Good',
            5: '5.0 - Excellent'
        };

        function openRatingModal(btn) {
            const apptId = btn.getAttribute('data-appt-id');
            const docName = btn.getAttribute('data-doc-name');
            const stars = parseInt(btn.getAttribute('data-stars')) || 5;
            const comment = btn.getAttribute('data-comment') || '';

            document.getElementById('rating_appt_id').value = apptId;
            document.getElementById('rating_doc_name').textContent = docName;
            document.getElementById('rating_comment').value = comment;

            setStarRating(stars);

            document.getElementById('ratingModal').classList.add('show');
        }

        function closeRatingModal() {
            document.getElementById('ratingModal').classList.remove('show');
        }

        function setStarRating(rating) {
            document.getElementById('rating_stars_input').value = rating;
            const stars = document.querySelectorAll('#starRatingContainer .star-rating-icon');
            stars.forEach((star, idx) => {
                if (idx < rating) {
                    star.style.color = '#f59e0b';
                } else {
                    star.style.color = '#cbd5e1';
                }
            });
            document.getElementById('starRatingText').textContent = starLabels[rating] || `${rating}.0`;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const stars = document.querySelectorAll('#starRatingContainer .star-rating-icon');
            stars.forEach(star => {
                star.addEventListener('mouseenter', () => {
                    const val = parseInt(star.getAttribute('data-value'));
                    stars.forEach((s, idx) => {
                        s.style.color = (idx < val) ? '#f59e0b' : '#cbd5e1';
                    });
                    document.getElementById('starRatingText').textContent = starLabels[val] || `${val}.0`;
                });
                star.addEventListener('mouseleave', () => {
                    const currentVal = parseInt(document.getElementById('rating_stars_input').value) || 5;
                    setStarRating(currentVal);
                });
                star.addEventListener('click', () => {
                    const val = parseInt(star.getAttribute('data-value'));
                    setStarRating(val);
                });
            });
        });
    </script>
</body>
</html>
