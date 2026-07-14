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
    if (empty($appointment_time)) $errors[] = 'New appointment time is required.';

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
    <title>Patient Appointments | Dr. <?php echo htmlspecialchars($doctorName); ?> | MediCare+</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-appointments.css?v=<?php echo time(); ?>">
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
                <a href="#" class="sidebar-link" data-tooltip="My Patients">
                    <span class="sidebar-link-icon">🧑‍🤝‍🧑</span>
                    <span class="sidebar-link-text">My Patients</span>
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
                    <div class="sidebar-user-name">Dr. <?php echo htmlspecialchars($doctorName); ?></div>
                    <div class="sidebar-user-role"><?php echo htmlspecialchars($department); ?></div>
                </div>
            </div>
        </aside>

        <!-- ===== MAIN CONTENT ===== -->
        <main class="main-content">
            <header class="top-header">
                <div class="top-header-left">
                    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">☰</button>
                    <div>
                        <h1>Patient Appointments</h1>
                        <p>View patient schedules and reschedule if needed</p>
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

                <div class="appointment-table-card">
                    <table class="admin-table" style="margin-top: 0;">
                        <thead>
                            <tr>
                                <th>Appt ID</th>
                                <th>Patient Name</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Consultation Fee</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "SELECT a.*, p.first_name, p.middle_name, p.last_name 
                                      FROM tbl_appointment a
                                      JOIN tbl_patient p ON a.patient_id = p.patient_id
                                      WHERE a.doctor_id = ?
                                      ORDER BY a.appointment_date ASC, a.appointment_time ASC";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param('i', $doctorId);
                            $stmt->execute();
                            $apptRes = $stmt->get_result();

                            if ($apptRes && $apptRes->num_rows > 0):
                                while ($row = $apptRes->fetch_assoc()):
                                    $patName = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
                            ?>
                                    <tr>
                                        <td>#<?php echo $row['appointment_id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($patName); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></td>
                                        <td><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></td>
                                        <td>
                                            <span class="appt-badge <?php echo strtolower($row['appointment_type']) === 'online' ? 'online' : 'in-person'; ?>">
                                                <?php echo htmlspecialchars($row['appointment_type']); ?>
                                            </span>
                                        </td>
                                        <td><span style="color: var(--accent); font-weight:600;">Rs. <?php echo number_format($row['consultation_fee'], 2); ?></span></td>
                                        <td style="text-align: right;">
                                            <button class="btn-reschedule" onclick='openRescheduleModal(<?php echo json_encode($row); ?>, "<?php echo htmlspecialchars($patName); ?>")'>
                                                Reschedule
                                            </button>
                                        </td>
                                    </tr>
                            <?php
                                endwhile;
                            else:
                            ?>
                                <tr>
                                    <td colspan="7" class="no-records">No appointments assigned to you yet.</td>
                                </tr>
                            <?php endif; $stmt->close(); ?>
                        </tbody>
                    </table>
                </div>
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
            <form method="POST" action="">
                <input type="hidden" name="reschedule_appointment" value="1">
                <input type="hidden" id="edit_appt_id" name="appointment_id">
                
                <div style="background:rgba(255,255,255,0.02); border: 1px solid var(--border-glass); padding: 14px; border-radius: 8px; margin-bottom: 20px; font-size: 0.88rem;">
                    <p style="color:var(--text-secondary);">Patient: <strong id="patient_name_text" style="color:var(--text-primary);"></strong></p>
                    <p style="color:var(--text-secondary); margin-top:6px;">Your Schedule: <strong style="color:var(--accent-light);"><?php echo htmlspecialchars($doctor['available_time'] ?: 'Not configured'); ?></strong></p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_date">New Date *</label>
                    <input type="date" id="edit_date" name="appointment_date" class="form-input" min="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_time">New Time *</label>
                    <input type="time" id="edit_time" name="appointment_time" class="form-input" required>
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
        // Sidebar collapse logic
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
    </script>
</body>
</html>
