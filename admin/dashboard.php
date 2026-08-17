<?php
session_start();
ini_set("mysqli.default_socket", "/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock");
require_once "../db-connection/db_conn.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$adminId = $_SESSION['admin_id'];
$errors = [];
$success = '';
$submittedAction = '';

// Fetch current Admin details
$stmt = $conn->prepare("SELECT admin_id, name, email, password, createdAt, updatedAt FROM tbl_admin WHERE admin_id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Process POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedAction = $_POST['action'] ?? '';

    // Action 1: Update Admin Profile
    if ($submittedAction === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($name)) $errors['name'] = "Name is required.";
        if (empty($email)) $errors['email'] = "Email is required.";
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = "Invalid email format.";

        if (empty($errors)) {
            $chk = $conn->prepare("SELECT admin_id FROM tbl_admin WHERE email = ? AND admin_id != ?");
            $chk->bind_param("si", $email, $adminId);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors['email'] = "This email is already in use.";
            }
            $chk->close();
        }

        if (empty($errors)) {
            $up = $conn->prepare("UPDATE tbl_admin SET name = ?, email = ?, updatedAt = NOW() WHERE admin_id = ?");
            $up->bind_param("ssi", $name, $email, $adminId);
            if ($up->execute()) {
                $_SESSION['admin_name'] = $name;
                $_SESSION['admin_email'] = $email;
                $admin['name'] = $name;
                $admin['email'] = $email;
                $success = "Profile updated successfully!";
            } else {
                $errors[] = "Failed to update profile: " . $conn->error;
            }
            $up->close();
        }
    }

    // Action 2: Change Password
    if ($submittedAction === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword)) {
            $errors['current_password'] = "Current password is required.";
        }

        if (empty($newPassword)) {
            $errors['new_password'] = "New password is required.";
        } elseif (strlen($newPassword) < 6) {
            $errors['new_password'] = "New password must be at least 6 characters.";
        }

        if (empty($confirmPassword)) {
            $errors['confirm_password'] = "Please confirm your new password.";
        } elseif ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = "Passwords do not match.";
        }

        if (empty($errors) && !password_verify($currentPassword, $admin['password'])) {
            $errors['current_password'] = "Incorrect current password.";
        }

        if (empty($errors)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $up = $conn->prepare("UPDATE tbl_admin SET password = ?, updatedAt = NOW() WHERE admin_id = ?");
            $up->bind_param("si", $hashedPassword, $adminId);
            if ($up->execute()) {
                $admin['password'] = $hashedPassword;
                $success = "Password changed successfully!";
            } else {
                $errors[] = "Failed to change password: " . $conn->error;
            }
            $up->close();
        }
    }

    // Action 3: Add Department
    if ($submittedAction === 'add_department') {
        $deptName = trim($_POST['department_name'] ?? '');
        if (empty($deptName)) $errors['department_name'] = "Department name is required.";

        if (empty($errors)) {
            $chk = $conn->prepare("SELECT department_id FROM tbl_department WHERE LOWER(department_name) = LOWER(?)");
            $chk->bind_param("s", $deptName);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors['department_name'] = "Department name already exists.";
            }
            $chk->close();
        }

        if (empty($errors)) {
            $ins = $conn->prepare("INSERT INTO tbl_department (department_name) VALUES (?)");
            $ins->bind_param("s", $deptName);
            if ($ins->execute()) {
                $success = "Department '$deptName' added successfully!";
            } else {
                $errors[] = "Failed to add department: " . $conn->error;
            }
            $ins->close();
        }
    }

    // Action 4: Update Department
    if ($submittedAction === 'update_department') {
        $deptId = intval($_POST['department_id'] ?? 0);
        $deptName = trim($_POST['department_name'] ?? '');

        if ($deptId <= 0 || empty($deptName)) {
            $errors[] = "Department ID and Name are required.";
        } else {
            $up = $conn->prepare("UPDATE tbl_department SET department_name = ? WHERE department_id = ?");
            $up->bind_param("si", $deptName, $deptId);
            if ($up->execute()) {
                $success = "Department updated successfully!";
            } else {
                $errors[] = "Failed to update department: " . $conn->error;
            }
            $up->close();
        }
    }

    // Action 5: Delete Department
    if ($submittedAction === 'delete_department') {
        $deptId = intval($_POST['department_id'] ?? 0);
        if ($deptId <= 0) {
            $errors[] = "Invalid Department ID.";
        } else {
            $del = $conn->prepare("DELETE FROM tbl_department WHERE department_id = ?");
            $del->bind_param("i", $deptId);
            if ($del->execute()) {
                $success = "Department deleted successfully!";
            } else {
                $errors[] = "Failed to delete department: " . $conn->error;
            }
            $del->close();
        }
    }

    // Action 6: Toggle Doctor Verification
    if ($submittedAction === 'toggle_verification') {
        $docId = intval($_POST['doctor_id'] ?? 0);
        $verStatus = trim($_POST['verification_status'] ?? '');
        if ($docId > 0 && in_array($verStatus, ['Verified', 'Unverified'])) {
            $up = $conn->prepare("UPDATE tbl_doctor SET verification_status = ? WHERE doctor_id = ?");
            $up->bind_param("si", $verStatus, $docId);
            if ($up->execute()) {
                $lbl = ($verStatus === 'Verified') ? 'verified & approved' : 'unverified';
                $success = "Doctor status successfully set to $lbl!";
            }
            $up->close();
        }
    }

    // Action 7: Edit Doctor Details
    if ($submittedAction === 'edit_doctor_admin') {
        $docId = intval($_POST['doctor_id'] ?? 0);
        $firstName = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone_number'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $licenceNumber = trim($_POST['licence_number'] ?? '');
        $experience = trim($_POST['years_experience'] ?? '0-3');
        $fee = floatval($_POST['consultation_fee'] ?? 0.00);
        $status = trim($_POST['status'] ?? 'Available');
        $verStatus = trim($_POST['verification_status'] ?? 'Unverified');

        if ($docId <= 0 || empty($firstName) || empty($lastName) || empty($email)) {
            $errors[] = "First name, last name, and valid email are required.";
        } else {
            $up = $conn->prepare("UPDATE tbl_doctor SET first_name=?, middle_name=?, last_name=?, email=?, phone_number=?, department=?, specialization=?, qualification=?, licence_number=?, years_experience=?, consultation_fee=?, status=?, verification_status=? WHERE doctor_id=?");
            $up->bind_param("ssssssssssdssi", $firstName, $middleName, $lastName, $email, $phone, $department, $specialization, $qualification, $licenceNumber, $experience, $fee, $status, $verStatus, $docId);
            if ($up->execute()) {
                $success = "Doctor profile updated successfully!";
            } else {
                $errors[] = "Failed to update doctor: " . $conn->error;
            }
            $up->close();
        }
    }

    // Action 8: Archive Doctor (Soft Delete)
    if ($submittedAction === 'archive_doctor') {
        $docId = intval($_POST['doctor_id'] ?? 0);
        if ($docId > 0) {
            $up = $conn->prepare("UPDATE tbl_doctor SET is_archived = 1 WHERE doctor_id = ?");
            $up->bind_param("i", $docId);
            if ($up->execute()) {
                $success = "Doctor soft-deleted (archived) successfully!";
            }
            $up->close();
        }
    }

    // Action 9: Restore Doctor
    if ($submittedAction === 'restore_doctor') {
        $docId = intval($_POST['doctor_id'] ?? 0);
        if ($docId > 0) {
            $up = $conn->prepare("UPDATE tbl_doctor SET is_archived = 0 WHERE doctor_id = ?");
            $up->bind_param("i", $docId);
            if ($up->execute()) {
                $success = "Doctor restored to active list successfully!";
            }
            $up->close();
        }
    }
}

