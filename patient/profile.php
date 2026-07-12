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
$message = '';
$messageType = ''; // 'success' or 'error'

// Fetch existing data
$stmt = $conn->prepare('SELECT * FROM tbl_patient WHERE patient_id = ? LIMIT 1');
$stmt->bind_param('i', $patientId);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc() ?: [];
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $updates = [];
    $types = '';
    $values = [];

    $map = [
        'first_name'        => 'first_name',
        'middle_name'       => 'middle_name',
        'last_name'         => 'last_name',
        'date_of_birth'     => 'date_of_birth',
        'gender'            => 'gender',
        'phone_number'      => 'phone_number',
        'email'             => 'email',
        'temporary_address' => 'temporary_address',
        'permanent_address' => 'permanent_address',
        'marital_status'    => 'marital_status',
        'occupation'        => 'occupation'
    ];

    foreach ($map as $input => $col) {
        if (isset($_POST[$input])) {
            $val = trim($_POST[$input]);
            $updates[] = "$col = ?";
            $types .= 's';
            $values[] = $val;
        }
    }

    // Password (optional)
    if (!empty($_POST['password'])) {
        $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $updates[] = "password = ?";
        $types .= 's';
        $values[] = $hashed;
    }

    // Handle profile photo upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        if (in_array($_FILES['profile_photo']['type'], $allowed)) {
            $uploadDir = __DIR__ . '/../uploads/patients/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
            $fileName = time() . '_' . basename($_FILES['profile_photo']['name']);
            $target = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target)) {
                $updates[] = "profile_photo = ?";
                $types .= 's';
                $values[] = $fileName;
            }
        }
    }

    if (!empty($updates)) {
        $types .= 'i';
        $values[] = $patientId;
        $sql = "UPDATE tbl_patient SET " . implode(', ', $updates) . " WHERE patient_id = ?";
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
                // Refresh patient data
                $stmt->close();
                $stmt = $conn->prepare('SELECT * FROM tbl_patient WHERE patient_id = ? LIMIT 1');
                $stmt->bind_param('i', $patientId);
                $stmt->execute();
                $patient = $stmt->get_result()->fetch_assoc() ?: $patient;
                // Update session name
                $_SESSION['patient_name'] = trim(($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
                $patientName = $_SESSION['patient_name'];
            } else {
                $message = 'Failed to update profile. Please try again.';
                $messageType = 'error';
            }
            $stmt->close();
        } else {
            $message = 'Database error: ' . $conn->error;
            $messageType = 'error';
        }
    }
}

// Get initials for avatar fallback
$initials = '';
if (!empty($patient['first_name'])) $initials .= strtoupper($patient['first_name'][0]);
if (!empty($patient['last_name'])) $initials .= strtoupper($patient['last_name'][0]);
if (empty($initials)) $initials = 'PT';

