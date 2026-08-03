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

// Fetch patient data
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
$occupation = $patient['occupation'] ?? '';
$memberSince = !empty($patient['created_at']) ? date('M Y', strtotime($patient['created_at'])) : date('M Y');

// Calculate profile completion
$profileFields = ['first_name','last_name','date_of_birth','gender','phone_number','email','temporary_address','permanent_address','profile_photo','marital_status','occupation'];
$filledCount = 0;
foreach ($profileFields as $f) {
    if (!empty($patient[$f])) $filledCount++;
}
$completionPct = round(($filledCount / count($profileFields)) * 100);

// Fetch upcoming appointments count
$apptStmt = $conn->prepare("SELECT COUNT(*) AS appt_count FROM tbl_appointment WHERE patient_id = ? AND status IN ('Pending', 'Confirmed')");
$apptStmt->bind_param('i', $patientId);
$apptStmt->execute();
$apptRes = $apptStmt->get_result()->fetch_assoc();
$upcomingAppointments = $apptRes['appt_count'] ?? 0;
$apptStmt->close();

// Fetch active prescriptions count
$rxCountStmt = $conn->prepare("SELECT COUNT(*) AS rx_count FROM tbl_prescription WHERE patient_id = ?");
$rxCountStmt->bind_param('i', $patientId);
$rxCountStmt->execute();
$rxCountRes = $rxCountStmt->get_result()->fetch_assoc();
$totalPrescriptions = $rxCountRes['rx_count'] ?? 0;
$rxCountStmt->close();

// Fetch total completed medical records count
$recStmt = $conn->prepare("SELECT COUNT(*) AS rec_count FROM tbl_appointment WHERE patient_id = ? AND status = 'Completed'");
$recStmt->bind_param('i', $patientId);
$recStmt->execute();
$recRes = $recStmt->get_result()->fetch_assoc();
$totalMedicalRecords = $recRes['rec_count'] ?? 0;
$recStmt->close();

// Fetch days to next visit
$nextStmt = $conn->prepare("SELECT DATEDIFF(MIN(appointment_date), CURRENT_DATE()) AS days_left, MIN(appointment_date) as next_date FROM tbl_appointment WHERE patient_id = ? AND appointment_date >= CURRENT_DATE() AND status IN ('Pending', 'Confirmed')");
$nextStmt->bind_param('i', $patientId);
$nextStmt->execute();
$nextRes = $nextStmt->get_result()->fetch_assoc();
$daysToNextVisit = isset($nextRes['days_left']) && $nextRes['days_left'] !== null ? intval($nextRes['days_left']) : '—';
$nextVisitTrend = !empty($nextRes['next_date']) ? date('M d', strtotime($nextRes['next_date'])) : 'Upcoming';
$nextStmt->close();

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
    while ($row = $timelineRes->fetch_assoc()) {
        $timelineRecords[] = $row;
    }
}
$tStmt->close();
$totalHistoryCount = count($timelineRecords);

