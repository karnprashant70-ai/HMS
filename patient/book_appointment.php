<?php
ob_start();
session_start();
if (empty($_SESSION['patient_id'])) {
    $currentDoctorId = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
    $redir = 'book_appointment.php' . ($currentDoctorId ? '?doctor_id=' . $currentDoctorId : '');
    header('Location: login.php?redirect=' . urlencode($redir));
    exit;
}
require_once __DIR__ . '/../db-connection/db_conn.php';

$patientId = intval($_SESSION['patient_id']);
$patientName = $_SESSION['patient_name'] ?? 'Patient';

// Fetch patient data for sidebar
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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $doctor_id = intval($_POST['doctor_id'] ?? 0);
    $department_id = intval($_POST['department_id'] ?? 0);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $appointment_type = trim($_POST['appointment_type'] ?? '');

    // Validation
    if ($doctor_id <= 0) $errors['doctor_id'] = 'Please select a doctor.';
    if ($department_id <= 0) $errors['department_id'] = 'Please select a department.';
    $today = date('Y-m-d');
    if (empty($appointment_date) || $appointment_date < $today) {
        $errors['appointment_date'] = 'Please select a valid date.';
    }
    if (empty($appointment_time)) {
        $errors['appointment_time'] = 'Appointment time is required.';
    } else {
        $parsedTime = strtotime($appointment_time);
        if ($parsedTime) {
            $appointment_time = date('H:i:s', $parsedTime);
        }
    }
    if (empty($appointment_type)) $errors['appointment_type'] = 'Appointment type is required.';

    if (empty($errors)) {
        // Get consultation fee for the selected doctor
        $feeStmt = $conn->prepare('SELECT consultation_fee FROM tbl_doctor WHERE doctor_id = ? LIMIT 1');
        $feeStmt->bind_param('i', $doctor_id);
        $feeStmt->execute();
        $feeRes = $feeStmt->get_result()->fetch_assoc();
        $consultation_fee = $feeRes ? floatval($feeRes['consultation_fee']) : 0.00;
        $feeStmt->close();

        // Insert appointment (mark new bookings as Pending)
        try {
            $status = 'Pending';
            $insertStmt = $conn->prepare('INSERT INTO tbl_appointment (patient_id, doctor_id, department_id, appointment_date, appointment_time, appointment_type, status, consultation_fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $insertStmt->bind_param('iiissssd', $patientId, $doctor_id, $department_id, $appointment_date, $appointment_time, $appointment_type, $status, $consultation_fee);
            if ($insertStmt->execute()) {
                $_SESSION['appt_success'] = 'Appointment booked successfully!';
                header('Location: appointments.php');
                exit;
            } else {
                $errors[] = 'Failed to book appointment. Error: ' . $insertStmt->error;
            }
            $insertStmt->close();
        } catch (mysqli_sql_exception $e) {
            $errors[] = 'Database Error: ' . $e->getMessage() . '. Please verify your database table column types match the form data.';
        }
    }
}

// Fetch all departments
$depts = [];
$deptRes = $conn->query('SELECT * FROM tbl_department ORDER BY department_name ASC');
if ($deptRes) {
    while ($r = $deptRes->fetch_assoc()) {
        $depts[] = $r;
    }
}

// Fetch all available doctors for dynamic filtering
$docs = [];
$docRes = $conn->query('SELECT doctor_id, first_name, middle_name, last_name, department, consultation_fee, available_time FROM tbl_doctor WHERE status = "Available" ORDER BY first_name ASC');
if ($docRes) {
    while ($r = $docRes->fetch_assoc()) {
        $docs[] = $r;
    }
}