$profilePhoto = !empty($patient['profile_photo']) ? '../uploads/patients/' . $patient['profile_photo'] : '';
$memberSince = !empty($patient['created_at']) ? date('F j, Y', strtotime($patient['created_at'])) : date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Patient Profile | MediCare+ Hospital Management System">
    <title>My Profile | <?php echo htmlspecialchars($patientName); ?> | MediCare+</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-profile.css?v=<?php echo time(); ?>">
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
                <a href="dashboard.php" class="sidebar-link" data-tooltip="Dashboard">
                    <span class="sidebar-link-icon">📊</span>
                    <span class="sidebar-link-text">Dashboard</span>
                </a>
                <a href="#" class="sidebar-link" data-tooltip="Appointments">
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
                <a href="profile.php" class="sidebar-link active" data-tooltip="My Profile">
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
                        <h1>My Profile</h1>
                        <p>Manage your personal information</p>
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
                        <span class="header-profile-name"><?php echo htmlspecialchars(explode(' ', $patientName)[0]); ?></span>
                    </a>
                </div>
            </header>

            <!-- Profile Content -->
            <div class="dashboard-content">

                <!-- Profile Hero -->
                <div class="profile-hero">
                    <div class="profile-hero-avatar">
                        <?php if ($profilePhoto): ?>
                            <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile Photo">
                        <?php else: ?>
                            <?php echo $initials; ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-hero-info">
                        <div class="profile-hero-name"><?php echo htmlspecialchars($patientName); ?></div>
                        <div class="profile-hero-role"><?php echo htmlspecialchars($patient['occupation'] ?? 'Patient'); ?></div>
                        <div class="profile-hero-meta">
                            <div class="profile-meta-item">
                                <span>📧</span> <?php echo htmlspecialchars($patient['email'] ?? '—'); ?>
                            </div>
                            <div class="profile-meta-item">
                                <span>📱</span> <?php echo htmlspecialchars($patient['phone_number'] ?? '—'); ?>
                            </div>
                            <div class="profile-meta-item">
                                <span>📅</span> Member since <?php echo $memberSince; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Edit Form -->
                <form method="POST" action="" enctype="multipart/form-data" id="profileForm">

                    <!-- Section 1: Personal Information -->
                    <div class="profile-section">
                        <div class="profile-section-header">
                            <div class="profile-section-icon teal">👤</div>
                            <div>
                                <div class="profile-section-title">Personal Information</div>
                                <div class="profile-section-subtitle">Your basic identity details</div>
                            </div>
                        </div>
                        <div class="profile-form-grid">
                            <div class="profile-form-group">
                                <label class="profile-form-label" for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" class="profile-form-input" value="<?php echo htmlspecialchars($patient['first_name'] ?? ''); ?>" placeholder="Enter first name">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label" for="middle_name">Middle Name</label>
                                <input type="text" id="middle_name" name="middle_name" class="profile-form-input" value="<?php echo htmlspecialchars($patient['middle_name'] ?? ''); ?>" placeholder="Enter middle name (optional)">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label" for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" class="profile-form-input" value="<?php echo htmlspecialchars($patient['last_name'] ?? ''); ?>" placeholder="Enter last name">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label" for="date_of_birth">Date of Birth</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" class="profile-form-input" value="<?php echo htmlspecialchars($patient['date_of_birth'] ?? ''); ?>">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label" for="gender">Gender</label>
                                <select id="gender" name="gender" class="profile-form-input">
                                    <option value="" <?php echo empty($patient['gender']) ? 'selected' : ''; ?>>Select Gender</option>
                                    <option value="Male" <?php echo (($patient['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo (($patient['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo (($patient['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label" for="occupation">Occupation</label>
                                <input type="text" id="occupation" name="occupation" class="profile-form-input" value="<?php echo htmlspecialchars($patient['occupation'] ?? ''); ?>" placeholder="e.g. Teacher, Engineer, Student">
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Contact Information -->
                    <div class="profile-section">
                        <div class="profile-section-header">
                            <div class="profile-section-icon purple">📞</div>
                            <div>
                                <div class="profile-section-title">Contact Information</div>
                                <div class="profile-section-subtitle">How we can reach you</div>
                            </div>
                        </div>
                        <div class="profile-form-grid">
                            <div class="profile-form-group">
                                <label class="profile-form-label" for="phone_number">Phone Number</label>
                                <input type="tel" id="phone_number" name="phone_number" class="profile-form-input" value="<?php echo htmlspecialchars($patient['phone_number'] ?? ''); ?>" placeholder="98XXXXXXXX">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label" for="email">Email Address</label>
                                <input type="email" id="email" name="email" class="profile-form-input" value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>" placeholder="name@example.com">
                            </div>
                            <div class="profile-form-group full-width">
                                <label class="profile-form-label" for="temporary_address">Temporary Address</label>
                                <textarea id="temporary_address" name="temporary_address" class="profile-form-input" placeholder="Enter your current/temporary address"><?php echo htmlspecialchars($patient['temporary_address'] ?? ''); ?></textarea>
                            </div>
                            <div class="profile-form-group full-width">
                                <label class="profile-form-label" for="permanent_address">Permanent Address</label>
                                <textarea id="permanent_address" name="permanent_address" class="profile-form-input" placeholder="Enter your permanent address"><?php echo htmlspecialchars($patient['permanent_address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Account & Security -->
                    <div class="profile-section">
                        <div class="profile-section-header">
                            <div class="profile-section-icon orange">🔒</div>
                            <div>
                                <div class="profile-section-title">Account & Security</div>
                                <div class="profile-section-subtitle">Manage your account settings and photo</div>
                            </div>
                        </div>
                        <div class="profile-form-grid">
                            <div class="profile-form-group">
                                <label class="profile-form-label" for="marital_status">Marital Status</label>
                                <select id="marital_status" name="marital_status" class="profile-form-input">
                                    <option value="" <?php echo empty($patient['marital_status']) ? 'selected' : ''; ?>>Select Status</option>
                                    <option value="Single" <?php echo (($patient['marital_status'] ?? '') === 'Single') ? 'selected' : ''; ?>>Single</option>
                                    <option value="Married" <?php echo (($patient['marital_status'] ?? '') === 'Married') ? 'selected' : ''; ?>>Married</option>
                                    <option value="Divorced" <?php echo (($patient['marital_status'] ?? '') === 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                    <option value="Widowed" <?php echo (($patient['marital_status'] ?? '') === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                </select>
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label">Patient ID</label>
                                <input type="text" class="profile-form-input" value="PT-<?php echo str_pad($patientId, 5, '0', STR_PAD_LEFT); ?>" disabled>
                            </div>
                            <div class="profile-form-group full-width">
                                <label class="profile-form-label">Profile Photo</label>
                                <div class="photo-upload-area">
                                    <div class="photo-upload-preview" id="photoPreview">
                                        <?php if ($profilePhoto): ?>
                                            <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Photo" id="previewImg">
                                        <?php else: ?>
                                            <?php echo $initials; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="photo-upload-info">
                                        <p>Upload a profile photo. JPG, PNG or WebP (max 2MB)</p>
                                        <label class="photo-upload-btn">
                                            📷 Choose Photo
                                            <input type="file" name="profile_photo" id="profilePhotoInput" accept="image/jpeg,image/png,image/webp,image/gif">
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label" for="password">New Password</label>
                                <input type="password" id="password" name="password" class="profile-form-input" placeholder="Leave blank to keep current">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label" for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="profile-form-input" placeholder="Re-enter new password">
                            </div>
                        </div>
                    </div>

                    <!-- Save Actions -->
                    <div class="profile-save-bar">
                        <a href="dashboard.php" class="btn-profile-cancel">Cancel</a>
                        <button type="submit" name="save_profile" value="1" class="btn-profile-save">💾 Save Changes</button>
                    </div>

                </form>

            </div>
        </main>
    </div>

    <!-- Toast Notification -->
    <?php if (!empty($message)): ?>
    <div class="toast-popup <?php echo $messageType; ?> show" id="profileToast">
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

        // --- Profile Photo Preview ---
        const photoInput = document.getElementById('profilePhotoInput');
        const photoPreview = document.getElementById('photoPreview');

        photoInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    photoPreview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" id="previewImg">';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });

        // --- Password Confirmation Validation ---
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const pw = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;

            if (pw && pw !== confirm) {
                e.preventDefault();
                alert('Passwords do not match. Please check and try again.');
                document.getElementById('confirm_password').focus();
            }
        });
    </script>
</body>
</html>
