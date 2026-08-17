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
$errors = [];

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

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword)) {
        $errors['current_password'] = 'Current password is required.';
        $hasError = true;
    } else if (!password_verify($currentPassword, $doctor['password'])) {
        $errors['current_password'] = 'Current password is incorrect.';
        $hasError = true;
    }

    if (empty($newPassword)) {
        $errors['password'] = 'New password is required.';
        $hasError = true;
    } else if (strlen($newPassword) < 6) {
        $errors['password'] = 'New password must be at least 6 characters.';
        $hasError = true;
    }

    if (empty($confirmPassword)) {
        $errors['confirm_password'] = 'Please confirm your new password.';
        $hasError = true;
    } else if ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
        $hasError = true;
    }

    if (!$hasError) {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE tbl_doctor SET password = ? WHERE doctor_id = ?");
        $stmt->bind_param("si", $hashed, $doctorId);
        if ($stmt->execute()) {
            $message = 'Password updated successfully!';
            $messageType = 'success';
            $stmt->close();
            // Refresh doctor data
            $stmt = $conn->prepare('SELECT * FROM tbl_doctor WHERE doctor_id = ? LIMIT 1');
            $stmt->bind_param('i', $doctorId);
            $stmt->execute();
            $doctor = $stmt->get_result()->fetch_assoc() ?: $doctor;
            $stmt->close();
            $_SESSION['doctor_name'] = trim($doctor['first_name'] . ' ' . ($doctor['middle_name'] ?? '') . ' ' . $doctor['last_name']);
            $doctorName = $_SESSION['doctor_name'];
        } else {
            $message = 'Error updating password: ' . $conn->error;
            $messageType = 'error';
        }
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
    <link rel="stylesheet" href="../css/sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-profile.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- Animated Background -->
    <div class="bg-pattern"></div>

    <div class="dashboard-layout">
        <!-- Shared Sidebar Component -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- ===== MAIN CONTENT ===== -->
        <main class="main-content">

            <!-- Top Header Bar -->
            <header class="top-header">
                <div class="top-header-left">
                    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">☰</button>
                    <div>
                        <?php include __DIR__ . '/../includes/breadcrumb.php'; ?>
                        <h1>Reset Password</h1>
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
                <form method="POST" action="" enctype="multipart/form-data" novalidate id="resetPasswordForm">

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
                                <label class="profile-form-label" for="current_password">Current Password</label>
                                <?php if (isset($errors['current_password'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['current_password']); ?></div><?php endif; ?>
                                <div class="password-input-wrapper">
                                    <input type="password" id="current_password" name="current_password" class="profile-form-input" placeholder="••••••••">
                                    <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('current_password', this)" title="Show / Hide Password" aria-label="Show or hide current password">
                                        <i class="fi fi-rr-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label" for="password">New Password</label>
                                <?php if (isset($errors['password'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['password']); ?></div><?php endif; ?>
                                <div class="password-input-wrapper">
                                    <input type="password" id="password" name="password" class="profile-form-input" placeholder="••••••••" autocomplete="new-password">
                                    <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('password', this)" title="Show / Hide Password" aria-label="Show or hide new password">
                                        <i class="fi fi-rr-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label" for="confirm_password">Confirm Password</label>
                                <?php if (isset($errors['confirm_password'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['confirm_password']); ?></div><?php endif; ?>
                                <div class="password-input-wrapper">
                                    <input type="password" id="confirm_password" name="confirm_password" class="profile-form-input" placeholder="••••••••" autocomplete="new-password">
                                    <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('confirm_password', this)" title="Show / Hide Password" aria-label="Show or hide confirm password">
                                        <i class="fi fi-rr-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Bar -->
                    <div class="profile-save-bar">
                        <a href="dashboard.php" class="btn-profile-cancel">Cancel</a>
                        <button type="submit" name="save_profile" value="1" class="btn-profile-save">
                            <i class="fi fi-rr-key"></i> Update Password
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

        // --- Toggle Password Visibility ---
        function togglePasswordVisibility(inputId, btn) {
            const input = (typeof inputId === 'string') ? document.getElementById(inputId) : inputId;
            if (!input) return;
            const icon = btn.querySelector('i');
            if (icon) {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.className = 'fi fi-rr-eye-crossed';
                } else {
                    input.type = 'password';
                    icon.className = 'fi fi-rr-eye';
                }
            } else {
                const svgEye = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
                const svgEyeOff = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;
                if (input.type === 'password') {
                    input.type = 'text';
                    btn.innerHTML = svgEyeOff;
                } else {
                    input.type = 'password';
                    btn.innerHTML = svgEye;
                }
            }
        }

        // --- Photo Preview ---
        const photoInput = document.getElementById('photoInput');
        if (photoInput) {
            photoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        const preview = document.getElementById('photoPreview');
                        if (preview) preview.innerHTML = '<img src="' + ev.target.result + '" alt="Preview" id="previewImg">';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // --- Password Match Check ---
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const pwd = document.querySelector('input[name="password"]')?.value;
                const confirm = document.getElementById('confirm_password')?.value || document.getElementById('confirmPassword')?.value;
                if (pwd && confirm && pwd !== confirm) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                }
            });
        }
    </script>
</body>
</html>
