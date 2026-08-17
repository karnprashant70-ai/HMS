<?php
session_start();
error_log("admin/profile.php access: REQUEST_METHOD=" . $_SERVER['REQUEST_METHOD'] . ", SESSION_ADMIN_ID=" . ($_SESSION['admin_id'] ?? 'UNSET') . ", POST_ACTION=" . ($_POST['action'] ?? 'NONE'));
require_once __DIR__ . '/../db-connection/db_conn.php';

// Auth guard
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch full admin data
$stmt = $conn->prepare("SELECT * FROM tbl_admin WHERE admin_id = ?");
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if (!$admin) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Handle profile update
$errors = [];
$success = '';
$submittedAction = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $submittedAction = $action;

    if ($action === 'update_profile') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';

        // Validation
        if (empty($name)) {
            $errors['name'] = 'Name is required.';
        } elseif (strlen($name) < 2) {
            $errors['name'] = 'Name must be at least 2 characters.';
        } elseif (strlen($name) > 100) {
            $errors['name'] = 'Name must not exceed 100 characters.';
        }

        if (empty($email)) {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } else {
            // Check if email is taken by another admin
            $checkEmail = $conn->prepare("SELECT admin_id FROM tbl_admin WHERE email = ? AND admin_id != ?");
            $checkEmail->bind_param("si", $email, $admin['admin_id']);
            $checkEmail->execute();
            if ($checkEmail->get_result()->num_rows > 0) {
                $errors['email'] = 'This email is already used by another admin.';
            }
            $checkEmail->close();
        }

        if (empty($errors)) {
            $updateStmt = $conn->prepare("UPDATE tbl_admin SET name = ?, email = ? WHERE admin_id = ?");
            $updateStmt->bind_param("ssi", $name, $email, $admin['admin_id']);
            $updateStmt->execute();
            $updateStmt->close();

            // Update session
            $_SESSION['admin_name'] = $name;
            $_SESSION['admin_email'] = $email;

            $success = 'Profile updated successfully!';

            // Refresh admin data
            $stmt = $conn->prepare("SELECT * FROM tbl_admin WHERE admin_id = ?");
            $stmt->bind_param("i", $admin['admin_id']);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }

    if ($action === 'change_password') {
        $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        if (empty($currentPassword)) {
            $errors['current_password'] = 'Current password is required.';
        }

        if (empty($newPassword)) {
            $errors['new_password'] = 'New password is required.';
        } elseif (strlen($newPassword) < 6) {
            $errors['new_password'] = 'New password must be at least 6 characters.';
        }

        if (empty($confirmPassword)) {
            $errors['confirm_password'] = 'Please confirm your new password.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'New passwords do not match.';
        }

        if (empty($errors)) {
            if (!password_verify($currentPassword, $admin['password'])) {
                $errors['current_password'] = 'Current password is incorrect.';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updatePwd = $conn->prepare("UPDATE tbl_admin SET password = ? WHERE admin_id = ?");
                $updatePwd->bind_param("si", $hashedPassword, $admin['admin_id']);
                $updatePwd->execute();
                $updatePwd->close();

                $success = 'Password changed successfully!';
            }
        }
    }

    if ($action === 'add_department') {
        $deptName = isset($_POST['department_name']) ? trim($_POST['department_name']) : '';

        if (empty($deptName)) {
            $errors['department_name'] = 'Department name is required.';
        } elseif (strlen($deptName) < 2) {
            $errors['department_name'] = 'Department name must be at least 2 characters.';
        } elseif (strlen($deptName) > 100) {
            $errors['department_name'] = 'Department name must not exceed 100 characters.';
        } else {
            // Check if department name already exists
            $checkDept = $conn->prepare("SELECT department_id FROM tbl_department WHERE department_name = ?");
            $checkDept->bind_param("s", $deptName);
            $checkDept->execute();
            if ($checkDept->get_result()->num_rows > 0) {
                $errors['department_name'] = 'This department already exists.';
            }
            $checkDept->close();
        }

        if (empty($errors)) {
            $insertDept = $conn->prepare("INSERT INTO tbl_department (department_name) VALUES (?)");
            $insertDept->bind_param("s", $deptName);
            if ($insertDept->execute()) {
                $success = 'Department added successfully!';
            } else {
                $errors[] = 'Failed to add department: ' . $conn->error;
            }
            $insertDept->close();
        }
    }

    if ($action === 'delete_department') {
        $deptId = isset($_POST['department_id']) ? intval($_POST['department_id']) : 0;
        $deptName = '';

        if ($deptId <= 0) {
            $errors[] = 'Invalid department ID.';
        } else {
            // Get department name
            $getDept = $conn->prepare("SELECT department_name FROM tbl_department WHERE department_id = ?");
            $getDept->bind_param("i", $deptId);
            $getDept->execute();
            $deptResult = $getDept->get_result();
            if ($deptResult->num_rows === 0) {
                $errors[] = 'Department not found.';
            } else {
                $deptData = $deptResult->fetch_assoc();
                $deptName = $deptData['department_name'];
            }
            $getDept->close();
        }

        if (empty($errors)) {
            try {
                $conn->begin_transaction();

                // Reassign any doctors registered under this department to 'Unassigned'
                $updateDocs = $conn->prepare("UPDATE tbl_doctor SET department = 'Unassigned' WHERE department = ?");
                $updateDocs->bind_param("s", $deptName);
                $updateDocs->execute();
                $updateDocs->close();

                // Delete associated appointments for this department ID
                $delAppts = $conn->prepare("DELETE FROM tbl_appointment WHERE department_id = ?");
                $delAppts->bind_param("i", $deptId);
                $delAppts->execute();
                $delAppts->close();

                // Delete department from tbl_department
                $deleteDept = $conn->prepare("DELETE FROM tbl_department WHERE department_id = ?");
                $deleteDept->bind_param("i", $deptId);
                $deleteDept->execute();
                $deleteDept->close();

                $conn->commit();
                $success = "Department '$deptName' deleted successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Failed to delete department: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'update_department') {
        $deptId = isset($_POST['department_id']) ? intval($_POST['department_id']) : 0;
        $deptName = isset($_POST['department_name']) ? trim($_POST['department_name']) : '';

        if ($deptId <= 0) {
            $errors[] = 'Invalid department ID.';
        } elseif (empty($deptName)) {
            $errors[] = 'Department name is required.';
        } elseif (strlen($deptName) < 2) {
            $errors[] = 'Department name must be at least 2 characters.';
        } elseif (strlen($deptName) > 100) {
            $errors[] = 'Department name must not exceed 100 characters.';
        } else {
            // Check if another department has the same name
            $checkDept = $conn->prepare("SELECT department_id FROM tbl_department WHERE department_name = ? AND department_id != ?");
            $checkDept->bind_param("si", $deptName, $deptId);
            $checkDept->execute();
            if ($checkDept->get_result()->num_rows > 0) {
                $errors[] = 'Another department with this name already exists.';
            }
            $checkDept->close();
        }

        if (empty($errors)) {
            try {
                $conn->begin_transaction();

                // Get old department name to sync doctor records
                $getOld = $conn->prepare("SELECT department_name FROM tbl_department WHERE department_id = ?");
                $getOld->bind_param("i", $deptId);
                $getOld->execute();
                $oldRes = $getOld->get_result()->fetch_assoc();
                $oldDeptName = $oldRes ? $oldRes['department_name'] : '';
                $getOld->close();

                // Update tbl_department
                $updateDept = $conn->prepare("UPDATE tbl_department SET department_name = ? WHERE department_id = ?");
                $updateDept->bind_param("si", $deptName, $deptId);
                $updateDept->execute();
                $updateDept->close();

                // Sync tbl_doctor department name if changed
                if (!empty($oldDeptName) && $oldDeptName !== $deptName) {
                    $updateDocs = $conn->prepare("UPDATE tbl_doctor SET department = ? WHERE department = ?");
                    $updateDocs->bind_param("ss", $deptName, $oldDeptName);
                    $updateDocs->execute();
                    $updateDocs->close();
                }

                $conn->commit();
                $success = 'Department updated successfully!';
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Failed to update department: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'update_consultation_fee') {
        $doctorId = isset($_POST['doctor_id']) ? intval($_POST['doctor_id']) : 0;
        $fee = isset($_POST['consultation_fee']) ? floatval($_POST['consultation_fee']) : 0.00;

        if ($doctorId <= 0) {
            $errors[] = 'Invalid doctor ID.';
        } elseif ($fee < 0) {
            $errors[] = 'Consultation fee cannot be negative.';
        }

        if (empty($errors)) {
            $updateFee = $conn->prepare("UPDATE tbl_doctor SET consultation_fee = ? WHERE doctor_id = ?");
            $updateFee->bind_param("di", $fee, $doctorId);
            if ($updateFee->execute()) {
                $success = 'Doctor consultation fee updated successfully!';
            } else {
                $errors[] = 'Failed to update fee: ' . $conn->error;
            }
            $updateFee->close();
        }
    }

    if ($action === 'edit_doctor_admin') {
        $doctorId = isset($_POST['doctor_id']) ? intval($_POST['doctor_id']) : 0;
        $firstName = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
        $middleName = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
        $lastName = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
        $department = isset($_POST['department']) ? trim($_POST['department']) : '';
        $specialization = isset($_POST['specialization']) ? trim($_POST['specialization']) : '';
        $qualification = isset($_POST['qualification']) ? trim($_POST['qualification']) : '';
        $licenceNumber = isset($_POST['licence_number']) ? trim($_POST['licence_number']) : '';
        $experience = isset($_POST['years_experience']) ? trim($_POST['years_experience']) : '0-3';
        $consultationFee = isset($_POST['consultation_fee']) ? floatval($_POST['consultation_fee']) : 0.00;
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'Available';
        $verStatus = isset($_POST['verification_status']) ? trim($_POST['verification_status']) : 'Unverified';

        if ($doctorId <= 0) {
            $errors[] = 'Invalid doctor ID.';
        }
        if (empty($firstName) || empty($lastName)) {
            $errors[] = 'First and Last name are required.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email address is required.';
        }

        if (empty($errors)) {
            $chk = $conn->prepare("SELECT doctor_id FROM tbl_doctor WHERE email = ? AND doctor_id != ? LIMIT 1");
            $chk->bind_param("si", $email, $doctorId);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors[] = 'This email address is already in use by another doctor.';
            }
            $chk->close();
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE tbl_doctor SET first_name=?, middle_name=?, last_name=?, email=?, phone_number=?, department=?, specialization=?, qualification=?, licence_number=?, years_experience=?, consultation_fee=?, status=?, verification_status=? WHERE doctor_id=?");
            $stmt->bind_param("ssssssssssdssi", $firstName, $middleName, $lastName, $email, $phone, $department, $specialization, $qualification, $licenceNumber, $experience, $consultationFee, $status, $verStatus, $doctorId);
            if ($stmt->execute()) {
                $success = 'Doctor details updated successfully!';
            } else {
                $errors[] = 'Failed to update doctor details: ' . $conn->error;
            }
            $stmt->close();
        }
    }

    if ($action === 'archive_doctor') {
        $doctorId = isset($_POST['doctor_id']) ? intval($_POST['doctor_id']) : 0;
        if ($doctorId <= 0) {
            $errors[] = 'Invalid doctor ID.';
        } else {
            $stmt = $conn->prepare("UPDATE tbl_doctor SET is_archived = 1 WHERE doctor_id = ?");
            $stmt->bind_param("i", $doctorId);
            if ($stmt->execute()) {
                $success = 'Doctor archived successfully (soft deleted).';
            } else {
                $errors[] = 'Failed to archive doctor: ' . $conn->error;
            }
            $stmt->close();
        }
    }

    if ($action === 'restore_doctor') {
        $doctorId = isset($_POST['doctor_id']) ? intval($_POST['doctor_id']) : 0;
        if ($doctorId <= 0) {
            $errors[] = 'Invalid doctor ID.';
        } else {
            $stmt = $conn->prepare("UPDATE tbl_doctor SET is_archived = 0 WHERE doctor_id = ?");
            $stmt->bind_param("i", $doctorId);
            if ($stmt->execute()) {
                $success = 'Doctor restored successfully to active list.';
            } else {
                $errors[] = 'Failed to restore doctor: ' . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Format dates
$createdDate = date('M d, Y \a\t h:i A', strtotime($admin['createdAt']));
$updatedDate = date('M d, Y \a\t h:i A', strtotime($admin['updatedAt']));
$initials = strtoupper(substr($admin['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Medi-Care Admin Profile — Manage your admin account settings.">
    <title>Admin Profile | Medi-Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/auth/admin-login.css">
    <link rel="stylesheet" href="../css/admin-profile.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- Animated Background -->
    <div class="bg-pattern"></div>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar scrolled" id="navbar">
        <a href="../index.php" class="nav-brand">
            <div class="nav-brand-icon">M+</div>
            <div class="nav-brand-text">Medi-<span>Care</span></div>
        </a>
        <ul class="nav-links" id="navLinks">
            <li><a href="../index.php" class="nav-link">Home</a></li>
            <li><a href="logout.php" class="nav-link nav-link-logout" onclick="return confirm('Are you sure you want to logout?');">Logout</a></li>
        </ul>
    </nav>

    <!-- ===== PROFILE SECTION ===== -->
    <div class="profile-wrapper">
        <?php include __DIR__ . '/../includes/breadcrumb.php'; ?>

        <!-- Profile Header Card -->
        <div class="profile-hero-card">
            <div class="profile-hero-bg"></div>
            <div class="profile-hero-content">
                <div class="profile-avatar">
                    <span class="avatar-initials"><?php echo htmlspecialchars($initials); ?></span>
                    <span class="avatar-status <?php echo $admin['isAdmin'] ? 'online' : ''; ?>"></span>
                </div>
                <div class="profile-hero-info">
                    <h1><?php echo htmlspecialchars($admin['name']); ?></h1>
                    <p class="profile-email"><?php echo htmlspecialchars($admin['email']); ?></p>
                    <div class="profile-badges">
                        <?php if ($admin['isAdmin']): ?>
                            <span class="badge badge-admin"><i class="fi fi-rr-shield"></i> Admin</span>
                        <?php endif; ?>
                        <?php if ($admin['isStaff']): ?>
                            <span class="badge badge-staff"><i class="fi fi-rr-user"></i> Staff</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert-box alert-success" id="successAlert">
                <div class="alert-icon"><i class="fi fi-rr-check-circle"></i></div>
                <div class="alert-content">
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
                <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Admin Navigation Tabs -->
        <div class="admin-tabs">
            <button class="tab-btn active" onclick="switchTab('profile')"><i class="fi fi-rr-user"></i> My Profile</button>
            <button class="tab-btn" onclick="switchTab('departments')"><i class="fi fi-rr-hospital"></i> Manage Departments</button>
            <button class="tab-btn" onclick="switchTab('doctors')"><i class="fi fi-rr-stethoscope"></i> Doctor Consultation Fees</button>
        </div>

        <!-- Tab 1: Admin Profile -->
        <div id="tab-profile" class="tab-content active">
            <!-- Profile Content Grid -->
            <div class="profile-grid">

                <!-- Left Column: Info Cards -->
                <div class="profile-col">
                    <!-- Account Info Card -->
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <h2><i class="fi fi-rr-file-medical"></i> Account Details</h2>
                        </div>
                        <div class="profile-card-body">
                            <div class="info-row">
                                <span class="info-label">Admin ID</span>
                                <span class="info-value">#<?php echo htmlspecialchars($admin['admin_id']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Full Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($admin['name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?php echo htmlspecialchars($admin['email']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Admin Access</span>
                                <span class="info-value">
                                    <?php echo $admin['isAdmin'] ? '<span class="status-badge active">Active</span>' : '<span class="status-badge inactive">Inactive</span>'; ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Staff Access</span>
                                <span class="info-value">
                                    <?php echo $admin['isStaff'] ? '<span class="status-badge active">Active</span>' : '<span class="status-badge inactive">Inactive</span>'; ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Account Created</span>
                                <span class="info-value"><?php echo $createdDate; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Last Updated</span>
                                <span class="info-value"><?php echo $updatedDate; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Edit Forms -->
                <div class="profile-col">
                    <!-- Edit Profile Card -->
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <h2><i class="fi fi-rr-edit"></i> Edit Profile</h2>
                        </div>
                        <div class="profile-card-body">
                            <form method="POST" action="" novalidate id="editProfileForm">
                                <input type="hidden" name="action" value="update_profile">
                                <?php 
                                $generalErrors = array_filter($errors, function($key) { return is_numeric($key); }, ARRAY_FILTER_USE_KEY);
                                if (!empty($generalErrors) && $submittedAction === 'update_profile'): 
                                ?>
                                    <div class="hms-error-box">
                                        <ul>
                                            <?php foreach ($generalErrors as $error): ?>
                                                <li><?php echo htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <div class="form-group">
                                    <label class="form-label" for="name">Full Name</label>
                                    <?php if (isset($errors['name'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['name']); ?></div><?php endif; ?>
                                    <input type="text" class="form-input" id="name" name="name"
                                           value="<?php echo htmlspecialchars($admin['name']); ?>"
                                           placeholder="Enter your full name">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="profile_email">Email Address</label>
                                    <?php if (isset($errors['email'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['email']); ?></div><?php endif; ?>
                                    <input type="text" class="form-input" id="profile_email" name="email"
                                           value="<?php echo htmlspecialchars($admin['email']); ?>"
                                           placeholder="Enter your email">
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn-auth btn-auth-primary">
                                        <i class="fi fi-rr-disk"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Change Password Card -->
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <h2><i class="fi fi-rr-lock"></i> Change Password</h2>
                        </div>
                        <div class="profile-card-body">
                            <form method="POST" action="" novalidate id="changePasswordForm">
                                <input type="hidden" name="action" value="change_password">
                                <?php 
                                $generalErrors = array_filter($errors, function($key) { return is_numeric($key); }, ARRAY_FILTER_USE_KEY);
                                if (!empty($generalErrors) && $submittedAction === 'change_password'): 
                                ?>
                                    <div class="hms-error-box">
                                        <ul>
                                            <?php foreach ($generalErrors as $error): ?>
                                                <li><?php echo htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <div class="form-group">
                                    <label class="form-label" for="current_password">Current Password</label>
                                    <?php if (isset($errors['current_password'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['current_password']); ?></div><?php endif; ?>
                                    <div class="password-input-wrapper">
                                        <input type="password" class="form-input" id="current_password" name="current_password" placeholder="Enter current password">
                                        <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('current_password', this)" title="Show / Hide Password">
                                            <i class="fi fi-rr-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="new_password">New Password</label>
                                    <?php if (isset($errors['new_password'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['new_password']); ?></div><?php endif; ?>
                                    <div class="password-input-wrapper">
                                        <input type="password" class="form-input" id="new_password" name="new_password" placeholder="Enter new password">
                                        <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('new_password', this)" title="Show / Hide Password">
                                            <i class="fi fi-rr-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="confirm_password">Confirm New Password</label>
                                    <?php if (isset($errors['confirm_password'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['confirm_password']); ?></div><?php endif; ?>
                                    <div class="password-input-wrapper">
                                        <input type="password" class="form-input" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                                        <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('confirm_password', this)" title="Show / Hide Password">
                                            <i class="fi fi-rr-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn-auth btn-auth-primary">
                                        <i class="fi fi-rr-key"></i> Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Tab 2: Manage Departments -->
        <div id="tab-departments" class="tab-content">
            <div class="profile-grid">
                <!-- Add Department Card -->
                <div class="profile-col">
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <h2><i class="fi fi-rr-plus"></i> Add New Department</h2>
                        </div>
                        <div class="profile-card-body">
                            <form method="POST" action="" novalidate>
                                <input type="hidden" name="action" value="add_department">
                                <?php 
                                $generalErrors = array_filter($errors, function($key) { return is_numeric($key); }, ARRAY_FILTER_USE_KEY);
                                if (!empty($generalErrors) && $submittedAction === 'add_department'): 
                                ?>
                                    <div class="hms-error-box">
                                        <ul>
                                            <?php foreach ($generalErrors as $error): ?>
                                                <li><?php echo htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <div class="form-group">
                                    <label class="form-label" for="department_name">Department Name</label>
                                    <?php if (isset($errors['department_name'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['department_name']); ?></div><?php endif; ?>
                                    <input type="text" class="form-input" id="department_name" name="department_name" placeholder="e.g. Cardiology" required>
                                </div>
                                <div class="form-actions" style="margin-top: 15px;">
                                    <button type="submit" class="btn-auth btn-auth-primary">
                                        <i class="fi fi-rr-hospital"></i> Add Department
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Existing Departments List Card -->
                <div class="profile-col">
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <h2><i class="fi fi-rr-file-medical"></i> Existing Departments</h2>
                        </div>
                        <div class="profile-card-body" style="overflow-x: auto;">
                            <?php if (!empty($errors) && in_array($submittedAction, ['delete_department', 'update_department'])): ?>
                                <div class="hms-error-box">
                                    <ul>
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Department Name</th>
                                        <th style="text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $allDepts = $conn->query("SELECT * FROM tbl_department ORDER BY department_name ASC");
                                    if ($allDepts && $allDepts->num_rows > 0):
                                        while ($dept = $allDepts->fetch_assoc()):
                                    ?>
                                            <tr>
                                                <td>#<?php echo $dept['department_id']; ?></td>
                                                <td><strong><?php echo htmlspecialchars($dept['department_name']); ?></strong></td>
                                                <td style="text-align: right;">
                                                    <div class="dropdown-action-wrapper">
                                                        <button type="button" class="dropdown-action-trigger" onclick="toggleDropdown(this)">
                                                            Actions <span class="arrow-icon">▼</span>
                                                        </button>
                                                        <div class="dropdown-action-menu">
                                                            <button type="button" class="dropdown-action-item item-edit" onclick="openUpdateDeptModal(<?php echo $dept['department_id']; ?>, '<?php echo htmlspecialchars(addslashes($dept['department_name'])); ?>')">
                                                                <i class="fi fi-rr-edit"></i> Edit
                                                            </button>
                                                            <form method="POST" action="" class="dept-action-form" onsubmit="return confirmDelete(event);">
                                                                <input type="hidden" name="action" value="delete_department">
                                                                <input type="hidden" name="department_id" value="<?php echo $dept['department_id']; ?>">
                                                                <button type="submit" class="dropdown-action-item item-delete"><i class="fi fi-rr-trash"></i> Delete</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                    <?php
                                        endwhile;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="3" class="no-records">No departments found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Registered Doctors Management & Consultation Fees -->
        <div id="tab-doctors" class="tab-content">
            <div class="profile-card" style="width: 100%;">
                <div class="profile-card-header">
                    <h2><i class="fi fi-rr-stethoscope"></i> Doctor Profile Management & Consultation Fees</h2>
                </div>

                <div class="profile-card-body">
                    <?php if (!empty($errors) && in_array($submittedAction, ['update_consultation_fee', 'edit_doctor_admin', 'archive_doctor', 'restore_doctor'])): ?>
                        <div class="hms-error-box">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php
                    $actCnt = $conn->query("SELECT COUNT(*) AS total FROM tbl_doctor WHERE is_archived = 0")->fetch_assoc()['total'] ?? 0;
                    $arcCnt = $conn->query("SELECT COUNT(*) AS total FROM tbl_doctor WHERE is_archived = 1")->fetch_assoc()['total'] ?? 0;
                    ?>

                    <!-- Filter Sub-tabs -->
                    <div class="sub-tabs">
                        <button type="button" class="sub-tab-btn active" id="btnSubActive" onclick="switchDocSubTab('active')">
                            <i class="fi fi-rr-users"></i> Active Doctors (<?php echo $actCnt; ?>)
                        </button>
                        <button type="button" class="sub-tab-btn" id="btnSubArchived" onclick="switchDocSubTab('archived')">
                            <i class="fi fi-rr-box-alt"></i> Archived / Soft Deleted (<?php echo $arcCnt; ?>)
                        </button>
                    </div>

                    <!-- Responsive Table Container -->
                    <div class="table-responsive-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>S.N.</th>
                                    <th>Doctor Name</th>
                                    <th>Department</th>
                                    <th>Licence No.</th>
                                    <th>Availability</th>
                                    <th>Verification</th>
                                    <th>Consultation Fee</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $allDocsSql = "SELECT * FROM tbl_doctor ORDER BY is_archived ASC, first_name ASC";
                                $allDocs = $conn->query($allDocsSql);
                                $docSn = 1;
                                if ($allDocs && $allDocs->num_rows > 0):
                                    while ($doc = $allDocs->fetch_assoc()):
                                        $fullname = trim($doc['first_name'] . ' ' . $doc['middle_name'] . ' ' . $doc['last_name']);
                                        $isVerified = ($doc['verification_status'] === 'Verified');
                                        $isArchived = intval($doc['is_archived'] ?? 0);
                                        $jsonDoc = htmlspecialchars(json_encode($doc, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
                                ?>
                                        <tr class="doc-row <?php echo $isArchived ? 'row-archived' : 'row-active'; ?>" style="<?php echo $isArchived ? 'display: none; background: rgba(0,0,0,0.02);' : ''; ?>">
                                            <td><strong><?php echo $docSn++; ?></strong></td>
                                            <td>
                                                <strong style="color: var(--text-primary); font-size: 0.92rem;">Dr. <?php echo htmlspecialchars($fullname); ?></strong>
                                            </td>
                                            <td>
                                                <strong style="font-size: 0.85rem; color: var(--text-primary);"><?php echo htmlspecialchars($doc['department']); ?></strong>
                                            </td>
                                            <td>
                                                <code style="background: rgba(0,0,0,0.05); padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;"><?php echo htmlspecialchars($doc['licence_number']); ?></code>
                                            </td>
                                            <td>
                                                <?php if ($doc['status'] === 'Available'): ?>
                                                    <span class="status-badge active">Available</span>
                                                <?php elseif ($doc['status'] === 'Unavailable'): ?>
                                                    <span class="status-badge inactive">Unavailable</span>
                                                <?php else: ?>
                                                    <span class="status-badge" style="background: rgba(245, 158, 11, 0.15); color: #D97706; border: 1px solid rgba(245, 158, 11, 0.3);">On Leave</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($isVerified): ?>
                                                    <span class="ver-badge verified">
                                                        <i class="fi fi-rr-check-circle"></i> Verified
                                                    </span>
                                                <?php else: ?>
                                                    <span class="ver-badge unverified">
                                                        <i class="fi fi-rr-exclamation"></i> Unverified
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong style="font-size: 0.9rem; color: var(--accent);">Rs. <?php echo number_format($doc['consultation_fee'], 2); ?></strong>
                                            </td>
                                            <td style="text-align: right;">
                                                <div class="dropdown-action-wrapper">
                                                    <button type="button" class="dropdown-action-trigger" onclick="toggleDropdown(this)" title="Actions">
                                                        Actions <i class="fi fi-rr-angle-small-down"></i>
                                                    </button>
                                                    <div class="dropdown-action-menu">
                                                        <button type="button" class="dropdown-action-item item-view" data-doctor='<?php echo $jsonDoc; ?>' onclick="closeDropdowns(); handleViewClick(this)">
                                                            <i class="fi fi-rr-eye"></i> View Details
                                                        </button>
                                                        
                                                        <button type="button" class="dropdown-action-item item-edit" data-doctor='<?php echo $jsonDoc; ?>' onclick="closeDropdowns(); handleEditClick(this)">
                                                            <i class="fi fi-rr-edit"></i> Edit Doctor
                                                        </button>

                                                        <?php if (!$isVerified && !$isArchived): ?>
                                                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to approve profile of Dr. <?php echo htmlspecialchars(addslashes($fullname)); ?>?');">
                                                                <input type="hidden" name="action" value="toggle_verification">
                                                                <input type="hidden" name="doctor_id" value="<?php echo $doc['doctor_id']; ?>">
                                                                <input type="hidden" name="verification_status" value="Verified">
                                                                <button type="submit" class="dropdown-action-item item-approve">
                                                                    <i class="fi fi-rr-check-circle"></i> Approve
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <?php if (!$isArchived): ?>
                                                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to soft delete (archive) Dr. <?php echo htmlspecialchars($fullname); ?>?');">
                                                                <input type="hidden" name="action" value="archive_doctor">
                                                                <input type="hidden" name="doctor_id" value="<?php echo $doc['doctor_id']; ?>">
                                                                <button type="submit" class="dropdown-action-item item-archive">
                                                                    <i class="fi fi-rr-box-alt"></i> Archive
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <form method="POST" action="">
                                                                <input type="hidden" name="action" value="restore_doctor">
                                                                <input type="hidden" name="doctor_id" value="<?php echo $doc['doctor_id']; ?>">
                                                                <button type="submit" class="dropdown-action-item item-restore">
                                                                    <i class="fi fi-rr-refresh"></i> Restore
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="7" class="no-records">No doctors found in database.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== FOOTER ===== -->
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> Medi-Care Hospital Management System. All rights reserved.</p>
    </footer>

    <!-- Edit Department Modal -->
    <div id="updateDeptModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fi fi-rr-edit"></i> Edit Department</h3>
                <button type="button" class="modal-close" onclick="closeUpdateDeptModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_department">
                    <input type="hidden" name="department_id" id="update_dept_id">
                    
                    <div class="form-group">
                        <label class="form-label" for="update_dept_name">Department Name</label>
                        <input type="text" class="form-input" id="update_dept_name" name="department_name" required>
                    </div>
                    
                    <div class="form-actions" style="margin-top: 20px;">
                        <button type="submit" class="btn-auth btn-auth-primary" style="width: 100%;">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Doctor Modal -->
    <div id="viewDoctorModal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h3><i class="fi fi-rr-user"></i> Doctor Profile Details</h3>
                <button type="button" class="modal-close" onclick="closeViewDoctorModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewDoctorBody">
                <!-- Dynamically populated via JS -->
            </div>
        </div>
    </div>

    <!-- Edit Doctor Modal -->
    <div id="editDoctorModal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h3><i class="fi fi-rr-edit"></i> Edit Doctor Profile</h3>
                <button type="button" class="modal-close" onclick="closeEditDoctorModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="editDoctorForm">
                    <input type="hidden" name="action" value="edit_doctor_admin">
                    <input type="hidden" name="doctor_id" id="edit_doc_id">

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px;">
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-input" name="first_name" id="edit_doc_first_name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-input" name="middle_name" id="edit_doc_middle_name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-input" name="last_name" id="edit_doc_last_name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-input" name="email" id="edit_doc_email" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="text" class="form-input" name="phone_number" id="edit_doc_phone" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <select class="form-input" name="department" id="edit_doc_department" required>
                                <?php
                                $deptsRes = $conn->query("SELECT department_name FROM tbl_department ORDER BY department_name ASC");
                                if ($deptsRes) {
                                    while ($dRow = $deptsRes->fetch_assoc()) {
                                        echo '<option value="' . htmlspecialchars($dRow['department_name']) . '">' . htmlspecialchars($dRow['department_name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Specialization</label>
                            <input type="text" class="form-input" name="specialization" id="edit_doc_specialization" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Qualification</label>
                            <input type="text" class="form-input" name="qualification" id="edit_doc_qualification" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">NMC Licence Number</label>
                            <input type="text" class="form-input" name="licence_number" id="edit_doc_licence" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Years of Experience</label>
                            <select class="form-input" name="years_experience" id="edit_doc_experience" required>
                                <option value="">Select years</option>
                                <option value="0-3">0-3 years</option>
                                <option value="3-6">3-6 years</option>
                                <option value="6-9">6-9 years</option>
                                <option value="9-12">9-12 years</option>
                                <option value="12-15">12-15 years</option>
                                <option value="15-18">15-18 years</option>
                                <option value="18-21">18-21 years</option>
                                <option value="21-24">21-24 years</option>
                                <option value="24-27">24-27 years</option>
                                <option value="27-30">27-30 years</option>
                                <option value="30+">30+ years</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Consultation Fee (Rs.)</label>
                            <input type="number" class="form-input" name="consultation_fee" id="edit_doc_fee" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Availability Status</label>
                            <select class="form-input" name="status" id="edit_doc_status">
                                <option value="Available">Available</option>
                                <option value="Unavailable">Unavailable</option>
                                <option value="On Leave">On Leave</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label class="form-label">Verification Status</label>
                            <select class="form-input" name="verification_status" id="edit_doc_verification_status">
                                <option value="Unverified">Unverified (Pending Approval)</option>
                                <option value="Verified">Verified Doctor</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 20px;">
                        <button type="submit" class="btn-auth btn-auth-primary" style="width: 100%;">
                            Save Doctor Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Navbar scroll
        const navbar = document.getElementById('navbar');
        if (navbar) {
            window.addEventListener('scroll', () => {
                navbar.classList.toggle('scrolled', window.scrollY > 20);
            });
        }

        // Auto-hide success alert after 4s
        const successAlert = document.getElementById('successAlert');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.opacity = '0';
                successAlert.style.transform = 'translateY(-10px)';
                setTimeout(() => successAlert.style.display = 'none', 300);
            }, 4000);
        }

        // Tab Switching Logic
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            const selectedContent = document.getElementById('tab-' + tabId);
            const selectedBtn = document.querySelector(`[onclick="switchTab('${tabId}')"]`);
            if (selectedContent && selectedBtn) {
                selectedContent.classList.add('active');
                selectedBtn.classList.add('active');
                localStorage.setItem('active_admin_tab', tabId);
            }
        }

        // Doctor Sub-tab Filter (Active vs Archived)
        function switchDocSubTab(type) {
            const btnActive = document.getElementById('btnSubActive');
            const btnArchived = document.getElementById('btnSubArchived');
            const activeRows = document.querySelectorAll('.doc-row.row-active');
            const archivedRows = document.querySelectorAll('.doc-row.row-archived');

            if (type === 'active') {
                if (btnActive) btnActive.classList.add('active');
                if (btnArchived) btnArchived.classList.remove('active');
                activeRows.forEach(r => r.style.display = '');
                archivedRows.forEach(r => r.style.display = 'none');
            } else {
                if (btnArchived) btnArchived.classList.add('active');
                if (btnActive) btnActive.classList.remove('active');
                activeRows.forEach(r => r.style.display = 'none');
                archivedRows.forEach(r => r.style.display = '');
            }
        }

        // On load, restore active tab
        document.addEventListener('DOMContentLoaded', () => {
            const savedTab = localStorage.getItem('active_admin_tab') || 'profile';
            switchTab(savedTab);
        });

        function confirmDelete(event) {
            return confirm("Are you sure to delete this department?");
        }

        // Dropdown toggle logic with smart auto-upward positioning
        function toggleDropdown(trigger) {
            const wrapper = trigger.closest('.dropdown-action-wrapper');
            if (!wrapper) return;
            const wasOpen = wrapper.classList.contains('open');
            closeDropdowns();

            if (!wasOpen) {
                // Calculate trigger position relative to container and window viewport
                const triggerRect = trigger.getBoundingClientRect();
                const container = trigger.closest('.table-responsive-container') || trigger.closest('.profile-card-body') || trigger.closest('.profile-card') || document.body;
                const containerRect = container.getBoundingClientRect();

                const spaceBelowContainer = containerRect.bottom - triggerRect.bottom;
                const spaceBelowViewport = window.innerHeight - triggerRect.bottom;
                const spaceBelow = Math.min(spaceBelowContainer, spaceBelowViewport);

                // Menu height requirement (~165px)
                const menuHeight = 165;

                if (spaceBelow < menuHeight) {
                    wrapper.classList.add('open-upward');
                } else {
                    wrapper.classList.remove('open-upward');
                }

                const tr = trigger.closest('tr');
                if (tr) tr.classList.add('doc-row-active-dropdown');

                wrapper.classList.add('open');
            }
        }

        function closeDropdowns() {
            document.querySelectorAll('.dropdown-action-wrapper.open').forEach(el => {
                el.classList.remove('open');
                el.classList.remove('open-upward');
            });
            document.querySelectorAll('.doc-row-active-dropdown').forEach(tr => {
                tr.classList.remove('doc-row-active-dropdown');
            });
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-action-wrapper')) {
                closeDropdowns();
            }
        });

        // Edit Department Modal Logic
        const updateDeptModal = document.getElementById('updateDeptModal');
        const updateDeptIdInput = document.getElementById('update_dept_id');
        const updateDeptNameInput = document.getElementById('update_dept_name');

        function openUpdateDeptModal(id, name) {
            if (updateDeptIdInput) updateDeptIdInput.value = id;
            if (updateDeptNameInput) updateDeptNameInput.value = name;
            if (updateDeptModal) updateDeptModal.classList.add('show');
            closeDropdowns();
        }

        function closeUpdateDeptModal() {
            if (updateDeptModal) updateDeptModal.classList.remove('show');
        }

        function handleViewClick(btn) {
            try {
                const doc = JSON.parse(btn.getAttribute('data-doctor'));
                openViewDoctorModal(doc);
            } catch (e) {
                console.error("Error parsing doctor data:", e);
            }
        }

        function handleEditClick(btn) {
            try {
                const doc = JSON.parse(btn.getAttribute('data-doctor'));
                openEditDoctorModal(doc);
            } catch (e) {
                console.error("Error parsing doctor data:", e);
            }
        }

        // View Doctor Modal Logic
        const viewDoctorModal = document.getElementById('viewDoctorModal');
        const viewDoctorBody = document.getElementById('viewDoctorBody');

        function openViewDoctorModal(doc) {
            const fullname = (doc.first_name + ' ' + (doc.middle_name || '') + ' ' + doc.last_name).trim();
            const feeFormatted = parseFloat(doc.consultation_fee || 0).toFixed(2);
            
            viewDoctorBody.innerHTML = `
                <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border-glass);">
                    <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--accent)); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 800; border: 3px solid #FFF;">
                        ${(doc.first_name.charAt(0) + doc.last_name.charAt(0)).toUpperCase()}
                    </div>
                    <div>
                        <h4 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 2px;">Dr. ${escapeHtml(fullname)}</h4>
                        <span style="color: var(--accent); font-weight: 600; font-size: 0.88rem;">${escapeHtml(doc.specialization || '')} — ${escapeHtml(doc.department || '')}</span>
                    </div>
                </div>

                <div class="doctor-detail-grid">
                    <div class="doctor-detail-item">
                        <label>Email Address</label>
                        <span>${escapeHtml(doc.email || '—')}</span>
                    </div>
                    <div class="doctor-detail-item">
                        <label>Phone Number</label>
                        <span>${escapeHtml(doc.phone_number || '—')}</span>
                    </div>
                    <div class="doctor-detail-item">
                        <label>Gender / Marital Status</label>
                        <span>${escapeHtml(doc.gender || '—')} / ${escapeHtml(doc.marital_status || '—')}</span>
                    </div>
                    <div class="doctor-detail-item">
                        <label>NMC Licence Number</label>
                        <span><code style="background: rgba(0,0,0,0.05); padding: 2px 6px; border-radius: 4px;">${escapeHtml(doc.licence_number || '—')}</code></span>
                    </div>
                    <div class="doctor-detail-item">
                        <label>Qualification</label>
                        <span>${escapeHtml(doc.qualification || '—')}</span>
                    </div>
                    <div class="doctor-detail-item">
                        <label>Years of Experience</label>
                        <span>${escapeHtml(doc.years_experience || '0-3')} years</span>
                    </div>
                    <div class="doctor-detail-item">
                        <label>Consultation Fee</label>
                        <span style="font-weight: 700; color: var(--accent);">Rs. ${feeFormatted}</span>
                    </div>
                    <div class="doctor-detail-item">
                        <label>Shift Hours & Slot Duration</label>
                        <span>${escapeHtml(doc.shift_start || '09:00')} - ${escapeHtml(doc.shift_end || '17:00')} (${doc.slot_duration || 30} mins)</span>
                    </div>
                    <div class="doctor-detail-item">
                        <label>Availability Status</label>
                        <span>${escapeHtml(doc.status || 'Available')}</span>
                    </div>
                    <div class="doctor-detail-item">
                        <label>Verification Status</label>
                        <span>${escapeHtml(doc.verification_status || 'Unverified')}</span>
                    </div>
                    <div class="doctor-detail-item full-width">
                        <label>Temporary Address</label>
                        <span>${escapeHtml(doc.temporary_address || '—')}</span>
                    </div>
                    <div class="doctor-detail-item full-width">
                        <label>Permanent Address</label>
                        <span>${escapeHtml(doc.permanent_address || '—')}</span>
                    </div>
                </div>
            `;
            viewDoctorModal.classList.add('show');
        }

        function closeViewDoctorModal() {
            if (viewDoctorModal) viewDoctorModal.classList.remove('show');
        }

        // Edit Doctor Modal Logic
        const editDoctorModal = document.getElementById('editDoctorModal');
        function openEditDoctorModal(doc) {
            document.getElementById('edit_doc_id').value = doc.doctor_id || '';
            document.getElementById('edit_doc_first_name').value = doc.first_name || '';
            document.getElementById('edit_doc_middle_name').value = doc.middle_name || '';
            document.getElementById('edit_doc_last_name').value = doc.last_name || '';
            document.getElementById('edit_doc_email').value = doc.email || '';
            document.getElementById('edit_doc_phone').value = doc.phone_number || '';
            
            const deptSelect = document.getElementById('edit_doc_department');
            if (deptSelect) {
                const targetDept = (doc.department || '').trim();
                let matched = false;
                for (let i = 0; i < deptSelect.options.length; i++) {
                    if (deptSelect.options[i].value.trim().toLowerCase() === targetDept.toLowerCase()) {
                        deptSelect.selectedIndex = i;
                        matched = true;
                        break;
                    }
                }
                if (!matched && targetDept !== '') {
                    const opt = document.createElement('option');
                    opt.value = targetDept;
                    opt.textContent = targetDept;
                    opt.selected = true;
                    deptSelect.appendChild(opt);
                }
            }

            document.getElementById('edit_doc_specialization').value = doc.specialization || '';
            document.getElementById('edit_doc_qualification').value = doc.qualification || '';
            document.getElementById('edit_doc_licence').value = doc.licence_number || '';
            document.getElementById('edit_doc_experience').value = doc.years_experience || '0-3';
            document.getElementById('edit_doc_fee').value = doc.consultation_fee || 0;
            document.getElementById('edit_doc_status').value = doc.status || 'Available';
            document.getElementById('edit_doc_verification_status').value = doc.verification_status || 'Unverified';

            if (editDoctorModal) editDoctorModal.classList.add('show');
        }

        function closeEditDoctorModal() {
            if (editDoctorModal) editDoctorModal.classList.remove('show');
        }

        function togglePasswordVisibility(inputId, btn) {
            const input = (typeof inputId === 'string') ? document.getElementById(inputId) : inputId;
            if (!input) return;
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) icon.className = 'fi fi-rr-eye-crossed';
            } else {
                input.type = 'password';
                if (icon) icon.className = 'fi fi-rr-eye';
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            return String(text)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        window.addEventListener('click', (e) => {
            if (e.target === updateDeptModal) closeUpdateDeptModal();
            if (e.target === viewDoctorModal) closeViewDoctorModal();
            if (e.target === editDoctorModal) closeEditDoctorModal();
        });
    </script>

</body>
</html>