// Map department names to IDs for easier referencing on JS side
$deptNameToIdMap = [];
foreach ($depts as $d) {
    $deptNameToIdMap[$d['department_name']] = $d['department_id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment | Medi-Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/doctor-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/auth.css?v=<?php echo time(); ?>">
</head>
<body>

    <div class="dashboard-layout">

        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- ===== MAIN CONTENT ===== -->
        <main class="main-content">
            <header class="top-header">
                <div class="top-header-left">
                    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">☰</button>
                    <div>
                        <?php include __DIR__ . '/includes/breadcrumb.php'; ?>
                    </div>
                </div>
            </header>

            <div class="dashboard-content">


                <div class="card" style="max-width: 700px; margin: 0 auto;">
                    <div style="padding: 30px;">
                        <form method="POST" action="" novalidate>
                            <input type="hidden" name="book_appointment" value="1">
                            
                            <?php 
                            $generalErrors = array_filter($errors, function($key) {
                                return is_numeric($key);
                            }, ARRAY_FILTER_USE_KEY);
                            if (!empty($generalErrors)): 
                            ?>
                                <div class="hms-error-box" style="margin-bottom: 20px;">
                                    <ul>
                                        <?php foreach ($generalErrors as $e): ?>
                                            <li><?php echo htmlspecialchars($e); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>


                            <div class="form-group">
                                <label class="form-label" for="book_dept">Select Department *</label>
                                <?php if (isset($errors['department_id'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['department_id']); ?></div><?php endif; ?>
                                <select id="book_dept" name="department_id" class="form-input" onchange="filterDoctors('book')">
                                    <option value="" disabled selected>Choose Department</option>
                                    <?php foreach ($depts as $d): ?>
                                        <option value="<?php echo $d['department_id']; ?>"><?php echo htmlspecialchars($d['department_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="book_doc">Select Doctor *</label>
                                <?php if (isset($errors['doctor_id'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['doctor_id']); ?></div><?php endif; ?>
                                <select id="book_doc" name="doctor_id" class="form-input" onchange="updateFee('book')" disabled>
                                    <option value="" disabled selected>Choose Doctor</option>
                                </select>
                            </div>

                            <div class="form-group" id="book_schedule_info" style="display:none; background:rgba(0,0,0,0.02); padding:10px 14px; border-radius:6px; border:1px solid var(--border-glass); margin-bottom:15px; font-size:0.82rem; color:var(--text-secondary);">
                                <i class="fi fi-rr-calendar"></i> Doctor Schedule: <span id="book_schedule_text" style="color:var(--accent); font-weight:600;"></span>
                            </div>

                            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:16px;">
                                <div class="form-group">
                                    <label class="form-label" for="book_date">Date *</label>
                                    <?php if (isset($errors['appointment_date'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['appointment_date']); ?></div><?php endif; ?>
                                    <input type="date" id="book_date" name="appointment_date" class="form-input" min="<?php echo date('Y-m-d'); ?>" onchange="loadSlots()">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Selected Time *</label>
                                    <?php if (isset($errors['appointment_time'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['appointment_time']); ?></div><?php endif; ?>
                                    <input type="text" id="book_time_display" class="form-input" placeholder="Click a slot below" readonly>
                                    <input type="hidden" id="book_time" name="appointment_time">
                                </div>
                            </div>

                            <div class="form-group" style="margin-top: 10px;">
                                <label class="form-label">Available Time Slots</label>
                                <div id="slots_container" style="display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; min-height:42px; align-items:center;">
                                    <span style="font-size:0.85rem; color:var(--text-secondary);">Select a doctor and date to view available time slots.</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Appointment Type *</label>
                                <?php if (isset($errors['appointment_type'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['appointment_type']); ?></div><?php endif; ?>
                                <select name="appointment_type" class="form-input">
                                    <option value="Physical">Physical (In-Person)</option>
                                    <option value="Online">Online Consultation</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Consultation Fee</label>
                                <div class="fee-display" id="book_fee" style="font-size: 1.2rem; font-weight: 700; color: var(--accent); padding: 12px; background: rgba(0, 184, 148, 0.08); border-radius: var(--radius-sm); border: 1px solid rgba(0, 184, 148, 0.15);">Rs. 0.00</div>
                            </div>

                            <div style="display:flex; gap:12px; margin-top:30px;">
                                <a href="appointments.php" class="btn-auth btn-auth-secondary" style="flex:1; text-align:center; text-decoration:none;">Cancel</a>
                                <button type="submit" class="btn-auth btn-auth-primary" style="flex:1;">Confirm Booking</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- ===== JS LOGIC ===== -->
    <script>
        // Store doctors list locally as JSON
        const doctorsData = <?php echo json_encode($docs); ?>;
        const deptNameToIdMap = <?php echo json_encode($deptNameToIdMap); ?>;



        // Dropdown interactive filtering
        function filterDoctors(prefix) {
            const deptSelect = document.getElementById(`${prefix}_dept`);
            const docSelect = document.getElementById(`${prefix}_doc`);
            const selectedDeptId = parseInt(deptSelect.value);

            // Enable doctor select
            docSelect.disabled = false;
            docSelect.innerHTML = '<option value="" disabled selected>Choose Doctor</option>';

            // Find matching doctors
            const filteredDocs = doctorsData.filter(d => {
                const mapId = parseInt(deptNameToIdMap[d.department]);
                return mapId === selectedDeptId;
            });

            if (filteredDocs.length === 0) {
                docSelect.innerHTML = '<option value="" disabled>No available doctors in this department</option>';
            } else {
                filteredDocs.forEach(d => {
                    const docName = `Dr. ${d.first_name} ${d.middle_name || ''} ${d.last_name}`;
                    docSelect.innerHTML += `<option value="${d.doctor_id}">${docName}</option>`;
                });
            }

            // Reset fee display
            document.getElementById(`${prefix}_fee`).textContent = 'Rs. 0.00';
            document.getElementById(`${prefix}_schedule_info`).style.display = 'none';
        }

        function updateFee(prefix) {
            const docSelect = document.getElementById(`${prefix}_doc`);
            const selectedDocId = parseInt(docSelect.value);
            const doctorObj = doctorsData.find(d => parseInt(d.doctor_id) === selectedDocId);

            if (doctorObj) {
                const feeFormatted = parseFloat(doctorObj.consultation_fee).toFixed(2);
                document.getElementById(`${prefix}_fee`).textContent = `Rs. ${feeFormatted}`;
                
                // Show doctor schedule if set
                if (doctorObj.available_time) {
                    document.getElementById(`${prefix}_schedule_text`).textContent = doctorObj.available_time;
                    document.getElementById(`${prefix}_schedule_info`).style.display = 'block';
                } else {
                    document.getElementById(`${prefix}_schedule_info`).style.display = 'none';
                }
                loadSlots();
            } else {
                document.getElementById(`${prefix}_fee`).textContent = 'Rs. 0.00';
                document.getElementById(`${prefix}_schedule_info`).style.display = 'none';
            }
        }

        function loadSlots() {
            const docId = document.getElementById('book_doc').value;
            const dateVal = document.getElementById('book_date').value;
            const container = document.getElementById('slots_container');
            const timeInput = document.getElementById('book_time');
            const timeDisplay = document.getElementById('book_time_display');

            if (!docId || !dateVal) {
                container.innerHTML = '<span style="font-size:0.85rem; color:var(--text-secondary);">Select a doctor and date to view available time slots.</span>';
                return;
            }

            container.innerHTML = '<span style="font-size:0.85rem; color:var(--accent);">⏳ Loading available slots...</span>';

            fetch(`get_available_slots.php?doctor_id=${docId}&date=${dateVal}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success || !data.slots || data.slots.length === 0) {
                        container.innerHTML = '<span style="font-size:0.85rem; color:#e74c3c;">No available slots for this date.</span>';
                        return;
                    }

                    container.innerHTML = '';
                    data.slots.forEach(s => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.textContent = s.time;
                        btn.style.padding = '6px 14px';
                        btn.style.borderRadius = '20px';
                        btn.style.fontSize = '0.82rem';
                        btn.style.fontWeight = '600';
                        btn.style.cursor = s.available ? 'pointer' : 'not-allowed';
                        btn.style.border = '1px solid';
                        btn.style.transition = 'all 0.2s ease';

                        if (s.available) {
                            btn.style.backgroundColor = 'rgba(0, 184, 148, 0.1)';
                            btn.style.borderColor = 'rgba(0, 184, 148, 0.4)';
                            btn.style.color = 'var(--accent)';

                            btn.onclick = () => {
                                // Deselect all other buttons
                                container.querySelectorAll('button').forEach(b => {
                                    if (!b.disabled) {
                                        b.style.backgroundColor = 'rgba(0, 184, 148, 0.1)';
                                        b.style.color = 'var(--accent)';
                                    }
                                });
                                // Highlight selected button
                                btn.style.backgroundColor = 'var(--accent)';
                                btn.style.color = '#ffffff';

                                timeInput.value = s.time;
                                timeDisplay.value = s.time;
                            };
                        } else {
                            btn.disabled = true;
                            btn.style.backgroundColor = 'rgba(0, 0, 0, 0.05)';
                            btn.style.borderColor = 'rgba(0, 0, 0, 0.1)';
                            btn.style.color = 'var(--text-muted, #aaa)';
                            btn.title = s.reason === 'Booked' ? 'Already Booked' : 'Past Time';
                        }

                        container.appendChild(btn);
                    });
                })
                .catch(err => {
                    container.innerHTML = '<span style="font-size:0.85rem; color:#e74c3c;">Failed to load slots.</span>';
                });
        }
    </script>

</body>
</html>
