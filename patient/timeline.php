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

// Fetch patient data for header/sidebar
$stmt = $conn->prepare('SELECT * FROM tbl_patient WHERE patient_id = ? LIMIT 1');
$stmt->bind_param('i', $patientId);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc() ?: [];
$stmt->close();

// Get initials for avatar fallback
$initials = '';
if (!empty($patient['first_name'])) $initials .= strtoupper($patient['first_name'][0]);
if (!empty($patient['last_name'])) $initials .= strtoupper($patient['last_name'][0]);
if (empty($initials)) $initials = 'PT';
$profilePhoto = !empty($patient['profile_photo']) ? '../uploads/patients/' . $patient['profile_photo'] : '';

$viewMode = $_GET['view'] ?? '';

// Handle view mode specific titles & filtering
$headerTitle = 'Timeline / History';
$cardTitle = '<i class="fi fi-rr-time-past"></i> Timeline / Medical History';
$cardSubtitle = 'Your complete healthcare journey in chronological order';

if ($viewMode === 'records') {
    $headerTitle = 'Medical Records';
    $cardTitle = '<i class="fi fi-rr-file-medical"></i> Medical Records';
    $cardSubtitle = 'Your diagnostic consultation reports, test investigations, and prescriptions';
} elseif ($viewMode === 'followup') {
    $headerTitle = 'Follow Up Records';
    $cardTitle = '<i class="fi fi-rr-refresh"></i> Follow Up Visits';
    $cardSubtitle = 'Scheduled follow-up appointments and doctor follow-up instructions';
}

// Fetch complete chronological medical history timeline records for currently logged-in patient
$timelineSql = "SELECT a.*, 
                       d.first_name AS doc_fname, d.middle_name AS doc_mname, d.last_name AS doc_lname, d.specialization AS doc_spec,
                       dept.department_name,
                       pr.prescription_id, pr.medications, pr.instructions AS rx_instructions,
                       fup.follow_up_id, fup.follow_up_date, fup.follow_up_reason, fup.status AS fup_status
                FROM tbl_appointment a
                JOIN tbl_doctor d ON a.doctor_id = d.doctor_id
                JOIN tbl_department dept ON a.department_id = dept.department_id
                LEFT JOIN tbl_prescription pr ON a.appointment_id = pr.appointment_id
                LEFT JOIN tbl_follow_up fup ON a.appointment_id = fup.appointment_id
                WHERE a.patient_id = ?
                ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.appointment_id DESC";
