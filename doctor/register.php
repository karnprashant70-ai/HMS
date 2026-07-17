<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/register-debug.log');
error_log('register.php debug start');
ob_start();
session_start();

require_once __DIR__ . '/../db-connection/db_conn.php';

// Fetch departments from database
$validDepartments = [];
$deptRes = $conn->query("SELECT department_name FROM tbl_department ORDER BY department_name ASC");
if ($deptRes) {
    while ($row = $deptRes->fetch_assoc()) {
        $validDepartments[] = $row['department_name'];
    }
}

// ===== PHP SERVER-SIDE VALIDATION =====
$errors = [];
$formData = [];
$showOtp = false;
$activeStep = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    error_log('register.php POST start');
    error_log('POST keys: ' . implode(',', array_keys($_POST)));
    error_log('FILES keys: ' . implode(',', array_keys($_FILES)));
    // Sanitize and collect form data
    $formData['firstName'] = trim($_POST['firstName'] ?? '');
    $formData['middleName'] = trim($_POST['middleName'] ?? '');
    $formData['lastName'] = trim($_POST['doctor_lname'] ?? '');
    $formData['gender'] = trim($_POST['gender'] ?? '');
    $formData['maritalStatus'] = trim($_POST['maritalStatus'] ?? '');
    $formData['phoneNumber'] = trim($_POST['phoneNumber'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['tempAddress'] = trim($_POST['tempAddress'] ?? '');
    $formData['permAddress'] = trim($_POST['permAddress'] ?? '');
    $formData['department'] = trim($_POST['department'] ?? '');
    $formData['specialization'] = trim($_POST['specialization'] ?? '');
    $formData['qualification'] = trim($_POST['qualification'] ?? '');
    $formData['licenceNumber'] = trim($_POST['licenceNumber'] ?? '');
    $formData['experience'] = trim($_POST['experience'] ?? '');
    $formData['password'] = $_POST['password'] ?? '';
    $formData['confirmPassword'] = $_POST['confirmPassword'] ?? '';

    // ===== STEP 1: PERSONAL DETAILS VALIDATION =====
    if (empty($formData['firstName'])) {
        $errors['firstName'] = 'First name is required.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $formData['firstName'])) {
        $errors['firstName'] = 'First name must contain only letters.';
    } elseif (strlen($formData['firstName']) > 50) {
        $errors['firstName'] = 'First name must be less than 50 characters.';
    }

    if (!empty($formData['middleName'])) {
        if (!preg_match('/^[a-zA-Z\s]+$/', $formData['middleName'])) {
            $errors['middleName'] = 'Middle name must contain only letters.';
        } elseif (strlen($formData['middleName']) > 50) {
            $errors['middleName'] = 'Middle name must be less than 50 characters.';
        }
    }

    if (empty($formData['lastName'])) {
        $errors['lastName'] = 'Last name is required.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $formData['lastName'])) {
        $errors['lastName'] = 'Last name must contain only letters.';
    } elseif (strlen($formData['lastName']) > 50) {
        $errors['lastName'] = 'Last name must be less than 50 characters.';
    }

    $validGenders = ['Male', 'Female', 'Other'];
    if (empty($formData['gender'])) {
        $errors['gender'] = 'Please select your gender.';
    } elseif (!in_array($formData['gender'], $validGenders)) {
        $errors['gender'] = 'Invalid gender selection.';
    }

    $validStatuses = ['Single', 'Married', 'Divorced', 'Widowed'];
    if (empty($formData['maritalStatus'])) {
        $errors['maritalStatus'] = 'Please select your marital status.';
    } elseif (!in_array($formData['maritalStatus'], $validStatuses)) {
        $errors['maritalStatus'] = 'Invalid marital status selection.';
    }

    // Profile Photo validation (optional)
    if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profilePhoto']['error'] !== UPLOAD_ERR_OK) {
            $errors['profilePhoto'] = 'Error uploading profile photo. Please try again.';
        } else {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($_FILES['profilePhoto']['type'], $allowedTypes)) {
                $errors['profilePhoto'] = 'Photo must be JPEG, PNG, GIF, or WebP format.';
            } elseif ($_FILES['profilePhoto']['size'] > 5 * 1024 * 1024) {
                $errors['profilePhoto'] = 'Photo must be less than 5MB.';
            }
        }
    }

    // ===== STEP 2: CONTACT & ADDRESS VALIDATION =====
    if (empty($formData['phoneNumber'])) {
        $errors['phoneNumber'] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9]{10}$/', $formData['phoneNumber'])) {
        $errors['phoneNumber'] = 'Enter a valid 10-digit Nepali phone number.';
    } elseif (!preg_match('/^(97|98)/', $formData['phoneNumber'])) {
        $errors['phoneNumber'] = 'Nepali mobile numbers must start with 97 or 98.';
    }

    if (empty($formData['email'])) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (empty($formData['tempAddress'])) {
        $errors['tempAddress'] = 'Temporary address is required.';
    } elseif (strlen($formData['tempAddress']) < 5) {
        $errors['tempAddress'] = 'Please provide a more detailed address.';
    } elseif (!preg_match('/^[A-Za-z]/', $formData['tempAddress'])) {
        $errors['tempAddress'] = 'Temporary address must start with a letter.';
    }

    if (empty($formData['permAddress'])) {
        $errors['permAddress'] = 'Permanent address is required.';
    } elseif (strlen($formData['permAddress']) < 5) {
        $errors['permAddress'] = 'Please provide a more detailed address.';
    } elseif (!preg_match('/^[A-Za-z]/', $formData['permAddress'])) {
        $errors['permAddress'] = 'Permanent address must start with a letter.';
    }

    // ===== STEP 3: PROFESSIONAL INFO VALIDATION =====
    if (empty($formData['department'])) {
        $errors['department'] = 'Please select a department.';
    } elseif (!in_array($formData['department'], $validDepartments)) {
        $errors['department'] = 'Invalid department selection.';
    }

    if (empty($formData['specialization'])) {
        $errors['specialization'] = 'Specialization is required.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $formData['specialization'])) {
        $errors['specialization'] = 'Specialization must contain only letters and spaces.';
    } elseif (strlen($formData['specialization']) > 100) {
        $errors['specialization'] = 'Specialization must be less than 100 characters.';
    }

    if (empty($formData['qualification'])) {
        $errors['qualification'] = 'Qualification is required.';
    } elseif (preg_match('/\d/', $formData['qualification'])) {
        $errors['qualification'] = 'Qualification must not contain numbers.';
    } elseif (strlen($formData['qualification']) > 100) {
        $errors['qualification'] = 'Qualification must be less than 100 characters.';
    }

    // NMC Registration Number (Nepal Medical Council)
    $licRaw = strtoupper(trim($formData['licenceNumber'] ?? ''));
    if (empty($licRaw)) {
        $errors['licenceNumber'] = 'NMC registration number is required.';
    } elseif (!preg_match('/^NMC[-\/]?\d{4,6}$/i', $licRaw)) {
        $errors['licenceNumber'] = 'Must be a valid NMC number (examples: NMC-12345, NMC/12345, NMC12345).';
    } else {
        // Normalize to NMC-<digits>
        $licNorm = preg_replace('/^NMC[-\/]?(\d{4,6})$/i', 'NMC-$1', $licRaw);
        $formData['licenceNumber'] = $licNorm;
    }

    if ($formData['experience'] === '') {
        $errors['experience'] = 'Years of experience is required.';
    } elseif (!is_numeric($formData['experience']) || intval($formData['experience']) < 0) {
        $errors['experience'] = 'Experience must be a valid non-negative number.';
    } elseif (intval($formData['experience']) > 70) {
        $errors['experience'] = 'Please enter a realistic number of years.';
    }

    // ===== STEP 4: SECURITY VALIDATION =====
    if (empty($formData['password'])) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($formData['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $formData['password'])) {
        $errors['password'] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $formData['password'])) {
        $errors['password'] = 'Password must contain at least one number.';
    }

    if (empty($formData['confirmPassword'])) {
        $errors['confirmPassword'] = 'Please confirm your password.';
    } elseif ($formData['password'] !== $formData['confirmPassword']) {
        $errors['confirmPassword'] = 'Passwords do not match.';
    }

    // Determine which step has the first error
    if (!empty($errors)) {
        $step1Fields = ['firstName', 'middleName', 'lastName', 'gender', 'maritalStatus', 'profilePhoto'];
        $step2Fields = ['phoneNumber', 'email', 'tempAddress', 'permAddress'];
        $step3Fields = ['department', 'specialization', 'qualification', 'licenceNumber', 'experience'];
        $step4Fields = ['password', 'confirmPassword'];

        foreach (array_keys($errors) as $key) {
            if (in_array($key, $step1Fields)) { $activeStep = 1; break; }
            if (in_array($key, $step2Fields)) { $activeStep = 2; break; }
            if (in_array($key, $step3Fields)) { $activeStep = 3; break; }
            if (in_array($key, $step4Fields)) { $activeStep = 4; break; }
        }
    }

    // If no errors, proceed to database insertion
    if (empty($errors)) {
        require_once __DIR__ . '/../db-connection/db_conn.php';
        
        // Handle file upload (optional)
        $uploadDir = __DIR__ . '/../uploads/doctors/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                error_log('Failed to create upload directory: ' . $uploadDir);
                $errors['profilePhoto'] = 'Server error: cannot create upload directory.';
            }
        }

        $fileName = '';
        if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] === UPLOAD_ERR_OK) {
            error_log('Attempting file upload: ' . print_r($_FILES['profilePhoto'], true));
            $fileName = time() . '_' . basename($_FILES['profilePhoto']['name']);
            $targetFilePath = $uploadDir . $fileName;
            if (!move_uploaded_file($_FILES['profilePhoto']['tmp_name'], $targetFilePath)) {
                error_log('move_uploaded_file failed. tmp_name=' . ($_FILES['profilePhoto']['tmp_name'] ?? ''));
                $errors['profilePhoto'] = "Failed to upload profile photo.";
            } else {
                error_log('File uploaded to: ' . $targetFilePath);
            }
        }

        if (empty($errors)) {
            $hashedPassword = password_hash($formData['password'], PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO tbl_doctor (first_name, middle_name, last_name, gender, marital_status, profile_photo, phone_number, email, temporary_address, permanent_address, department, specialization, qualification, licence_number, years_experience, consultation_fee, available_time, status, OTP, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Check prepare success
            if (!$stmt) {
                $errors['db'] = 'Database prepare error: ' . $conn->error;
                error_log('DB prepare failed: ' . $conn->error);
            } else {
                error_log('DB prepare OK');
            }

            // Prepare variables for bind_param (must be variables, not expressions)
            $b_firstName = $formData['firstName'];
            $b_middleName = $formData['middleName'];
            $b_lastName = $formData['lastName'];
            $b_gender = $formData['gender'];
            $b_maritalStatus = $formData['maritalStatus'];
            $b_profilePhoto = $fileName !== '' ? $fileName : null;
            $b_phoneNumber = $formData['phoneNumber'];
            $b_email = $formData['email'];
            $b_tempAddress = $formData['tempAddress'];
            $b_permAddress = $formData['permAddress'];
            $b_department = $formData['department'];
            $b_specialization = $formData['specialization'];
            $b_qualification = $formData['qualification'];
            $b_licenceNumber = $formData['licenceNumber'];
            $b_experience = intval($formData['experience']);
            $b_consultationFee = 0.00;
            $b_availableTime = '';
            $b_status = 'Available';
            $b_otp = '000000';
            $b_password = $hashedPassword;

            if (empty($errors)) {
                // Prevent duplicate licence numbers or emails before executing the insert.
                $dupCheckStmt = $conn->prepare('SELECT doctor_id, email, licence_number FROM tbl_doctor WHERE email = ? OR licence_number = ? LIMIT 1');
                if ($dupCheckStmt) {
                    $dupCheckStmt->bind_param('ss', $b_email, $b_licenceNumber);
                    $dupCheckStmt->execute();
                    $dupRes = $dupCheckStmt->get_result()->fetch_assoc();
                    $dupCheckStmt->close();
                    if ($dupRes) {
                        if ($dupRes['email'] === $b_email) {
                            $errors['email'] = 'This email address is already registered.';
                        }
                        if ($dupRes['licence_number'] === $b_licenceNumber) {
                            $errors['licenceNumber'] = 'This NMC registration number is already in use.';
                        }
                    }
                }
            }

            if (empty($errors)) {
                $types = str_repeat('s', 14) . 'id' . str_repeat('s', 4);
                error_log('Bind types: ' . $types);
                error_log('Bind values preview: ' . json_encode([
                    'first' => $b_firstName,
                    'middle' => $b_middleName,
                    'last' => $b_lastName,
                    'gender' => $b_gender,
                    'maritalStatus' => $b_maritalStatus,
                    'profilePhoto' => $b_profilePhoto,
                    'phone' => $b_phoneNumber,
                    'email' => $b_email,
                    'dept' => $b_department,
                    'experience' => $b_experience
                ]));

                if ($stmt->bind_param($types,
                    $b_firstName, $b_middleName, $b_lastName,
                    $b_gender, $b_maritalStatus, $b_profilePhoto,
                    $b_phoneNumber, $b_email, $b_tempAddress,
                    $b_permAddress, $b_department, $b_specialization,
                    $b_qualification, $b_licenceNumber, $b_experience,
                    $b_consultationFee, $b_availableTime, $b_status,
                    $b_otp, $b_password
                )) {
                    error_log('bind_param OK');
                    try {
                        if ($stmt->execute()) {
                            // Registration successful
                            $successMessage = 'Registration completed successfully. Redirecting to login...';
                            error_log('Insert executed, insert_id=' . $stmt->insert_id);
                        }
                    } catch (mysqli_sql_exception $e) {
                        error_log('Execute exception: ' . $e->getMessage());
                        if ($e->getCode() === 1062) {
                            if (strpos($e->getMessage(), 'licence_number') !== false) {
                                $errors['licenceNumber'] = 'This NMC registration number is already in use.';
                            } elseif (strpos($e->getMessage(), 'email') !== false) {
                                $errors['email'] = 'This email address is already registered.';
                            } else {
                                $errors['db'] = 'A duplicate record already exists. Please use a different email or NMC number.';
                            }
                        } else {
                            $errors['db'] = 'Error inserting data: ' . $e->getMessage();
                        }
                    }
                } else {
                    $errors['db'] = 'Failed to bind parameters: ' . $stmt->error;
                    error_log('bind_param failed: ' . $stmt->error);
                }
            }
            if ($stmt) {
                $stmt->close();
            }
        }
        $conn->close();
    }
}

