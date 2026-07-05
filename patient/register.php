<?php
$errors = [];
$formData = [];
$activeStep = 1;
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $formData['firstName'] = trim($_POST['firstName'] ?? '');
    $formData['lastName'] = trim($_POST['lastName'] ?? '');
    $formData['gender'] = trim($_POST['gender'] ?? '');
    $formData['phoneNumber'] = trim($_POST['phoneNumber'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['address'] = trim($_POST['address'] ?? '');
    $formData['emergencyContact'] = trim($_POST['emergencyContact'] ?? '');
    $formData['password'] = $_POST['password'] ?? '';
    $formData['confirmPassword'] = $_POST['confirmPassword'] ?? '';

    if (empty($formData['firstName'])) {
        $errors['firstName'] = 'First name is required.';
    }

    if (empty($formData['lastName'])) {
        $errors['lastName'] = 'Last name is required.';
    }

    if (empty($formData['gender'])) {
        $errors['gender'] = 'Please select your gender.';
    }

    if (empty($formData['phoneNumber'])) {
        $errors['phoneNumber'] = 'Phone number is required.';
    } elseif (!preg_match('/^(97|98)\d{8}$/', $formData['phoneNumber'])) {
        $errors['phoneNumber'] = 'Enter a valid 10-digit Nepali phone number.';
    }

    if (empty($formData['email'])) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (empty($formData['address'])) {
        $errors['address'] = 'Address is required.';
    }

    if (empty($formData['emergencyContact'])) {
        $errors['emergencyContact'] = 'Emergency contact is required.';
    }

    if (empty($formData['password'])) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($formData['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    }

    if (empty($formData['confirmPassword'])) {
        $errors['confirmPassword'] = 'Please confirm your password.';
    } elseif ($formData['password'] !== $formData['confirmPassword']) {
        $errors['confirmPassword'] = 'Passwords do not match.';
    }

    if (!empty($errors)) {
        $activeStep = 1;
        foreach (array_keys($errors) as $field) {
            if (in_array($field, ['phoneNumber', 'email', 'address', 'emergencyContact'])) {
                $activeStep = 2;
                break;
            }
            if (in_array($field, ['password', 'confirmPassword'])) {
                $activeStep = 3;
                break;
            }
        }
    } else {
        $successMessage = 'Patient registration submitted successfully.';
    }
}

function showError($errors, $field) {
    if (isset($errors[$field])) {
        return '<span class="form-error">' . htmlspecialchars($errors[$field]) . '</span>';
    }
    return '';
}

function oldVal($formData, $field) {
    return htmlspecialchars($formData[$field] ?? '');
}

function errClass($errors, $field) {
    return isset($errors[$field]) ? ' input-error' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration | MediCare+</title>
    <link rel="stylesheet" href="../css/main.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/patient-register.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card large">
            <div class="auth-header">
                <div class="auth-logo">🧑</div>
                <h2>Patient Registration</h2>
                <p>Create your account to access appointments and records.</p>
            </div>

            <div class="step-progress">
                <div class="progress-line" id="progressLine"></div>
                <div class="step-dot <?php echo ($activeStep === 1) ? 'active' : (($activeStep > 1) ? 'completed' : ''); ?>">1</div>
                <div class="step-dot <?php echo ($activeStep === 2) ? 'active' : (($activeStep > 2) ? 'completed' : ''); ?>">2</div>
                <div class="step-dot <?php echo ($activeStep === 3) ? 'active' : ''; ?>">3</div>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="error-banner">
                <p>Please fix the highlighted errors below to continue.</p>
            </div>
            <?php endif; ?>

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

            <form id="registrationForm" method="POST" action="" novalidate>
                <div class="form-step <?php echo ($activeStep === 1) ? 'active' : ''; ?>" id="step1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="firstName">First Name *</label>
                            <input type="text" id="firstName" name="firstName" class="form-input<?php echo errClass($errors, 'firstName'); ?>" value="<?php echo oldVal($formData, 'firstName'); ?>">
                            <?php echo showError($errors, 'firstName'); ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="lastName">Last Name *</label>
                            <input type="text" id="lastName" name="lastName" class="form-input<?php echo errClass($errors, 'lastName'); ?>" value="<?php echo oldVal($formData, 'lastName'); ?>">
                            <?php echo showError($errors, 'lastName'); ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="gender">Gender *</label>
                            <select id="gender" name="gender" class="form-input<?php echo errClass($errors, 'gender'); ?>">
                                <option value="" <?php echo (empty($formData['gender'] ?? '')) ? 'selected' : ''; ?>>Select Gender</option>
                                <option value="Male" <?php echo (($formData['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (($formData['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (($formData['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <?php echo showError($errors, 'gender'); ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="phoneNumber">Phone Number *</label>
                            <input type="tel" id="phoneNumber" name="phoneNumber" class="form-input<?php echo errClass($errors, 'phoneNumber'); ?>" value="<?php echo oldVal($formData, 'phoneNumber'); ?>">
                            <?php echo showError($errors, 'phoneNumber'); ?>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn-auth btn-auth-primary" onclick="nextStep(2)">Next Step</button>
                    </div>
                </div>

                <div class="form-step <?php echo ($activeStep === 2) ? 'active' : ''; ?>" id="step2">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="email">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-input<?php echo errClass($errors, 'email'); ?>" value="<?php echo oldVal($formData, 'email'); ?>">
                            <?php echo showError($errors, 'email'); ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="emergencyContact">Emergency Contact *</label>
                            <input type="tel" id="emergencyContact" name="emergencyContact" class="form-input<?php echo errClass($errors, 'emergencyContact'); ?>" value="<?php echo oldVal($formData, 'emergencyContact'); ?>">
                            <?php echo showError($errors, 'emergencyContact'); ?>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label" for="address">Address *</label>
                            <textarea id="address" name="address" class="form-input<?php echo errClass($errors, 'address'); ?>"><?php echo oldVal($formData, 'address'); ?></textarea>
                            <?php echo showError($errors, 'address'); ?>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn-auth btn-auth-secondary" onclick="prevStep(1)">Back</button>
                        <button type="button" class="btn-auth btn-auth-primary" onclick="nextStep(3)">Next Step</button>
                    </div>
                </div>

                <div class="form-step <?php echo ($activeStep === 3) ? 'active' : ''; ?>" id="step3">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="password">Password *</label>
                            <input type="password" id="password" name="password" class="form-input<?php echo errClass($errors, 'password'); ?>">
                            <?php echo showError($errors, 'password'); ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="confirmPassword">Confirm Password *</label>
                            <input type="password" id="confirmPassword" name="confirmPassword" class="form-input<?php echo errClass($errors, 'confirmPassword'); ?>">
                            <?php echo showError($errors, 'confirmPassword'); ?>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn-auth btn-auth-secondary" onclick="prevStep(2)">Back</button>
                        <button type="submit" name="register" value="1" class="btn-auth btn-auth-primary">Register</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentStep = <?php echo $activeStep; ?>;
        const totalSteps = 3;
        const progressLine = document.getElementById('progressLine');
        const dots = document.querySelectorAll('.step-dot');

        function updateProgress() {
            if (!progressLine) return;
            const progressPct = ((currentStep - 1) / (totalSteps - 1)) * 100;
            progressLine.style.width = `${progressPct}%`;
            dots.forEach((dot, index) => {
                const stepNum = index + 1;
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
            const fields = step === 1 ? ['firstName', 'lastName', 'gender', 'phoneNumber'] : step === 2 ? ['email', 'emergencyContact', 'address'] : ['password', 'confirmPassword'];
            fields.forEach(clearFieldError);
        }

        function validateStep(step) {
            clearStepErrors(step);
            const errors = [];

            if (step === 1) {
                const firstName = document.querySelector('[name="firstName"]').value.trim();
                const lastName = document.querySelector('[name="lastName"]').value.trim();
                const gender = document.querySelector('[name="gender"]').value;
                const phoneNumber = document.querySelector('[name="phoneNumber"]').value.trim();

                if (!firstName) errors.push({ field: 'firstName', message: 'First name is required.' });
                if (!lastName) errors.push({ field: 'lastName', message: 'Last name is required.' });
                if (!gender) errors.push({ field: 'gender', message: 'Please select your gender.' });
                if (!phoneNumber) {
                    errors.push({ field: 'phoneNumber', message: 'Phone number is required.' });
                } else if (!/^(97|98)\d{8}$/.test(phoneNumber)) {
                    errors.push({ field: 'phoneNumber', message: 'Enter a valid 10-digit Nepali phone number.' });
                }
            }

            if (step === 2) {
                const email = document.querySelector('[name="email"]').value.trim();
                const emergencyContact = document.querySelector('[name="emergencyContact"]').value.trim();
                const address = document.querySelector('[name="address"]').value.trim();

                if (!email) {
                    errors.push({ field: 'email', message: 'Email address is required.' });
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    errors.push({ field: 'email', message: 'Please enter a valid email address.' });
                }
                if (!emergencyContact) errors.push({ field: 'emergencyContact', message: 'Emergency contact is required.' });
                if (!address) errors.push({ field: 'address', message: 'Address is required.' });
            }

            if (step === 3) {
                const password = document.querySelector('[name="password"]').value;
                const confirmPassword = document.querySelector('[name="confirmPassword"]').value;

                if (!password) {
                    errors.push({ field: 'password', message: 'Password is required.' });
                } else if (password.length < 8) {
                    errors.push({ field: 'password', message: 'Password must be at least 8 characters long.' });
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

        function nextStep(step) {
            if (step > currentStep && !validateStep(currentStep)) return;
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
            if (!validateStep(3)) {
                event.preventDefault();
            }
        });

        updateProgress();
    </script>
</body>
</html>
