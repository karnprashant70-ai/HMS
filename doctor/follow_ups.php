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

// Handle Follow-up Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_follow_up'])) {
    $follow_up_id = intval($_POST['follow_up_id'] ?? 0);
    if ($follow_up_id > 0) {
        // Verify this follow-up belongs to this doctor
        $checkStmt = $conn->prepare('SELECT doctor_id FROM tbl_follow_up WHERE follow_up_id = ? LIMIT 1');
        $checkStmt->bind_param('i', $follow_up_id);
        $checkStmt->execute();
        $checkRes = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($checkRes && intval($checkRes['doctor_id']) === $doctorId) {
            $updateStmt = $conn->prepare('UPDATE tbl_follow_up SET status = "Completed" WHERE follow_up_id = ?');
            $updateStmt->bind_param('i', $follow_up_id);
            if ($updateStmt->execute()) {
                $_SESSION['fup_success'] = 'Follow-up session marked as completed successfully.';
                header('Location: follow_ups.php');
                exit;
            } else {
                $errors[] = 'Failed to mark follow-up as completed.';
            }
            $updateStmt->close();
        } else {
            $errors[] = 'Access denied or invalid follow-up session.';
        }
    } else {
        $errors[] = 'Invalid follow-up ID.';
    }
}

if (!empty($_SESSION['fup_success'])) {
    $successMessage = $_SESSION['fup_success'];
    unset($_SESSION['fup_success']);
}