// KPI Statistics
$totalDocs = $conn->query("SELECT COUNT(*) AS total FROM tbl_doctor WHERE is_archived = 0")->fetch_assoc()['total'] ?? 0;
$pendingVerDocs = $conn->query("SELECT COUNT(*) AS total FROM tbl_doctor WHERE verification_status = 'Unverified' AND is_archived = 0")->fetch_assoc()['total'] ?? 0;
$totalPatients = $conn->query("SELECT COUNT(*) AS total FROM tbl_patient")->fetch_assoc()['total'] ?? 0;
$totalDepts = $conn->query("SELECT COUNT(*) AS total FROM tbl_department")->fetch_assoc()['total'] ?? 0;

$appRes = $conn->query("SELECT COUNT(*) AS total FROM tbl_appointment");
$totalAppointments = $appRes ? ($appRes->fetch_assoc()['total'] ?? 0) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Hospital Management System</title>
    <link rel="stylesheet" href="../css/index/variables.css">
    <link rel="stylesheet" href="../css/auth/auth.css">
    <link rel="stylesheet" href="../css/admin-profile.css">
    <link rel="stylesheet" href="../css/admin-sidebar.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="admin-layout">
        <!-- Include Sidebar -->
        <?php include "sidebar.php"; ?>

        <!-- Main Workspace -->
        <main class="admin-main">
            <!-- Top Header -->
            <header class="admin-top-header">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <button type="button" class="admin-mobile-toggle" onclick="toggleAdminSidebar()">
                        <i class="fi fi-rr-menu-burger"></i>
                    </button>
                    <div class="admin-header-title">
                        <?php include __DIR__ . '/../includes/breadcrumb.php'; ?>
                        <h1 id="pageSectionTitle">Dashboard Overview</h1>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 16px;">
                    <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">
                        <i class="fi fi-rr-clock"></i> <?php echo date('D, M d, Y'); ?>
                    </span>
                </div>
            </header>

            <!-- Body Content -->
            <div class="admin-body-content">

                <!-- Alert Messages -->
                <?php if ($success): ?>
                    <div class="hms-success-alert" id="successAlert" style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10B981; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                        <i class="fi fi-rr-check-circle" style="font-size: 1.2rem;"></i>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>

                <!-- ===== SECTION 1: DASHBOARD OVERVIEW ===== -->
                <section id="sec-overview" class="admin-section active">
                    <!-- KPI Cards Grid -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 28px;">
                        <!-- Doctors KPI -->
                        <div class="profile-card stat-card-clickable" onclick="switchAdminSection('doctors')" title="Click to view Doctor Management" style="padding: 24px; border-left: 4px solid var(--primary);">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <span style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Active Doctors</span>
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(91, 84, 224, 0.12); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                                    <i class="fi fi-rr-stethoscope"></i>
                                </div>
                            </div>
                            <h3 style="font-size: 2rem; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;"><?php echo $totalDocs; ?></h3>
                            <?php if ($pendingVerDocs > 0): ?>
                                <span style="font-size: 0.78rem; font-weight: 600; color: #D97706; background: rgba(245, 158, 11, 0.12); padding: 3px 8px; border-radius: 6px;">
                                    <i class="fi fi-rr-exclamation"></i> <?php echo $pendingVerDocs; ?> pending approval
                                </span>
                            <?php else: ?>
                                <span style="font-size: 0.78rem; font-weight: 600; color: #10B981; background: rgba(16, 185, 129, 0.12); padding: 3px 8px; border-radius: 6px;">
                                    <i class="fi fi-rr-check-circle"></i> All doctors verified
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Patients KPI -->
                        <div class="profile-card stat-card-clickable" onclick="switchAdminSection('patients')" title="Click to view Patient Directory" style="padding: 24px; border-left: 4px solid var(--accent);">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <span style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Total Patients</span>
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(0, 184, 148, 0.12); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                                    <i class="fi fi-rr-users-alt"></i>
                                </div>
                            </div>
                            <h3 style="font-size: 2rem; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;"><?php echo $totalPatients; ?></h3>
                            <span style="font-size: 0.78rem; font-weight: 500; color: var(--text-muted);">Registered hospital patients</span>
                        </div>

                        <!-- Departments KPI -->
                        <div class="profile-card stat-card-clickable" onclick="switchAdminSection('departments')" title="Click to view Departments" style="padding: 24px; border-left: 4px solid #3B82F6;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <span style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Departments</span>
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(59, 130, 246, 0.12); color: #3B82F6; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                                    <i class="fi fi-rr-building"></i>
                                </div>
                            </div>
                            <h3 style="font-size: 2rem; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;"><?php echo $totalDepts; ?></h3>
                            <span style="font-size: 0.78rem; font-weight: 500; color: var(--text-muted);">Active medical units</span>
                        </div>

                        <!-- Appointments KPI -->
                        <div class="profile-card stat-card-clickable" onclick="switchAdminSection('appointments')" title="Click to view Appointments Master" style="padding: 24px; border-left: 4px solid #8B5CF6;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <span style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Appointments</span>
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(139, 92, 246, 0.12); color: #8B5CF6; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                                    <i class="fi fi-rr-calendar"></i>
                                </div>
                            </div>
                            <h3 style="font-size: 2rem; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;"><?php echo $totalAppointments; ?></h3>
                            <span style="font-size: 0.78rem; font-weight: 500; color: var(--text-muted);">Total hospital bookings</span>
                        </div>
                    </div>

                    <?php if ($pendingVerDocs > 0): ?>
                        <!-- Pending Verification Alert Banner -->
                        <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 14px; padding: 20px 24px; margin-bottom: 28px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                            <div style="display: flex; align-items: center; gap: 14px;">
                                <div style="width: 46px; height: 46px; border-radius: 12px; background: #D97706; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                                    <i class="fi fi-rr-exclamation"></i>
                                </div>
                                <div>
                                    <h4 style="font-size: 1.05rem; font-weight: 700; color: #B45309; margin-bottom: 2px;"><?php echo $pendingVerDocs; ?> Doctor Profile(s) Awaiting Verification</h4>
                                    <p style="font-size: 0.85rem; color: #B45309; opacity: 0.9;">Newly registered doctors require administrative review before appearing as verified to patients.</p>
                                </div>
                            </div>
                            <button type="button" onclick="switchAdminSection('doctors')" style="padding: 8px 18px; background: #D97706; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; white-space: nowrap;">
                                Review Doctors <i class="fi fi-rr-arrow-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Quick Overview Grid -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
                        <!-- Recent Registered Doctors Card -->
                        <div class="profile-card">
                            <div class="profile-card-header" style="display: flex; align-items: center; justify-content: space-between;">
                                <h2><i class="fi fi-rr-stethoscope"></i> Recent Doctors</h2>
                                <button type="button" onclick="switchAdminSection('doctors')" style="background: none; border: none; color: var(--primary); font-weight: 600; font-size: 0.82rem; cursor: pointer;">View All</button>
                            </div>
                            <div class="profile-card-body" style="padding: 16px;">
                                <?php
                                $recDocs = $conn->query("SELECT first_name, last_name, department, verification_status, status FROM tbl_doctor WHERE is_archived = 0 ORDER BY doctor_id DESC LIMIT 4");
                                if ($recDocs && $recDocs->num_rows > 0):
                                    while ($rd = $recDocs->fetch_assoc()):
                                        $isV = ($rd['verification_status'] === 'Verified');
                                ?>
                                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border-glass);">
                                            <div>
                                                <strong style="font-size: 0.9rem; color: var(--text-primary);">Dr. <?php echo htmlspecialchars($rd['first_name'] . ' ' . $rd['last_name']); ?></strong>
                                                <span style="display: block; font-size: 0.76rem; color: var(--text-muted);"><?php echo htmlspecialchars($rd['department']); ?></span>
                                            </div>
                                            <span class="status-badge <?php echo $isV ? 'active' : 'inactive'; ?>" style="font-size: 0.72rem;">
                                                <?php echo $isV ? '✓ Verified' : '⚠ Unverified'; ?>
                                            </span>
                                        </div>
                                <?php
                                    endwhile;
                                else:
                                    echo '<p style="text-align: center; color: var(--text-muted); padding: 20px;">No doctors registered yet.</p>';
                                endif;
                                ?>
                            </div>
                        </div>

                        <!-- System Departments Overview Card -->
                        <div class="profile-card">
                            <div class="profile-card-header" style="display: flex; align-items: center; justify-content: space-between;">
                                <h2><i class="fi fi-rr-building"></i> Medical Departments</h2>
                                <button type="button" onclick="switchAdminSection('departments')" style="background: none; border: none; color: var(--primary); font-weight: 600; font-size: 0.82rem; cursor: pointer;">Manage</button>
                            </div>
                            <div class="profile-card-body" style="padding: 16px;">
                                <?php
                                $recDepts = $conn->query("SELECT department_name, created_at FROM tbl_department ORDER BY department_name ASC LIMIT 5");
                                if ($recDepts && $recDepts->num_rows > 0):
                                    while ($dp = $recDepts->fetch_assoc()):
                                ?>
                                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border-glass);">
                                            <span style="font-size: 0.88rem; font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($dp['department_name']); ?></span>
                                            <span style="font-size: 0.75rem; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($dp['created_at'])); ?></span>
                                        </div>
                                <?php
                                    endwhile;
                                else:
                                    echo '<p style="text-align: center; color: var(--text-muted); padding: 20px;">No departments added yet.</p>';
                                endif;
                                ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ===== SECTION 2: DOCTOR MANAGEMENT ===== -->
                <section id="sec-doctors" class="admin-section">
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <h2><i class="fi fi-rr-stethoscope"></i> Registered Doctor Directory & Verification</h2>
                        </div>
                        <div class="profile-card-body">
                            <!-- Filter Sub-tabs -->
                            <div class="sub-tabs">
                                <button type="button" class="sub-tab-btn active" id="btnSubActive" onclick="switchDocSubTab('active')">
                                    <i class="fi fi-rr-users"></i> Active Doctors (<?php echo $totalDocs; ?>)
                                </button>
                                <button type="button" class="sub-tab-btn" id="btnSubArchived" onclick="switchDocSubTab('archived')">
                                    <i class="fi fi-rr-box-alt"></i> Archived / Soft Deleted
                                </button>
                            </div>

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
                                            echo '<tr><td colspan="8" class="no-records">No doctors found in database.</td></tr>';
                                        endif;
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ===== SECTION 3: PATIENT DIRECTORY ===== -->
                <section id="sec-patients" class="admin-section">
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <h2><i class="fi fi-rr-users-alt"></i> Registered Patients Directory</h2>
                        </div>
                        <div class="profile-card-body">
                            <div style="width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: var(--radius-md);">
                                <table class="admin-table" style="min-width: 800px;">
                                    <thead>
                                        <tr>
                                            <th>S.N.</th>
                                            <th>Patient Name</th>
                                            <th>Email Address</th>
                                            <th>Phone Number</th>
                                            <th>Gender</th>
                                            <th>Address</th>
                                            <th>Registered On</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $pts = $conn->query("SELECT * FROM tbl_patient ORDER BY first_name ASC");
                                        $ptSn = 1;
                                        if ($pts && $pts->num_rows > 0):
                                            while ($p = $pts->fetch_assoc()):
                                                $ptName = trim(($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
                                        ?>
                                                <tr>
                                                    <td><strong><?php echo $ptSn++; ?></strong></td>
                                                    <td>
                                                        <strong style="color: var(--text-primary);">Patient: <?php echo htmlspecialchars($ptName ?: 'Patient'); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($p['email'] ?? '—'); ?></td>
                                                    <td><?php echo htmlspecialchars($p['phone_number'] ?? '—'); ?></td>
                                                    <td><?php echo htmlspecialchars($p['gender'] ?? '—'); ?></td>
                                                    <td><?php echo htmlspecialchars($p['temporary_address'] ?? $p['permanent_address'] ?? '—'); ?></td>
                                                    <td><?php echo isset($p['created_at']) ? date('M d, Y', strtotime($p['created_at'])) : '—'; ?></td>
                                                </tr>
                                        <?php
                                            endwhile;
                                        else:
                                            echo '<tr><td colspan="7" class="no-records">No patients registered yet.</td></tr>';
                                        endif;
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ===== SECTION 4: DEPARTMENT MANAGEMENT ===== -->
                <section id="sec-departments" class="admin-section">
                    <!-- Add Department Form -->
                    <div class="profile-card" style="margin-bottom: 24px;">
                        <div class="profile-card-header">
                            <h2><i class="fi fi-rr-plus"></i> Add New Medical Department</h2>
                        </div>
                        <div class="profile-card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="add_department">
                                <div class="form-group-row">
                                    <div class="form-group">
                                        <label class="form-label" for="department_name">Department Name</label>
                                        <input type="text" class="form-input" id="department_name" name="department_name" placeholder="e.g. Cardiology, Neurology, Pediatrics" required>
                                    </div>
                                    <button type="submit" class="btn-auth btn-auth-primary" style="white-space: nowrap;">
                                        Add Department
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Department Directory Table -->
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <h2><i class="fi fi-rr-building"></i> Existing Medical Departments</h2>
                        </div>
                        <div class="profile-card-body">
                            <div style="width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: var(--radius-md);">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>S.N.</th>
                                            <th>Department Name</th>
                                            <th>Created Date</th>
                                            <th style="text-align: right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $allDepts = $conn->query("SELECT * FROM tbl_department ORDER BY department_name ASC");
                                        $deptSn = 1;
                                        if ($allDepts && $allDepts->num_rows > 0):
                                            while ($d = $allDepts->fetch_assoc()):
                                        ?>
                                                <tr>
                                                    <td><strong><?php echo $deptSn++; ?></strong></td>
                                                    <td><strong><?php echo htmlspecialchars($d['department_name']); ?></strong></td>
                                                    <td><?php echo date('M d, Y', strtotime($d['created_at'])); ?></td>
                                                    <td style="text-align: right;">
                                                        <div class="dropdown-action-wrapper">
                                                            <button type="button" class="dropdown-action-trigger" onclick="toggleDropdown(this)" title="Actions">
                                                                Actions <i class="fi fi-rr-angle-small-down"></i>
                                                            </button>
                                                            <div class="dropdown-action-menu">
                                                                <button type="button" class="dropdown-action-item item-edit" onclick="closeDropdowns(); openUpdateDeptModal(<?php echo $d['department_id']; ?>, '<?php echo htmlspecialchars($d['department_name'], ENT_QUOTES); ?>')">
                                                                    <i class="fi fi-rr-edit"></i> Edit
                                                                </button>
                                                                <form method="POST" action="" class="dept-action-form" onsubmit="return confirm('Are you sure you want to delete this department?');">
                                                                    <input type="hidden" name="action" value="delete_department">
                                                                    <input type="hidden" name="department_id" value="<?php echo $d['department_id']; ?>">
                                                                    <button type="submit" class="dropdown-action-item item-delete">
                                                                        <i class="fi fi-rr-trash"></i> Delete
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                        <?php
                                            endwhile;
                                        else:
                                            echo '<tr><td colspan="4" class="no-records">No departments added yet.</td></tr>';
                                        endif;
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ===== SECTION 5: APPOINTMENTS MASTER ===== -->
                <section id="sec-appointments" class="admin-section">
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <h2><i class="fi fi-rr-calendar"></i> Master Hospital Appointments Monitor</h2>
                        </div>
                        <div class="profile-card-body">
                            <div style="width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: var(--radius-md);">
                                <table class="admin-table" style="min-width: 850px;">
                                    <thead>
                                        <tr>
                                            <th>S.N.</th>
                                            <th>Patient Name</th>
                                            <th>Doctor Name</th>
                                            <th>Date & Time</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $appsMaster = $conn->query("
                                            SELECT a.*, 
                                                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                                                   CONCAT('Dr. ', d.first_name, ' ', d.last_name) AS doctor_name
                                            FROM tbl_appointment a
                                            LEFT JOIN tbl_patient p ON a.patient_id = p.patient_id
                                            LEFT JOIN tbl_doctor d ON a.doctor_id = d.doctor_id
                                            ORDER BY a.appointment_id DESC
                                        ");
                                        $apptSn = 1;
                                        if ($appsMaster && $appsMaster->num_rows > 0):
                                            while ($ap = $appsMaster->fetch_assoc()):
                                                $st = $ap['status'] ?? 'Pending';
                                                $stClass = 'inactive';
                                                if ($st === 'Approved' || $st === 'Completed') $stClass = 'active';
                                        ?>
                                                <tr>
                                                    <td><strong><?php echo $apptSn++; ?></strong></td>
                                                    <td><strong><?php echo htmlspecialchars($ap['patient_name'] ?: 'Patient'); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($ap['doctor_name'] ?: 'Doctor'); ?></td>
                                                    <td><?php echo htmlspecialchars(($ap['appointment_date'] ?? '—') . ' at ' . ($ap['appointment_time'] ?? '—')); ?></td>
                                                    <td><?php echo htmlspecialchars($ap['reason'] ?? 'Routine Checkup'); ?></td>
                                                    <td><span class="status-badge <?php echo $stClass; ?>"><?php echo htmlspecialchars($st); ?></span></td>
                                                </tr>
                                        <?php
                                            endwhile;
                                        else:
                                            echo '<tr><td colspan="6" class="no-records">No appointments recorded yet.</td></tr>';
                                        endif;
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ===== SECTION 6: ADMIN PROFILE ===== -->
                <section id="sec-profile" class="admin-section">
                    <div style="max-width: 600px;">
                        <div class="profile-card">
                            <div class="profile-card-header">
                                <h2><i class="fi fi-rr-user"></i> Admin Account Info</h2>
                            </div>
                            <div class="profile-card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_profile">

                                    <div class="form-group">
                                        <label class="form-label" for="admin_name">Administrator Name</label>
                                        <input type="text" class="form-input" id="admin_name" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="admin_email">Email Address</label>
                                        <input type="email" class="form-input" id="admin_email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                    </div>

                                    <div style="margin-top: 24px;">
                                        <button type="submit" class="btn-auth btn-auth-primary" style="width: 100%;">
                                            Save Account Details
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ===== SECTION 7: CHANGE PASSWORD ===== -->
                <section id="sec-security" class="admin-section">
                    <div style="max-width: 600px;">
                        <div class="profile-card">
                            <div class="profile-card-header">
                                <h2><i class="fi fi-rr-lock"></i> Change Security Password</h2>
                            </div>
                            <div class="profile-card-body">
                                <form method="POST" action="" novalidate id="changePasswordForm">
                                    <input type="hidden" name="action" value="change_password">

                                    <div class="form-group">
                                        <label class="form-label" for="current_password">Current Password</label>
                                        <?php if (isset($errors['current_password'])): ?>
                                            <div class="field-error" style="color: #ef4444; font-size: 0.82rem; font-weight: 500; margin-bottom: 6px;"><?php echo htmlspecialchars($errors['current_password']); ?></div>
                                        <?php endif; ?>
                                        <div class="password-input-wrapper">
                                            <input type="password" class="form-input <?php echo isset($errors['current_password']) ? 'input-error' : ''; ?>" id="current_password" name="current_password" placeholder="••••••••">
                                            <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('current_password', this)" title="Show / Hide Password">
                                                <i class="fi fi-rr-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="new_password">New Password</label>
                                        <?php if (isset($errors['new_password'])): ?>
                                            <div class="field-error" style="color: #ef4444; font-size: 0.82rem; font-weight: 500; margin-bottom: 6px;"><?php echo htmlspecialchars($errors['new_password']); ?></div>
                                        <?php endif; ?>
                                        <div class="password-input-wrapper">
                                            <input type="password" class="form-input <?php echo isset($errors['new_password']) ? 'input-error' : ''; ?>" id="new_password" name="new_password" placeholder="••••••••">
                                            <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('new_password', this)" title="Show / Hide Password">
                                                <i class="fi fi-rr-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                                        <?php if (isset($errors['confirm_password'])): ?>
                                            <div class="field-error" style="color: #ef4444; font-size: 0.82rem; font-weight: 500; margin-bottom: 6px;"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                                        <?php endif; ?>
                                        <div class="password-input-wrapper">
                                            <input type="password" class="form-input <?php echo isset($errors['confirm_password']) ? 'input-error' : ''; ?>" id="confirm_password" name="confirm_password" placeholder="••••••••">
                                            <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('confirm_password', this)" title="Show / Hide Password">
                                                <i class="fi fi-rr-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div style="margin-top: 24px;">
                                        <button type="submit" class="btn-auth btn-auth-primary" style="width: 100%;">
                                            Update Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </main>
    </div>

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
            <div class="modal-body" id="viewDoctorBody"></div>
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

    <!-- Client Scripts -->
    <script>
        const sectionTitles = {
            'overview': 'Dashboard Overview',
            'doctors': 'Doctor Directory & Verification',
            'patients': 'Patient Directory',
            'departments': 'Department Management',
            'appointments': 'Appointments Master',
            'profile': 'Admin Profile Details',
            'security': 'Change Security Password'
        };

        const breadcrumbSectionTitles = {
            'overview': 'Overview',
            'doctors': 'Doctor Management',
            'patients': 'Patient Directory',
            'departments': 'Departments',
            'appointments': 'Appointments Master',
            'profile': 'Admin Profile',
            'security': 'Change Password'
        };

        function switchAdminSection(secId) {
            document.querySelectorAll('.admin-section').forEach(sec => sec.classList.remove('active'));
            document.querySelectorAll('.admin-nav-item').forEach(item => item.classList.remove('active'));

            const targetSec = document.getElementById('sec-' + secId);
            const targetNav = document.querySelector(`.admin-nav-item[data-section="${secId}"]`);

            if (targetSec) targetSec.classList.add('active');
            if (targetNav) targetNav.classList.add('active');

            const pageTitle = document.getElementById('pageSectionTitle');
            if (pageTitle && sectionTitles[secId]) {
                pageTitle.textContent = sectionTitles[secId];
            }

            const bcSectionText = document.getElementById('breadcrumbAdminSectionText');
            if (bcSectionText && breadcrumbSectionTitles[secId]) {
                bcSectionText.textContent = breadcrumbSectionTitles[secId];
            }

            localStorage.setItem('active_admin_section', secId);

            // Close sidebar on mobile after clicking
            const sidebar = document.getElementById('adminSidebar');
            if (sidebar && window.innerWidth <= 992) {
                sidebar.classList.remove('open');
            }
        }

        function toggleAdminSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            if (sidebar) sidebar.classList.toggle('open');
        }

        function toggleSidebarCollapse() {
            const sidebar = document.getElementById('adminSidebar');
            const main = document.querySelector('.admin-main');

            if (sidebar && main) {
                sidebar.classList.toggle('collapsed');
                main.classList.toggle('sidebar-collapsed');
                const isCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('admin_sidebar_collapsed', isCollapsed ? 'true' : 'false');
            }
        }

        // Restore section on load
        document.addEventListener('DOMContentLoaded', () => {
            <?php if (!empty($submittedAction)): ?>
                <?php if ($submittedAction === 'change_password'): ?>
                    switchAdminSection('security');
                <?php elseif ($submittedAction === 'update_profile'): ?>
                    switchAdminSection('profile');
                <?php elseif (in_array($submittedAction, ['add_department', 'update_department', 'delete_department'])): ?>
                    switchAdminSection('departments');
                <?php else: ?>
                    const savedSec = localStorage.getItem('active_admin_section') || 'overview';
                    switchAdminSection(savedSec);
                <?php endif; ?>
            <?php else: ?>
                const savedSec = localStorage.getItem('active_admin_section') || 'overview';
                switchAdminSection(savedSec);
            <?php endif; ?>

            // Restore collapse state
            if (localStorage.getItem('admin_sidebar_collapsed') === 'true') {
                const sidebar = document.getElementById('adminSidebar');
                const main = document.querySelector('.admin-main');
                if (sidebar) sidebar.classList.add('collapsed');
                if (main) main.classList.add('sidebar-collapsed');
            }

            // Auto-hide alert
            const alert = document.getElementById('successAlert');
            if (alert) {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.style.display = 'none', 300);
                }, 4000);
            }
        });

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

        // Doctor Sub-tab Filter
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

        // Modals Logic
        const updateDeptModal = document.getElementById('updateDeptModal');
        function openUpdateDeptModal(id, name) {
            document.getElementById('update_dept_id').value = id;
            document.getElementById('update_dept_name').value = name;
            if (updateDeptModal) updateDeptModal.classList.add('show');
        }
        function closeUpdateDeptModal() {
            if (updateDeptModal) updateDeptModal.classList.remove('show');
        }

        const viewDoctorModal = document.getElementById('viewDoctorModal');
        const viewDoctorBody = document.getElementById('viewDoctorBody');

        function handleViewClick(btn) {
            try {
                const doc = JSON.parse(btn.getAttribute('data-doctor'));
                openViewDoctorModal(doc);
            } catch (e) {
                console.error("Error parsing doctor data:", e);
            }
        }

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
            if (viewDoctorModal) viewDoctorModal.classList.add('show');
        }

        function closeViewDoctorModal() {
            if (viewDoctorModal) viewDoctorModal.classList.remove('show');
        }

        const editDoctorModal = document.getElementById('editDoctorModal');
        function handleEditClick(btn) {
            try {
                const doc = JSON.parse(btn.getAttribute('data-doctor'));
                openEditDoctorModal(doc);
            } catch (e) {
                console.error("Error parsing doctor data:", e);
            }
        }

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