// Check for login success toast
$loginSuccess = '';
if (!empty($_SESSION['login_success'])) {
    $loginSuccess = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Patient Dashboard | Medi-Care Hospital Management System">
    <title>Dashboard | <?php echo htmlspecialchars($patientName); ?> | Medi-Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/auth.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- Animated Background -->
    <div class="bg-pattern"></div>

    <div class="dashboard-layout">

        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- ===== MAIN CONTENT ===== -->
        <main class="main-content">

            <!-- Top Header Bar -->
            <header class="top-header">
                <div class="top-header-left">
                    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">☰</button>
                    <div>
                        <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $patientName)[0]); ?> 👋</h1>
                        <p><?php echo date('l, F j, Y'); ?></p>
                    </div>
                </div>
                <div class="top-header-right">
                    <button class="header-icon-btn" title="Notifications">
                        🔔
                        <span class="notification-dot"></span>
                    </button>
                    <button class="header-icon-btn" title="Messages">
                        ✉️
                    </button>
                    <a href="profile.php" class="header-profile">
                        <div class="header-profile-avatar">
                            <?php if ($profilePhoto): ?>
                                <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Avatar">
                            <?php else: ?>
                                <?php echo $initials; ?>
                            <?php endif; ?>
                        </div>
                        <span class="header-profile-name"><?php echo htmlspecialchars(explode(' ', $patientName)[0]); ?></span>
                    </a>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card teal">
                        <div class="stat-card-header">
                            <div class="stat-card-icon teal">📅</div>
                            <span class="stat-card-trend up">Upcoming</span>
                        </div>
                        <div class="stat-card-value"><?php echo $upcomingAppointments; ?></div>
                        <div class="stat-card-label">Appointments</div>
                    </div>
                    <div class="stat-card purple">
                        <div class="stat-card-header">
                            <div class="stat-card-icon purple">💊</div>
                            <span class="stat-card-trend up">Active</span>
                        </div>
                        <div class="stat-card-value"><?php echo $totalPrescriptions; ?></div>
                        <div class="stat-card-label">Prescriptions</div>
                    </div>
                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div class="stat-card-icon orange">📋</div>
                            <span class="stat-card-trend up">Total</span>
                        </div>
                        <div class="stat-card-value"><?php echo $totalMedicalRecords; ?></div>
                        <div class="stat-card-label">Medical Records</div>
                    </div>
                    <div class="stat-card pink">
                        <div class="stat-card-header">
                            <div class="stat-card-icon pink">🩺</div>
                            <span class="stat-card-trend up"><?php echo htmlspecialchars($nextVisitTrend); ?></span>
                        </div>
                        <div class="stat-card-value"><?php echo $daysToNextVisit; ?></div>
                        <div class="stat-card-label">Days to Next Visit</div>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="content-grid">

                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Quick Actions</h3>
                            <span class="card-badge">Shortcuts</span>
                        </div>
                        <div class="quick-actions-grid">
                            <a href="book_appointment.php" class="quick-action-card">
                                <div class="quick-action-icon teal">📅</div>
                                <div class="quick-action-label">Book Appointment</div>
                                <div class="quick-action-desc">Schedule a visit with a doctor</div>
                            </a>
                            <a href="#timeline" class="quick-action-card">
                                <div class="quick-action-icon purple">📋</div>
                                <div class="quick-action-label">View Records</div>
                                <div class="quick-action-desc">Access your medical history</div>
                            </a>
                            <a href="profile.php" class="quick-action-card">
                                <div class="quick-action-icon orange">👤</div>
                                <div class="quick-action-label">Edit Profile</div>
                                <div class="quick-action-desc">Update your personal info</div>
                            </a>
                        </div>
                    </div>

                    <!-- Profile Completion + Health Tip -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Your Profile</h3>
                            <span class="card-badge">Member since <?php echo $memberSince; ?></span>
                        </div>

                        <!-- Profile Completion -->
                        <div style="margin-bottom: 24px;">
                            <p style="font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 4px;">Profile Completion</p>
                            <div class="completion-bar-bg">
                                <div class="completion-bar-fill" id="completionBar" style="width: 0%;"></div>
                            </div>
                            <div class="completion-text">
                                <span><?php echo $filledCount; ?> of <?php echo count($profileFields); ?> fields completed</span>
                                <strong><?php echo $completionPct; ?>%</strong>
                            </div>
                            <?php if ($completionPct < 100): ?>
                            <a href="profile.php" style="display: inline-block; margin-top: 12px; font-size: 0.82rem; color: var(--accent); text-decoration: none; font-weight: 600;">Complete your profile →</a>
                            <?php endif; ?>
                        </div>

                        <!-- Health Tip -->
                        <div class="health-tip-card">
                            <div class="health-tip-icon">💡</div>
                            <div class="health-tip-content">
                                <h4>Health Tip of the Day</h4>
                                <p>Stay hydrated! Drinking 8 glasses of water daily helps maintain energy levels and supports overall health.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chronological Medical History Timeline -->
                <div class="card" id="timeline" style="margin-top: 24px;">
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
                                $statusLower = strtolower($item['appt_status']);
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
                                                <?php echo htmlspecialchars($item['appt_status']); ?>
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
                                        $hasFup = !empty($item['follow_up_date']) || !empty($item['follow_up_reason']) || !empty($item['appointment_follow_up_text']);
                                        if ($hasFup): 
                                            $fupDate = !empty($item['follow_up_date']) ? date('d F Y', strtotime($item['follow_up_date'])) : 'Scheduled';
                                            $fupReason = !empty($item['follow_up_reason']) ? $item['follow_up_reason'] : $item['appointment_follow_up_text'];
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

    <!-- Login Success Toast -->
    <?php if (!empty($loginSuccess)): ?>
    <div class="toast-popup show" id="loginSuccessToast">
        <div class="toast-icon">✅</div>
        <p><?php echo htmlspecialchars($loginSuccess); ?></p>
    </div>
    <script>
        setTimeout(function() {
            document.getElementById('loginSuccessToast').classList.remove('show');
        }, 3000);
    </script>
    <?php endif; ?>

    <!-- ===== JavaScript ===== -->
    <script>

        // --- Animate completion bar ---
        document.addEventListener('DOMContentLoaded', () => {
            const bar = document.getElementById('completionBar');
            if (bar) {
                setTimeout(() => {
                    bar.style.width = '<?php echo $completionPct; ?>%';
                }, 300);
            }
        });

        // --- Stat cards counter animation ---
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.stat-card-value').forEach(el => {
                const target = parseFloat(el.textContent);
                const isDecimal = el.textContent.includes('.');
                let current = 0;
                const increment = target / 40;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    el.textContent = isDecimal ? current.toFixed(1) : Math.round(current);
                }, 25);
            });
        });
    </script>
</body>
</html>
