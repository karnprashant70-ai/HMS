<?php
session_start();
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

// KPI Statistics Summary
$totalDocs = $conn->query("SELECT COUNT(*) AS total FROM tbl_doctor WHERE is_archived = 0")->fetch_assoc()['total'] ?? 0;
$pendingVerDocs = $conn->query("SELECT COUNT(*) AS total FROM tbl_doctor WHERE verification_status = 'Unverified' AND is_archived = 0")->fetch_assoc()['total'] ?? 0;
$totalPatients = $conn->query("SELECT COUNT(*) AS total FROM tbl_patient")->fetch_assoc()['total'] ?? 0;
$totalDepts = $conn->query("SELECT COUNT(*) AS total FROM tbl_department")->fetch_assoc()['total'] ?? 0;

$appRes = $conn->query("SELECT COUNT(*) AS total FROM tbl_appointment");
$totalAppointments = $appRes ? ($appRes->fetch_assoc()['total'] ?? 0) : 0;

// 1. Fetch Patient Bookings by Department (STRICTLY excluding departments with 0 bookings)
$deptPatientStats = [];
$maxUniquePatients = 0;
$totalUniqueBookedPatients = 0;

$deptStatsSql = "
    SELECT 
        d.department_id,
        d.department_name,
        COUNT(DISTINCT a.patient_id) AS unique_patients,
        COUNT(a.appointment_id) AS total_appointments
    FROM tbl_department d
    INNER JOIN tbl_appointment a ON d.department_id = a.department_id
    INNER JOIN tbl_patient p ON a.patient_id = p.patient_id
    GROUP BY d.department_id, d.department_name
    HAVING unique_patients > 0
    ORDER BY unique_patients DESC, d.department_name ASC
";
$deptStatsRes = $conn->query($deptStatsSql);
if ($deptStatsRes) {
    while ($row = $deptStatsRes->fetch_assoc()) {
        $row['unique_patients'] = intval($row['unique_patients']);
        $row['total_appointments'] = intval($row['total_appointments']);
        if ($row['unique_patients'] > $maxUniquePatients) {
            $maxUniquePatients = $row['unique_patients'];
        }
        $deptPatientStats[] = $row;
    }
}

// Calculate total overall unique patients with bookings
$uniquePatientRes = $conn->query("SELECT COUNT(DISTINCT patient_id) AS total_unique FROM tbl_appointment");
if ($uniquePatientRes && $uRow = $uniquePatientRes->fetch_assoc()) {
    $totalUniqueBookedPatients = intval($uRow['total_unique'] ?? 0);
}

// 2. Deep Analysis Graph: Doctors with Most Appointments
$topDoctorsList = [];
$topDocQuery = "
    SELECT 
        doc.doctor_id,
        doc.first_name,
        doc.middle_name,
        doc.last_name,
        doc.department,
        doc.specialization,
        COUNT(a.appointment_id) AS total_appts,
        SUM(CASE WHEN a.status = 'Completed' THEN 1 ELSE 0 END) AS completed_appts,
        COUNT(DISTINCT a.patient_id) AS unique_patients_seen
    FROM tbl_doctor doc
    INNER JOIN tbl_appointment a ON doc.doctor_id = a.doctor_id
    WHERE doc.is_archived = 0
    GROUP BY doc.doctor_id
    ORDER BY total_appts DESC, completed_appts DESC
    LIMIT 5
";
$topDocRes = $conn->query($topDocQuery);
if ($topDocRes) {
    while ($tRow = $topDocRes->fetch_assoc()) {
        $topDoctorsList[] = $tRow;
    }
}

// 3. Appointment Status Distribution Graph Data
$statusDistribution = [
    'Pending' => 0,
    'Confirmed' => 0,
    'Completed' => 0,
    'Cancelled' => 0
];
$statusDistRes = $conn->query("SELECT status, COUNT(*) AS status_count FROM tbl_appointment GROUP BY status");
if ($statusDistRes) {
    while ($stRow = $statusDistRes->fetch_assoc()) {
        $st = $stRow['status'];
        if (isset($statusDistribution[$st])) {
            $statusDistribution[$st] = intval($stRow['status_count']);
        }
    }
}

// 4. Appointment Trend: Last 7 days (for sparkline/mini chart)
$trendData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime("-$i days"));
    $trendData[$date] = ['label' => $label, 'count' => 0];
}
$trendRes = $conn->query("SELECT DATE(appointment_date) AS d, COUNT(*) AS c FROM tbl_appointment WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY d");
if ($trendRes) {
    while ($tr = $trendRes->fetch_assoc()) {
        if (isset($trendData[$tr['d']])) {
            $trendData[$tr['d']]['count'] = intval($tr['c']);
        }
    }
}
$maxTrend = max(array_column($trendData, 'count'));
if ($maxTrend < 1) $maxTrend = 1;

// 5. Revenue Insights
$revenueRes = $conn->query("SELECT COALESCE(SUM(consultation_fee), 0) AS total_revenue, COALESCE(AVG(consultation_fee), 0) AS avg_fee FROM tbl_appointment WHERE status IN ('Completed', 'Confirmed')");
$revenueData = $revenueRes ? $revenueRes->fetch_assoc() : ['total_revenue' => 0, 'avg_fee' => 0];
$totalRevenue = floatval($revenueData['total_revenue']);
$avgFee = floatval($revenueData['avg_fee']);

