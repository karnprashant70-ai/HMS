<?php
ob_start();
session_start();
if (empty($_SESSION['patient_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/../db-connection/db_conn.php';

$patientId = intval($_SESSION['patient_id']);
$appointmentId = intval($_GET['appointment_id'] ?? 0);

if ($appointmentId <= 0) {
    die('Invalid appointment ID.');
}

// Fetch prescription and details
$query = "SELECT pr.*, 
                 a.appointment_date, a.appointment_time, a.appointment_type, a.report, a.investigation,
                 d.first_name AS doc_fname, d.middle_name AS doc_mname, d.last_name AS doc_lname, 
                 d.specialization, d.licence_number, d.phone_number AS doc_phone, d.email AS doc_email,
                 p.first_name AS pat_fname, p.middle_name AS pat_mname, p.last_name AS pat_lname,
                 p.gender, p.age, p.phone_number AS pat_phone
          FROM tbl_prescription pr
          JOIN tbl_appointment a ON pr.appointment_id = a.appointment_id
          JOIN tbl_doctor d ON pr.doctor_id = d.doctor_id
          JOIN tbl_patient p ON pr.patient_id = p.patient_id
          WHERE pr.appointment_id = ? AND pr.patient_id = ?
          LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $appointmentId, $patientId);
$stmt->execute();
$rx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rx) {
    die('Prescription not found or access denied.');
}

$doctorName = trim('Dr. ' . $rx['doc_fname'] . ' ' . $rx['doc_mname'] . ' ' . $rx['doc_lname']);
$patientName = trim($rx['pat_fname'] . ' ' . $rx['pat_mname'] . ' ' . $rx['pat_lname']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Prescription - <?php echo htmlspecialchars($patientName); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <style>
        :root {
            --primary: #00b894;
            --text-dark: #2d3436;
            --text-muted: #636e72;
            --border: #dfe6e9;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #f4f6f9; color: var(--text-dark); padding: 40px 20px; }
        
        .prescription-card {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 40px;
            border: 1px solid var(--border);
        }
        
        .rx-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .hospital-brand h1 { color: var(--primary); font-size: 1.6rem; font-weight: 800; display: flex; align-items: center; gap: 8px; }
        .hospital-brand p { color: var(--text-muted); font-size: 0.85rem; margin-top: 4px; }
        .doctor-meta { text-align: right; }
        .doctor-meta h3 { font-size: 1.1rem; color: var(--text-dark); }
        .doctor-meta p { font-size: 0.82rem; color: var(--text-muted); }
        
        .patient-bar {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px 20px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 30px;
            border: 1px solid #e2e8f0;
        }
        .patient-bar label { display: block; font-size: 0.72rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.5px; }
        .patient-bar span { font-size: 0.92rem; font-weight: 600; color: var(--text-dark); display: block; margin-top: 2px; }
        
        .rx-symbol {
            font-size: 2.2rem;
            font-weight: 900;
            font-family: serif;
            color: var(--primary);
            margin-bottom: 16px;
        }
        
        .section-box {
            margin-bottom: 24px;
        }
        .section-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section-content {
            font-size: 0.9rem;
            line-height: 1.6;
            color: #475569;
            background: #fafafa;
            padding: 12px 16px;
            border-radius: 6px;
            border-left: 3px solid var(--primary);
        }
        
        .rx-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .signature-box {
            text-align: center;
        }
        .signature-line {
            width: 160px;
            border-bottom: 1px solid #000;
            margin-bottom: 6px;
        }
        
        .action-bar {
            max-width: 800px;
            margin: 24px auto 0;
            display: flex;
            justify-content: space-between;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        .btn:hover { opacity: 0.9; }

        @media print {
            body { background: #fff; padding: 0; }
            .prescription-card { box-shadow: none; border: none; padding: 0; width: 100%; max-width: 100%; }
            .action-bar { display: none; }
        }
    </style>
</head>
<body>

    <div class="prescription-card">
        <div class="action-bar" style="margin-bottom: 16px;">
            <?php include __DIR__ . '/includes/breadcrumb.php'; ?>
        </div>
        <!-- Hospital & Doctor Header -->
        <div class="rx-header">
            <div class="hospital-brand">
                <h1><i class="fi fi-rr-hospital"></i> Medi-Care Hospital</h1>
                <p>Advanced Health Management System</p>
                <p style="font-size:0.8rem; color:#94a3b8;">Email: support@medicare.com | Helpline: +977-1-4000000</p>
            </div>
            <div class="doctor-meta">
                <h3><?php echo htmlspecialchars($doctorName); ?></h3>
                <p><?php echo htmlspecialchars($rx['specialization'] ?? 'General Physician'); ?></p>
                <p>NMC Licence No: <?php echo htmlspecialchars($rx['licence_number'] ?? 'N/A'); ?></p>
            </div>
        </div>

        <!-- Patient Demographics Bar -->
        <div class="patient-bar">
            <div>
                <label>Patient Name</label>
                <span><?php echo htmlspecialchars($patientName); ?></span>
            </div>
            <div>
                <label>Gender / Age</label>
                <span><?php echo htmlspecialchars($rx['gender'] ?? 'N/A'); ?> / <?php echo htmlspecialchars($rx['age'] ?? 'N/A'); ?> yrs</span>
            </div>
            <div>
                <label>Consultation Date</label>
                <span><?php echo date('M d, Y', strtotime($rx['appointment_date'])); ?></span>
            </div>
            <div>
                <label>Rx ID</label>
                <span>#RX-<?php echo StringPad($rx['prescription_id'], 5, '0'); ?></span>
            </div>
        </div>

        <div class="rx-symbol">Rx</div>

        <!-- Diagnosis / Medical Report -->
        <?php if (!empty($rx['report'])): ?>
        <div class="section-box">
            <div class="section-title"><i class="fi fi-rr-file-medical"></i> Clinical Diagnosis & Diagnosis Report</div>
            <div class="section-content"><?php echo htmlspecialchars($rx['report']); ?></div>
        </div>
        <?php endif; ?>

        <!-- Medications List -->
        <div class="section-box">
            <div class="section-title"><i class="fi fi-rr-medicine"></i> Prescribed Medications</div>
            <div class="section-content" style="font-weight: 600; color: #1e293b; font-size: 1rem;"><?php echo htmlspecialchars($rx['medications']); ?></div>
        </div>

        <!-- Advice & Instructions -->
        <?php if (!empty($rx['instructions'])): ?>
        <div class="section-box">
            <div class="section-title"><i class="fi fi-rr-bulb"></i> Special Advice & Instructions</div>
            <div class="section-content"><?php echo htmlspecialchars($rx['instructions']); ?></div>
        </div>
        <?php endif; ?>

        <!-- Footer / Signature -->
        <div class="rx-footer">
            <div style="font-size:0.8rem; color:var(--text-muted);">
                <p>This is an official digital prescription issued by Medi-Care HMS.</p>
                <p>Issued on: <?php echo date('M d, Y \a\t h:i A', strtotime($rx['created_at'])); ?></p>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <p style="font-size:0.85rem; font-weight:700;"><?php echo htmlspecialchars($doctorName); ?></p>
                <p style="font-size:0.75rem; color:var(--text-muted);">Authorized Doctor Signature</p>
            </div>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="action-bar">
        <a href="appointments.php" class="btn btn-secondary"><i class="fi fi-rr-arrow-left"></i> Back to Appointments</a>
        <button onclick="window.print()" class="btn btn-primary"><i class="fi fi-rr-print"></i> Print Prescription</button>
    </div>

</body>
</html>
<?php
function StringPad($val, $len, $char) {
    return str_pad($val, $len, $char, STR_PAD_LEFT);
}
