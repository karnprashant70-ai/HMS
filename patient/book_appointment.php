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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $doctor_id = intval($_POST['doctor_id'] ?? 0);
    $department_id = intval($_POST['department_id'] ?? 0);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $appointment_type = trim($_POST['appointment_type'] ?? '');

    // Validation
    if ($doctor_id <= 0) $errors[] = 'Please select a doctor.';
    if ($department_id <= 0) $errors[] = 'Please select a department.';
    if (empty($appointment_date)) $errors[] = 'Appointment date is required.';
    if (empty($appointment_time)) $errors[] = 'Appointment time is required.';
    if (empty($appointment_type)) $errors[] = 'Appointment type is required.';

    if (empty($errors)) {
        // Get consultation fee for the selected doctor
        $feeStmt = $conn->prepare('SELECT consultation_fee FROM tbl_doctor WHERE doctor_id = ? LIMIT 1');
        $feeStmt->bind_param('i', $doctor_id);
        $feeStmt->execute();
        $feeRes = $feeStmt->get_result()->fetch_assoc();
        $consultation_fee = $feeRes ? floatval($feeRes['consultation_fee']) : 0.00;
        $feeStmt->close();

        // Insert appointment (mark new bookings as Pending)
        try {
            $status = 'Pending';
            $insertStmt = $conn->prepare('INSERT INTO tbl_appointment (patient_id, doctor_id, department_id, appointment_date, appointment_time, appointment_type, status, consultation_fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $insertStmt->bind_param('iiissssd', $patientId, $doctor_id, $department_id, $appointment_date, $appointment_time, $appointment_type, $status, $consultation_fee);
            if ($insertStmt->execute()) {
                $_SESSION['appt_success'] = 'Appointment booked successfully!';
                header('Location: appointments.php');
                exit;
            } else {
                $errors[] = 'Failed to book appointment. Error: ' . $insertStmt->error;
            }
            $insertStmt->close();
        } catch (mysqli_sql_exception $e) {
            $errors[] = 'Database Error: ' . $e->getMessage() . '. Please verify your database table column types match the form data.';
        }
    }
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
    <title>Book Appointment | Medi-Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/auth.css?v=<?php echo time(); ?>">
</head>
<body>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="dashboard-layout">
        <!-- ===== SIDEBAR ===== -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">🧑‍⚕️</div>
                <h2 class="sidebar-title">Medi-Care</h2>
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <div class="sidebar-nav-label">Menu</div>
                <a href="dashboard.php" class="sidebar-link">
                    <span class="sidebar-link-icon">📊</span>
                    <span class="sidebar-link-text">Dashboard</span>
                </a>
                <a href="book_appointment.php" class="sidebar-link active" data-tooltip="Book Appointment">
                    <span class="sidebar-link-icon">➕</span>
                    <span class="sidebar-link-text">Book Appointment</span>
                </a>
                <a href="appointments.php" class="sidebar-link" data-tooltip="My Appointments">
                    <span class="sidebar-link-icon">📅</span>
                    <span class="sidebar-link-text">My Appointments</span>
                </a>
                <a href="#" class="sidebar-link" data-tooltip="Billing">
                    <span class="sidebar-link-icon">💳</span>
                    <span class="sidebar-link-text">Billing</span>
                </a>

                <div class="sidebar-nav-label">Account</div>
                <details class="sidebar-dropdown">
                    <summary class="sidebar-link" data-tooltip="Settings">
                        <span class="sidebar-link-icon">⚙️</span>
                        <span class="sidebar-link-text">Settings</span>
                        <span class="dropdown-arrow">▼</span>
                    </summary>
                    <div class="sidebar-submenu">
                        <a href="profile.php" class="sidebar-link" data-tooltip="My Profile">
                            <span class="sidebar-link-icon">👤</span>
                            <span class="sidebar-link-text">My Profile</span>
                        </a>
                                                <a href="reset_password.php" class="sidebar-link" data-tooltip="Reset Password">
                            <span class="sidebar-link-icon">🔐</span>
                            <span class="sidebar-link-text">Reset Password</span>
                        </a>
                        <a href="logout.php" class="sidebar-link" data-tooltip="Logout" onclick="return confirm('Are you sure you want to logout?');">
                            <span class="sidebar-link-icon">🚪</span>
                            <span class="sidebar-link-text">Logout</span>
                        </a>
                    </div>
                </details>
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
                        <h1>Book New Appointment</h1>
                        <p>Fill out the form below to schedule a visit with a doctor</p>
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

                <div class="card" style="max-width: 700px; margin: 0 auto;">
                    <div style="padding: 30px;">
                        <form method="POST" action="">
                            <input type="hidden" name="book_appointment" value="1">
                            
                            <div class="form-group">
                                <label class="form-label" for="book_dept">Select Department *</label>
                                <select id="book_dept" name="department_id" class="form-input" onchange="filterDoctors('book')" required>
                                    <option value="" disabled selected>Choose Department</option>
                                    <?php foreach ($depts as $d): ?>
                                        <option value="<?php echo $d['department_id']; ?>"><?php echo htmlspecialchars($d['department_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="book_doc">Select Doctor *</label>
                                <select id="book_doc" name="doctor_id" class="form-input" onchange="updateFee('book')" required disabled>
                                    <option value="" disabled selected>Choose Doctor</option>
                                </select>
                            </div>

                            <div class="form-group" id="book_schedule_info" style="display:none; background:rgba(0,0,0,0.02); padding:10px 14px; border-radius:6px; border:1px solid var(--border-glass); margin-bottom:15px; font-size:0.82rem; color:var(--text-secondary);">
                                📅 Doctor Schedule: <span id="book_schedule_text" style="color:var(--accent); font-weight:600;"></span>
                            </div>

                            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:16px;">
                                <div class="form-group">
                                    <label class="form-label" for="book_date">Date *</label>
                                    <input type="date" id="book_date" name="appointment_date" class="form-input" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="book_time">Time *</label>
                                    <input type="time" id="book_time" name="appointment_time" class="form-input" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Appointment Type *</label>
                                <select name="appointment_type" class="form-input" required>
                                    <option value="Physical">Physical (In-Person)</option>
                                    <option value="Online">Online Consultation</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Consultation Fee</label>
                                <div class="fee-display" id="book_fee" style="font-size: 1.2rem; font-weight: 700; color: var(--accent); padding: 12px; background: rgba(0, 184, 148, 0.08); border-radius: var(--radius-sm); border: 1px solid rgba(0, 184, 148, 0.15);">Rs. 0.00</div>
                            </div>

                            <div style="display:flex; gap:12px; margin-top:30px;">
                                <a href="appointments.php" class="btn-auth btn-auth-secondary" style="flex:1; text-align:center; text-decoration:none;">Cancel</a>
                                <button type="submit" class="btn-auth btn-auth-primary" style="flex:1;">Confirm Booking</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
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