// 6. Top rated doctors (from tbl_rating)
$topRatedDocs = [];
$ratingQuery = "
    SELECT doc.first_name, doc.last_name, doc.department, doc.profile_photo,
           ROUND(AVG(r.rating_stars), 1) AS avg_rating, COUNT(r.rating_id) AS review_count
    FROM tbl_rating r
    INNER JOIN tbl_doctor doc ON r.doctor_id = doc.doctor_id
    WHERE doc.is_archived = 0
    GROUP BY doc.doctor_id
    HAVING review_count >= 1
    ORDER BY avg_rating DESC, review_count DESC
    LIMIT 3
";
$ratingRes = $conn->query($ratingQuery);
if ($ratingRes) {
    while ($rr = $ratingRes->fetch_assoc()) {
        $topRatedDocs[] = $rr;
    }
}

// 7. Busiest Day of Week
$busiestDay = 'N/A';
$busiestDayCount = 0;
$dayRes = $conn->query("SELECT DAYNAME(appointment_date) AS day_name, COUNT(*) AS cnt FROM tbl_appointment GROUP BY day_name ORDER BY cnt DESC LIMIT 1");
if ($dayRes && $dRow = $dayRes->fetch_assoc()) {
    $busiestDay = $dRow['day_name'];
    $busiestDayCount = intval($dRow['cnt']);
}