$tStmt = $conn->prepare($timelineSql);
$tStmt->bind_param('i', $patientId);
$tStmt->execute();
$timelineRes = $tStmt->get_result();
$timelineRecords = [];
if ($timelineRes) {
    while ($row = $timelineRes->fetch_assoc()) {
        if ($viewMode === 'records') {
            if (empty($row['report']) && empty($row['investigation']) && empty($row['medications']) && empty($row['prescription_id'])) {
                continue;
            }
        } elseif ($viewMode === 'followup') {
            if (empty($row['follow_up_date']) && empty($row['follow_up_reason']) && empty($row['follow_up_id'])) {
                continue;
            }
        }
        $timelineRecords[] = $row;
    }
}
$tStmt->close();
$totalHistoryCount = count($timelineRecords);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($headerTitle); ?> | Medi-Care Hospital Management System">
    <title><?php echo htmlspecialchars($headerTitle); ?> | <?php echo htmlspecialchars($patientName); ?> | Medi-Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-appointments.css?v=<?php echo time(); ?>">
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
                        <h1><?php echo htmlspecialchars($headerTitle); ?></h1>
                    </div>
                <div class="top-header-right">
                    <button class="header-icon-btn" title="Notifications">
                        <i class="fi fi-rr-bell"></i>
                        <span class="notification-dot"></span>
                    </button>
                    <button class="header-icon-btn" title="Messages">
                        <i class="fi fi-rr-envelope"></i>
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

            <!-- Dashboard Content -->
            <div class="dashboard-content">

                <!-- Chronological Medical History Timeline Card -->
                <div class="card" id="timeline">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 class="card-title"><?php echo $cardTitle; ?></h3>
                            <p style="font-size: 0.82rem; color: var(--text-secondary); margin-top: 2px;"><?php echo htmlspecialchars($cardSubtitle); ?></p>
                        </div>
                        <span class="card-badge"><?php echo $totalHistoryCount; ?> record<?php echo $totalHistoryCount !== 1 ? 's' : ''; ?></span>
                    </div>

                    <?php if ($totalHistoryCount > 0): ?>
                        <div class="patient-timeline">
                            <?php foreach ($timelineRecords as $item): 
                                $docName = trim('Dr. ' . $item['doc_fname'] . ' ' . $item['doc_mname'] . ' ' . $item['doc_lname']);
                                $statusLower = strtolower($item['status']);
                                $dateFormatted = date('d F Y', strtotime($item['appointment_date']));
                                $timeFormatted = date('h:i A', strtotime($item['appointment_time']));
                            ?>
                                <div class="patient-timeline-item">
                                    <div class="patient-timeline-dot <?php echo $statusLower; ?>"></div>
                                    <div class="patient-timeline-card">
                                        <div class="timeline-date-title"><?php echo $dateFormatted; ?></div>
                                        <div class="timeline-doc-dept">
                                            <?php echo htmlspecialchars($docName); ?> • <span style="color: var(--accent);"><?php echo htmlspecialchars($item['department_name']); ?></span>
                                        </div>
                                        <div class="timeline-meta-bar">
                                            <span><i class="fi fi-rr-clock"></i> <?php echo $timeFormatted; ?></span>
                                            <span>•</span>
                                            <span class="appt-badge <?php echo strtolower($item['appointment_type']) === 'online' ? 'online' : 'in-person'; ?>">
                                                <?php echo htmlspecialchars($item['appointment_type']); ?>
                                            </span>
                                            <span>•</span>
                                            <span class="appt-badge status-badge <?php echo $statusLower; ?>">
                                                <?php echo htmlspecialchars($item['status']); ?>
                                            </span>
                                        </div>

                                        <!-- Consultation / Medical Report Section -->
                                        <?php if (!empty($item['report'])): ?>
                                            <div class="timeline-section-block">
                                                <div class="timeline-section-title"><i class="fi fi-rr-file-medical"></i> Consultation / Medical Report</div>
                                                <div class="timeline-section-content">
                                                    <?php echo nl2br(htmlspecialchars($item['report'])); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Investigation Section -->
                                        <?php if (!empty($item['investigation'])): ?>
                                            <div class="timeline-section-block">
                                                <div class="timeline-section-title"><i class="fi fi-rr-microscope"></i> Investigation & Tests</div>
                                                <div class="timeline-section-content">
                                                    <?php echo nl2br(htmlspecialchars($item['investigation'])); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Prescription Section -->
                                        <?php if (!empty($item['medications']) || !empty($item['prescription_id'])): ?>
                                            <div class="timeline-section-block">
                                                <div class="timeline-section-title"><i class="fi fi-rr-medicine"></i> Prescription / Medication</div>
                                                <div class="timeline-section-content">
                                                    <?php if (!empty($item['medications'])): ?>
                                                        <div style="font-weight: 600; margin-bottom: 4px;">Medications:</div>
                                                        <div><?php echo nl2br(htmlspecialchars($item['medications'])); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['rx_instructions'])): ?>
                                                        <div style="font-weight: 600; margin-top: 8px; margin-bottom: 4px;">Instructions:</div>
                                                        <div><?php echo nl2br(htmlspecialchars($item['rx_instructions'])); ?></div>
                                                    <?php endif; ?>
                                                    <div style="margin-top: 10px;">
                                                        <a href="view_prescription.php?appointment_id=<?php echo (int)$item['appointment_id']; ?>" class="btn-auth btn-auth-secondary" style="padding: 6px 12px; font-size: 0.78rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                                            <i class="fi fi-rr-file-medical"></i> View Full Prescription
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Follow Up Section -->
                                        <?php 
                                        $hasFup = !empty($item['follow_up_date']) || !empty($item['follow_up_reason']) || !empty($item['follow_up']);
                                        if ($hasFup): 
                                            $fupDate = !empty($item['follow_up_date']) ? date('d F Y', strtotime($item['follow_up_date'])) : 'Scheduled';
                                            $fupReason = !empty($item['follow_up_reason']) ? $item['follow_up_reason'] : $item['follow_up'];
                                            $fupStatus = !empty($item['fup_status']) ? $item['fup_status'] : 'Pending';
                                            $fupStatusLower = strtolower($fupStatus);
                                        ?>
                                            <div class="timeline-section-block">
                                                <div class="timeline-section-title"><i class="fi fi-rr-refresh"></i> Follow Up</div>
                                                <div class="timeline-followup-card">
                                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                                        <strong style="color: var(--text-primary); font-size: 0.88rem;"><?php echo $fupDate; ?></strong>
                                                        <span class="appt-badge status-badge <?php echo $fupStatusLower; ?>">
                                                            Status: <?php echo htmlspecialchars($fupStatus); ?>
                                                        </span>
                                                    </div>
                                                    <?php if (!empty($fupReason)): ?>
                                                        <div style="font-size: 0.84rem; color: var(--text-secondary);">
                                                            <strong>Reason:</strong> <?php echo htmlspecialchars($fupReason); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="padding: 30px; text-align: center; color: var(--text-secondary); font-size: 0.9rem;">
                            No medical history or appointment timeline records found.
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

</body>
</html>
