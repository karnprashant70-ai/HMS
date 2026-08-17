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
$errors = [];

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
    } else if (!password_verify($currentPassword, $patient['password'])) {
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
        $stmt = $conn->prepare("UPDATE tbl_patient SET password = ? WHERE patient_id = ?");
        $stmt->bind_param("si", $hashed, $patientId);
        if ($stmt->execute()) {
            $message = 'Password updated successfully!';
            $messageType = 'success';
            $stmt->close();
            // Refresh patient data
            $stmt = $conn->prepare('SELECT * FROM tbl_patient WHERE patient_id = ? LIMIT 1');
            $stmt->bind_param('i', $patientId);
            $stmt->execute();
            $patient = $stmt->get_result()->fetch_assoc() ?: $patient;
            $stmt->close();
            $_SESSION['patient_name'] = trim(($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
            $patientName = $_SESSION['patient_name'];
        } else {
            $message = 'Failed to update password. Please try again.';
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
    <meta name="description" content="Patient Profile | Medi-Care Hospital Management System">
    <title>Reset Password | <?php echo htmlspecialchars($patientName); ?> | Medi-Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-profile.css?v=<?php echo time(); ?>">
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
                        <?php include __DIR__ . '/includes/breadcrumb.php'; ?>
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
                        <span class="header-profile-name"><?php echo htmlspecialchars(explode(' ', $patientName)[0]); ?></span>
                    </a>
                </div>
            </header>

            <!-- Profile Content -->
            <div class="dashboard-content">

                <!-- Profile Edit Form -->
                <form method="POST" action="" enctype="multipart/form-data" novalidate id="profileForm">

                    <!-- Section: Security -->
                    <div class="profile-section" id="security-section">
                        <div class="profile-section-header">
                            <div class="profile-section-icon pink"><i class="fi fi-rr-lock"></i></div>
                            <div>
                                <div class="profile-section-title">Security</div>
                                <div class="profile-section-subtitle">Update your password</div>
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


                    <!-- Save Actions -->
                    <div class="profile-save-bar">
                        <a href="dashboard.php" class="btn-profile-cancel">Cancel</a>
                        <button type="submit" name="save_profile" value="1" class="btn-profile-save"><i class="fi fi-rr-key"></i> Update Password</button>
                    </div>

                </form>

            </div>
        </main>
    </div>

    <!-- Toast Notification -->
    <?php if (!empty($message)): ?>
    <div class="toast-popup <?php echo $messageType; ?> show" id="profileToast">
        <div class="toast-icon"><?php echo $messageType === 'success' ? '<i class="fi fi-rr-check-circle"></i>' : '<i class="fi fi-rr-cross-circle"></i>'; ?></div>
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

        // --- Profile Photo Preview ---
        const photoInput = document.getElementById('profilePhotoInput');
        const photoPreview = document.getElementById('photoPreview');

        if (photoInput && photoPreview) {
            photoInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        photoPreview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" id="previewImg">';
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }

        // --- Password Confirmation Validation ---
        const profileForm = document.getElementById('profileForm');
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                const pw = document.getElementById('password').value;
                const confirm = document.getElementById('confirm_password').value;

                if (pw && pw !== confirm) {
                    e.preventDefault();
                    alert('Passwords do not match. Please check and try again.');
                    document.getElementById('confirm_password').focus();
                }
            });
        }
    </script>
</body>
</html>
