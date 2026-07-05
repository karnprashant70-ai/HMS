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

    $map = [
        'first_name'        => 'first_name',
        'middle_name'       => 'middle_name',
        'last_name'         => 'last_name',
        'gender'            => 'gender',
        'phone_number'      => 'phone_number',
        'email'             => 'email',
        'temporary_address' => 'temporary_address',
        'permanent_address' => 'permanent_address',
        'marital_status'    => 'marital_status',
        'department'        => 'department',
        'specialization'    => 'specialization',
        'qualification'     => 'qualification',
        'licence_number'    => 'licence_number',
        'years_experience'  => 'years_experience',
        'consultation_fee'  => 'consultation_fee',
        'available_time'    => 'available_time',
        'status'            => 'status'
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
            $uploadDir = __DIR__ . '/../uploads/doctors/';
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
    <meta name="description" content="Doctor Profile | MediCare+ Hospital Management System">
    <title>My Profile | Dr. <?php echo htmlspecialchars($doctorName); ?> | MediCare+</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/auth.css?v=<?php echo time(); ?>">
    <style>
        /* ===== Profile Page Specific Styles ===== */
        .profile-hero {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 32px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 28px;
            position: relative;
            overflow: hidden;
        }

        .profile-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.15), rgba(0, 212, 170, 0.1));
            z-index: 0;
        }

        .profile-hero-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 800;
            color: white;
            flex-shrink: 0;
            z-index: 1;
            border: 4px solid var(--bg-dark);
            box-shadow: 0 8px 24px rgba(108, 99, 255, 0.25);
            overflow: hidden;
        }

        .profile-hero-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-hero-info {
            z-index: 1;
        }

        .profile-hero-name {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }

        .profile-hero-dept {
            font-size: 0.9rem;
            color: var(--accent);
            font-weight: 600;
            margin-bottom: 8px;
        }

        .profile-hero-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .profile-meta-item {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .profile-meta-item span {
            font-size: 0.95rem;
        }

        /* Status Badge */
        .profile-status-badge {
            position: absolute;
            top: 20px;
            right: 24px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            z-index: 1;
        }

        .profile-status-badge.available {
            background: rgba(0, 212, 170, 0.12);
            color: var(--accent);
            border: 1px solid rgba(0, 212, 170, 0.25);
        }

        .profile-status-badge.unavailable {
            background: rgba(255, 107, 107, 0.12);
            color: #FF6B6B;
            border: 1px solid rgba(255, 107, 107, 0.25);
        }

        .profile-status-badge.on-leave {
            background: rgba(255, 173, 96, 0.12);
            color: #FFAD60;
            border: 1px solid rgba(255, 173, 96, 0.25);
        }

        /* Profile Form Sections */
        .profile-section {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-md);
            padding: 28px;
            margin-bottom: 24px;
        }

        .profile-section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-glass);
        }

        .profile-section-icon {
            width: 38px;
            height: 38px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .profile-section-icon.purple { background: rgba(108, 99, 255, 0.15); }
        .profile-section-icon.teal { background: rgba(0, 212, 170, 0.15); }
        .profile-section-icon.orange { background: rgba(255, 140, 66, 0.15); }
        .profile-section-icon.pink { background: rgba(255, 107, 138, 0.15); }

        .profile-section-title {
            font-size: 1rem;
            font-weight: 700;
        }

        .profile-section-subtitle {
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        /* Profile Form Grid */
        .profile-form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .profile-form-grid .full-width {
            grid-column: span 2;
        }

        .profile-form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .profile-form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .profile-form-input {
            width: 100%;
            padding: 11px 14px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.9rem;
            transition: var(--transition);
            outline: none;
        }

        .profile-form-input:focus {
            border-color: var(--primary-light);
            background: rgba(255, 255, 255, 0.06);
            box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.12);
        }

        .profile-form-input:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        select.profile-form-input {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 14px;
            padding-right: 36px;
        }

        textarea.profile-form-input {
            resize: vertical;
            min-height: 70px;
        }

        /* Photo Upload */
        .photo-upload-area {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 16px;
            background: var(--bg-glass);
            border: 1px dashed var(--border-glass);
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .photo-upload-area:hover {
            border-color: var(--border-hover);
            background: var(--bg-glass-hover);
        }

        .photo-upload-preview {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
            overflow: hidden;
        }

        .photo-upload-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-upload-info {
            flex: 1;
        }

        .photo-upload-info p {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .photo-upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            background: var(--bg-glass-hover);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .photo-upload-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
        }

        .photo-upload-btn input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        /* Save Button */
        .profile-save-bar {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 8px;
        }

        .btn-profile-save {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.92rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
        }

        .btn-profile-save:hover {
            box-shadow: 0 6px 20px rgba(108, 99, 255, 0.35);
            transform: translateY(-1px);
        }

        .btn-profile-cancel {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: var(--bg-glass);
            color: var(--text-primary);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-sm);
            font-size: 0.92rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            font-family: inherit;
        }

        .btn-profile-cancel:hover {
            background: var(--bg-glass-hover);
            border-color: var(--border-hover);
        }

        @media (max-width: 768px) {
            .profile-hero {
                flex-direction: column;
                text-align: center;
                padding: 24px 20px;
            }
            .profile-hero-meta {
                justify-content: center;
            }
            .profile-status-badge {
                position: static;
                margin-bottom: 12px;
                display: inline-block;
            }
            .profile-form-grid {
                grid-template-columns: 1fr;
            }
            .profile-form-grid .full-width {
                grid-column: span 1;
            }
            .profile-section {
                padding: 20px 16px;
            }
        }
    </style>
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
                <div class="sidebar-brand-text">Medi<span>Care+</span></div>
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
                        <h1>My Profile</h1>
                        <p>Manage your personal and professional details</p>
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

                <!-- Profile Hero Card -->
                <div class="profile-hero">
                    <div class="profile-hero-avatar">
                        <?php if ($profilePhoto): ?>
                            <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo $initials; ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-hero-info">
                        <div class="profile-hero-name">Dr. <?php echo htmlspecialchars($doctorName); ?></div>
                        <div class="profile-hero-dept"><?php echo htmlspecialchars($doctor['specialization'] ?? 'Specialist'); ?> — <?php echo htmlspecialchars($department); ?></div>
                        <div class="profile-hero-meta">
                            <div class="profile-meta-item"><span>📧</span> <?php echo htmlspecialchars($doctor['email'] ?? '—'); ?></div>
                            <div class="profile-meta-item"><span>📱</span> <?php echo htmlspecialchars($doctor['phone_number'] ?? '—'); ?></div>
                            <div class="profile-meta-item"><span>🎓</span> <?php echo htmlspecialchars($doctor['years_experience'] ?? '0'); ?> yrs exp.</div>
                            <div class="profile-meta-item"><span>🆔</span> ID: <?php echo htmlspecialchars($doctor['doctor_id'] ?? $doctorId); ?></div>
                        </div>
                    </div>
                    <?php
                    $statusClass = 'available';
                    $statusText = $doctor['status'] ?? 'Available';
                    if ($statusText === 'Unavailable') $statusClass = 'unavailable';
                    if ($statusText === 'On Leave') $statusClass = 'on-leave';
                    ?>
                    <div class="profile-status-badge <?php echo $statusClass; ?>">● <?php echo htmlspecialchars($statusText); ?></div>
                </div>

                <!-- Profile Edit Form -->
                <form method="POST" action="" enctype="multipart/form-data">

                    <!-- Section 1: Personal Info -->
                    <div class="profile-section">
                        <div class="profile-section-header">
                            <div class="profile-section-icon purple">👤</div>
                            <div>
                                <div class="profile-section-title">Personal Information</div>
                                <div class="profile-section-subtitle">Your basic identity and contact details</div>
                            </div>
                        </div>

                        <!-- Photo Upload -->
                        <div class="photo-upload-area" style="margin-bottom: 24px;">
                            <div class="photo-upload-preview" id="photoPreview">
                                <?php if ($profilePhoto): ?>
                                    <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Photo" id="previewImg">
                                <?php else: ?>
                                    <?php echo $initials; ?>
                                <?php endif; ?>
                            </div>
                            <div class="photo-upload-info">
                                <p>JPG, PNG, WebP or GIF. Max 2MB.</p>
                                <label class="photo-upload-btn">
                                    📷 Change Photo
                                    <input type="file" name="profile_photo" accept="image/*" id="photoInput">
                                </label>
                            </div>
                        </div>

                        <div class="profile-form-grid">
                            <div class="profile-form-group">
                                <label class="profile-form-label">First Name</label>
                                <input class="profile-form-input" name="first_name" value="<?php echo htmlspecialchars($doctor['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label">Middle Name</label>
                                <input class="profile-form-input" name="middle_name" value="<?php echo htmlspecialchars($doctor['middle_name'] ?? ''); ?>">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label">Last Name</label>
                                <input class="profile-form-input" name="last_name" value="<?php echo htmlspecialchars($doctor['last_name'] ?? ''); ?>" required>
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label">Gender</label>
                                <select name="gender" class="profile-form-input" required>
                                    <option value="">Select</option>
                                    <option value="Male" <?php if (($doctor['gender'] ?? '') === 'Male') echo 'selected'; ?>>Male</option>
                                    <option value="Female" <?php if (($doctor['gender'] ?? '') === 'Female') echo 'selected'; ?>>Female</option>
                                    <option value="Other" <?php if (($doctor['gender'] ?? '') === 'Other') echo 'selected'; ?>>Other</option>
                                </select>
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label">Marital Status</label>
                                <select name="marital_status" class="profile-form-input">
                                    <option value="">Select</option>
                                    <option value="Single" <?php if (($doctor['marital_status'] ?? '') === 'Single') echo 'selected'; ?>>Single</option>
                                    <option value="Married" <?php if (($doctor['marital_status'] ?? '') === 'Married') echo 'selected'; ?>>Married</option>
                                    <option value="Divorced" <?php if (($doctor['marital_status'] ?? '') === 'Divorced') echo 'selected'; ?>>Divorced</option>
                                    <option value="Widowed" <?php if (($doctor['marital_status'] ?? '') === 'Widowed') echo 'selected'; ?>>Widowed</option>
                                </select>
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label">Doctor ID</label>
                                <input class="profile-form-input" value="<?php echo htmlspecialchars($doctor['doctor_id'] ?? $doctorId); ?>" disabled>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Contact Info -->
                    <div class="profile-section">
                        <div class="profile-section-header">
                            <div class="profile-section-icon teal">📞</div>
                            <div>
                                <div class="profile-section-title">Contact & Address</div>
                                <div class="profile-section-subtitle">How patients and staff can reach you</div>
                            </div>
                        </div>
                        <div class="profile-form-grid">
                            <div class="profile-form-group">
                                <label class="profile-form-label">Phone Number</label>
                                <input class="profile-form-input" name="phone_number" value="<?php echo htmlspecialchars($doctor['phone_number'] ?? ''); ?>">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label">Email</label>
                                <input class="profile-form-input" type="email" name="email" value="<?php echo htmlspecialchars($doctor['email'] ?? ''); ?>">
                            </div>
                            <div class="profile-form-group full-width">
                                <label class="profile-form-label">Temporary Address</label>
                                <textarea class="profile-form-input" name="temporary_address"><?php echo htmlspecialchars($doctor['temporary_address'] ?? ''); ?></textarea>
                            </div>
                            <div class="profile-form-group full-width">
                                <label class="profile-form-label">Permanent Address</label>
                                <textarea class="profile-form-input" name="permanent_address"><?php echo htmlspecialchars($doctor['permanent_address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Professional Info -->
                    <div class="profile-section">
                        <div class="profile-section-header">
                            <div class="profile-section-icon orange">🩺</div>
                            <div>
                                <div class="profile-section-title">Professional Details</div>
                                <div class="profile-section-subtitle">Your qualifications and practice information</div>
                            </div>
                        </div>
                        <div class="profile-form-grid">
                            <div class="profile-form-group">
                                <label class="profile-form-label">Department</label>
                                <select class="profile-form-input" name="department">
                                    <?php
                                    $deptRes = $conn->query("SELECT department_name FROM tbl_department ORDER BY department_name ASC");
                                    if ($deptRes) {
                                        while ($row = $deptRes->fetch_assoc()) {
                                            $deptName = $row['department_name'];
                                            $selected = ($doctor['department'] ?? '') === $deptName ? 'selected' : '';
                                            echo "<option value=\"" . htmlspecialchars($deptName) . "\" $selected>" . htmlspecialchars($deptName) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label">Specialization</label>
                                <input class="profile-form-input" name="specialization" value="<?php echo htmlspecialchars($doctor['specialization'] ?? ''); ?>">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label">Qualification</label>
                                <input class="profile-form-input" name="qualification" value="<?php echo htmlspecialchars($doctor['qualification'] ?? ''); ?>">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label">Licence Number</label>
                                <input class="profile-form-input" name="licence_number" value="<?php echo htmlspecialchars($doctor['licence_number'] ?? ''); ?>">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label">Years of Experience</label>
                                <input class="profile-form-input" type="number" name="years_experience" value="<?php echo htmlspecialchars($doctor['years_experience'] ?? ''); ?>">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label">Consultation Fee (Rs.)</label>
                                <input class="profile-form-input" type="number" step="0.01" name="consultation_fee" value="<?php echo htmlspecialchars($doctor['consultation_fee'] ?? ''); ?>">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label">Available Time</label>
                                <input class="profile-form-input" name="available_time" value="<?php echo htmlspecialchars($doctor['available_time'] ?? ''); ?>" placeholder="e.g. 9:00 AM - 5:00 PM">
                            </div>
                            <div class="profile-form-group">
                                <label class="profile-form-label">Status</label>
                                <select name="status" class="profile-form-input">
                                    <option value="Available" <?php if (($doctor['status'] ?? '') === 'Available') echo 'selected'; ?>>Available</option>
                                    <option value="Unavailable" <?php if (($doctor['status'] ?? '') === 'Unavailable') echo 'selected'; ?>>Unavailable</option>
                                    <option value="On Leave" <?php if (($doctor['status'] ?? '') === 'On Leave') echo 'selected'; ?>>On Leave</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Security -->
                    <div class="profile-section">
                        <div class="profile-section-header">
                            <div class="profile-section-icon pink">🔒</div>
                            <div>
                                <div class="profile-section-title">Security</div>
                                <div class="profile-section-subtitle">Update your password (leave blank to keep current)</div>
                            </div>
                        </div>
                        <div class="profile-form-grid">
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
                        <button type="submit" name="save_profile" class="btn-profile-save">
                            💾 Save Changes
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
