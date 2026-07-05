<?php
session_start();
require_once __DIR__ . '/../db-connection/db_conn.php';

$errors = [];
$email = '';

// Process login on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // PHP Validation — no HTML5 validation used
    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    }

    // Authenticate if no validation errors
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT admin_id, name, email, password, isAdmin, isStaff FROM tbl_admin WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            if (password_verify($password, $admin['password'])) {
                // Set session
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['isAdmin'] = $admin['isAdmin'];
                $_SESSION['isStaff'] = $admin['isStaff'];

                header("Location: profile.php");
                exit;
            } else {
                $errors[] = 'Incorrect password. Please try again.';
            }
        } else {
            $errors[] = 'No admin account found with that email.';
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
    <meta name="description" content="MediCare+ Admin Login — Secure access to the hospital management admin dashboard.">
    <title>Admin Login | MediCare+</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/auth/admin-login.css">
</head>
<body>

    <!-- Animated Background -->
    <div class="bg-pattern"></div>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar" id="navbar">
        <a href="../index.php" class="nav-brand">
            <div class="nav-brand-icon">M+</div>
            <div class="nav-brand-text">Medi<span>Care+</span></div>
        </a>
        <ul class="nav-links" id="navLinks">
            <li><a href="../index.php" class="nav-link">Home</a></li>
        </ul>
    </nav>

    <!-- ===== LOGIN FORM ===== -->
    <div class="auth-wrapper">
        <div class="auth-card">
            <!-- Header -->
            <div class="auth-header">
                <div class="auth-logo">🛡️</div>
                <h1>Admin Portal</h1>
                <p>Sign in to access the administration dashboard</p>
            </div>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert-box alert-error" id="errorAlert">
                    <div class="alert-icon">⚠️</div>
                    <div class="alert-content">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                    <button class="alert-close" onclick="this.parentElement.style.display='none'" aria-label="Close alert">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Login Form — novalidate disables HTML5 validation -->
            <form method="POST" action="" novalidate id="adminLoginForm">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon">✉️</span>
                        <input
                            type="text"
                            class="form-input has-icon <?php echo (!empty($errors) && !empty($email)) ? 'input-error' : ''; ?>"
                            id="email"
                            name="email"
                            placeholder="admin@medicare.com"
                            value="<?php echo htmlspecialchars($email); ?>"
                            autocomplete="email"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon">🔒</span>
                        <input
                            type="password"
                            class="form-input has-icon <?php echo (!empty($errors) && empty($email) === false) ? 'input-error' : ''; ?>"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                        >
                        <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                            <span class="eye-icon" id="eyeIcon">👁️</span>
                        </button>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-auth btn-auth-primary" id="loginBtn">
                        <span class="btn-text">Sign In</span>
                        <span class="btn-loader" style="display:none;">
                            <span class="spinner"></span>
                        </span>
                    </button>
                </div>
            </form>

            <!-- Footer -->
            <div class="auth-footer-links">
                <a href="../index.php">← Back to Home</a>
            </div>
        </div>
    </div>

    <script>
        // Navbar scroll
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            navbar.classList.toggle('scrolled', window.scrollY > 20);
        });

        // Toggle password visibility
        const toggleBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        toggleBtn.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            eyeIcon.textContent = isPassword ? '🙈' : '👁️';
        });

        // Button loading state on submit
        const form = document.getElementById('adminLoginForm');
        form.addEventListener('submit', () => {
            const btn = document.getElementById('loginBtn');
            btn.querySelector('.btn-text').style.display = 'none';
            btn.querySelector('.btn-loader').style.display = 'inline-flex';
            btn.disabled = true;
        });
    </script>

</body>
</html>
