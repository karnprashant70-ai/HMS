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
        $stmt = $conn->prepare('SELECT patient_id, password, first_name, middle_name, last_name FROM tbl_patient WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                // successful login
                $_SESSION['patient_id'] = $row['patient_id'];
                $_SESSION['patient_name'] = trim($row['first_name'] . ' ' . ($row['middle_name'] ?? '') . ' ' . $row['last_name']);
                $_SESSION['login_success'] = 'Login successful! Welcome back, ' . $row['first_name'] . '.';
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
    <meta name="description" content="Patient Login | MediCare+ Hospital Management System">
    <title>Patient Login | MediCare+</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/main.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth/patient-login.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/patient-login.css?v=<?php echo time(); ?>">
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
                    <a href="../doctor/login.php" class="dropdown-item" role="menuitem">
                        <div class="dropdown-item-icon doctor">🩺</div>
                        <div class="dropdown-item-info">
                            <h4>Doctor</h4>
                            <p>Access your dashboard</p>
                        </div>
                    </a>
                    <a href="login.php" class="dropdown-item" role="menuitem">
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
                    <a href="../doctor/register.php" class="dropdown-item" role="menuitem">
                        <div class="dropdown-item-icon doctor">🩺</div>
                        <div class="dropdown-item-info">
                            <h4>Doctor</h4>
                            <p>Join our medical network</p>
                        </div>
                    </a>
                    <a href="register.php" class="dropdown-item" role="menuitem">
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
            <!-- Login Form -->
            <div id="loginStep" class="form-step active">
                <div class="auth-header">
                    <div class="auth-logo">🧑</div>
                    <h2>Patient Portal</h2>
                    <p>Sign in to access your health dashboard</p>
                </div>

                <form id="loginForm" method="POST" action="" novalidate>
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="name@example.com" autocomplete="username" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" autocomplete="current-password">
                            <button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password">👁️</button>
                        </div>
                    </div>

                    <?php if (!empty($registrationSuccess)): ?>
                    <div class="toast-popup show" id="regSuccessToast">
                        <div class="toast-icon">✅</div>
                        <p><?php echo htmlspecialchars($registrationSuccess); ?></p>
                    </div>
                    <script>
                        setTimeout(function() {
                            document.getElementById('regSuccessToast').classList.remove('show');
                        }, 4000);
                    </script>
                    <?php endif; ?>

                    <?php if (!empty($loginError)): ?>
                    <div class="error-banner" style="background: rgba(255, 107, 107, 0.1); border: 1px solid rgba(255, 107, 107, 0.25); border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 16px;">
                        <p style="color: #FF6B6B; font-size: 0.88rem; font-weight: 500; margin: 0;"><?php echo htmlspecialchars($loginError); ?></p>
                    </div>
                    <?php endif; ?>

                    <button type="submit" name="login" value="1" class="btn-auth btn-auth-primary btn-full">Sign In</button>
                </form>

                <div class="auth-footer-links">
                    <a href="register.php">Create an Account</a>
                    <a href="#">Forgot Password?</a>
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

        // Password visibility toggle
        const passwordToggle = document.getElementById('passwordToggle');
        const passwordInput = document.getElementById('password');
        passwordToggle.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            passwordToggle.textContent = isPassword ? '🙈' : '👁️';
        });

        // Client-side validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            if (!email || !password) {
                e.preventDefault();
                // Shake the card
                const card = document.getElementById('authCard');
                card.style.animation = 'none';
                card.offsetHeight; // trigger reflow
                card.style.animation = 'shake 0.5s ease';
            }
        });
    </script>

</body>
</html>
