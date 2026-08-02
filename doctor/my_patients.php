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

// Handle Confirm / Cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = intval($_POST['appointment_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($appointment_id > 0 && in_array($action, ['confirm', 'cancel'])) {
        // Verify ownership
        $checkStmt = $conn->prepare('SELECT doctor_id FROM tbl_appointment WHERE appointment_id = ? LIMIT 1');
        $checkStmt->bind_param('i', $appointment_id);
        $checkStmt->execute();
        $checkRes = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($checkRes && intval($checkRes['doctor_id']) === $doctorId) {
            $newStatus = ($action === 'confirm') ? 'Confirmed' : 'Cancelled';
            $updateStmt = $conn->prepare('UPDATE tbl_appointment SET status = ?, reschedule_note = NULL WHERE appointment_id = ?');
            $updateStmt->bind_param('si', $newStatus, $appointment_id);
            if ($updateStmt->execute()) {
                $_SESSION['appt_success'] = 'Appointment ' . strtolower($newStatus) . ' successfully.';
                header('Location: my_patients.php');
                exit;
            } else {
                $errors[] = 'Failed to update appointment status.';
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
    <title>My Patients / Approvals | Dr. <?php echo htmlspecialchars($doctorName); ?> | Medi-Care</title>
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
    <!-- Shared Sidebar Component -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="dashboard-layout">

        <!-- ===== MAIN CONTENT ===== -->
        <main class="main-content">
            <header class="top-header">
                <div class="top-header-left">
                    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">☰</button>
                    <div>
                        <h1>Pending Approvals</h1>
                        <p>Review and confirm new patient appointments</p>
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
                                      WHERE a.doctor_id = ? AND a.status = 'Pending'
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
                                            <div class="dropdown-action-wrapper">
                                                <button type="button" class="dropdown-action-trigger" onclick="toggleDropdown(this)">
                                                    Actions <span class="arrow-icon">▼</span>
                                                </button>
                                                <div class="dropdown-action-menu">
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="action" value="confirm">
                                                        <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                                                        <button type="submit" class="dropdown-action-item item-confirm">✓ Confirm</button>
                                                    </form>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="action" value="cancel">
                                                        <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                                                        <button type="submit" class="dropdown-action-item item-decline">✕ Decline</button>
                                                    </form>
                                                </div>
                                            </div>
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



    <!-- ===== JS LOGIC ===== -->
    <script>


        // Dropdown toggle logic
        function toggleDropdown(trigger) {
            const wrapper = trigger.closest('.dropdown-action-wrapper');
            const wasOpen = wrapper.classList.contains('open');
            closeDropdowns();
            if (!wasOpen) {
                wrapper.classList.add('open');
            }
        }

        function closeDropdowns() {
            document.querySelectorAll('.dropdown-action-wrapper.open').forEach(el => {
                el.classList.remove('open');
            });
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-action-wrapper')) {
                closeDropdowns();
            }
        });

    </script>
</body>
</html>
