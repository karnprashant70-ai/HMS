<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Cookie Policy & Session Management | Medi-Care Hospital Management System. Learn how cookies and session security protect your medical dashboard.">
    <title>Cookie Policy | Medi-Care Hospital Management System</title>
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
            <li><a href="cookie_policy.php" class="nav-link active" style="color: var(--primary); font-weight: 700;">Cookies</a></li>

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
            <i class="fi fi-rr-cookie"></i> Secure Session & Cookie Governance
        </div>
        <h1 class="doctors-page-title">
            Cookie <span class="gradient-text">Policy</span>
        </h1>
        <p class="doctors-page-subtitle">
            Understand how Medi-Care utilizes cookies and web tokens to deliver a secure, reliable healthcare management experience.
        </p>
    </header>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="privacy-container">

        <!-- Meta Info Bar -->
        <div class="privacy-meta-bar">
            <span><i class="fi fi-rr-calendar-clock"></i> <strong>Effective Date:</strong> August 17, 2026</span>
            <span><i class="fi fi-rr-shield-check"></i> <strong>Ad Tracking:</strong> 0% (Strictly Non-Commercial)</span>
            <span><i class="fi fi-rr-lock"></i> <strong>Session Security:</strong> HTTP-Only & SameSite Strict</span>
        </div>

        <!-- Section 1: What Are Cookies? -->
        <section class="privacy-card">
            <div class="privacy-section-header">
                <div class="privacy-section-icon purple">
                    <i class="fi fi-rr-info"></i>
                </div>
                <h2 class="privacy-section-title">1. What Are Cookies?</h2>
            </div>
            <div class="privacy-content">
                <p>
                    Cookies are small text files placed on your computer, tablet, or mobile phone by websites you visit. They are widely used to make web applications function efficiently, authenticate user identities across requests, and remember your interface preferences.
                </p>
                <div class="privacy-callout">
                    <strong>Zero Third-Party Advertising:</strong> Medi-Care does <em>not</em> use third-party marketing cookies, ad networks, cross-app tracking beacons, or profiling pixels. Every cookie served on our system is strictly functional or security-related.
                </div>
            </div>
        </section>

        <!-- Section 2: Cookies We Use -->
        <section class="privacy-card">
            <div class="privacy-section-header">
                <div class="privacy-section-icon teal">
                    <i class="fi fi-rr-list-check"></i>
                </div>
                <h2 class="privacy-section-title">2. Types of Cookies We Use</h2>
            </div>
            <div class="privacy-content">
                <p>
                    We classify cookies utilized on the Medi-Care Hospital Management platform into the following functional categories:
                </p>

                <div class="policy-table-wrapper">
                    <table class="policy-table">
                        <thead>
                            <tr>
                                <th>Cookie Name</th>
                                <th>Category</th>
                                <th>Purpose</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>PHPSESSID</code></td>
                                <td><span class="cookie-type-badge essential">Essential</span></td>
                                <td>Maintains your authenticated session state across patient, doctor, and admin portals.</td>
                                <td>Session (expires when browser closes)</td>
                            </tr>
                            <tr>
                                <td><code>hms_csrf_token</code></td>
                                <td><span class="cookie-type-badge sec">Security</span></td>
                                <td>Protects forms from Cross-Site Request Forgery (CSRF) attacks.</td>
                                <td>Session</td>
                            </tr>
                            <tr>
                                <td><code>hms_sidebar_state</code></td>
                                <td><span class="cookie-type-badge pref">Preference</span></td>
                                <td>Remembers whether your dashboard sidebar is expanded or collapsed.</td>
                                <td>30 Days</td>
                            </tr>
                            <tr>
                                <td><code>hms_theme_mode</code></td>
                                <td><span class="cookie-type-badge pref">Preference</span></td>
                                <td>Saves your preferred color theme settings.</td>
                                <td>1 Year</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Section 3: Essential vs Non-Essential -->
        <section class="privacy-card">
            <div class="privacy-section-header">
                <div class="privacy-section-icon orange">
                    <i class="fi fi-rr-settings"></i>
                </div>
                <h2 class="privacy-section-title">3. Strictly Necessary vs Preference Cookies</h2>
            </div>
            <div class="privacy-content">
                <ul class="privacy-list">
                    <li>
                        <strong>Strictly Necessary Cookies:</strong> These cookies are critical to navigating Medi-Care, accessing protected patient files, and authorizing appointment reservations. Without these cookies, services like logging into your doctor dashboard or patient portal cannot function.
                    </li>
                    <li>
                        <strong>Functional & Preference Cookies:</strong> These cookies allow the platform to remember choices you make (such as UI collapse modes, active filters, or selected hospital department tabs) to provide an optimized user experience.
                    </li>
                </ul>
            </div>
        </section>

        <!-- Section 4: How to Manage and Disable Cookies -->
        <section class="privacy-card">
            <div class="privacy-section-header">
                <div class="privacy-section-icon blue">
                    <i class="fi fi-rr-toggle-on"></i>
                </div>
                <h2 class="privacy-section-title">4. Managing & Controlling Cookies in Your Browser</h2>
            </div>
            <div class="privacy-content">
                <p>
                    Most modern web browsers allow you to manage or block cookies through their application settings. You can choose to delete existing cookies or reject new ones:
                </p>
                <ul class="privacy-list">
                    <li><strong>Google Chrome:</strong> Settings &gt; Privacy and Security &gt; Cookies and other site data.</li>
                    <li><strong>Mozilla Firefox:</strong> Options &gt; Privacy &amp; Security &gt; Cookies and Site Data.</li>
                    <li><strong>Apple Safari:</strong> Preferences &gt; Privacy &gt; Block all cookies.</li>
                    <li><strong>Microsoft Edge:</strong> Settings &gt; Cookies and site permissions &gt; Manage and delete cookies.</li>
                </ul>
                <div class="privacy-callout">
                    <strong>Please Note:</strong> Blocking essential session cookies (<code>PHPSESSID</code>) will prevent you from signing in to your patient or doctor portal.
                </div>
            </div>
        </section>

        <!-- Contact Box -->
        <div class="privacy-contact-box">
            <i class="fi fi-rr-cookie" style="font-size: 2.2rem; color: var(--primary); margin-bottom: 12px; display: inline-block;"></i>
            <h3>Cookie Policy Inquiries</h3>
            <p>
                Have questions regarding our cookie practices or session management standards? Contact our technical security team for assistance.
            </p>
            <div class="privacy-contact-methods">
                <a href="mailto:security@medicare.com" class="privacy-contact-pill">
                    <i class="fi fi-rr-envelope" style="color: var(--primary);"></i> security@medicare.com
                </a>
                <a href="privacy_policy.php" class="privacy-contact-pill">
                    <i class="fi fi-rr-shield-check" style="color: var(--accent);"></i> View Full Privacy Policy
                </a>
            </div>
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
