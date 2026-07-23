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
$message = '';
$messageType = ''; // 'success' or 'error'

// Fetch existing data
$stmt = $conn->prepare('SELECT * FROM tbl_doctor WHERE doctor_id = ? LIMIT 1');
$stmt->bind_param('i', $doctorId);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc() ?: [];
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $updates = [];
    $types = '';
    $values = [];

    $map = [];

    foreach ($map as $input => $col) {
        if (isset($_POST[$input])) {
            $val = trim($_POST[$input]);
            
            // Only update if a value is provided, otherwise keep existing data
            if ($val !== '') {
                $updates[] = "$col = ?";
                $types .= 's';
                $values[] = $val;
            }
        }
    }

    $hasError = false;

    // Password (optional)
    if (!empty($_POST['password'])) {
        if (empty($_POST['current_password'])) {
            $message = 'Please enter your current password to reset.';
            $messageType = 'error';
            $hasError = true;
        } else if (!password_verify($_POST['current_password'], $doctor['password'])) {
            $message = 'Current password is incorrect.';
            $messageType = 'error';
            $hasError = true;
        } else {
            $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $updates[] = "password = ?";
            $types .= 's';
            $values[] = $hashed;
        }
    }

    if (!empty($updates) && !$hasError) {
        $types .= 'i';
        $values[] = $doctorId;
        $sql = "UPDATE tbl_doctor SET " . implode(', ', $updates) . " WHERE doctor_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $bind_names = [$types];
            for ($i = 0; $i < count($values); $i++) {
                $bind_names[] = &$values[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_names);
            if ($stmt->execute()) {
                $message = 'Profile updated successfully!';
                $messageType = 'success';
                // Refresh doctor data
                $stmt->close();
                $stmt = $conn->prepare('SELECT * FROM tbl_doctor WHERE doctor_id = ? LIMIT 1');
                $stmt->bind_param('i', $doctorId);
                $stmt->execute();
                $doctor = $stmt->get_result()->fetch_assoc() ?: $doctor;
                $stmt->close();
                // Update session name
                $_SESSION['doctor_name'] = trim($doctor['first_name'] . ' ' . ($doctor['middle_name'] ?? '') . ' ' . $doctor['last_name']);
                $doctorName = $_SESSION['doctor_name'];
            } else {
                $message = 'Error updating profile: ' . $stmt->error;
                $messageType = 'error';
            }
        } else {
            $message = 'Database error: ' . $conn->error;
            $messageType = 'error';
        }
    } else {
        $message = 'No changes submitted.';
        $messageType = 'error';
    }
}

// Get initials for avatar fallback
$initials = '';
if (!empty($doctor['first_name'])) $initials .= strtoupper($doctor['first_name'][0]);
if (!empty($doctor['last_name'])) $initials .= strtoupper($doctor['last_name'][0]);
if (empty($initials)) $initials = 'DR';

$profilePhoto = !empty($doctor['profile_photo']) ? '../uploads/doctors/' . $doctor['profile_photo'] : '';
$department = $doctor['department'] ?? 'General';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Doctor Profile | Medi-Care Hospital Management System">
    <title>Reset Password | Dr. <?php echo htmlspecialchars($doctorName); ?> | Medi-Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-profile.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- Animated Background -->
    <div class="bg-pattern"></div>

    <!-- Mobile Sidebar Overlay -->
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
                <div class="sidebar-brand-text">Medi-<span>Care</span></div>
            </div>

            <nav class="sidebar-nav">
                <div class="sidebar-nav-label">Main</div>
                <a href="dashboard.php" class="sidebar-link" data-tooltip="Dashboard">
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
                <details class="sidebar-dropdown" open>
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
                                                <a href="reset_password.php" class="sidebar-link active" data-tooltip="Reset Password">
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
                        <h1>Reset Password</h1>
                        <p>Update your account password</p>
                    </div>
                </div>
                <div class="top-header-right">
                    <button class="header-icon-btn" title="Notifications">
                        🔔
                        <span class="notification-dot"></span>
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

            <!-- Profile Content -->
            <div class="dashboard-content">

                <!-- Profile Edit Form -->
                <form method="POST" action="" enctype="multipart/form-data">

                    <!-- Section 4: Security -->
                    <div class="profile-section" id="security-section">
                        <div class="profile-section-header">
                            <div class="profile-section-icon pink">🔒</div>
                            <div>
                                <div class="profile-section-title">Security</div>
                                <div class="profile-section-subtitle">Update your password (leave blank to keep current)</div>
                            </div>
                        </div>
                        <div class="profile-form-grid">
                            <div class="profile-form-group full-width">
                                <label class="profile-form-label">Current Password</label>
                                <input class="profile-form-input" type="password" name="current_password" placeholder="••••••••">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label">New Password</label>
                                <input class="profile-form-input" type="password" name="password" placeholder="••••••••" autocomplete="new-password">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label">Confirm Password</label>
                                <input class="profile-form-input" type="password" id="confirmPassword" placeholder="••••••••" autocomplete="new-password">
                            </div>
                        </div>
                    </div>

                    <!-- Save Bar -->
                    <div class="profile-save-bar">
                        <a href="dashboard.php" class="btn-profile-cancel">Cancel</a>
                        <button type="submit" name="save_profile" value="1" class="btn-profile-save">
                            💾 Update Password
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Toast Popup -->
    <?php if (!empty($message)): ?>
    <div class="toast-popup show" id="profileToast" style="<?php echo $messageType === 'error' ? 'background: linear-gradient(135deg, #FF6B6B, #FF8FA3);' : ''; ?>">
        <div class="toast-icon"><?php echo $messageType === 'success' ? '✅' : '❌'; ?></div>
        <p><?php echo htmlspecialchars($message); ?></p>
    </div>
    <script>
        setTimeout(function() {
            document.getElementById('profileToast').classList.remove('show');
        }, 3000);
    </script>
    <?php endif; ?>

    <!-- ===== JavaScript ===== -->
    <script>
        // --- Sidebar Collapse Toggle ---
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');

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

        // --- Photo Preview ---
        document.getElementById('photoInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    const preview = document.getElementById('photoPreview');
                    preview.innerHTML = '<img src="' + ev.target.result + '" alt="Preview" id="previewImg">';
                };
                reader.readAsDataURL(file);
            }
        });

        // --- Password Match Check ---
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const pwd = document.querySelector('input[name="password"]').value;
            const confirm = document.getElementById('confirmPassword').value;
            if (pwd && confirm && pwd !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>
