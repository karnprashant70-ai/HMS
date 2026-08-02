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

    // Password (optional)
    if (!empty($_POST['password'])) {
        if (empty($_POST['current_password'])) {
            $errors['current_password'] = 'Please enter your current password to reset.';
            $hasError = true;
        } else if (!password_verify($_POST['current_password'], $doctor['password'])) {
            $errors['current_password'] = 'Current password is incorrect.';
            $hasError = true;
        } else if (empty($_POST['confirm_password']) || $_POST['password'] !== $_POST['confirm_password']) {
            $errors['confirm_password'] = 'Passwords do not match.';
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
    } else if (!$hasError) {
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
    <link rel="stylesheet" href="../css/sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-profile.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- Animated Background -->
    <div class="bg-pattern"></div>

    <!-- Shared Sidebar Component -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="dashboard-layout">

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
                                <label class="profile-form-label" for="current_password">Current Password</label>
                                <?php if (isset($errors['current_password'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['current_password']); ?></div><?php endif; ?>
                                <input type="password" id="current_password" name="current_password" class="profile-form-input" placeholder="••••••••">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label" for="password">New Password</label>
                                <?php if (isset($errors['password'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['password']); ?></div><?php endif; ?>
                                <input type="password" id="password" name="password" class="profile-form-input" placeholder="••••••••" autocomplete="new-password">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label" for="confirm_password">Confirm Password</label>
                                <?php if (isset($errors['confirm_password'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['confirm_password']); ?></div><?php endif; ?>
                                <input type="password" id="confirm_password" name="confirm_password" class="profile-form-input" placeholder="••••••••" autocomplete="new-password">
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
