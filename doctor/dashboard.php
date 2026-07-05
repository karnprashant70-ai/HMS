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

// Fetch doctor data
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
$specialization = $doctor['specialization'] ?? '';
$status = $doctor['status'] ?? 'Available';
$experience = $doctor['years_experience'] ?? 0;

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
    <meta name="description" content="Doctor Dashboard | MediCare+ Hospital Management System">
    <title>Dashboard | Dr. <?php echo htmlspecialchars($doctorName); ?> | MediCare+</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
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
                <a href="#" class="sidebar-link" data-tooltip="Appointments">
                    <span class="sidebar-link-icon">📅</span>
                    <span class="sidebar-link-text">Appointments</span>
                </a>
                <a href="#" class="sidebar-link" data-tooltip="My Patients">
                    <span class="sidebar-link-icon">🧑‍🤝‍🧑</span>
                    <span class="sidebar-link-text">My Patients</span>
                </a>
                <a href="#" class="sidebar-link" data-tooltip="Schedule">
                    <span class="sidebar-link-icon">🕐</span>
                    <span class="sidebar-link-text">Schedule</span>
                </a>

                <div class="sidebar-nav-label">Management</div>
                <a href="#" class="sidebar-link" data-tooltip="Prescriptions">
                    <span class="sidebar-link-icon">💊</span>
                    <span class="sidebar-link-text">Prescriptions</span>
                </a>
                <a href="#" class="sidebar-link" data-tooltip="Medical Records">
                    <span class="sidebar-link-icon">📋</span>
                    <span class="sidebar-link-text">Medical Records</span>
                </a>
                <a href="#" class="sidebar-link" data-tooltip="Reports">
                    <span class="sidebar-link-icon">📈</span>
                    <span class="sidebar-link-text">Reports</span>
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

            <!-- Footer: Doctor Info -->
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

            <!-- Top Header Bar -->
            <header class="top-header">
                <div class="top-header-left">
                    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">☰</button>
                    <div>
                        <h1>Welcome back, Dr. <?php echo htmlspecialchars(explode(' ', $doctorName)[0]); ?> 👋</h1>
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
                        <span class="header-profile-name">Dr. <?php echo htmlspecialchars(explode(' ', $doctorName)[0]); ?></span>
                    </a>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card purple">
                        <div class="stat-card-header">
                            <div class="stat-card-icon purple">📅</div>
                            <span class="stat-card-trend up">+12%</span>
                        </div>
                        <div class="stat-card-value">24</div>
                        <div class="stat-card-label">Today's Appointments</div>
                    </div>
                    <div class="stat-card teal">
                        <div class="stat-card-header">
                            <div class="stat-card-icon teal">🧑</div>
                            <span class="stat-card-trend up">+5%</span>
                        </div>
                        <div class="stat-card-value">182</div>
                        <div class="stat-card-label">Total Patients</div>
                    </div>
                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div class="stat-card-icon orange">💊</div>
                            <span class="stat-card-trend down">-3%</span>
                        </div>
                        <div class="stat-card-value">18</div>
                        <div class="stat-card-label">Prescriptions Today</div>
                    </div>
                    <div class="stat-card pink">
                        <div class="stat-card-header">
                            <div class="stat-card-icon pink">⭐</div>
                            <span class="stat-card-trend up">+2%</span>
                        </div>
                        <div class="stat-card-value">4.8</div>
                        <div class="stat-card-label">Patient Rating</div>
                    </div>
                </div>

                <!-- Content Grid: Chart + Upcoming Appointments -->
                <div class="content-grid">
                    <!-- Weekly Overview Chart -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Weekly Overview</h3>
                            <span class="card-badge">This Week</span>
                        </div>
                        <div class="chart-bars">
                            <div class="chart-bar-wrapper">
                                <div class="chart-bar purple" style="height: 80px;"></div>
                                <span class="chart-day">Mon</span>
                            </div>
                            <div class="chart-bar-wrapper">
                                <div class="chart-bar teal" style="height: 120px;"></div>
                                <span class="chart-day">Tue</span>
                            </div>
                            <div class="chart-bar-wrapper">
                                <div class="chart-bar purple" style="height: 60px;"></div>
                                <span class="chart-day">Wed</span>
                            </div>
                            <div class="chart-bar-wrapper">
                                <div class="chart-bar teal" style="height: 140px;"></div>
                                <span class="chart-day">Thu</span>
                            </div>
                            <div class="chart-bar-wrapper">
                                <div class="chart-bar purple" style="height: 100px;"></div>
                                <span class="chart-day">Fri</span>
                            </div>
                            <div class="chart-bar-wrapper">
                                <div class="chart-bar teal" style="height: 50px;"></div>
                                <span class="chart-day">Sat</span>
                            </div>
                            <div class="chart-bar-wrapper">
                                <div class="chart-bar purple" style="height: 30px;"></div>
                                <span class="chart-day">Sun</span>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Appointments -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Upcoming Appointments</h3>
                            <span class="card-badge">Today</span>
                        </div>
                        <div class="appointment-list">
                            <div class="appointment-item">
                                <div class="appointment-avatar a1">RK</div>
                                <div class="appointment-info">
                                    <div class="appointment-name">Ramesh Kumar</div>
                                    <div class="appointment-detail">General Checkup</div>
                                </div>
                                <div class="appointment-time">9:00 AM</div>
                                <span class="appointment-status confirmed">Confirmed</span>
                            </div>
                            <div class="appointment-item">
                                <div class="appointment-avatar a2">SP</div>
                                <div class="appointment-info">
                                    <div class="appointment-name">Sita Poudel</div>
                                    <div class="appointment-detail">Follow-up Visit</div>
                                </div>
                                <div class="appointment-time">10:30 AM</div>
                                <span class="appointment-status pending">Pending</span>
                            </div>
                            <div class="appointment-item">
                                <div class="appointment-avatar a3">AT</div>
                                <div class="appointment-info">
                                    <div class="appointment-name">Ankit Thapa</div>
                                    <div class="appointment-detail">Lab Results Review</div>
                                </div>
                                <div class="appointment-time">11:45 AM</div>
                                <span class="appointment-status confirmed">Confirmed</span>
                            </div>
                            <div class="appointment-item">
                                <div class="appointment-avatar a4">MG</div>
                                <div class="appointment-info">
                                    <div class="appointment-name">Maya Gurung</div>
                                    <div class="appointment-detail">Consultation</div>
                                </div>
                                <div class="appointment-time">2:00 PM</div>
                                <span class="appointment-status pending">Pending</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activity</h3>
                        <span class="card-badge">Last 24h</span>
                    </div>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-dot teal"></div>
                            <div>
                                <div class="activity-text"><strong>Prescription issued</strong> for patient Ramesh Kumar — Amoxicillin 500mg</div>
                                <div class="activity-time">2 hours ago</div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-dot purple"></div>
                            <div>
                                <div class="activity-text"><strong>Appointment completed</strong> with Sita Poudel — Follow-up consultation</div>
                                <div class="activity-time">4 hours ago</div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-dot orange"></div>
                            <div>
                                <div class="activity-text"><strong>Lab report reviewed</strong> for Ankit Thapa — Blood test results normal</div>
                                <div class="activity-time">6 hours ago</div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-dot pink"></div>
                            <div>
                                <div class="activity-text"><strong>New patient registered</strong> — Maya Gurung added to your patient list</div>
                                <div class="activity-time">Yesterday</div>
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

        // --- Animate chart bars on load ---
        document.addEventListener('DOMContentLoaded', () => {
            const bars = document.querySelectorAll('.chart-bar');
            bars.forEach((bar, i) => {
                const targetHeight = bar.style.height;
                bar.style.height = '0px';
                setTimeout(() => {
                    bar.style.height = targetHeight;
                }, 100 + i * 80);
            });
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
