<?php
ob_start();
session_start();
require_once __DIR__ . '/db-connection/db_conn.php';

// Handle AJAX Ticket Status Lookup
if (isset($_GET['action']) && $_GET['action'] === 'track_ticket') {
    header('Content-Type: application/json');
    $ticketCode = trim($_GET['ticket'] ?? '');

    if (empty($ticketCode)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid ticket code.']);
        exit;
    }

    $stmt = $conn->prepare('SELECT ticket_code, bug_title, bug_category, severity, status, admin_notes, created_at FROM tbl_bug_report WHERE ticket_code = ? LIMIT 1');
    $stmt->bind_param('s', $ticketCode);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        echo json_encode([
            'success'     => true,
            'ticket'      => $row['ticket_code'],
            'title'       => $row['bug_title'],
            'category'    => $row['bug_category'],
            'severity'    => $row['severity'],
            'status'      => $row['status'],
            'notes'       => $row['admin_notes'] ?? '',
            'created_at'  => date('M d, Y - h:i A', strtotime($row['created_at']))
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No bug report found with ticket code "' . htmlspecialchars($ticketCode) . '".']);
    }
    $stmt->close();
    exit;
}

// Pre-fill user data if logged in
$loggedInRole = 'Visitor';
$defaultName = '';
$defaultEmail = '';

if (!empty($_SESSION['patient_id'])) {
    $loggedInRole = 'Patient';
    $defaultName = $_SESSION['patient_name'] ?? '';
    // fetch email
    $st = $conn->prepare('SELECT email FROM tbl_patient WHERE patient_id = ? LIMIT 1');
    $st->bind_param('i', $_SESSION['patient_id']);
    $st->execute();
    $defaultEmail = $st->get_result()->fetch_assoc()['email'] ?? '';
    $st->close();
} elseif (!empty($_SESSION['doctor_id'])) {
    $loggedInRole = 'Doctor';
    $defaultName = $_SESSION['doctor_name'] ?? '';
    $st = $conn->prepare('SELECT email FROM tbl_doctor WHERE doctor_id = ? LIMIT 1');
    $st->bind_param('i', $_SESSION['doctor_id']);
    $st->execute();
    $defaultEmail = $st->get_result()->fetch_assoc()['email'] ?? '';
    $st->close();
} elseif (!empty($_SESSION['admin_id'])) {
    $loggedInRole = 'Administrator';
    $defaultName = 'System Administrator';
}

$errors = [];
$successTicket = '';

if (!empty($_SESSION['bug_success_ticket'])) {
    $successTicket = $_SESSION['bug_success_ticket'];
    unset($_SESSION['bug_success_ticket']);
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_bug'])) {
    $name = trim($_POST['reporter_name'] ?? '');
    $email = trim($_POST['reporter_email'] ?? '');
    $role = trim($_POST['user_role'] ?? 'Visitor');
    $title = trim($_POST['bug_title'] ?? '');
    $category = trim($_POST['bug_category'] ?? '');
    $severity = trim($_POST['severity'] ?? 'Medium');
    $steps = trim($_POST['steps_to_reproduce'] ?? '');
    $expected = trim($_POST['expected_behavior'] ?? '');
    $actual = trim($_POST['actual_behavior'] ?? '');
    $browserOs = trim($_POST['browser_os'] ?? '');

    // Validation
    if (empty($name)) $errors['reporter_name'] = 'Your full name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['reporter_email'] = 'A valid email address is required.';
    if (empty($title)) $errors['bug_title'] = 'Please provide a clear issue title / summary.';
    if (empty($category)) $errors['bug_category'] = 'Please select an issue category.';
    if (empty($steps)) $errors['steps_to_reproduce'] = 'Please outline the steps to reproduce the issue.';

    $validSeverities = ['Low', 'Medium', 'High', 'Critical'];
    if (!in_array($severity, $validSeverities)) $severity = 'Medium';

    $validRoles = ['Patient', 'Doctor', 'Visitor', 'Administrator'];
    if (!in_array($role, $validRoles)) $role = 'Visitor';

    // Handle Screenshot Upload
    $screenshotPath = null;
    if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];
        $fileInfo = pathinfo($_FILES['screenshot']['name']);
        $ext = strtolower($fileInfo['extension'] ?? '');

        if (!in_array($ext, $allowedExtensions)) {
            $errors['screenshot'] = 'Invalid file format. Allowed formats: JPG, PNG, WEBP, GIF, PDF.';
        } elseif ($_FILES['screenshot']['size'] > 5 * 1024 * 1024) {
            $errors['screenshot'] = 'File size exceeds 5MB limit.';
        } else {
            $targetDir = __DIR__ . '/uploads/bugs/';
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            $newFileName = 'bug_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $targetFile = $targetDir . $newFileName;

            if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $targetFile)) {
                $screenshotPath = 'uploads/bugs/' . $newFileName;
            } else {
                $errors['screenshot'] = 'Failed to upload screenshot. Please try again.';
            }
        }
    }

    if (empty($errors)) {
        // Generate unique ticket code
        $ticketCode = 'TKT-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

        $stmt = $conn->prepare('INSERT INTO tbl_bug_report (ticket_code, reporter_name, reporter_email, user_role, bug_title, bug_category, severity, steps_to_reproduce, expected_behavior, actual_behavior, browser_os, screenshot_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Open")');
        $stmt->bind_param('ssssssssssss', $ticketCode, $name, $email, $role, $title, $category, $severity, $steps, $expected, $actual, $browserOs, $screenshotPath);

        if ($stmt->execute()) {
            $_SESSION['bug_success_ticket'] = $ticketCode;
            header('Location: report_bug.php');
            exit;
        } else {
            $errors['db'] = 'Failed to record bug report. Error: ' . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Report a Bug or Issue | Medi-Care Hospital Management System. Submit feedback, system glitches, or technical issues to our engineering team.">
    <title>Report a Bug or Issue | Medi-Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- Animated Background -->
    <div class="bg-pattern"></div>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar" id="navbar">
        <a href="index.php" class="nav-brand">
            <div class="nav-brand-icon">M+</div>
            <div class="nav-brand-text">Medi-<span>Care</span></div>
        </a>

        <ul class="nav-links" id="navLinks">
            <li><a href="index.php" class="nav-link">Home</a></li>
            <li><a href="doctors.php" class="nav-link">Doctors</a></li>
            <li><a href="index.php#features" class="nav-link">Features</a></li>
            <li><a href="privacy_policy.php" class="nav-link">Privacy</a></li>
            <li><a href="report_bug.php" class="nav-link active" style="color: var(--primary); font-weight: 700;">Report Bug</a></li>

            <!-- Login Dropdown -->
            <li class="nav-dropdown btn-nav-login" id="loginDropdown">
                <button class="dropdown-trigger" aria-expanded="false" aria-haspopup="true">
                    Login
                    <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
                <div class="dropdown-menu" role="menu">
                    <div class="dropdown-label">Login as</div>
                    <a href="doctor/login.php" class="dropdown-item" role="menuitem">
                        <div class="dropdown-item-icon doctor"><i class="fi fi-rr-stethoscope"></i></div>
                        <div class="dropdown-item-info">
                            <h4>Doctor</h4>
                            <p>Access your dashboard</p>
                        </div>
                    </a>
                    <a href="patient/login.php" class="dropdown-item" role="menuitem">
                        <div class="dropdown-item-icon patient"><i class="fi fi-rr-user"></i></div>
                        <div class="dropdown-item-info">
                            <h4>Patient</h4>
                            <p>View appointments & records</p>
                        </div>
                    </a>
                </div>
            </li>

            <!-- Register Dropdown -->
            <li class="nav-dropdown btn-nav-register" id="registerDropdown">
                <button class="dropdown-trigger" aria-expanded="false" aria-haspopup="true">
                    Register
                    <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
                <div class="dropdown-menu" role="menu">
                    <div class="dropdown-label">Register as</div>
                    <a href="doctor/register.php" class="dropdown-item" role="menuitem">
                        <div class="dropdown-item-icon doctor"><i class="fi fi-rr-stethoscope"></i></div>
                        <div class="dropdown-item-info">
                            <h4>Doctor</h4>
                            <p>Join our medical network</p>
                        </div>
                    </a>
                    <a href="patient/register.php" class="dropdown-item" role="menuitem">
                        <div class="dropdown-item-icon patient"><i class="fi fi-rr-user"></i></div>
                        <div class="dropdown-item-info">
                            <h4>Patient</h4>
                            <p>Create your health profile</p>
                        </div>
                    </a>
                </div>
            </li>
        </ul>

        <!-- Mobile Toggle -->
        <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle navigation">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </nav>

    <!-- ===== PAGE HEADER ===== -->
    <header class="bug-page-header">
        <div class="bug-badge">
            <i class="fi fi-rr-bug"></i> Quality Assurance & Support
        </div>
        <h1 class="doctors-page-title">
            Report a <span class="gradient-text">Bug or Issue</span>
        </h1>
        <p class="doctors-page-subtitle">
            Found an error, unexpected glitch, or display issue? Submit the details below and our development team will investigate.
        </p>
    </header>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="bug-container">

        <?php if (!empty($successTicket)): ?>
            <div class="ticket-success-box">
                <i class="fi fi-rr-check-circle" style="font-size: 2.5rem; color: #00b894; margin-bottom: 8px; display: inline-block;"></i>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary);">Bug Report Submitted Successfully!</h3>
                <p style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 4px;">
                    Thank you for helping us improve Medi-Care. Your report has been logged with the tracking code:
                </p>
                <div class="ticket-code-display"><?php echo htmlspecialchars($successTicket); ?></div>
                <p style="font-size: 0.82rem; color: var(--text-muted);">
                    Save this code to track the resolution status of your issue using the tracker tool below.
                </p>
            </div>
        <?php endif; ?>

        <?php if (isset($errors['db'])): ?>
            <div class="hms-error-box" style="margin-bottom: 24px; padding: 14px 18px; border-radius: var(--radius-md); background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #dc2626;">
                <?php echo htmlspecialchars($errors['db']); ?>
            </div>
        <?php endif; ?>

        <!-- Bug Submission Card -->
        <div class="bug-card">
            <form id="bugReportForm" method="POST" action="" enctype="multipart/form-data" novalidate>

                <!-- Reporter Details Grid -->
                <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="reporter_name">Your Name *</label>
                        <?php if (isset($errors['reporter_name'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['reporter_name']); ?></div><?php endif; ?>
                        <input type="text" id="reporter_name" name="reporter_name" class="form-input<?php echo isset($errors['reporter_name']) ? ' input-error' : ''; ?>" placeholder="e.g. John Doe" value="<?php echo htmlspecialchars($_POST['reporter_name'] ?? $defaultName); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reporter_email">Email Address *</label>
                        <?php if (isset($errors['reporter_email'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['reporter_email']); ?></div><?php endif; ?>
                        <input type="email" id="reporter_email" name="reporter_email" class="form-input<?php echo isset($errors['reporter_email']) ? ' input-error' : ''; ?>" placeholder="name@example.com" value="<?php echo htmlspecialchars($_POST['reporter_email'] ?? $defaultEmail); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="user_role">You are a *</label>
                        <select id="user_role" name="user_role" class="form-input">
                            <option value="Patient" <?php echo (($_POST['user_role'] ?? $loggedInRole) === 'Patient') ? 'selected' : ''; ?>>Patient</option>
                            <option value="Doctor" <?php echo (($_POST['user_role'] ?? $loggedInRole) === 'Doctor') ? 'selected' : ''; ?>>Doctor</option>
                            <option value="Visitor" <?php echo (($_POST['user_role'] ?? $loggedInRole) === 'Visitor') ? 'selected' : ''; ?>>Visitor / Guest</option>
                            <option value="Administrator" <?php echo (($_POST['user_role'] ?? $loggedInRole) === 'Administrator') ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                    </div>
                </div>

                <!-- Issue Details Grid -->
                <div class="form-grid" style="grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="bug_title">Bug Summary / Title *</label>
                        <?php if (isset($errors['bug_title'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['bug_title']); ?></div><?php endif; ?>
                        <input type="text" id="bug_title" name="bug_title" class="form-input<?php echo isset($errors['bug_title']) ? ' input-error' : ''; ?>" placeholder="Brief summary of the glitch (e.g. Cannot select 2:00 PM slot)" value="<?php echo htmlspecialchars($_POST['bug_title'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="bug_category">Category *</label>
                        <?php if (isset($errors['bug_category'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['bug_category']); ?></div><?php endif; ?>
                        <select id="bug_category" name="bug_category" class="form-input<?php echo isset($errors['bug_category']) ? ' input-error' : ''; ?>">
                            <option value="" disabled selected>Select Category</option>
                            <option value="Appointment Booking" <?php echo (($_POST['bug_category'] ?? '') === 'Appointment Booking') ? 'selected' : ''; ?>>Appointment Booking</option>
                            <option value="Authentication & Login" <?php echo (($_POST['bug_category'] ?? '') === 'Authentication & Login') ? 'selected' : ''; ?>>Authentication & Login</option>
                            <option value="UI & Visual Display" <?php echo (($_POST['bug_category'] ?? '') === 'UI & Visual Display') ? 'selected' : ''; ?>>UI & Visual Display</option>
                            <option value="Doctor Schedule & Ratings" <?php echo (($_POST['bug_category'] ?? '') === 'Doctor Schedule & Ratings') ? 'selected' : ''; ?>>Doctor Schedule & Ratings</option>
                            <option value="Patient Records & Prescriptions" <?php echo (($_POST['bug_category'] ?? '') === 'Patient Records & Prescriptions') ? 'selected' : ''; ?>>Patient Records & Prescriptions</option>
                            <option value="Performance & Slowness" <?php echo (($_POST['bug_category'] ?? '') === 'Performance & Slowness') ? 'selected' : ''; ?>>Performance & Slowness</option>
                            <option value="Other" <?php echo (($_POST['bug_category'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <!-- Severity Level -->
                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-label">Severity Level</label>
                    <div class="severity-selector-grid">
                        <label class="severity-pill-label <?php echo (($_POST['severity'] ?? 'Medium') === 'Low') ? 'active-low' : ''; ?>">
                            <input type="radio" name="severity" value="Low" <?php echo (($_POST['severity'] ?? 'Medium') === 'Low') ? 'checked' : ''; ?> onchange="updateSeverityPills()">
                            <span>🟢 Low</span>
                            <small style="font-size: 0.72rem; color: var(--text-muted);">Minor cosmetic glitch</small>
                        </label>
                        <label class="severity-pill-label <?php echo (($_POST['severity'] ?? 'Medium') === 'Medium') ? 'active-medium' : ''; ?>">
                            <input type="radio" name="severity" value="Medium" <?php echo (($_POST['severity'] ?? 'Medium') === 'Medium') ? 'checked' : ''; ?> onchange="updateSeverityPills()">
                            <span>🔵 Medium</span>
                            <small style="font-size: 0.72rem; color: var(--text-muted);">Feature partially broken</small>
                        </label>
                        <label class="severity-pill-label <?php echo (($_POST['severity'] ?? '') === 'High') ? 'active-high' : ''; ?>">
                            <input type="radio" name="severity" value="High" <?php echo (($_POST['severity'] ?? '') === 'High') ? 'checked' : ''; ?> onchange="updateSeverityPills()">
                            <span>🟠 High</span>
                            <small style="font-size: 0.72rem; color: var(--text-muted);">Important feature blocked</small>
                        </label>
                        <label class="severity-pill-label <?php echo (($_POST['severity'] ?? '') === 'Critical') ? 'active-critical' : ''; ?>">
                            <input type="radio" name="severity" value="Critical" <?php echo (($_POST['severity'] ?? '') === 'Critical') ? 'checked' : ''; ?> onchange="updateSeverityPills()">
                            <span>🔴 Critical</span>
                            <small style="font-size: 0.72rem; color: var(--text-muted);">System crash / data loss</small>
                        </label>
                    </div>
                </div>

                <!-- Steps to Reproduce -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" for="steps_to_reproduce">Steps to Reproduce *</label>
                    <?php if (isset($errors['steps_to_reproduce'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['steps_to_reproduce']); ?></div><?php endif; ?>
                    <textarea id="steps_to_reproduce" name="steps_to_reproduce" class="form-input<?php echo isset($errors['steps_to_reproduce']) ? ' input-error' : ''; ?>" rows="4" placeholder="1. Go to homepage&#10;2. Click on Doctors&#10;3. Click 'Book Visit' on doctor #1&#10;4. Observe error..." style="resize: vertical; font-family: inherit;"><?php echo htmlspecialchars($_POST['steps_to_reproduce'] ?? ''); ?></textarea>
                </div>

                <!-- Expected vs Actual Behavior Grid -->
                <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="expected_behavior">Expected Behavior</label>
                        <textarea id="expected_behavior" name="expected_behavior" class="form-input" rows="3" placeholder="What should have happened?" style="resize: vertical; font-family: inherit;"><?php echo htmlspecialchars($_POST['expected_behavior'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="actual_behavior">Actual Behavior</label>
                        <textarea id="actual_behavior" name="actual_behavior" class="form-input" rows="3" placeholder="What actually happened?" style="resize: vertical; font-family: inherit;"><?php echo htmlspecialchars($_POST['actual_behavior'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Browser / Environment & Hidden Field -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" for="browser_os">Device / Browser Environment</label>
                    <input type="text" id="browser_os" name="browser_os" class="form-input" placeholder="e.g. Chrome 127 on macOS / Safari on iOS" value="<?php echo htmlspecialchars($_POST['browser_os'] ?? ''); ?>">
                </div>

                <!-- Screenshot Upload -->
                <div class="form-group" style="margin-bottom: 28px;">
                    <label class="form-label">Screenshot / Error Attachment (Optional)</label>
                    <?php if (isset($errors['screenshot'])): ?><div class="field-error"><?php echo htmlspecialchars($errors['screenshot']); ?></div><?php endif; ?>
                    <div class="file-upload-box" id="dropzone">
                        <input type="file" id="screenshot" name="screenshot" accept="image/*,.pdf" onchange="handleFileChosen(this)">
                        <div class="file-upload-icon"><i class="fi fi-rr-picture"></i></div>
                        <div class="file-upload-text">Click or drag & drop screenshot here</div>
                        <div class="file-upload-hint">PNG, JPG, WEBP, GIF or PDF (Max 5MB)</div>
                        <div class="file-chosen-name" id="chosenFileName"></div>
                    </div>
                </div>

                <div style="text-align: right;">
                    <button type="submit" name="submit_bug" value="1" class="btn btn-primary" style="padding: 12px 28px; font-weight: 700; font-size: 0.95rem; border-radius: var(--radius-md);">
                        <i class="fi fi-rr-paper-plane" style="margin-right: 6px;"></i> Submit Bug Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Ticket Tracker Card -->
        <div class="track-ticket-card">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">
                <i class="fi fi-rr-search-alt" style="color: var(--primary); margin-right: 6px;"></i> Track an Existing Bug Report
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px;">
                Enter your ticket code (e.g. <code>TKT-9F3A1B</code>) to check the current resolution status.
            </p>

            <!-- Error message shown ABOVE the input without any crossing icon -->
            <div id="trackTicketError" style="display: none; color: #dc2626; font-size: 0.84rem; font-weight: 600; margin-bottom: 10px;"></div>

            <form class="track-ticket-form" novalidate onsubmit="trackTicket(event)">
                <input type="text" id="trackTicketInput" class="form-input" placeholder="Enter Ticket Code (e.g. TKT-...)">
                <button type="submit" class="btn btn-outline" style="white-space: nowrap; font-weight: 700;">
                    Check Status
                </button>
            </form>

            <div id="ticketResultBox" class="ticket-status-result"></div>
        </div>

    </main>

    <!-- ===== FOOTER ===== -->
    <footer class="footer">
        <div class="footer-nav-links">
            <a href="index.php">Home</a>
            <a href="doctors.php">Doctors Directory</a>
            <a href="index.php#features">Features</a>
            <a href="privacy_policy.php">Privacy & Policy</a>
            <a href="cookie_policy.php">Cookie Policy</a>
            <a href="report_bug.php">Report a Bug</a>
            <a href="patient/login.php">Patient Login</a>
            <a href="doctor/login.php">Doctor Login</a>
        </div>
        <p>&copy; <?php echo date('Y'); ?> Medi-Care Hospital Management System. All rights reserved.</p>
    </footer>

    <!-- ===== JS LOGIC ===== -->
    <script>
        // Auto-detect browser and OS
        window.addEventListener('DOMContentLoaded', () => {
            const browserInput = document.getElementById('browser_os');
            if (browserInput && !browserInput.value) {
                const userAgent = navigator.userAgent;
                let browser = "Unknown Browser";
                let os = "Unknown OS";

                if (userAgent.indexOf("Win") !== -1) os = "Windows";
                if (userAgent.indexOf("Mac") !== -1) os = "macOS";
                if (userAgent.indexOf("Linux") !== -1) os = "Linux";
                if (userAgent.indexOf("Android") !== -1) os = "Android";
                if (userAgent.indexOf("like Mac") !== -1) os = "iOS";

                if (userAgent.indexOf("Chrome") !== -1 && userAgent.indexOf("Edg") === -1) browser = "Chrome";
                else if (userAgent.indexOf("Safari") !== -1 && userAgent.indexOf("Chrome") === -1) browser = "Safari";
                else if (userAgent.indexOf("Firefox") !== -1) browser = "Firefox";
                else if (userAgent.indexOf("Edg") !== -1) browser = "Edge";

                browserInput.value = `${browser} on ${os} (${window.innerWidth}x${window.innerHeight})`;
            }
        });

        // Severity Pills UI Update
        function updateSeverityPills() {
            document.querySelectorAll('.severity-pill-label').forEach(label => {
                const radio = label.querySelector('input[type="radio"]');
                label.className = 'severity-pill-label';
                if (radio.checked) {
                    label.classList.add(`active-${radio.value.toLowerCase()}`);
                }
            });
        }

        // File upload chosen handler
        function handleFileChosen(input) {
            const display = document.getElementById('chosenFileName');
            if (input.files && input.files[0]) {
                display.textContent = `✓ Selected: ${input.files[0].name} (${(input.files[0].size / 1024).toFixed(1)} KB)`;
                display.style.display = 'block';
            } else {
                display.textContent = '';
                display.style.display = 'none';
            }
        }

        // AJAX Track Ticket
        function trackTicket(e) {
            e.preventDefault();
            const input = document.getElementById('trackTicketInput');
            const code = input.value.trim().toUpperCase();
            const errorBox = document.getElementById('trackTicketError');
            const resultBox = document.getElementById('ticketResultBox');

            // Reset error & result
            errorBox.style.display = 'none';
            errorBox.textContent = '';
            input.classList.remove('input-error');
            resultBox.style.display = 'none';

            if (!code) {
                errorBox.textContent = 'Please enter a ticket code to track your issue.';
                errorBox.style.display = 'block';
                input.classList.add('input-error');
                input.focus();
                return;
            }

            resultBox.style.display = 'block';
            resultBox.innerHTML = '<span style="color: var(--text-muted);"><i class="fi fi-rr-spinner" style="animation: spin 1s infinite linear;"></i> Looking up ticket status...</span>';

            fetch(`report_bug.php?action=track_ticket&ticket=${encodeURIComponent(code)}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        resultBox.style.display = 'none';
                        errorBox.textContent = data.message || 'No bug report found with this ticket code.';
                        errorBox.style.display = 'block';
                        input.classList.add('input-error');
                        return;
                    }

                    const statusClass = data.status.toLowerCase().replace(/\s+/g, '-');
                    resultBox.innerHTML = `
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                            <div>
                                <span class="ticket-code-display" style="font-size: 1rem; margin: 0 0 6px 0; padding: 4px 10px;">${data.ticket}</span>
                                <h4 style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin-top: 4px;">${escapeHtml(data.title)}</h4>
                            </div>
                            <span class="status-badge ${statusClass}">${escapeHtml(data.status)}</span>
                        </div>
                        <div style="font-size: 0.82rem; color: var(--text-secondary); display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 8px;">
                            <span><strong>Category:</strong> ${escapeHtml(data.category)}</span>
                            <span><strong>Severity:</strong> ${escapeHtml(data.severity)}</span>
                            <span><strong>Reported:</strong> ${escapeHtml(data.created_at)}</span>
                        </div>
                        ${data.notes ? `
                            <div style="margin-top: 10px; padding: 10px 14px; background: rgba(91, 84, 224, 0.05); border-left: 3px solid var(--primary); border-radius: 4px; font-size: 0.84rem;">
                                <strong>Admin Update:</strong> ${escapeHtml(data.notes)}
                            </div>
                        ` : ''}
                    `;
                })
                .catch(() => {
                    resultBox.style.display = 'none';
                    errorBox.textContent = 'Failed to fetch ticket info. Please try again.';
                    errorBox.style.display = 'block';
                });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        // Navbar scroll effect
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            if (navbar) navbar.classList.toggle('scrolled', window.scrollY > 20);
        });

        // Mobile toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const navLinks = document.getElementById('navLinks');
        if (mobileToggle && navLinks) {
            mobileToggle.addEventListener('click', () => {
                navLinks.classList.toggle('open');
                mobileToggle.classList.toggle('active');
            });
        }
    </script>
</body>
</html>