// Fetch Pending Follow-ups
$stmtPending = $conn->prepare("SELECT f.*, p.first_name, p.middle_name, p.last_name, a.appointment_date, a.appointment_time, a.appointment_type 
                               FROM tbl_follow_up f
                               JOIN tbl_patient p ON f.patient_id = p.patient_id
                               JOIN tbl_appointment a ON f.appointment_id = a.appointment_id
                               WHERE f.doctor_id = ? AND f.status = 'Pending'
                               ORDER BY f.follow_up_date ASC, a.appointment_time ASC");
$stmtPending->bind_param('i', $doctorId);
$stmtPending->execute();
$pendingResult = $stmtPending->get_result();
$pendingCount = $pendingResult ? $pendingResult->num_rows : 0;
$stmtPending->close();

// Fetch Completed Follow-ups
$stmtCompleted = $conn->prepare("SELECT f.*, p.first_name, p.middle_name, p.last_name, a.appointment_date, a.appointment_time, a.appointment_type 
                                 FROM tbl_follow_up f
                                 JOIN tbl_patient p ON f.patient_id = p.patient_id
                                 JOIN tbl_appointment a ON f.appointment_id = a.appointment_id
                                 WHERE f.doctor_id = ? AND f.status = 'Completed'
                                 ORDER BY f.follow_up_date DESC, a.appointment_time DESC");
$stmtCompleted->bind_param('i', $doctorId);
$stmtCompleted->execute();
$completedResult = $stmtCompleted->get_result();
$completedCount = $completedResult ? $completedResult->num_rows : 0;
$stmtCompleted->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Follow Ups | Dr. <?php echo htmlspecialchars($doctorName); ?> | Medi-Care</title>
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
                        <h1>Patient Follow Ups</h1>
                        <p>Manage pending, upcoming, and completed follow-up sessions</p>
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

                <!-- Tabs Navigation -->
                <div class="appt-tabs">
                    <button type="button" class="appt-tab-btn active" data-tab="pending" onclick="switchTab('pending')">
                        ⏳ Pending Follow Ups (<?php echo $pendingCount; ?>)
                    </button>
                    <button type="button" class="appt-tab-btn" data-tab="completed" onclick="switchTab('completed')">
                        ✅ Completed Follow Ups (<?php echo $completedCount; ?>)
                    </button>
                </div>

                <!-- ===== PENDING FOLLOW UPS TAB ===== -->
                <div id="pending-tab" class="tab-content active">
                    <div class="appointment-table-card">
                        <div class="card-header" style="margin-bottom: 16px;">
                            <h3 class="card-title">Pending & Upcoming Sessions</h3>
                            <span class="card-badge"><?php echo $pendingCount; ?> record<?php echo $pendingCount !== 1 ? 's' : ''; ?></span>
                        </div>
                        <table class="admin-table" style="margin-top: 0;">
                            <thead>
                                <tr>
                                    <th>F/U ID</th>
                                    <th>Patient Name</th>
                                    <th>Original Appt Date</th>
                                    <th>Original Appt Time</th>
                                    <th>Follow-up Date</th>
                                    <th>Reason / Instructions</th>
                                    <th>Status</th>
                                    <th style="text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($pendingCount > 0): 
                                    while ($row = $pendingResult->fetch_assoc()):
                                        $patName = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
                                        $status = $row['status'];
                                        $statusLower = strtolower($status);
                                ?>
                                    <tr>
                                        <td>#<?php echo $row['follow_up_id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($patName); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></td>
                                        <td><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></td>
                                        <td><strong style="color: var(--primary);"><?php echo date('M d, Y', strtotime($row['follow_up_date'])); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['follow_up_reason']); ?></td>
                                        <td>
                                            <span class="appt-badge status-badge <?php echo $statusLower; ?>">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to mark this follow-up session as completed?');">
                                                <input type="hidden" name="complete_follow_up" value="1">
                                                <input type="hidden" name="follow_up_id" value="<?php echo $row['follow_up_id']; ?>">
                                                <button type="submit" class="btn-confirm" style="padding: 6px 14px; display: inline-flex; align-items: center; gap: 4px; border: none; font-family: inherit;">
                                                    ✓ Done
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php 
                                    endwhile; 
                                else: 
                                ?>
                                    <tr>
                                        <td colspan="8" class="no-records">No pending follow-ups found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ===== COMPLETED FOLLOW UPS TAB ===== -->
                <div id="completed-tab" class="tab-content">
                    <div class="appointment-table-card">
                        <div class="card-header" style="margin-bottom: 16px;">
                            <h3 class="card-title">Completed Sessions History</h3>
                            <span class="card-badge"><?php echo $completedCount; ?> record<?php echo $completedCount !== 1 ? 's' : ''; ?></span>
                        </div>
                        <table class="admin-table" style="margin-top: 0;">
                            <thead>
                                <tr>
                                    <th>F/U ID</th>
                                    <th>Patient Name</th>
                                    <th>Original Appt Date</th>
                                    <th>Original Appt Time</th>
                                    <th>Follow-up Date</th>
                                    <th>Reason / Instructions</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($completedCount > 0): 
                                    while ($row = $completedResult->fetch_assoc()):
                                        $patName = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
                                        $status = $row['status'];
                                        $statusLower = strtolower($status);
                                ?>
                                    <tr>
                                        <td>#<?php echo $row['follow_up_id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($patName); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></td>
                                        <td><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['follow_up_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['follow_up_reason']); ?></td>
                                        <td>
                                            <span class="appt-badge status-badge <?php echo $statusLower; ?>">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php 
                                    endwhile; 
                                else: 
                                ?>
                                    <tr>
                                        <td colspan="7" class="no-records">No completed follow-ups found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- ===== JS LOGIC ===== -->
    <script>


        // Tab switching logic
        function switchTab(tabId) {
            document.querySelectorAll('.appt-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            const activeBtn = document.querySelector(`.appt-tab-btn[data-tab="${tabId}"]`);
            if (activeBtn) activeBtn.classList.add('active');

            const activeContent = document.getElementById(`${tabId}-tab`);
            if (activeContent) activeContent.classList.add('active');

            localStorage.setItem('activeFupTab', tabId);
        }

        // On page load, restore active tab if exists
        document.addEventListener('DOMContentLoaded', () => {
            const activeTab = localStorage.getItem('activeFupTab') || 'pending';
            switchTab(activeTab);
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>
