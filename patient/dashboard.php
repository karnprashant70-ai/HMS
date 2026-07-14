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
    <meta name="description" content="Patient Dashboard | MediCare+ Hospital Management System">
    <title>Dashboard | <?php echo htmlspecialchars($patientName); ?> | MediCare+</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/auth.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- Animated Background -->
    <div class="bg-pattern"></div>

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="dashboard-layout">

        <!-- ===== SIDEBAR ===== -->
        <aside class="sidebar" id="sidebar">
            <!-- Collapse Toggle -->
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>

            <!-- Brand -->
            <div class="sidebar-header">
                <div class="sidebar-brand-icon">M+</div>
                <div class="sidebar-brand-text">Medi<span>Care+</span></div>
            </div>

            <!-- Navigation -->
            <nav class="sidebar-nav">
                <div class="sidebar-nav-label">Main</div>
                <a href="dashboard.php" class="sidebar-link active" data-tooltip="Dashboard">
                    <span class="sidebar-link-icon">📊</span>
                    <span class="sidebar-link-text">Dashboard</span>
                </a>
                <a href="appointments.php" class="sidebar-link" data-tooltip="Appointments">
                    <span class="sidebar-link-icon">📅</span>
                    <span class="sidebar-link-text">Appointments</span>
                </a>
                <a href="#" class="sidebar-link" data-tooltip="Medical Records">
                    <span class="sidebar-link-icon">📋</span>
                    <span class="sidebar-link-text">Medical Records</span>
                </a>
                <a href="#" class="sidebar-link" data-tooltip="Prescriptions">
                    <span class="sidebar-link-icon">💊</span>
                    <span class="sidebar-link-text">Prescriptions</span>
                </a>

                <div class="sidebar-nav-label">Health</div>
                <a href="#" class="sidebar-link" data-tooltip="Lab Results">
                    <span class="sidebar-link-icon">🔬</span>
                    <span class="sidebar-link-text">Lab Results</span>
                </a>
                <a href="#" class="sidebar-link" data-tooltip="Billing">
                    <span class="sidebar-link-icon">💳</span>
                    <span class="sidebar-link-text">Billing</span>
                </a>

                <div class="sidebar-nav-label">Account</div>
                <a href="profile.php" class="sidebar-link" data-tooltip="My Profile">
                    <span class="sidebar-link-icon">👤</span>
                    <span class="sidebar-link-text">My Profile</span>
                </a>
                <a href="#" class="sidebar-link" data-tooltip="Settings">
                    <span class="sidebar-link-icon">⚙️</span>
                    <span class="sidebar-link-text">Settings</span>
                </a>
                <a href="logout.php" class="sidebar-link" data-tooltip="Logout">
                    <span class="sidebar-link-icon">🚪</span>
                    <span class="sidebar-link-text">Logout</span>
                </a>
            </nav>

            <!-- Footer: Patient Info -->
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
                        <div class="stat-card-value">3</div>
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
                            <a href="appointments.php" class="quick-action-card">
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
                                <div class="activity-text"><strong>Account created</strong> — Welcome to MediCare+! Complete your profile to get started.</div>
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
        // --- Sidebar Collapse Toggle ---
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');

        // Load saved state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }

        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });

        // --- Mobile Menu ---
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