// Helper: render an inline error message
function showError($errors, $field) {
    if (isset($errors[$field])) {
        return '<span class="form-error" data-error-for="' . htmlspecialchars($field) . '">' . htmlspecialchars($errors[$field]) . '</span>';
    }
    return '';
}

// Helper: get submitted value for re-populating form
function oldVal($formData, $field) {
    return htmlspecialchars($formData[$field] ?? '');
}

// Helper: return error CSS class if field has error
function errClass($errors, $field) {
    return isset($errors[$field]) ? ' input-error' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Doctor Registration | Join Medi-Care Hospital Management Network">
    <title>Doctor Registration | Medi-Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/main.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/doctor-register.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- Animated Background Blob -->
    <div class="bg-pattern"></div>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar" id="navbar">
        <a href="../index.php" class="nav-brand">
            <div class="nav-brand-icon">M+</div>
            <div class="nav-brand-text">Medi-<span>Care</span></div>
        </a>

        <ul class="nav-links" id="navLinks">
            <li><a href="../index.php" class="nav-link">Home</a></li>
            <li><a href="../index.php#features" class="nav-link">Features</a></li>
            <li><a href="#" class="nav-link">About</a></li>

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
                    <a href="login.php" class="dropdown-item" role="menuitem">
                        <div class="dropdown-item-icon doctor">🩺</div>
                        <div class="dropdown-item-info">
                            <h4>Doctor</h4>
                            <p>Access your dashboard</p>
                        </div>
                    </a>
                    <a href="../patient/login.php" class="dropdown-item" role="menuitem">
                        <div class="dropdown-item-icon patient">🧑</div>
                        <div class="dropdown-item-info">
                            <h4>Patient</h4>
                            <p>View appointments &amp; records</p>
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
                    <a href="register.php" class="dropdown-item" role="menuitem">
                        <div class="dropdown-item-icon doctor">🩺</div>
                        <div class="dropdown-item-info">
                            <h4>Doctor</h4>
                            <p>Join our medical network</p>
                        </div>
                    </a>
                    <a href="../patient/register.php" class="dropdown-item" role="menuitem">
                        <div class="dropdown-item-icon patient">🧑</div>
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

    <!-- ===== MAIN CONTENT ===== -->
    <div class="auth-wrapper">
        <div class="auth-card large" id="authCard">

            <?php if (!$showOtp): ?>
            <!-- ===== REGISTRATION FORM ===== -->
            <div class="auth-header">
                <div class="auth-logo">🩺</div>
                <h2>Doctor Application</h2>
                <p>Register to join Medi-Care health network</p>
            </div>

            <!-- Step Progress Indicator -->
            <div class="step-progress">
                <div class="progress-line" id="progressLine"></div>
                <div class="step-dot <?php echo ($activeStep === 1) ? 'active' : (($activeStep > 1) ? 'completed' : ''); ?>" data-step="1" data-title="Personal">1</div>
                <div class="step-dot <?php echo ($activeStep === 2) ? 'active' : (($activeStep > 2) ? 'completed' : ''); ?>" data-step="2" data-title="Contact &amp; Address">2</div>
                <div class="step-dot <?php echo ($activeStep === 3) ? 'active' : (($activeStep > 3) ? 'completed' : ''); ?>" data-step="3" data-title="Professional">3</div>
                <div class="step-dot <?php echo ($activeStep === 4) ? 'active' : ''; ?>" data-step="4" data-title="Security">4</div>
            </div>

            <?php if (!empty($successMessage)): ?>
            <div class="toast-popup show" id="successToast">
                <div class="toast-icon">✅</div>
                <p><?php echo htmlspecialchars($successMessage); ?></p>
            </div>
            <script>
                setTimeout(function() {
                    document.getElementById('successToast').classList.remove('show');
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 500);
                }, 2000);
            </script>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="error-banner">
                <span style="font-size: 1.2rem;">⚠️</span>
                <p>
                    <?php 
                        if (isset($errors['db'])) {
                            echo htmlspecialchars($errors['db']);
                        } else {
                            echo "Please fix the highlighted errors below to continue.";
                        }
                    ?>
                </p>
            </div>
            <?php endif; ?>

            <form id="registrationForm" method="POST" action="" enctype="multipart/form-data" novalidate>
                <!-- STEP 1: PERSONAL DETAILS -->
                <div class="form-step <?php echo ($activeStep === 1) ? 'active' : ''; ?>" id="step1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="firstName">First Name *</label>
                            <input type="text" id="firstName" name="firstName" class="form-input<?php echo errClass($errors, 'firstName'); ?>" placeholder="e.g. Ram" value="<?php echo oldVal($formData, 'firstName'); ?>">
                            <?php echo showError($errors, 'firstName'); ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="middleName">Middle Name</label>
                            <input type="text" id="middleName" name="middleName" class="form-input<?php echo errClass($errors, 'middleName'); ?>" placeholder="e.g. Bahadur" value="<?php echo oldVal($formData, 'middleName'); ?>">
                            <?php echo showError($errors, 'middleName'); ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="doctor_lname">Last Name *</label>
                            <input type="text" id="doctor_lname" name="doctor_lname" class="form-input<?php echo errClass($errors, 'lastName'); ?>" placeholder="e.g. Sharma" value="<?php echo oldVal($formData, 'lastName'); ?>" autocomplete="off">
                            <?php echo showError($errors, 'lastName'); ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="gender">Gender *</label>
                            <select id="gender" name="gender" class="form-input<?php echo errClass($errors, 'gender'); ?>">
                                <option value="" disabled <?php echo (empty($formData['gender'] ?? '')) ? 'selected' : ''; ?>>Select Gender</option>
                                <option value="Male" <?php echo (($formData['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (($formData['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (($formData['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <?php echo showError($errors, 'gender'); ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="maritalStatus">Marital Status *</label>
                            <select id="maritalStatus" name="maritalStatus" class="form-input<?php echo errClass($errors, 'maritalStatus'); ?>">
                                <option value="" disabled <?php echo (empty($formData['maritalStatus'] ?? '')) ? 'selected' : ''; ?>>Select Marital Status</option>
                                <option value="Single" <?php echo (($formData['maritalStatus'] ?? '') === 'Single') ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo (($formData['maritalStatus'] ?? '') === 'Married') ? 'selected' : ''; ?>>Married</option>
                                <option value="Divorced" <?php echo (($formData['maritalStatus'] ?? '') === 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Widowed" <?php echo (($formData['maritalStatus'] ?? '') === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                            <?php echo showError($errors, 'maritalStatus'); ?>
                        </div>

                        <!-- Profile Photo Upload -->
                        <div class="form-group">
                            <label class="form-label">Profile Photo </label>
                            <div class="photo-upload-container">
                                <div class="photo-preview" id="photoPreview">
                                    <span>📷</span>
                                </div>
                                <div class="photo-upload-btn btn-auth btn-auth-secondary">
                                    Choose Photo
                                    <input type="file" id="profilePhoto" name="profilePhoto" accept="image/*" onchange="previewImage(event)">
                                </div>
                            </div>
                            <?php echo showError($errors, 'profilePhoto'); ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <span class="required-note">* Required Fields</span>
                        <button type="button" class="btn-auth btn-auth-primary" onclick="nextStep(2)">
                            Next Step
                        </button>
                    </div>
                </div>

                <!-- STEP 2: CONTACT & ADDRESS -->
                <div class="form-step <?php echo ($activeStep === 2) ? 'active' : ''; ?>" id="step2">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="phoneNumber">Phone Number *</label>
                            <div class="phone-input-group">
                                <span class="phone-prefix">🇳🇵 +977</span>
                                <input type="tel" id="phoneNumber" name="phoneNumber" class="form-input<?php echo errClass($errors, 'phoneNumber'); ?>" placeholder="98XXXXXXXX" value="<?php echo oldVal($formData, 'phoneNumber'); ?>">
                            </div>
                            <?php echo showError($errors, 'phoneNumber'); ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">Email Address *</label>
                            <input type="text" id="email" name="email" class="form-input<?php echo errClass($errors, 'email'); ?>" placeholder="dr.ram@medicare.com" value="<?php echo oldVal($formData, 'email'); ?>">
                            <?php echo showError($errors, 'email'); ?>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="tempAddress">Temporary Address *</label>
                            <textarea id="tempAddress" name="tempAddress" class="form-input<?php echo errClass($errors, 'tempAddress'); ?>" placeholder="Street address, City, District"><?php echo oldVal($formData, 'tempAddress'); ?></textarea>
                            <?php echo showError($errors, 'tempAddress'); ?>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="permAddress">Permanent Address *</label>
                            <textarea id="permAddress" name="permAddress" class="form-input<?php echo errClass($errors, 'permAddress'); ?>" placeholder="Street address, City, District"><?php echo oldVal($formData, 'permAddress'); ?></textarea>
                            <div class="same-address-toggle">
                                <label>
                                    <input type="checkbox" id="sameAddress" onchange="copyAddress()"> Same as temporary address
                                </label>
                            </div>
                            <?php echo showError($errors, 'permAddress'); ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-auth btn-auth-secondary" onclick="prevStep(1)">
                            Back
                        </button>
                        <button type="button" class="btn-auth btn-auth-primary" onclick="nextStep(3)">
                            Next Step
                        </button>
                    </div>
                </div>

                <!-- STEP 3: PROFESSIONAL INFO -->
                <div class="form-step <?php echo ($activeStep === 3) ? 'active' : ''; ?>" id="step3">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="department">Department *</label>
                            <select id="department" name="department" class="form-input<?php echo errClass($errors, 'department'); ?>">
                                <option value="" disabled <?php echo (empty($formData['department'] ?? '')) ? 'selected' : ''; ?>>Select Department</option>
                                <?php foreach ($validDepartments as $deptName): ?>
                                    <option value="<?php echo htmlspecialchars($deptName); ?>" <?php echo (($formData['department'] ?? '') === $deptName) ? 'selected' : ''; ?>><?php echo htmlspecialchars($deptName); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php echo showError($errors, 'department'); ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="specialization">Specialization *</label>
                            <input type="text" id="specialization" name="specialization" class="form-input<?php echo errClass($errors, 'specialization'); ?>" placeholder="e.g. Pediatric Cardiology" value="<?php echo oldVal($formData, 'specialization'); ?>">
                            <?php echo showError($errors, 'specialization'); ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="qualification">Qualification *</label>
                            <input type="text" id="qualification" name="qualification" class="form-input<?php echo errClass($errors, 'qualification'); ?>" placeholder="e.g. MD, MBBS" value="<?php echo oldVal($formData, 'qualification'); ?>">
                            <?php echo showError($errors, 'qualification'); ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="licenceNumber">NMC Registration Number *</label>
                            <input type="text" id="licenceNumber" name="licenceNumber" class="form-input<?php echo errClass($errors, 'licenceNumber'); ?>" placeholder="e.g. NMC-12345" value="<?php echo oldVal($formData, 'licenceNumber'); ?>">
                            <?php echo showError($errors, 'licenceNumber'); ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="experience">Years of Experience *</label>
                            <select id="experience" name="experience" class="form-input<?php echo errClass($errors, 'experience'); ?>">
                                <option value="">Select years</option>
                                <?php for ($i = 0; $i <= 70; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ((string)($formData['experience'] ?? '') === (string)$i) ? 'selected' : ''; ?>><?php echo $i; ?><?php echo $i === 1 ? ' year' : ' years'; ?></option>
                                <?php endfor; ?>
                            </select>
                            <?php echo showError($errors, 'experience'); ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-auth btn-auth-secondary" onclick="prevStep(2)">
                            Back
                        </button>
                        <button type="button" class="btn-auth btn-auth-primary" onclick="nextStep(4)">
                            Next Step
                        </button>
                    </div>
                </div>

                <!-- STEP 4: SECURITY & REGISTRATION -->
                <div class="form-step <?php echo ($activeStep === 4) ? 'active' : ''; ?>" id="step4">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="password">Password *</label>
                            <input type="password" id="password" name="password" class="form-input<?php echo errClass($errors, 'password'); ?>" placeholder="••••••••">
                            <?php echo showError($errors, 'password'); ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirmPassword">Confirm Password *</label>
                            <input type="password" id="confirmPassword" name="confirmPassword" class="form-input<?php echo errClass($errors, 'confirmPassword'); ?>" placeholder="••••••••">
                            <?php echo showError($errors, 'confirmPassword'); ?>
                        </div>

                        <!-- Info Fields -->
                        <div class="form-group full-width registration-details-card">
                            <h4>Registration Details</h4>
                            <p>
                                <strong>Doctor ID:</strong> <span class="accent-text">Assigning automatically...</span><br>
                                <strong>Status:</strong> <span class="accent-text-success">Pending Verification (Active)</span><br>
                                <strong>Date Registered:</strong> <span id="createdAtPlaceholder"></span>
                            </p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-auth btn-auth-secondary" onclick="prevStep(3)">
                            Back
                        </button>
                        <button type="submit" name="register" value="1" class="btn-auth btn-auth-primary">
                            Register Profile
                        </button>
                    </div>
                </div>
            </form>

            <?php else: ?>
            <!-- ===== OTP VERIFICATION STEP ===== -->
            <div id="otpStep" class="form-step active">
                <div class="auth-header">
                    <div class="auth-logo" style="background: linear-gradient(135deg, var(--accent), var(--primary));">🔑</div>
                    <h2>Verify Your Code</h2>
                    <p>Enter the 6-digit OTP code sent to your email to verify your registration</p>
                </div>

                <form id="otpForm" onsubmit="handleRegistrationVerify(event)" novalidate>
                    <div class="otp-container">
                        <input type="text" class="otp-input" maxlength="1" data-index="0" inputmode="numeric">
                        <input type="text" class="otp-input" maxlength="1" data-index="1" inputmode="numeric">
                        <input type="text" class="otp-input" maxlength="1" data-index="2" inputmode="numeric">
                        <input type="text" class="otp-input" maxlength="1" data-index="3" inputmode="numeric">
                        <input type="text" class="otp-input" maxlength="1" data-index="4" inputmode="numeric">
                        <input type="text" class="otp-input" maxlength="1" data-index="5" inputmode="numeric">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-auth btn-auth-primary" style="width: 100%;">
                            Submit &amp; Finalize
                        </button>
                    </div>
                </form>

                <div class="auth-footer-links otp-help-links">
                    <span>Didn't receive code?</span>
                    <a href="#" onclick="resendOTP(event)">Resend Code</a>
                </div>
            </div>
            <?php endif; ?>

            <div class="auth-footer-links login-link-row">
                <span>Already have a portal account?</span>
                <a href="login.php">Log In here</a>
            </div>
        </div>
    </div>

    <!-- ===== Footer ===== -->
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> Medi-Care Hospital Management System. All rights reserved.</p>
    </footer>

    <!-- ===== JS LOGIC ===== -->
    <script>
        // Set creation date
        const createdAtPlaceholder = document.getElementById('createdAtPlaceholder');
        if (createdAtPlaceholder) createdAtPlaceholder.innerText = new Date().toLocaleString();

        // Navbar scroll effect
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            navbar.classList.toggle('scrolled', window.scrollY > 20);
        });

        // Mobile toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const navLinks = document.getElementById('navLinks');

        mobileToggle.addEventListener('click', () => {
            navLinks.classList.toggle('open');
            mobileToggle.classList.toggle('active');
        });

        // Mobile dropdown toggle
        const dropdowns = document.querySelectorAll('.nav-dropdown');
        dropdowns.forEach(dropdown => {
            const trigger = dropdown.querySelector('.dropdown-trigger');
            trigger.addEventListener('click', (e) => {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    dropdowns.forEach(d => {
                        if (d !== dropdown) d.classList.remove('active');
                    });
                    dropdown.classList.toggle('active');
                }
            });
        });

        // Preview Profile Image
        function previewImage(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('photoPreview');
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Profile Preview">`;
                }
                reader.readAsDataURL(file);
            }
        }

        // Copy Address helper
        function copyAddress() {
            const isChecked = document.getElementById('sameAddress').checked;
            const temp = document.getElementById('tempAddress').value;
            if (isChecked) {
                document.getElementById('permAddress').value = temp;
            }
        }

        // Multi-step Wizard Navigation
        let currentStep = <?php echo $activeStep; ?>;
        const totalSteps = 4;
        const progressLine = document.getElementById('progressLine');
        const dots = document.querySelectorAll('.step-dot');

        function updateProgress() {
            if (!progressLine) return;
            const progressPct = ((currentStep - 1) / (totalSteps - 1)) * 100;
            progressLine.style.width = `${progressPct}%`;

            dots.forEach(dot => {
                const stepNum = parseInt(dot.getAttribute('data-step'));
                if (stepNum < currentStep) {
                    dot.className = 'step-dot completed';
                } else if (stepNum === currentStep) {
                    dot.className = 'step-dot active';
                } else {
                    dot.className = 'step-dot';
                }
            });
        }

        function clearFieldError(fieldName) {
            const field = document.querySelector(`[name="${fieldName}"]`) || document.getElementById(fieldName);
            if (!field) return;

            field.classList.remove('input-error');
            const container = field.closest('.form-group') || field.parentElement;
            if (!container) return;

            const existing = container.querySelector(`[data-error-for="${fieldName}"]`);
            if (existing) existing.remove();
        }

        function showFieldError(fieldName, message) {
            const field = document.querySelector(`[name="${fieldName}"]`) || document.getElementById(fieldName);
            if (!field) return;

            field.classList.add('input-error');
            const container = field.closest('.form-group') || field.parentElement;
            if (!container) return;

            // Remove any unrelated existing error elements in this container
            const existingErrors = Array.from(container.querySelectorAll('.form-error'));
            existingErrors.forEach(el => {
                const target = el.getAttribute('data-error-for');
                if (!target || target !== fieldName) {
                    el.remove();
                }
            });

            let errorEl = container.querySelector(`[data-error-for="${fieldName}"]`);
            if (!errorEl) {
                errorEl = document.createElement('span');
                errorEl.className = 'form-error';
                errorEl.setAttribute('data-error-for', fieldName);
                container.appendChild(errorEl);
            }
            errorEl.textContent = message;
        }

        function clearStepErrors(step) {
            const fields = step === 1
                ? ['firstName', 'middleName', 'doctor_lname', 'gender', 'maritalStatus']
                : step === 2
                    ? ['phoneNumber', 'email', 'tempAddress', 'permAddress']
                    : step === 3
                        ? ['department', 'specialization', 'qualification', 'licenceNumber', 'experience']
                        : ['password', 'confirmPassword'];

            fields.forEach(clearFieldError);
        }

        function validateStep(step) {
            clearStepErrors(step);
            const errors = [];

            if (step === 1) {
                const firstName = document.querySelector('[name="firstName"]').value.trim();
                const lastName = document.querySelector('[name="doctor_lname"]').value.trim();
                const gender = document.querySelector('[name="gender"]').value;
                const maritalStatus = document.querySelector('[name="maritalStatus"]').value;

                if (!firstName) {
                    errors.push({ field: 'firstName', message: 'First name is required.' });
                } else if (!/^[A-Za-z\s]+$/.test(firstName)) {
                    errors.push({ field: 'firstName', message: 'First name must contain only letters and spaces.' });
                }

                const middleName = document.querySelector('[name="middleName"]').value.trim();
                if (middleName && !/^[A-Za-z\s]+$/.test(middleName)) {
                    errors.push({ field: 'middleName', message: 'Middle name must contain only letters and spaces.' });
                }

                if (!lastName) {
                    errors.push({ field: 'doctor_lname', message: 'Last name is required.' });
                } else if (!/^[A-Za-z\s]+$/.test(lastName)) {
                    errors.push({ field: 'doctor_lname', message: 'Last name must contain only letters and spaces.' });
                }
                if (!gender) {
                    errors.push({ field: 'gender', message: 'Please select your gender.' });
                }
                if (!maritalStatus) {
                    errors.push({ field: 'maritalStatus', message: 'Please select your marital status.' });
                }
            }

            if (step === 2) {
                const phoneNumber = document.querySelector('[name="phoneNumber"]').value.trim();
                const email = document.querySelector('[name="email"]').value.trim();
                const tempAddress = document.querySelector('[name="tempAddress"]').value.trim();
                const permAddress = document.querySelector('[name="permAddress"]').value.trim();

                if (!phoneNumber) {
                    errors.push({ field: 'phoneNumber', message: 'Phone number is required.' });
                } else if (!/^(97|98)\d{8}$/.test(phoneNumber)) {
                    errors.push({ field: 'phoneNumber', message: 'Enter a valid 10-digit Nepali phone number.' });
                }

                if (!email) {
                    errors.push({ field: 'email', message: 'Email address is required.' });
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    errors.push({ field: 'email', message: 'Please enter a valid email address.' });
                }

                            if (!tempAddress) {
                                errors.push({ field: 'tempAddress', message: 'Temporary address is required.' });
                            } else if (!/^[A-Za-z]/.test(tempAddress)) {
                                errors.push({ field: 'tempAddress', message: 'Temporary address must start with a letter.' });
                            }

                            if (!permAddress) {
                                errors.push({ field: 'permAddress', message: 'Permanent address is required.' });
                            } else if (!/^[A-Za-z]/.test(permAddress)) {
                                errors.push({ field: 'permAddress', message: 'Permanent address must start with a letter.' });
                            }
            }

            if (step === 3) {
                const department = document.querySelector('[name="department"]').value;
                const specialization = document.querySelector('[name="specialization"]').value.trim();
                const qualification = document.querySelector('[name="qualification"]').value.trim();
                const licenceNumber = document.querySelector('[name="licenceNumber"]').value.trim();
                const experience = document.querySelector('[name="experience"]').value.trim();

                if (!department) {
                    errors.push({ field: 'department', message: 'Please select a department.' });
                }
                if (!specialization) {
                    errors.push({ field: 'specialization', message: 'Specialization is required.' });
                } else if (!/^[A-Za-z\s]+$/.test(specialization)) {
                    errors.push({ field: 'specialization', message: 'Specialization must contain only letters and spaces.' });
                }
                if (!qualification) {
                    errors.push({ field: 'qualification', message: 'Qualification is required.' });
                } else if (/\d/.test(qualification)) {
                    errors.push({ field: 'qualification', message: 'Qualification must not contain numbers.' });
                }
                if (!licenceNumber) {
                    errors.push({ field: 'licenceNumber', message: 'NMC registration number is required.' });
                } else if (!/^NMC[-\/]?\d{4,6}$/i.test(licenceNumber)) {
                    errors.push({ field: 'licenceNumber', message: 'Enter a valid NMC number (e.g. NMC-12345 or NMC/12345).' });
                }
                if (!experience) {
                    errors.push({ field: 'experience', message: 'Years of experience is required.' });
                }
                // availableTime removed by design; no client-side requirement
            }

            if (step === 4) {
                const password = document.querySelector('[name="password"]').value;
                const confirmPassword = document.querySelector('[name="confirmPassword"]').value;

                if (!password) {
                    errors.push({ field: 'password', message: 'Password is required.' });
                } else if (password.length < 8) {
                    errors.push({ field: 'password', message: 'Password must be at least 8 characters long.' });
                } else if (!/[A-Z]/.test(password)) {
                    errors.push({ field: 'password', message: 'Password must contain at least one uppercase letter.' });
                } else if (!/[0-9]/.test(password)) {
                    errors.push({ field: 'password', message: 'Password must contain at least one number.' });
                }

                if (!confirmPassword) {
                    errors.push({ field: 'confirmPassword', message: 'Please confirm your password.' });
                } else if (password !== confirmPassword) {
                    errors.push({ field: 'confirmPassword', message: 'Passwords do not match.' });
                }
            }

            if (errors.length > 0) {
                errors.forEach(error => showFieldError(error.field, error.message));
                const firstField = document.querySelector(`[name="${errors[0].field}"]`) || document.getElementById(errors[0].field);
                if (firstField) firstField.focus();
                return false;
            }

            return true;
        }

        // Initialize progress on page load
        updateProgress();

        function nextStep(step) {
            if (step > currentStep && !validateStep(currentStep)) {
                return;
            }

            document.getElementById(`step${currentStep}`).classList.remove('active');
            currentStep = step;
            document.getElementById(`step${currentStep}`).classList.add('active');
            updateProgress();
        }

        function prevStep(step) {
            document.getElementById(`step${currentStep}`).classList.remove('active');
            currentStep = step;
            document.getElementById(`step${currentStep}`).classList.add('active');
            updateProgress();
        }

        document.getElementById('registrationForm').addEventListener('submit', function (event) {
            if (!validateStep(4)) {
                event.preventDefault();
                const firstError = document.querySelector('.input-error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });

        // OTP inputs auto-advance (only if OTP step exists)
        const otpInputs = document.querySelectorAll('.otp-input');
        otpInputs.forEach((input, idx) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value;
                if (value.length === 1 && idx < otpInputs.length - 1) {
                    otpInputs[idx + 1].focus();
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !input.value && idx > 0) {
                    otpInputs[idx - 1].focus();
                }
            });
        });

        // Immediate NMC registration field validation
        (function() {
            const licenceInput = document.querySelector('[name="licenceNumber"]');
            if (!licenceInput) return;

            function validateLicenceImmediate() {
                const v = licenceInput.value.trim();
                if (!v) {
                    clearFieldError('licenceNumber');
                    return;
                }
                const re = /^NMC[-\/]?\d{4,6}$/i;
                if (!re.test(v)) {
                    showFieldError('licenceNumber', 'Must be a valid NMC number (e.g. NMC-12345, NMC/12345, NMC12345).');
                } else {
                    clearFieldError('licenceNumber');
                }
            }

            licenceInput.addEventListener('input', validateLicenceImmediate);
            licenceInput.addEventListener('blur', validateLicenceImmediate);
        })();

        function resendOTP(event) {
            event.preventDefault();
            alert("A registration OTP code has been resent to your email.");
            otpInputs.forEach(input => input.value = '');
            if (otpInputs[0]) otpInputs[0].focus();
        }

        // Handle OTP verification submit
        function handleRegistrationVerify(event) {
            event.preventDefault();
            let otp = "";
            otpInputs.forEach(input => otp += input.value);

            if (otp.length < 6) {
                alert("Please enter the complete 6-digit OTP code.");
                return;
            }

            alert(`Registration form submitted and OTP verified! Your account is created. Redirecting to Doctor login...`);
            window.location.href = "login.php";
        }

        // Auto-focus first OTP input if OTP step is shown
        const otpStep = document.getElementById('otpStep');
        if (otpInputs.length > 0 && otpStep && otpStep.classList.contains('active')) {
            setTimeout(() => { if (otpInputs[0]) otpInputs[0].focus(); }, 200);
        }
    </script>
</body>
</html>
