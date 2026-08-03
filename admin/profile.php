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
                                <?php if (!empty($errors) && $submittedAction === 'update_profile'): ?>
                                    <div class="hms-error-box">
                                        <ul>
                                            <?php foreach ($errors as $error): ?>
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
                                <?php if (!empty($errors) && $submittedAction === 'change_password'): ?>
                                    <div class="hms-error-box">
                                        <ul>
                                            <?php foreach ($errors as $error): ?>
                                                <li><?php echo htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <div class="form-group">
                                    <label class="form-label" for="current_password">Current Password</label>
                                    <?php if (isset($errors['current_password'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['current_password']); ?></div><?php endif; ?>
                                    <input type="password" class="form-input" id="current_password" name="current_password"
                                           placeholder="Enter current password">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="new_password">New Password</label>
                                    <?php if (isset($errors['new_password'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['new_password']); ?></div><?php endif; ?>
                                    <input type="password" class="form-input" id="new_password" name="new_password"
                                           placeholder="Enter new password">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="confirm_password">Confirm New Password</label>
                                    <?php if (isset($errors['confirm_password'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['confirm_password']); ?></div><?php endif; ?>
                                    <input type="password" class="form-input" id="confirm_password" name="confirm_password"
                                           placeholder="Confirm new password">
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
                                <?php if (!empty($errors) && $submittedAction === 'add_department'): ?>
                                    <div class="hms-error-box">
                                        <ul>
                                            <?php foreach ($errors as $error): ?>
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

        <!-- Tab 3: Doctor Consultation Fees -->
        <div id="tab-doctors" class="tab-content">
            <div class="profile-card" style="width: 100%;">
                <div class="profile-card-header">
                    <h2><i class="fi fi-rr-stethoscope"></i> Registered Doctors & Consultation Fees</h2>
                </div>
                <div class="profile-card-body" style="overflow-x: auto;">
                    <?php if (!empty($errors) && $submittedAction === 'update_consultation_fee'): ?>
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
                                <th>Doctor Name</th>
                                <th>Department</th>
                                <th>Licence Number</th>
                                <th>Status</th>
                                <th>Current Fee</th>
                                <th>Action / Update Fee</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $allDocs = $conn->query("SELECT doctor_id, first_name, middle_name, last_name, department, licence_number, status, consultation_fee FROM tbl_doctor ORDER BY first_name ASC");
                            if ($allDocs && $allDocs->num_rows > 0):
                                while ($doc = $allDocs->fetch_assoc()):
                                    $fullname = trim($doc['first_name'] . ' ' . $doc['middle_name'] . ' ' . $doc['last_name']);
                            ?>
                                    <tr>
                                        <td>
                                            <strong>Dr. <?php echo htmlspecialchars($fullname); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['department']); ?></td>
                                        <td><code style="background: rgba(0,0,0,0.04); padding: 2px 6px; border-radius: 4px;"><?php echo htmlspecialchars($doc['licence_number']); ?></code></td>
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
                                            <span style="font-weight: 600; color: var(--accent);">Rs. <?php echo number_format($doc['consultation_fee'], 2); ?></span>
                                        </td>
                                        <td>
                                            <form method="POST" action="" class="fee-form">
                                                <input type="hidden" name="action" value="update_consultation_fee">
                                                <input type="hidden" name="doctor_id" value="<?php echo $doc['doctor_id']; ?>">
                                                <div class="inline-fee-edit">
                                                    <span class="currency-symbol">Rs.</span>
                                                    <input type="number" name="consultation_fee" class="form-input inline-input" step="0.01" min="0" value="<?php echo htmlspecialchars($doc['consultation_fee']); ?>" required>
                                                    <button type="submit" class="btn-icon" title="Save Fee">💾</button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                            <?php
                                endwhile;
                            else:
                            ?>
                                <tr>
                                    <td colspan="6" class="no-records">No doctors registered yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
                <h3>✏️ Edit Department</h3>
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

    <script>
        // Navbar scroll
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            navbar.classList.toggle('scrolled', window.scrollY > 20);
        });

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

        // On load, restore active tab
        document.addEventListener('DOMContentLoaded', () => {
            const savedTab = localStorage.getItem('active_admin_tab') || 'profile';
            switchTab(savedTab);
        });

        function confirmDelete(event) {
            return confirm("Are you sure to delete this department?");
        }

        // Dropdown toggle logic
        function toggleDropdown(trigger) {
            const wrapper = trigger.closest('.dropdown-action-wrapper');
            const wasOpen = wrapper.classList.contains('open');
            closeDropdowns();
            if (!wasOpen) {
                wrapper.classList.add('open');
            }
        }

        function closeDropdowns() {
            document.querySelectorAll('.dropdown-action-wrapper.open').forEach(el => {
                el.classList.remove('open');
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
            updateDeptIdInput.value = id;
            updateDeptNameInput.value = name;
            updateDeptModal.classList.add('show');
            closeDropdowns();
        }

        function closeUpdateDeptModal() {
            updateDeptModal.classList.remove('show');
        }

        window.addEventListener('click', (e) => {
            if (e.target === updateDeptModal) {
                closeUpdateDeptModal();
            }
        });
    </script>

</body>
</html>
