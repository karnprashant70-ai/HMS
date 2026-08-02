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
                        <div class="stat-card-value">5</div>
                        <div class="stat-card-label">Prescriptions</div>
                    </div>
                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div class="stat-card-icon orange">📋</div>
                            <span class="stat-card-trend up">Total</span>
                        </div>
                        <div class="stat-card-value">12</div>
                        <div class="stat-card-label">Medical Records</div>
                    </div>
                    <div class="stat-card pink">
                        <div class="stat-card-header">
                            <div class="stat-card-icon pink">🩺</div>
                            <span class="stat-card-trend up">Jul 15</span>
                        </div>
                        <div class="stat-card-value">10</div>
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
                            <a href="#" class="quick-action-card">
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

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activity</h3>
                        <span class="card-badge">Last 7 Days</span>
                    </div>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-dot teal"></div>
                            <div>
                                <div class="activity-text"><strong>Account created</strong> — Welcome to Medi-Care! Complete your profile to get started.</div>
                                <div class="activity-time"><?php echo date('M j, Y', strtotime($patient['created_at'] ?? 'now')); ?></div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-dot purple"></div>
                            <div>
                                <div class="activity-text"><strong>Profile reminder</strong> — Please add your date of birth and address for better care.</div>
                                <div class="activity-time">System notification</div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-dot orange"></div>
                            <div>
                                <div class="activity-text"><strong>Health tip</strong> — Regular health check-ups can help detect potential issues early.</div>
                                <div class="activity-time">Wellness update</div>
                            </div>
                        </div>
                    </div>
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
