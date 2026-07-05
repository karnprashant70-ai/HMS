<?php
ob_start();
session_start();
$loginError = '';
$registrationSuccess = '';
if (!empty($_SESSION['registration_success'])) {
    $registrationSuccess = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    require_once __DIR__ . '/../db-connection/db_conn.php';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $loginError = 'Email and password are required.';
    } else {
        $stmt = $conn->prepare('SELECT doctor_id, password, first_name, middle_name, last_name FROM tbl_doctor WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                // successful login
                $_SESSION['doctor_id'] = $row['doctor_id'];
                $_SESSION['doctor_name'] = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
                $_SESSION['login_success'] = 'Login successful! Welcome back, Dr. ' . $row['first_name'] . '.';
                header('Location: dashboard.php');
                exit;
            } else {
                $loginError = 'Invalid credentials.';
            }
        } else {
            $loginError = 'No account found for that email.';
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Doctor Login | MediCare+ Hospital Management System">
    <title>Doctor Login | MediCare+</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/main.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/doctor-login.css?v=<?php echo time(); ?>">
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
        <div class="auth-card" id="authCard">
            <!-- Step 1: Login Form -->
            <div id="loginStep" class="form-step active">
                <div class="auth-header">
                    <div class="auth-logo">🩺</div>
                    <h2>Doctor Portal</h2>
                    <p>Enter your details below to log in</p>
                </div>

                <form id="loginForm" method="POST" action="" novalidate>
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="name@hospital.com" autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" autocomplete="current-password">
                    </div>
                    <?php if (!empty($registrationSuccess)): ?>
                    <div class="toast-popup show" id="regSuccessToast">
                        <div class="toast-icon">✅</div>
                        <p><?php echo htmlspecialchars($registrationSuccess); ?></p>
                    </div>
                    <script>
                        setTimeout(function() {
                            document.getElementById('regSuccessToast').classList.remove('show');
                        }, 3000);
                    </script>
                    <?php endif; ?>
                    <?php if (!empty($loginError)): ?>
                    <div class="error-banner">
                        <p><?php echo htmlspecialchars($loginError); ?></p>
                    </div>
                    <?php endif; ?>

                    <button type="submit" name="login" value="1" class="btn-auth btn-auth-primary btn-full">Login</button>
                </form>

                <div class="auth-footer-links">
                    <a href="register.php">Create an Account</a>
                    <a href="#">Forgot Password?</a>
                </div>
            </div>

            <!-- Step 2: OTP Verification -->
            <div id="otpStep" class="form-step">
                <div class="auth-header">
                    <div class="auth-logo otp-logo">🔑</div>
                    <h2>Two-Factor Auth</h2>
                    <p>We've sent a 6-digit OTP code to <strong id="userEmailPlaceholder">your email</strong></p>
                </div>

                <form id="otpForm" onsubmit="handleOTPVerify(event)">
                    <div class="otp-container">
                        <input type="text" class="otp-input" maxlength="1" required data-index="0" inputmode="numeric" pattern="[0-9]*">
                        <input type="text" class="otp-input" maxlength="1" required data-index="1" inputmode="numeric" pattern="[0-9]*">
                        <input type="text" class="otp-input" maxlength="1" required data-index="2" inputmode="numeric" pattern="[0-9]*">
                        <input type="text" class="otp-input" maxlength="1" required data-index="3" inputmode="numeric" pattern="[0-9]*">
                        <input type="text" class="otp-input" maxlength="1" required data-index="4" inputmode="numeric" pattern="[0-9]*">
                        <input type="text" class="otp-input" maxlength="1" required data-index="5" inputmode="numeric" pattern="[0-9]*">
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-auth btn-auth-secondary" onclick="backToLogin()">
                            Back
                        </button>
                        <button type="submit" class="btn-auth btn-auth-primary">
                            Verify & Login
                        </button>
                    </div>
                </form>

                <div class="auth-footer-links otp-help-links">
                    <span>Didn't receive code?</span>
                    <a href="#" onclick="resendOTP(event)">Resend Code</a>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Footer ===== -->
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> MediCare+ Hospital Management System. All rights reserved.</p>
    </footer>

    <!-- ===== JS LOGIC ===== -->
    <script>
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

        // Back to login form
        function backToLogin() {
            document.getElementById('otpStep').classList.remove('active');
            document.getElementById('loginStep').classList.add('active');
        }

        // Resend OTP trigger
        function resendOTP(event) {
            event.preventDefault();
            alert("A new 6-digit OTP code has been sent!");
            const otpInputs = document.querySelectorAll('.otp-input');
            otpInputs.forEach(input => input.value = '');
            if (otpInputs[0]) otpInputs[0].focus();
        }

        // OTP inputs auto-advance logic
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

        // Handle OTP verification (if used)
        function handleOTPVerify(event) {
            event.preventDefault();
            let otp = "";
            otpInputs.forEach(input => otp += input.value);
            alert(`OTP Verified successfully: ${otp}! Logging you into the Doctor dashboard...`);
            window.location.href = "dashboard.php";
        }
    </script>
</body>
</html>