// 8. Recent Activity Feed (last 5 appointments)
$recentActivity = [];
$actRes = $conn->query("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, a.created_at,
           CONCAT('Dr. ', doc.first_name, ' ', doc.last_name) AS doctor_name,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
           doc.department
    FROM tbl_appointment a
    INNER JOIN tbl_doctor doc ON a.doctor_id = doc.doctor_id
    INNER JOIN tbl_patient p ON a.patient_id = p.patient_id
    ORDER BY a.created_at DESC
    LIMIT 5
");
if ($actRes) {
    while ($ar = $actRes->fetch_assoc()) {
        $recentActivity[] = $ar;
    }
}

// 9. Completion rate
$completionRate = ($totalAppointments > 0) ? round(($statusDistribution['Completed'] / $totalAppointments) * 100) : 0;

// 10. Today's appointments
$todayAppts = 0;
$todayRes = $conn->query("SELECT COUNT(*) AS cnt FROM tbl_appointment WHERE appointment_date = CURDATE()");
if ($todayRes && $tRow = $todayRes->fetch_assoc()) {
    $todayAppts = intval($tRow['cnt']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Hospital Management System</title>
    <link rel="stylesheet" href="../css/index/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin-profile.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin-sidebar.css?v=<?php echo time(); ?>">
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
                        <h1 id="dashboardGreeting" style="font-size: clamp(1.1rem, 1rem + 0.5vw, 1.4rem); font-weight: 700; color: var(--text-primary); margin-top: 4px; display: block;">Welcome back, <?php echo htmlspecialchars($admin['name'] ?? 'Admin'); ?> 👋</h1>
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



                <!-- ===== SECTION 1: DASHBOARD OVERVIEW ===== -->
                <section id="sec-overview" class="admin-section active">

                    <style>
                        /* ===== Premium Dashboard Analytics Styles ===== */
                        .analytics-grid { display: grid; gap: 24px; margin-bottom: 28px; }
                        .analytics-grid.cols-4 { grid-template-columns: repeat(4, 1fr); }
                        .analytics-grid.cols-2 { grid-template-columns: repeat(2, 1fr); }
                        .analytics-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
                        .analytics-grid.cols-2-1 { grid-template-columns: 2fr 1fr; }
                        .analytics-grid.cols-1-1 { grid-template-columns: 1fr 1fr; }

                        /* KPI Hero Cards */
                        .kpi-card {
                            position: relative;
                            background: rgba(255, 255, 255, 0.92);
                            backdrop-filter: blur(20px);
                            border: 1px solid rgba(255, 255, 255, 0.5);
                            border-radius: 20px;
                            padding: 24px;
                            overflow: hidden;
                            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.03);
                        }
                        .kpi-card:hover {
                            transform: translateY(-4px);
                            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08), 0 4px 12px rgba(0, 0, 0, 0.04);
                            border-color: rgba(91, 84, 224, 0.2);
                        }
                        .kpi-card::before {
                            content: '';
                            position: absolute;
                            top: 0; left: 0; right: 0;
                            height: 3px;
                            border-radius: 20px 20px 0 0;
                        }
                        .kpi-card.kpi-purple::before { background: linear-gradient(135deg, #6366F1, #8B5CF6); }
                        .kpi-card.kpi-emerald::before { background: linear-gradient(135deg, #10B981, #059669); }
                        .kpi-card.kpi-blue::before { background: linear-gradient(135deg, #3B82F6, #2563EB); }
                        .kpi-card.kpi-amber::before { background: linear-gradient(135deg, #F59E0B, #D97706); }
                        .kpi-icon {
                            width: 48px; height: 48px; border-radius: 14px;
                            display: flex; align-items: center; justify-content: center;
                            font-size: 1.3rem; color: white;
                            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                        }
                        .kpi-icon.icon-purple { background: linear-gradient(135deg, #6366F1, #8B5CF6); }
                        .kpi-icon.icon-emerald { background: linear-gradient(135deg, #10B981, #059669); }
                        .kpi-icon.icon-blue { background: linear-gradient(135deg, #3B82F6, #2563EB); }
                        .kpi-icon.icon-amber { background: linear-gradient(135deg, #F59E0B, #D97706); }
                        .kpi-value {
                            font-size: 2rem; font-weight: 800; color: var(--text-primary);
                            line-height: 1; margin: 12px 0 4px;
                            font-feature-settings: 'tnum';
                        }
                        .kpi-label { font-size: 0.82rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
                        .kpi-sub { font-size: 0.76rem; color: var(--text-muted); margin-top: 8px; display: flex; align-items: center; gap: 4px; }
                        .kpi-sub .trend-up { color: #10B981; font-weight: 700; }
                        .kpi-sub .trend-neutral { color: var(--text-muted); font-weight: 600; }

                        /* Sparkline mini bar chart */
                        .sparkline { display: flex; align-items: flex-end; gap: 3px; height: 32px; }
                        .sparkline-bar {
                            flex: 1; border-radius: 3px; min-width: 4px;
                            transition: height 0.6s cubic-bezier(0.4, 0, 0.2, 1);
                            opacity: 0.7;
                        }
                        .sparkline-bar:last-child { opacity: 1; }

                        /* Analytics Card */
                        .analytics-card {
                            background: rgba(255, 255, 255, 0.92);
                            backdrop-filter: blur(20px);
                            border: 1px solid rgba(255, 255, 255, 0.5);
                            border-radius: 20px;
                            overflow: hidden;
                            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
                            transition: all 0.3s ease;
                        }
                        .analytics-card:hover {
                            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.07);
                            border-color: rgba(91, 84, 224, 0.15);
                        }
                        .analytics-card-header {
                            padding: 20px 24px 16px;
                            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
                        }
                        .analytics-card-header h3 {
                            font-size: 1rem; font-weight: 800; color: var(--text-primary);
                            display: flex; align-items: center; gap: 10px; margin: 0;
                        }
                        .analytics-card-header h3 i { color: var(--primary); font-size: 1.1rem; }
                        .analytics-card-header .badge {
                            font-size: 0.72rem; font-weight: 700; padding: 4px 10px; border-radius: 20px;
                            text-transform: uppercase; letter-spacing: 0.3px;
                        }
                        .analytics-card-body { padding: 0 24px 24px; }

                        /* Donut Chart (pure CSS) */
                        .donut-container { display: flex; align-items: center; gap: 32px; flex-wrap: wrap; justify-content: center; }
                        .donut-chart {
                            position: relative; width: 180px; height: 180px; border-radius: 50%;
                            display: flex; align-items: center; justify-content: center;
                            flex-shrink: 0;
                        }
                        .donut-center {
                            position: absolute; width: 110px; height: 110px; border-radius: 50%;
                            background: rgba(255, 255, 255, 0.95);
                            display: flex; flex-direction: column; align-items: center; justify-content: center;
                            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
                            z-index: 2;
                        }
                        .donut-center .donut-total { font-size: 1.6rem; font-weight: 800; color: var(--text-primary); line-height: 1; }
                        .donut-center .donut-label { font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
                        .donut-legend { display: flex; flex-direction: column; gap: 12px; flex: 1; min-width: 160px; }
                        .donut-legend-item {
                            display: flex; align-items: center; justify-content: space-between;
                            padding: 10px 14px; border-radius: 12px; transition: all 0.2s ease;
                        }
                        .donut-legend-item:hover { background: rgba(0, 0, 0, 0.02); }
                        .donut-legend-item .legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
                        .donut-legend-item .legend-name { font-size: 0.84rem; font-weight: 600; color: var(--text-primary); margin-left: 10px; flex: 1; }
                        .donut-legend-item .legend-value { font-size: 0.84rem; font-weight: 800; color: var(--text-primary); }
                        .donut-legend-item .legend-pct { font-size: 0.72rem; font-weight: 600; color: var(--text-muted); margin-left: 6px; }

                        /* Ranking bar */
                        .rank-row {
                            display: flex; align-items: center; gap: 14px; padding: 14px 0;
                            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
                            transition: background 0.2s ease;
                        }
                        .rank-row:last-child { border-bottom: none; }
                        .rank-row:hover { background: rgba(91, 84, 224, 0.02); margin: 0 -24px; padding: 14px 24px; border-radius: 12px; }
                        .rank-medal {
                            width: 36px; height: 36px; border-radius: 50%; display: flex;
                            align-items: center; justify-content: center; font-weight: 900;
                            font-size: 0.8rem; flex-shrink: 0; color: white;
                        }
                        .rank-medal.gold { background: linear-gradient(135deg, #F59E0B, #D97706); box-shadow: 0 3px 8px rgba(245, 158, 11, 0.3); }
                        .rank-medal.silver { background: linear-gradient(135deg, #94A3B8, #64748B); box-shadow: 0 3px 8px rgba(148, 163, 184, 0.3); }
                        .rank-medal.bronze { background: linear-gradient(135deg, #B45309, #92400E); box-shadow: 0 3px 8px rgba(180, 83, 9, 0.3); }
                        .rank-medal.default { background: linear-gradient(135deg, #CBD5E1, #94A3B8); box-shadow: 0 3px 8px rgba(203, 213, 225, 0.3); }
                        .rank-info { flex: 1; min-width: 0; }
                        .rank-name { font-size: 0.9rem; font-weight: 700; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                        .rank-dept { font-size: 0.76rem; color: var(--text-muted); font-weight: 500; }
                        .rank-stats { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; min-width: 120px; }
                        .rank-bar-track { width: 100%; height: 6px; background: rgba(0, 0, 0, 0.05); border-radius: 3px; overflow: hidden; }
                        .rank-bar-fill { height: 100%; border-radius: 3px; transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1); }

                        /* Activity feed */
                        .activity-item {
                            display: flex; align-items: flex-start; gap: 14px; padding: 14px 0;
                            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
                        }
                        .activity-item:last-child { border-bottom: none; }
                        .activity-dot {
                            width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; margin-top: 5px;
                            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.04);
                        }
                        .activity-dot.dot-completed { background: #10B981; }
                        .activity-dot.dot-confirmed { background: #3B82F6; }
                        .activity-dot.dot-pending { background: #F59E0B; }
                        .activity-dot.dot-cancelled { background: #EF4444; }

                        /* Dept bar styles */
                        .dept-bar-row {
                            display: flex; align-items: center; gap: 16px; padding: 8px 0;
                        }
                        .dept-bar-label { width: 140px; font-size: 0.84rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex-shrink: 0; }
                        .dept-bar-track { flex: 1; height: 20px; background: rgba(0, 0, 0, 0.04); border-radius: 6px; overflow: hidden; }
                        .dept-bar-fill-inner { height: 100%; border-radius: 6px; transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; padding-left: 8px; }
                        .dept-bar-fill-inner span { font-size: 0.72rem; font-weight: 700; color: white; }
                        .dept-bar-value { width: 70px; text-align: right; font-size: 0.82rem; font-weight: 800; color: var(--text-primary); flex-shrink: 0; }

                        /* Responsive */
                        @media (max-width: 1200px) {
                            .analytics-grid.cols-4 { grid-template-columns: repeat(2, 1fr); }
                            .analytics-grid.cols-2-1, .analytics-grid.cols-1-1, .analytics-grid.cols-3 { grid-template-columns: 1fr; }
                        }
                        @media (max-width: 640px) {
                            .analytics-grid.cols-4 { grid-template-columns: 1fr; }
                            .donut-container { flex-direction: column; align-items: center; }
                            .rank-row:hover { margin: 0; padding: 14px 0; }
                            .dept-bar-label { width: 100px; font-size: 0.78rem; }
                        }
                    </style>

                    <!-- ====== ROW 1: KPI Hero Cards ====== -->
                    <div class="analytics-grid cols-4">
                        <!-- Active Doctors -->
                        <div class="kpi-card kpi-purple">
                            <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                                <div>
                                    <div class="kpi-label">Active Doctors</div>
                                    <div class="kpi-value"><?php echo $totalDocs; ?></div>
                                    <div class="kpi-sub">
                                        <span class="trend-neutral"><i class="fi fi-rr-shield-check" style="font-size: 0.7rem;"></i></span>
                                        <span><?php echo ($totalDocs - $pendingVerDocs); ?> verified</span>
                                    </div>
                                </div>
                                <div class="kpi-icon icon-purple"><i class="fi fi-rr-stethoscope"></i></div>
                            </div>
                        </div>

                        <!-- Registered Patients -->
                        <div class="kpi-card kpi-emerald">
                            <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                                <div>
                                    <div class="kpi-label">Registered Patients</div>
                                    <div class="kpi-value"><?php echo $totalPatients; ?></div>
                                    <div class="kpi-sub">
                                        <span class="trend-up"><i class="fi fi-rr-users-alt" style="font-size: 0.7rem;"></i></span>
                                        <span><?php echo $totalUniqueBookedPatients; ?> have booked</span>
                                    </div>
                                </div>
                                <div class="kpi-icon icon-emerald"><i class="fi fi-rr-users-alt"></i></div>
                            </div>
                        </div>

                        <!-- Total Appointments -->
                        <div class="kpi-card kpi-blue">
                            <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                                <div>
                                    <div class="kpi-label">Total Appointments</div>
                                    <div class="kpi-value"><?php echo $totalAppointments; ?></div>
                                    <div class="kpi-sub">
                                        <span class="trend-up"><?php echo $todayAppts; ?></span>
                                        <span>scheduled today</span>
                                    </div>
                                </div>
                                <div class="kpi-icon icon-blue"><i class="fi fi-rr-calendar"></i></div>
                            </div>
                            <!-- Mini sparkline -->
                            <div class="sparkline" style="margin-top: 14px;">
                                <?php foreach ($trendData as $td): 
                                    $bh = max(4, round(($td['count'] / $maxTrend) * 32));
                                ?>
                                    <div class="sparkline-bar" style="height: <?php echo $bh; ?>px; background: linear-gradient(to top, #3B82F6, #60A5FA);" title="<?php echo $td['label']; ?>: <?php echo $td['count']; ?>"></div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Revenue -->
                        <div class="kpi-card kpi-amber">
                            <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                                <div>
                                    <div class="kpi-label">Total Revenue</div>
                                    <div class="kpi-value" style="font-size: 1.6rem;">Rs. <?php echo number_format($totalRevenue, 0); ?></div>
                                    <div class="kpi-sub">
                                        <span class="trend-neutral">Avg</span>
                                        <span>Rs. <?php echo number_format($avgFee, 0); ?> / consult</span>
                                    </div>
                                </div>
                                <div class="kpi-icon icon-amber"><i class="fi fi-rr-money-bill-wave"></i></div>
                            </div>
                        </div>
                    </div>

                    <?php if ($pendingVerDocs > 0): ?>
                        <div style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.08), rgba(245, 158, 11, 0.03)); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 16px; padding: 18px 24px; margin-bottom: 28px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                            <div style="display: flex; align-items: center; gap: 14px;">
                                <div style="width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, #F59E0B, #D97706); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 3px 8px rgba(245, 158, 11, 0.3);">
                                    <i class="fi fi-rr-bell-ring"></i>
                                </div>
                                <div>
                                    <strong style="font-size: 0.92rem; color: #92400E;"><?php echo $pendingVerDocs; ?> doctor(s) awaiting verification</strong>
                                    <p style="font-size: 0.78rem; color: #B45309; margin-top: 1px;">Review and approve new registrations to activate their profiles</p>
                                </div>
                            </div>
                            <button type="button" onclick="switchAdminSection('doctors')" style="padding: 8px 18px; background: linear-gradient(135deg, #F59E0B, #D97706); color: white; border: none; border-radius: 10px; font-weight: 700; font-size: 0.82rem; cursor: pointer; box-shadow: 0 3px 8px rgba(245, 158, 11, 0.25); transition: all 0.2s ease;">
                                Review Now <i class="fi fi-rr-arrow-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- ====== ROW 2: Status Donut + 7-Day Trend ====== -->
                    <div class="analytics-grid cols-1-1">
                        <!-- Appointment Status Donut Chart -->
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h3><i class="fi fi-rr-chart-pie-alt"></i> Appointment Status</h3>
                                <span class="badge" style="background: rgba(91, 84, 224, 0.1); color: var(--primary);">Distribution</span>
                            </div>
                            <div class="analytics-card-body">
                                <?php 
                                $totApptDist = array_sum($statusDistribution);
                                $pCompleted = ($totApptDist > 0) ? round(($statusDistribution['Completed'] / $totApptDist) * 100) : 0;
                                $pConfirmed = ($totApptDist > 0) ? round(($statusDistribution['Confirmed'] / $totApptDist) * 100) : 0;
                                $pPending = ($totApptDist > 0) ? round(($statusDistribution['Pending'] / $totApptDist) * 100) : 0;
                                $pCancelled = ($totApptDist > 0) ? round(($statusDistribution['Cancelled'] / $totApptDist) * 100) : 0;

                                // Conic gradient angles
                                $a1 = $pCompleted * 3.6;
                                $a2 = $a1 + ($pConfirmed * 3.6);
                                $a3 = $a2 + ($pPending * 3.6);
                                ?>
                                <div class="donut-container">
                                    <div class="donut-chart" style="background: conic-gradient(
                                        #10B981 0deg <?php echo $a1; ?>deg, 
                                        #3B82F6 <?php echo $a1; ?>deg <?php echo $a2; ?>deg, 
                                        #F59E0B <?php echo $a2; ?>deg <?php echo $a3; ?>deg, 
                                        #EF4444 <?php echo $a3; ?>deg 360deg
                                    );">
                                        <div class="donut-center">
                                            <div class="donut-total"><?php echo $totApptDist; ?></div>
                                            <div class="donut-label">Total</div>
                                        </div>
                                    </div>
                                    <div class="donut-legend">
                                        <div class="donut-legend-item">
                                            <div style="display: flex; align-items: center;">
                                                <span class="legend-dot" style="background: #10B981;"></span>
                                                <span class="legend-name">Completed</span>
                                            </div>
                                            <div>
                                                <span class="legend-value"><?php echo $statusDistribution['Completed']; ?></span>
                                                <span class="legend-pct">(<?php echo $pCompleted; ?>%)</span>
                                            </div>
                                        </div>
                                        <div class="donut-legend-item">
                                            <div style="display: flex; align-items: center;">
                                                <span class="legend-dot" style="background: #3B82F6;"></span>
                                                <span class="legend-name">Confirmed</span>
                                            </div>
                                            <div>
                                                <span class="legend-value"><?php echo $statusDistribution['Confirmed']; ?></span>
                                                <span class="legend-pct">(<?php echo $pConfirmed; ?>%)</span>
                                            </div>
                                        </div>
                                        <div class="donut-legend-item">
                                            <div style="display: flex; align-items: center;">
                                                <span class="legend-dot" style="background: #F59E0B;"></span>
                                                <span class="legend-name">Pending</span>
                                            </div>
                                            <div>
                                                <span class="legend-value"><?php echo $statusDistribution['Pending']; ?></span>
                                                <span class="legend-pct">(<?php echo $pPending; ?>%)</span>
                                            </div>
                                        </div>
                                        <div class="donut-legend-item">
                                            <div style="display: flex; align-items: center;">
                                                <span class="legend-dot" style="background: #EF4444;"></span>
                                                <span class="legend-name">Cancelled</span>
                                            </div>
                                            <div>
                                                <span class="legend-value"><?php echo $statusDistribution['Cancelled']; ?></span>
                                                <span class="legend-pct">(<?php echo $pCancelled; ?>%)</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 7-Day Appointment Trend -->
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h3><i class="fi fi-rr-chart-line-up"></i> 7-Day Appointment Trend</h3>
                                <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: #3B82F6;">Last 7 Days</span>
                            </div>
                            <div class="analytics-card-body">
                                <!-- Visual bar chart -->
                                <div style="display: flex; align-items: flex-end; gap: 10px; height: 160px; padding-bottom: 28px; position: relative;">
                                    <?php foreach ($trendData as $date => $td): 
                                        $barH = max(8, round(($td['count'] / $maxTrend) * 130));
                                        $isToday = ($date === date('Y-m-d'));
                                    ?>
                                        <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px;">
                                            <span style="font-size: 0.72rem; font-weight: 800; color: <?php echo $isToday ? 'var(--primary)' : 'var(--text-muted)'; ?>;"><?php echo $td['count']; ?></span>
                                            <div style="width: 100%; max-width: 40px; height: <?php echo $barH; ?>px; border-radius: 8px 8px 4px 4px; background: <?php echo $isToday ? 'linear-gradient(to top, #6366F1, #818CF8)' : 'linear-gradient(to top, rgba(99, 102, 241, 0.25), rgba(99, 102, 241, 0.45))'; ?>; transition: height 0.6s ease;"></div>
                                            <span style="font-size: 0.7rem; font-weight: <?php echo $isToday ? '800' : '600'; ?>; color: <?php echo $isToday ? 'var(--primary)' : 'var(--text-muted)'; ?>; position: absolute; bottom: 0;"><?php echo $td['label']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Quick stat pills below chart -->
                                <div style="display: flex; gap: 12px; margin-top: 16px; flex-wrap: wrap;">
                                    <div style="flex: 1; min-width: 120px; background: rgba(99, 102, 241, 0.06); padding: 12px 14px; border-radius: 12px; text-align: center;">
                                        <div style="font-size: 1.2rem; font-weight: 800; color: var(--primary);"><?php echo $completionRate; ?>%</div>
                                        <div style="font-size: 0.72rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.3px;">Completion Rate</div>
                                    </div>
                                    <div style="flex: 1; min-width: 120px; background: rgba(245, 158, 11, 0.06); padding: 12px 14px; border-radius: 12px; text-align: center;">
                                        <div style="font-size: 1.2rem; font-weight: 800; color: #D97706;"><?php echo $busiestDay; ?></div>
                                        <div style="font-size: 0.72rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.3px;">Busiest Day</div>
                                    </div>
                                    <div style="flex: 1; min-width: 120px; background: rgba(16, 185, 129, 0.06); padding: 12px 14px; border-radius: 12px; text-align: center;">
                                        <div style="font-size: 1.2rem; font-weight: 800; color: #059669;"><?php echo $totalDepts; ?></div>
                                        <div style="font-size: 0.72rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.3px;">Departments</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ====== ROW 3: Top Doctors Ranking + Department Distribution ====== -->
                    <div class="analytics-grid cols-1-1">
                        <!-- Top Doctors Leaderboard -->
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h3><i class="fi fi-rr-trophy"></i> Top Doctors Leaderboard</h3>
                                <span class="badge" style="background: rgba(245, 158, 11, 0.1); color: #D97706;">By Appointments</span>
                            </div>
                            <div class="analytics-card-body">
                                <?php if (!empty($topDoctorsList)):
                                    $maxDocAppts = max(array_column($topDoctorsList, 'total_appts'));
                                    $medalClasses = ['gold', 'silver', 'bronze', 'default', 'default'];
                                ?>
                                    <?php foreach ($topDoctorsList as $idx => $tDoc): 
                                        $docName = 'Dr. ' . trim($tDoc['first_name'] . ' ' . ($tDoc['middle_name'] ?? '') . ' ' . $tDoc['last_name']);
                                        $totalA = intval($tDoc['total_appts']);
                                        $completedA = intval($tDoc['completed_appts']);
                                        $uniqueP = intval($tDoc['unique_patients_seen']);
                                        $compRate = ($totalA > 0) ? round(($completedA / $totalA) * 100) : 0;
                                        $barW = ($maxDocAppts > 0) ? max(8, round(($totalA / $maxDocAppts) * 100)) : 0;
                                    ?>
                                        <div class="rank-row">
                                            <div class="rank-medal <?php echo $medalClasses[$idx] ?? 'default'; ?>">
                                                <?php echo $idx + 1; ?>
                                            </div>
                                            <div class="rank-info">
                                                <div class="rank-name"><?php echo htmlspecialchars($docName); ?></div>
                                                <div class="rank-dept">
                                                    <?php echo htmlspecialchars($tDoc['department']); ?> · <?php echo $uniqueP; ?> patient<?php echo $uniqueP === 1 ? '' : 's'; ?> · <?php echo $compRate; ?>% completion
                                                </div>
                                            </div>
                                            <div class="rank-stats">
                                                <div style="font-size: 0.88rem; font-weight: 800; color: var(--text-primary);"><?php echo $totalA; ?> <span style="font-size: 0.72rem; font-weight: 500; color: var(--text-muted);">appts</span></div>
                                                <div class="rank-bar-track">
                                                    <div class="rank-bar-fill" style="width: <?php echo $barW; ?>%; background: linear-gradient(135deg, #6366F1, #818CF8);"></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p style="text-align: center; color: var(--text-muted); padding: 24px; font-size: 0.88rem;">No appointment data available for doctor ranking.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Patient Bookings by Department -->
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h3><i class="fi fi-rr-building"></i> Department Distribution</h3>
                                <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #059669;"><?php echo $totalUniqueBookedPatients; ?> Patients</span>
                            </div>
                            <div class="analytics-card-body">
                                <?php if (!empty($deptPatientStats)):
                                    $deptColors = ['#6366F1', '#10B981', '#3B82F6', '#8B5CF6', '#F59E0B', '#EC4899', '#06B6D4', '#F97316'];
                                    $deptColorCount = count($deptColors);
                                ?>
                                    <div style="display: flex; flex-direction: column; gap: 10px;">
                                        <?php foreach ($deptPatientStats as $idx => $stat): 
                                            $deptName = htmlspecialchars($stat['department_name']);
                                            $uCount = intval($stat['unique_patients']);
                                            if ($uCount <= 0) continue;
                                            $bP = ($maxUniquePatients > 0) ? max(8, round(($uCount / $maxUniquePatients) * 100)) : 0;
                                            $dColor = $deptColors[$idx % $deptColorCount];
                                        ?>
                                            <div class="dept-bar-row">
                                                <div class="dept-bar-label" title="<?php echo $deptName; ?>">
                                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?php echo $dColor; ?>; margin-right: 8px; flex-shrink: 0;"></span>
                                                    <?php echo $deptName; ?>
                                                </div>
                                                <div class="dept-bar-track">
                                                    <div class="dept-bar-fill-inner" style="width: <?php echo $bP; ?>%; background: <?php echo $dColor; ?>;">
                                                        <?php if ($bP >= 18): ?>
                                                            <span><?php echo $uCount; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="dept-bar-value"><?php echo $uCount; ?> <span style="font-weight: 500; font-size: 0.72rem; color: var(--text-muted);">pts</span></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="text-align: center; color: var(--text-muted); padding: 24px; font-size: 0.88rem;">No department booking data yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ====== ROW 4: Recent Activity Feed + Top Rated Doctors ====== -->
                    <div class="analytics-grid cols-2-1">
                        <!-- Recent Activity Feed -->
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h3><i class="fi fi-rr-time-past"></i> Recent Activity</h3>
                                <span class="badge" style="background: rgba(99, 102, 241, 0.1); color: #6366F1;">Live Feed</span>
                            </div>
                            <div class="analytics-card-body">
                                <?php if (!empty($recentActivity)): ?>
                                    <?php foreach ($recentActivity as $act): 
                                        $statusLC = strtolower($act['status']);
                                        $dotClass = 'dot-' . $statusLC;
                                        $statusColors = ['completed' => '#10B981', 'confirmed' => '#3B82F6', 'pending' => '#F59E0B', 'cancelled' => '#EF4444'];
                                        $sColor = $statusColors[$statusLC] ?? '#94A3B8';
                                        $timeAgo = '';
                                        $diff = time() - strtotime($act['created_at']);
                                        if ($diff < 3600) $timeAgo = round($diff / 60) . 'm ago';
                                        elseif ($diff < 86400) $timeAgo = round($diff / 3600) . 'h ago';
                                        else $timeAgo = round($diff / 86400) . 'd ago';
                                    ?>
                                        <div class="activity-item">
                                            <div class="activity-dot <?php echo $dotClass; ?>"></div>
                                            <div style="flex: 1; min-width: 0;">
                                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap;">
                                                    <div>
                                                        <strong style="font-size: 0.86rem; color: var(--text-primary);"><?php echo htmlspecialchars($act['patient_name']); ?></strong>
                                                        <span style="font-size: 0.78rem; color: var(--text-muted);"> → <?php echo htmlspecialchars($act['doctor_name']); ?></span>
                                                    </div>
                                                    <span style="font-size: 0.7rem; font-weight: 600; color: var(--text-muted); white-space: nowrap;"><?php echo $timeAgo; ?></span>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                                    <span style="font-size: 0.72rem; font-weight: 700; color: <?php echo $sColor; ?>; background: <?php echo $sColor; ?>15; padding: 2px 8px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.3px;"><?php echo $act['status']; ?></span>
                                                    <span style="font-size: 0.72rem; color: var(--text-muted);"><?php echo htmlspecialchars($act['department']); ?> · <?php echo date('M d', strtotime($act['appointment_date'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p style="text-align: center; color: var(--text-muted); padding: 20px;">No recent appointment activity.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- System Health / Quick Stats -->
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h3><i class="fi fi-rr-pulse"></i> System Health</h3>
                            </div>
                            <div class="analytics-card-body">
                                <!-- Completion Rate Ring -->
                                <div style="text-align: center; margin-bottom: 20px;">
                                    <div style="position: relative; width: 100px; height: 100px; margin: 0 auto; border-radius: 50%; background: conic-gradient(#10B981 0deg <?php echo $completionRate * 3.6; ?>deg, rgba(0,0,0,0.06) <?php echo $completionRate * 3.6; ?>deg 360deg);">
                                        <div style="position: absolute; inset: 10px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                                            <span style="font-size: 1.3rem; font-weight: 800; color: var(--text-primary);"><?php echo $completionRate; ?>%</span>
                                            <span style="font-size: 0.6rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Complete</span>
                                        </div>
                                    </div>
                                </div>

                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: rgba(99, 102, 241, 0.05); border-radius: 10px;">
                                        <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-primary);"><i class="fi fi-rr-calendar-day" style="color: var(--primary); margin-right: 6px;"></i>Today's Slots</span>
                                        <strong style="font-size: 0.88rem; color: var(--primary);"><?php echo $todayAppts; ?></strong>
                                    </div>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: rgba(245, 158, 11, 0.05); border-radius: 10px;">
                                        <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-primary);"><i class="fi fi-rr-time-quarter-to" style="color: #D97706; margin-right: 6px;"></i>Peak Day</span>
                                        <strong style="font-size: 0.88rem; color: #D97706;"><?php echo $busiestDay; ?></strong>
                                    </div>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: rgba(16, 185, 129, 0.05); border-radius: 10px;">
                                        <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-primary);"><i class="fi fi-rr-shield-check" style="color: #059669; margin-right: 6px;"></i>Verified Doctors</span>
                                        <strong style="font-size: 0.88rem; color: #059669;"><?php echo ($totalDocs - $pendingVerDocs); ?> / <?php echo $totalDocs; ?></strong>
                                    </div>
                                    <?php if (!empty($topRatedDocs)): 
                                        $topR = $topRatedDocs[0];
                                    ?>
                                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: rgba(236, 72, 153, 0.05); border-radius: 10px;">
                                            <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-primary);"><i class="fi fi-rr-star" style="color: #EC4899; margin-right: 6px;"></i>Top Rated</span>
                                            <div style="text-align: right;">
                                                <strong style="font-size: 0.82rem; color: #EC4899;">Dr. <?php echo htmlspecialchars($topR['first_name'] . ' ' . $topR['last_name']); ?></strong>
                                                <div style="font-size: 0.7rem; color: var(--text-muted);">⭐ <?php echo $topR['avg_rating']; ?> (<?php echo $topR['review_count']; ?> reviews)</div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
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
                            <?php if (!empty($success) && in_array($submittedAction, ['toggle_verification', 'edit_doctor_admin', 'archive_doctor', 'restore_doctor'])): ?>
                                <div class="hms-success-alert" style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10B981; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 8px; font-size: 0.88rem;">
                                    <i class="fi fi-rr-check-circle" style="font-size: 1.1rem;"></i>
                                    <span><?php echo htmlspecialchars($success); ?></span>
                                </div>
                            <?php endif; ?>
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
                            <?php if (!empty($success) && in_array($submittedAction, ['add_department', 'update_department', 'delete_department'])): ?>
                                <div class="hms-success-alert" style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10B981; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 8px; font-size: 0.88rem;">
                                    <i class="fi fi-rr-check-circle" style="font-size: 1.1rem;"></i>
                                    <span><?php echo htmlspecialchars($success); ?></span>
                                </div>
                            <?php endif; ?>
                            <form method="POST" action="" novalidate>
                                <input type="hidden" name="action" value="add_department">
                                <div class="form-group-row">
                                    <div class="form-group">
                                        <label class="form-label" for="department_name">Department Name</label>
                                        <input type="text" class="form-input" id="department_name" name="department_name" placeholder="e.g. Cardiology, Neurology, Pediatrics">
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
                    <div class="admin-form-container" style="max-width: 520px; margin: 30px auto; width: 100%;">
                        <div class="profile-card">
                            <div class="profile-card-header">
                                <h2><i class="fi fi-rr-user"></i> Admin Account Info</h2>
                            </div>
                            <div class="profile-card-body">
                                <?php if (!empty($success) && $submittedAction === 'update_profile'): ?>
                                    <div class="hms-success-alert" style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10B981; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 8px; font-size: 0.88rem;">
                                        <i class="fi fi-rr-check-circle" style="font-size: 1.1rem;"></i>
                                        <span><?php echo htmlspecialchars($success); ?></span>
                                    </div>
                                <?php endif; ?>
                                <form method="POST" action="" novalidate>
                                    <input type="hidden" name="action" value="update_profile">

                                    <div class="form-group">
                                        <label class="form-label" for="admin_name">Administrator Name</label>
                                        <input type="text" class="form-input" id="admin_name" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="admin_email">Email Address</label>
                                        <input type="email" class="form-input" id="admin_email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>">
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
                    <div class="admin-form-container" style="max-width: 520px; margin: 30px auto; width: 100%;">
                        <div class="profile-card">
                            <div class="profile-card-header">
                                <h2><i class="fi fi-rr-lock"></i> Change Security Password</h2>
                            </div>
                            <div class="profile-card-body">
                                <?php if (!empty($success) && $submittedAction === 'change_password'): ?>
                                    <div class="hms-success-alert" style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10B981; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 8px; font-size: 0.88rem;">
                                        <i class="fi fi-rr-check-circle" style="font-size: 1.1rem;"></i>
                                        <span><?php echo htmlspecialchars($success); ?></span>
                                    </div>
                                <?php endif; ?>
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
                        <input type="text" class="form-input" id="update_dept_name" name="department_name">
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
                <form method="POST" action="" id="editDoctorForm" novalidate>
                    <input type="hidden" name="action" value="edit_doctor_admin">
                    <input type="hidden" name="doctor_id" id="edit_doc_id">

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px;">
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-input" name="first_name" id="edit_doc_first_name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-input" name="middle_name" id="edit_doc_middle_name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-input" name="last_name" id="edit_doc_last_name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-input" name="email" id="edit_doc_email">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="text" class="form-input" name="phone_number" id="edit_doc_phone">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <select class="form-input" name="department" id="edit_doc_department">
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
                            <input type="text" class="form-input" name="specialization" id="edit_doc_specialization">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Qualification</label>
                            <input type="text" class="form-input" name="qualification" id="edit_doc_qualification">
                        </div>
                        <div class="form-group">
                            <label class="form-label">NMC Licence Number</label>
                            <input type="text" class="form-input" name="licence_number" id="edit_doc_licence">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Years of Experience</label>
                            <select class="form-input" name="years_experience" id="edit_doc_experience">
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
                            <input type="number" class="form-input" name="consultation_fee" id="edit_doc_fee" step="0.01" min="0">
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

            const greetingEl = document.getElementById('dashboardGreeting');
            if (greetingEl) {
                greetingEl.style.display = (secId === 'overview') ? 'block' : 'none';
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

            // Auto-hide alerts
            document.querySelectorAll('.hms-success-alert').forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-6px)';
                    setTimeout(() => alert.style.display = 'none', 300);
                }, 4000);
            });
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

        function switchDirectoryTab(tab) {
            const docTab = document.getElementById('dirTabDocs');
            const patTab = document.getElementById('dirTabPats');
            const btnDocs = document.getElementById('btnDirDocs');
            const btnPats = document.getElementById('btnDirPats');

            if (!docTab || !patTab || !btnDocs || !btnPats) return;

            if (tab === 'docs') {
                docTab.style.display = 'block';
                patTab.style.display = 'none';
                btnDocs.style.background = 'var(--primary)';
                btnDocs.style.color = '#ffffff';
                btnDocs.style.border = 'none';
                btnPats.style.background = 'transparent';
                btnPats.style.color = 'var(--text-primary)';
                btnPats.style.border = '1px solid var(--border-glass)';
            } else {
                docTab.style.display = 'none';
                patTab.style.display = 'block';
                btnPats.style.background = 'var(--primary)';
                btnPats.style.color = '#ffffff';
                btnPats.style.border = 'none';
                btnDocs.style.background = 'transparent';
                btnDocs.style.color = 'var(--text-primary)';
                btnDocs.style.border = '1px solid var(--border-glass)';
            }
        }

        window.addEventListener('click', (e) => {
            if (e.target === updateDeptModal) closeUpdateDeptModal();
            if (e.target === viewDoctorModal) closeViewDoctorModal();
            if (e.target === editDoctorModal) closeEditDoctorModal();
        });
    </script>
</body>
</html>
