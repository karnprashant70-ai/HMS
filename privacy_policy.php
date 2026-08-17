<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Privacy Policy & Medical Data Confidentiality | Medi-Care Hospital Management System. Learn how your health records and personal data are protected.">
    <title>Privacy Policy | Medi-Care Hospital Management System</title>
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
            <li><a href="blog.php" class="nav-link">Blog</a></li>
            <li><a href="index.php#features" class="nav-link">Features</a></li>
            <li><a href="faq.php" class="nav-link">FAQ</a></li>
            <li><a href="privacy_policy.php" class="nav-link active" style="color: var(--primary); font-weight: 700;">Privacy</a></li>
            <li><a href="cookie_policy.php" class="nav-link">Cookies</a></li>

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
    <header class="privacy-page-header">
        <div class="privacy-badge">
            <i class="fi fi-rr-shield-check"></i> Patient Data Protection & Security
        </div>
        <h1 class="doctors-page-title">
            Privacy & <span class="gradient-text">Policy</span>
        </h1>
        <p class="doctors-page-subtitle">
            At Medi-Care Hospital Management System, your confidentiality and medical information security are our highest priority.
        </p>
    </header>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="privacy-container">

        <!-- Meta Info Bar -->
        <div class="privacy-meta-bar">
            <span><i class="fi fi-rr-calendar-clock"></i> <strong>Effective Date:</strong> August 17, 2026</span>
            <span><i class="fi fi-rr-lock"></i> <strong>Compliance:</strong> ISO 27001 & Healthcare Data Standards</span>
            <span><i class="fi fi-rr-eye"></i> <strong>Version:</strong> 2.4</span>
        </div>

        <!-- Section 1: Overview & Scope -->
        <section class="privacy-card">
            <div class="privacy-section-header">
                <div class="privacy-section-icon purple">
                    <i class="fi fi-rr-document"></i>
                </div>
                <h2 class="privacy-section-title">1. Introduction & Overview</h2>
            </div>
            <div class="privacy-content">
                <p>
                    Medi-Care Hospital Management System ("Medi-Care", "we", "our", or "the Platform") operates a centralized healthcare platform that connects patients, licensed medical doctors, and hospital administrators. This Privacy Policy outlines our principles and practices concerning the collection, storage, processing, and safeguarding of Protected Health Information (PHI) and Personal Identifiable Information (PII).
                </p>
                <div class="privacy-callout">
                    <strong>Our Core Commitment:</strong> We strictly adhere to medical confidentiality ethics. Your clinical consultations, prescriptions, diagnosis notes, and personal contact details are never monetized, rented, or sold to third parties.
                </div>
            </div>
        </section>

        <!-- Section 2: Information We Collect -->
        <section class="privacy-card">
            <div class="privacy-section-header">
                <div class="privacy-section-icon teal">
                    <i class="fi fi-rr-database"></i>
                </div>
                <h2 class="privacy-section-title">2. Information We Collect</h2>
            </div>
            <div class="privacy-content">
                <p>
                    To deliver reliable clinical and administrative healthcare services, Medi-Care gathers specific categories of information based on your user role:
                </p>
                <ul class="privacy-list">
                    <li>
                        <strong>Patient Account Information:</strong> Full legal name, date of birth, gender, marital status, emergency contact details, permanent/temporary residence address, email, and phone number.
                    </li>
                    <li>
                        <strong>Medical & Clinical Records:</strong> Appointment histories, consultation fee transactions, doctor clinical notes, investigation recommendations, laboratory reports, medication prescriptions, and scheduled follow-up visits.
                    </li>
                    <li>
                        <strong>Healthcare Provider Credentials:</strong> Doctor legal licensing numbers, medical council verification documents, specialization areas, department assignments, shift schedules, and fee structures.
                    </li>
                    <li>
                        <strong>Patient Feedback & Reviews:</strong> Ratings (1–5 stars) and optional testimonial comments submitted voluntarily following completed medical consultations.
                    </li>
                    <li>
                        <strong>Technical & Session Data:</strong> IP addresses, browser types, timestamps, and secure session identifiers utilized strictly for authentication integrity and brute-force prevention.
                    </li>
                </ul>
            </div>
        </section>

        <!-- Section 3: How We Use Your Data -->
        <section class="privacy-card">
            <div class="privacy-section-header">
                <div class="privacy-section-icon orange">
                    <i class="fi fi-rr-settings-sliders"></i>
                </div>
                <h2 class="privacy-section-title">3. How We Use Your Information</h2>
            </div>
            <div class="privacy-content">
                <p>We utilize the collected information strictly for legitimate medical, administrative, and clinical purposes:</p>
                <ul class="privacy-list">
                    <li>Facilitating appointment scheduling, real-time doctor slot reservations, and automated rescheduling notices.</li>
                    <li>Providing assigned doctors with instant, secure access to patient medical timelines during consultations.</li>
                    <li>Generating verified digital prescriptions and follow-up medical care directives.</li>
                    <li>Maintaining auditable financial logs for consultation fee receipts and hospital accounting.</li>
                    <li>Verifying doctor licenses through medical administration boards prior to public directory listing.</li>
                    <li>Enhancing platform reliability, bug resolution, and clinical workflow efficiency.</li>
                </ul>
            </div>
        </section>

        <!-- Section 4: Role-Based Access & Data Confidentiality -->
        <section class="privacy-card">
            <div class="privacy-section-header">
                <div class="privacy-section-icon blue">
                    <i class="fi fi-rr-user-lock"></i>
                </div>
                <h2 class="privacy-section-title">4. Role-Based Access Control (RBAC)</h2>
            </div>
            <div class="privacy-content">
                <p>
                    Medi-Care implements rigorous Role-Based Access Control barriers to guarantee that sensitive records are only viewable by authorized personnel:
                </p>
                <ul class="privacy-list">
                    <li><strong>Patients:</strong> Can exclusively access their personal health timeline, booked appointments, prescriptions, and profile configurations.</li>
                    <li><strong>Doctors:</strong> Can only view medical details of patients who have confirmed appointments or ongoing consultations under their specific care.</li>
                    <li><strong>Hospital Administrators:</strong> Supervise system health, verify doctor credentials, and manage departments without unauthorized exposure to private clinical diagnosis transcripts.</li>
                </ul>
            </div>
        </section>

        <!-- Section 5: Data Security & Encryption -->
        <section class="privacy-card">
            <div class="privacy-section-header">
                <div class="privacy-section-icon purple">
                    <i class="fi fi-rr-shield-interrogation"></i>
                </div>
                <h2 class="privacy-section-title">5. Data Security & Encryption Standards</h2>
            </div>
            <div class="privacy-content">
                <p>
                    We apply defense-in-depth security principles across all layers of the Medi-Care infrastructure:
                </p>
                <ul class="privacy-list">
                    <li><strong>Password Hashing:</strong> All user passwords are encrypted using one-way <code>bcrypt / PASSWORD_DEFAULT</code> hashing with adaptive cryptographic salts.</li>
                    <li><strong>In-Transit Encryption:</strong> All client-server transmissions are encrypted via modern Transport Layer Security (TLS 1.3 / HTTPS).</li>
                    <li><strong>Database Sanitization:</strong> All user input is bound through prepared SQL statements (parameterized queries) to eliminate SQL injection vulnerabilities.</li>
                    <li><strong>Session Security:</strong> Authenticated sessions utilize strict HTTP-only and SameSite cookies to protect against Cross-Site Scripting (XSS) and Session Hijacking.</li>
                </ul>
            </div>
        </section>

        <!-- Section 6: Patient Rights & Data Control -->
        <section class="privacy-card">
            <div class="privacy-section-header">
                <div class="privacy-section-icon teal">
                    <i class="fi fi-rr-user-check"></i>
                </div>
                <h2 class="privacy-section-title">6. Your Rights Regarding Health Data</h2>
            </div>
            <div class="privacy-content">
                <p>As a valued patient or healthcare provider on Medi-Care, you have clear rights over your information:</p>
                <ul class="privacy-list">
                    <li><strong>Right to Inspect:</strong> You may view and export your medical records, past appointments, and prescriptions at any time through your Patient Timeline.</li>
                    <li><strong>Right to Rectification:</strong> You can update inaccurate contact or demographic details in your Profile section.</li>
                    <li><strong>Right to Restrict Processing:</strong> You may request temporary suspension of active accounts by contacting hospital administration.</li>
                    <li><strong>Right to Erasure:</strong> Subject to mandatory medical record retention legislation, you may request permanent deletion of your account.</li>
                </ul>
            </div>
        </section>

        <!-- Section 7: Cookies & Session Tracking -->
        <section class="privacy-card">
            <div class="privacy-section-header">
                <div class="privacy-section-icon orange">
                    <i class="fi fi-rr-cookie"></i>
                </div>
                <h2 class="privacy-section-title">7. Cookies & Session Management</h2>
            </div>
            <div class="privacy-content">
                <p>
                    Medi-Care utilizes strictly functional session cookies required for user authentication, role verification, and navigation state. We do <strong>not</strong> employ cross-site marketing trackers, behavioral profiling beacons, or third-party advertising cookies.
                </p>
            </div>
        </section>

        <!-- Contact & DPO Box -->
        <div class="privacy-contact-box">
            <i class="fi fi-rr-headset" style="font-size: 2.2rem; color: var(--primary); margin-bottom: 12px; display: inline-block;"></i>
            <h3>Questions or Data Inquiries?</h3>
            <p>
                If you have inquiries regarding our data practices, wish to submit a medical record request, or report a security concern, our Data Protection Officer is ready to assist.
            </p>
            <div class="privacy-contact-methods">
                <a href="mailto:privacy@medicare.com" class="privacy-contact-pill">
                    <i class="fi fi-rr-envelope" style="color: var(--primary);"></i> privacy@medicare.com
                </a>
                <a href="tel:+97714000000" class="privacy-contact-pill">
                    <i class="fi fi-rr-phone-call" style="color: var(--accent);"></i> +977 1 4000000
                </a>
                <span class="privacy-contact-pill">
                    <i class="fi fi-rr-marker" style="color: #F59E0B;"></i> Medi-Care Hospital Main Campus
                </span>
            </div>
        </div>

    </main>

    <!-- ===== FOOTER ===== -->
    <footer class="footer">
        <div class="footer-nav-links">
            <a href="index.php">Home</a>
            <a href="doctors.php">Doctors Directory</a>
            <a href="blog.php">Blog</a>
            <a href="index.php#features">Features</a>
            <a href="faq.php">FAQ</a>
            <a href="privacy_policy.php">Privacy & Policy</a>
            <a href="cookie_policy.php">Cookie Policy</a>
            <a href="report_bug.php">Report a Bug</a>
            <a href="patient/login.php">Patient Login</a>
            <a href="doctor/login.php">Doctor Login</a>
        </div>
        <p>&copy; <?php echo date('Y'); ?> Medi-Care Hospital Management System. All rights reserved.</p>
    </footer>

    <!-- ===== JS ===== -->
    <script>
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
