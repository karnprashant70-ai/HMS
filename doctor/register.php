<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Doctor Registration | Join MediCare+ Hospital Management Network">
    <title>Doctor Registration | MediCare+</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .bg-pattern::before {
            background: radial-gradient(circle, rgba(108, 99, 255, 0.12), transparent 70%);
        }
        .bg-pattern::after {
            background: radial-gradient(circle, rgba(0, 212, 170, 0.08), transparent 70%);
        }
    </style>
</head>
<body>

    <!-- Animated Background Blob -->
    <div class="bg-pattern"></div>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar" id="navbar">
        <a href="../index.php" class="nav-brand">
            <div class="nav-brand-icon">M+</div>
            <div class="nav-brand-text">Medi<span>Care+</span></div>
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
            <div class="auth-header">
                <div class="auth-logo">🩺</div>
                <h2>Doctor Application</h2>
                <p>Register to join Medicare+ health network</p>
            </div>

            <!-- Step Progress Indicator -->
            <div class="step-progress">
                <div class="progress-line" id="progressLine"></div>
                <div class="step-dot active" data-step="1" data-title="Personal">1</div>
                <div class="step-dot" data-step="2" data-title="Contact & Address">2</div>
                <div class="step-dot" data-step="3" data-title="Professional">3</div>
                <div class="step-dot" data-step="4" data-title="Security">4</div>
            </div>

            <form id="registrationForm" onsubmit="handleRegistrationSubmit(event)">
                <!-- STEP 1: PERSONAL DETAILS -->
                <div class="form-step active" id="step1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="firstName">First Name *</label>
                            <input type="text" id="firstName" class="form-input" required placeholder="John">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="middleName">Middle Name</label>
                            <input type="text" id="middleName" class="form-input" placeholder="Robert">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="lastName">Last Name *</label>
                            <input type="text" id="lastName" class="form-input" required placeholder="Doe">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="gender">Gender *</label>
                            <select id="gender" class="form-input" required>
                                <option value="" disabled selected>Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="maritalStatus">Marital Status *</label>
                            <select id="maritalStatus" class="form-input" required>
                                <option value="" disabled selected>Select Marital Status</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                        </div>

                        <!-- Profile Photo Upload -->
                        <div class="form-group">
                            <label class="form-label">Profile Photo *</label>
                            <div class="photo-upload-container">
                                <div class="photo-preview" id="photoPreview">
                                    <span>📷</span>
                                </div>
                                <div class="photo-upload-btn btn-auth btn-auth-secondary">
                                    Choose Photo
                                    <input type="file" id="profilePhoto" accept="image/*" required onchange="previewImage(event)">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <span style="color: var(--text-muted); font-size: 0.88rem; display: flex; align-items: center;">* Required Fields</span>
                        <button type="button" class="btn-auth btn-auth-primary" onclick="nextStep(2)">
                            Next Step
                        </button>
                    </div>
                </div>

                <!-- STEP 2: CONTACT & ADDRESS -->
                <div class="form-step" id="step2">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="phoneNumber">Phone Number *</label>
                            <input type="tel" id="phoneNumber" class="form-input" required placeholder="+1 (555) 000-0000">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">Email Address *</label>
                            <input type="email" id="email" class="form-input" required placeholder="dr.john@medicare.com">
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="tempAddress">Temporary Address *</label>
                            <textarea id="tempAddress" class="form-input" required placeholder="Street address, City, Country"></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="permAddress">Permanent Address *</label>
                            <textarea id="permAddress" class="form-input" required placeholder="Street address, City, Country"></textarea>
                            <div style="margin-top: 8px;">
                                <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.82rem; color: var(--text-secondary); cursor: pointer;">
                                    <input type="checkbox" id="sameAddress" onchange="copyAddress()"> Same as temporary address
                                </label>
                            </div>
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
                <div class="form-step" id="step3">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="department">Department *</label>
                            <select id="department" class="form-input" required>
                                <option value="" disabled selected>Select Department</option>
                                <option value="Cardiology">Cardiology</option>
                                <option value="Neurology">Neurology</option>
                                <option value="Orthopedics">Orthopedics</option>
                                <option value="Pediatrics">Pediatrics</option>
                                <option value="General Medicine">General Medicine</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="specialization">Specialization *</label>
                            <input type="text" id="specialization" class="form-input" required placeholder="e.g. Pediatric Cardiology">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="qualification">Qualification *</label>
                            <input type="text" id="qualification" class="form-input" required placeholder="e.g. MD, MBBS">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="licenceNumber">Medical Licence Number *</label>
                            <input type="text" id="licenceNumber" class="form-input" required placeholder="e.g. LIC-987654321">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="experience">Years of Experience *</label>
                            <input type="number" id="experience" class="form-input" required min="0" placeholder="e.g. 8">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="fee">Consultation Fee ($) *</label>
                            <input type="number" id="fee" class="form-input" required min="0" placeholder="e.g. 150">
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="availableTime">Available Time Schedule *</label>
                            <input type="text" id="availableTime" class="form-input" required placeholder="e.g. Mon-Fri (09:00 AM - 04:00 PM)">
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
                <div class="form-step" id="step4">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="password">Password *</label>
                            <input type="password" id="password" class="form-input" required placeholder="••••••••">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirmPassword">Confirm Password *</label>
                            <input type="password" id="confirmPassword" class="form-input" required placeholder="••••••••">
                        </div>

                        <!-- Info Fields -->
                        <div class="form-group full-width" style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-glass); border-radius: var(--radius-sm); padding: 16px;">
                            <h4 style="font-size: 0.88rem; font-weight: 700; margin-bottom: 8px;">Registration Details</h4>
                            <p style="font-size: 0.8rem; color: var(--text-secondary); line-height: 1.5;">
                                <strong>Doctor ID:</strong> <span style="color: var(--accent-light);">Assigning automatically...</span><br>
                                <strong>Status:</strong> <span style="color: var(--accent);">Pending Verification (Active)</span><br>
                                <strong>Date Registered:</strong> <span id="createdAtPlaceholder"></span>
                            </p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-auth btn-auth-secondary" onclick="prevStep(3)">
                            Back
                        </button>
                        <button type="submit" class="btn-auth btn-auth-primary">
                            Register Profile
                        </button>
                    </div>
                </div>
            </form>

            <!-- Success & OTP Step -->
            <div id="otpStep" class="form-step">
                <div class="auth-header">
                    <div class="auth-logo" style="background: linear-gradient(135deg, var(--accent), var(--primary));">🔑</div>
                    <h2>Verify Your Code</h2>
                    <p>Enter the 6-digit OTP code sent to your email to verify your registration</p>
                </div>

                <form id="otpForm" onsubmit="handleRegistrationVerify(event)">
                    <div class="otp-container">
                        <input type="text" class="otp-input" maxlength="1" required data-index="0" inputmode="numeric" pattern="[0-9]*">
                        <input type="text" class="otp-input" maxlength="1" required data-index="1" inputmode="numeric" pattern="[0-9]*">
                        <input type="text" class="otp-input" maxlength="1" required data-index="2" inputmode="numeric" pattern="[0-9]*">
                        <input type="text" class="otp-input" maxlength="1" required data-index="3" inputmode="numeric" pattern="[0-9]*">
                        <input type="text" class="otp-input" maxlength="1" required data-index="4" inputmode="numeric" pattern="[0-9]*">
                        <input type="text" class="otp-input" maxlength="1" required data-index="5" inputmode="numeric" pattern="[0-9]*">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-auth btn-auth-primary" style="width: 100%;">
                            Submit & Finalize
                        </button>
                    </div>
                </form>

                <div class="auth-footer-links" style="justify-content: center; gap: 8px;">
                    <span style="color: var(--text-muted)">Didn't receive code?</span>
                    <a href="#" onclick="resendOTP(event)">Resend Code</a>
                </div>
            </div>

            <div class="auth-footer-links" style="justify-content: center; margin-top: 32px;">
                <span style="color: var(--text-muted);">Already have a portal account?</span>
                <a href="login.php" style="margin-left: 6px; color: var(--primary-light); font-weight: 600;">Log In here</a>
            </div>
        </div>
    </div>

    <!-- ===== Footer ===== -->
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> MediCare+ Hospital Management System. All rights reserved.</p>
    </footer>

    <!-- ===== JS LOGIC ===== -->
    <script>
        // Set creation date
        document.getElementById('createdAtPlaceholder').innerText = new Date().toLocaleString();

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
        let currentStep = 1;
        const totalSteps = 4;
        const progressLine = document.getElementById('progressLine');
        const dots = document.querySelectorAll('.step-dot');

        function updateProgress() {
            // Update progress line width
            const progressPct = ((currentStep - 1) / (totalSteps - 1)) * 100;
            progressLine.style.width = `${progressPct}%`;

            // Update step dots classes
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

        function validateStep(step) {
            // Validate all inputs in current step
            const container = document.getElementById(`step${step}`);
            const inputs = container.querySelectorAll('input, select, textarea');
            let isValid = true;

            inputs.forEach(input => {
                if (!input.checkValidity()) {
                    input.reportValidity();
                    isValid = false;
                }
            });

            return isValid;
        }

        function nextStep(step) {
            if (validateStep(currentStep)) {
                document.getElementById(`step${currentStep}`).classList.remove('active');
                currentStep = step;
                document.getElementById(`step${currentStep}`).classList.add('active');
                updateProgress();
            }
        }

        function prevStep(step) {
            document.getElementById(`step${currentStep}`).classList.remove('active');
            currentStep = step;
            document.getElementById(`step${currentStep}`).classList.add('active');
            updateProgress();
        }

        // Handle Registration Submit
        function handleRegistrationSubmit(event) {
            event.preventDefault();

            // Additional verification on password matching
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (password !== confirmPassword) {
                alert("Passwords do not match! Please verify your password entry.");
                return;
            }

            // Hide registration form steps
            document.getElementById('step4').classList.remove('active');
            // Hide progress line/dots completely for OTP verification screen
            document.querySelector('.step-progress').style.display = 'none';

            // Show OTP step
            document.getElementById('otpStep').classList.add('active');
            
            // Focus on first OTP input
            setTimeout(() => {
                otpInputs[0].focus();
            }, 100);
        }

        // OTP inputs auto-advance
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

        function resendOTP(event) {
            event.preventDefault();
            alert("A registration OTP code has been resent to your email.");
            otpInputs.forEach(input => input.value = '');
            otpInputs[0].focus();
        }

        // Handle OTP verification submit
        function handleRegistrationVerify(event) {
            event.preventDefault();
            let otp = "";
            otpInputs.forEach(input => otp += input.value);

            alert(`Registration form submitted and OTP verified! Your account is created. Redirecting to Doctor login...`);
            window.location.href = "login.php";
        }
    </script>
</body>
</html>
