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

// Handle Appointment Book/Update/Delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_appointment'])) {
        $appointment_id = intval($_POST['appointment_id'] ?? 0);
        $doctor_id = intval($_POST['doctor_id'] ?? 0);
        $department_id = intval($_POST['department_id'] ?? 0);
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $appointment_type = trim($_POST['appointment_type'] ?? '');

        // Validation
        if ($appointment_id <= 0) $errors[] = 'Invalid appointment transaction.';
        if ($doctor_id <= 0) $errors[] = 'Please select a doctor.';
        if ($department_id <= 0) $errors[] = 'Please select a department.';
        if (empty($appointment_date)) $errors[] = 'Appointment date is required.';
        if (empty($appointment_time)) $errors[] = 'Appointment time is required.';
        if (empty($appointment_type)) $errors[] = 'Appointment type is required.';

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

                // Update
                $updateStmt = $conn->prepare('UPDATE tbl_appointment SET doctor_id = ?, department_id = ?, appointment_date = ?, appointment_time = ?, appointment_type = ?, consultation_fee = ? WHERE appointment_id = ?');
                $updateStmt->bind_param('iisssdi', $doctor_id, $department_id, $appointment_date, $appointment_time, $appointment_type, $consultation_fee, $appointment_id);
                if ($updateStmt->execute()) {
                    $_SESSION['appt_success'] = 'Appointment rescheduled successfully!';
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
    <title>My Appointments | MediCare+</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/auth.css?v=<?php echo time(); ?>">
    <style>
        /* Appointments Specific Styles */
        .appt-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .btn-book {
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            color: var(--bg-dark);
            font-weight: 700;
            padding: 10px 20px;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            border: none;
        }
        .btn-book:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(0, 212, 170, 0.4);
        }
        .appointment-table-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 24px;
            overflow-x: auto;
        }
        .appt-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .appt-badge.online {
            background: rgba(0, 212, 170, 0.15);
            color: var(--accent-light);
            border: 1px solid rgba(0, 212, 170, 0.3);
        }
        .appt-badge.in-person {
            background: rgba(108, 99, 255, 0.15);
            color: var(--primary-light);
            border: 1px solid rgba(108, 99, 255, 0.3);
        }
        .action-btns {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        .btn-action-edit {
            background: rgba(108, 99, 255, 0.12);
            border: 1px solid rgba(108, 99, 255, 0.3);
            color: var(--primary-light);
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: 600;
            transition: var(--transition);
        }
        .btn-action-edit:hover {
            background: var(--primary);
            color: white;
        }
        .btn-action-cancel {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #FCA5A5;
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: 600;
            transition: var(--transition);
        }
        .btn-action-cancel:hover {
            background: #EF4444;
            color: white;
        }
        /* Modal Popup styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(11, 15, 26, 0.85);
            backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            background: rgba(20, 25, 40, 0.95);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 550px;
            padding: 32px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            animation: modalFadeIn 0.3s ease;
        }
        @keyframes modalFadeIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border-glass);
            padding-bottom: 12px;
        }
        .modal-header h3 {
            font-size: 1.25rem;
            font-weight: 700;
        }
        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
            transition: var(--transition);
        }
        .modal-close:hover {
            color: var(--text-primary);
        }
        .fee-display {
            font-size: 1.1rem;
            color: var(--accent);
            font-weight: 700;
            margin-top: 8px;
        }
    </style>
</head>
<body>

    <div class="bg-pattern"></div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="dashboard-layout">
        <!-- ===== SIDEBAR ===== -->
        <aside class="sidebar" id="sidebar">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <div class="sidebar-header">
                <div class="sidebar-brand-icon">M+</div>
                <div class="sidebar-brand-text">Medi<span>Care+</span></div>
            </div>
            <nav class="sidebar-nav">
                <div class="sidebar-nav-label">Main</div>
                <a href="dashboard.php" class="sidebar-link" data-tooltip="Dashboard">
                    <span class="sidebar-link-icon">📊</span>
                    <span class="sidebar-link-text">Dashboard</span>
                </a>
                <a href="appointments.php" class="sidebar-link active" data-tooltip="Appointments">
                    <span class="sidebar-link-icon">📅</span>
                    <span class="sidebar-link-text">Appointments</span>
                </a>
                <a href="#" class="sidebar-link" data-tooltip="Medical Records">
                    <span class="sidebar-link-icon">📋</span>
                    <span class="sidebar-link-text">Medical Records</span>
                </a>
                <a href="book_appointment.php" class="sidebar-link" data-tooltip="Book Appointment">
                    <span class="sidebar-link-icon">➕</span>
                    <span class="sidebar-link-text">Book Appointment</span>
                </a>
                <div class="sidebar-nav-label">Account</div>
                <a href="profile.php" class="sidebar-link" data-tooltip="My Profile">
                    <span class="sidebar-link-icon">👤</span>
                    <span class="sidebar-link-text">My Profile</span>
                </a>
                <a href="logout.php" class="sidebar-link" data-tooltip="Logout">
                    <span class="sidebar-link-icon">🚪</span>
                    <span class="sidebar-link-text">Logout</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="sidebar-avatar">
                    <?php if ($profilePhoto): ?>
                        <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Avatar">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?php echo htmlspecialchars($patientName); ?></div>
                    <div class="sidebar-user-role">Patient</div>
                </div>
            </div>
        </aside>

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
                <?php if (!empty($errors)): ?>
                    <div class="error-banner" style="margin-bottom: 20px;">
                        <?php foreach ($errors as $e): ?>
                            <p>⚠️ <?php echo htmlspecialchars($e); ?></p>
                        <?php endforeach; ?>
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

                <div class="appointment-table-card">
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
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "SELECT a.*, d.first_name, d.middle_name, d.last_name, dept.department_name 
                                      FROM tbl_appointment a
                                      JOIN tbl_doctor d ON a.doctor_id = d.doctor_id
                                      JOIN tbl_department dept ON a.department_id = dept.department_id
                                      WHERE a.patient_id = ?
                                      ORDER BY a.appointment_date ASC, a.appointment_time ASC";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param('i', $patientId);
                            $stmt->execute();
                            $apptRes = $stmt->get_result();

                            if ($apptRes && $apptRes->num_rows > 0):
                                while ($row = $apptRes->fetch_assoc()):
                                    $docName = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
                            ?>
                                    <tr>
                                        <td>#<?php echo $row['appointment_id']; ?></td>
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
                                            <div class="action-btns">
                                                <button class="btn-action-edit" onclick='openEditModal(<?php echo json_encode($row); ?>)'>Reschedule</button>
                                                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                                                    <button type="submit" name="delete_appointment" value="1" class="btn-action-cancel">Cancel</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                            <?php
                                endwhile;
                            else:
                            ?>
                                <tr>
                                    <td colspan="8" class="no-records">No appointments scheduled yet. Book one today!</td>
                                </tr>
                            <?php endif; $stmt->close(); ?>
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
                <h3>📅 Reschedule Appointment</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="update_appointment" value="1">
                <input type="hidden" id="edit_appt_id" name="appointment_id">
                
                <div class="form-group">
                    <label class="form-label" for="edit_dept">Select Department *</label>
                    <select id="edit_dept" name="department_id" class="form-input" onchange="filterDoctors('edit')" required>
                        <option value="" disabled>Choose Department</option>
                        <?php foreach ($depts as $d): ?>
                            <option value="<?php echo $d['department_id']; ?>"><?php echo htmlspecialchars($d['department_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_doc">Select Doctor *</label>
                    <select id="edit_doc" name="doctor_id" class="form-input" onchange="updateFee('edit')" required>
                        <option value="" disabled>Choose Doctor</option>
                    </select>
                </div>

                <div class="form-group" id="edit_schedule_info" style="display:none; background:rgba(255,255,255,0.03); padding:10px 14px; border-radius:6px; border:1px solid var(--border-glass); margin-bottom:15px; font-size:0.82rem; color:var(--text-secondary);">
                    📅 Doctor Schedule: <span id="edit_schedule_text" style="color:var(--accent-light); font-weight:600;"></span>
                </div>

                <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label class="form-label" for="edit_date">Date *</label>
                        <input type="date" id="edit_date" name="appointment_date" class="form-input" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_time">Time *</label>
                        <input type="time" id="edit_time" name="appointment_time" class="form-input" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Appointment Type *</label>
                    <select id="edit_type" name="appointment_type" class="form-input" required>
                        <option value="In-Person">In-Person</option>
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

        // Sidebar logic
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });

        // Mobile Menu
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        mobileMenuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
            sidebarOverlay.classList.toggle('active');
        });
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
        });

        // Modal triggers
        function openEditModal(apptData) {
            document.getElementById('edit_appt_id').value = apptData.appointment_id;
            
            const deptId = apptData.department_id;
            document.getElementById('edit_dept').value = deptId;
            
            // Re-filter doctors for edit modal
            filterDoctors('edit');
            
            document.getElementById('edit_doc').value = apptData.doctor_id;
            document.getElementById('edit_date').value = apptData.appointment_date;
            
            // Format time correctly to 24h for time input (e.g., '14:30:00' to '14:30')
            let timeStr = apptData.appointment_time;
            if (timeStr.length > 5) {
                timeStr = timeStr.substring(0, 5);
            }
            document.getElementById('edit_time').value = timeStr;
            document.getElementById('edit_type').value = apptData.appointment_type;
            
            updateFee('edit');
            document.getElementById('editModal').classList.add('show');
        }
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

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
            } else {
                document.getElementById(`${prefix}_fee`).textContent = 'Rs. 0.00';
                document.getElementById(`${prefix}_schedule_info`).style.display = 'none';
            }
        }
    </script>
</body>
</html>
