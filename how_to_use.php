<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="How to Use Medi-Care Hospital Management System. Step-by-step user guide for patients, doctors, and hospital administrators.">
    <title>How to Use Medi-Care | User Guide</title>
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
            <li><a href="how_to_use.php" class="nav-link active" style="color: var(--primary); font-weight: 700;">How to Use</a></li>
            <li><a href="faq.php" class="nav-link">FAQ</a></li>
            <li><a href="privacy_policy.php" class="nav-link">Privacy</a></li>

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
    <header class="guide-page-header">
        <div class="guide-badge">
            <i class="fi fi-rr-book-alt"></i> Complete User Guide
        </div>
        <h1 class="doctors-page-title">
            How to Use <span class="gradient-text">Medi-Care</span>
        </h1>
        <p class="doctors-page-subtitle">
            Follow our visual step-by-step walkthrough to get the most out of our appointment booking, patient portal, and doctor management workflows.
        </p>
    </header>

    <!-- ===== MAIN CONTAINER ===== -->
    <main class="guide-container">

        <!-- Role Tabs -->
        <div class="guide-role-tabs">
            <button type="button" class="guide-role-btn active" onclick="switchGuideRole('patient', this)">
                <i class="fi fi-rr-user"></i> For Patients
            </button>
            <button type="button" class="guide-role-btn" onclick="switchGuideRole('doctor', this)">
                <i class="fi fi-rr-stethoscope"></i> For Doctors
            </button>
            <button type="button" class="guide-role-btn" onclick="switchGuideRole('admin', this)">
                <i class="fi fi-rr-shield-check"></i> For Administrators
            </button>
        </div>

        <!-- ================= PATIENT GUIDE ================= -->
        <section id="guide-patient" class="guide-content-section active">
            <div class="guide-steps-list">

                <!-- Step 1 -->
                <div class="guide-step-card">
                    <div class="guide-step-number-col">
                        <div class="guide-step-num">01</div>
                    </div>
                    <div>
                        <h2 class="guide-step-title">
                            Create Your Patient Health Account
                        </h2>
                        <p class="guide-step-desc">
                            Registering gives you an electronic medical record, enabling seamless appointment booking, digital prescriptions, and doctor consultations.
                        </p>
                        <div class="guide-step-points">
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Navigate to <strong>Register &gt; Patient</strong> or click the registration button on any page.</span>
                            </div>
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Fill in your full name, email, phone number, gender, and date of birth.</span>
                            </div>
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Set a secure password and submit to access your patient dashboard immediately.</span>
                            </div>
                        </div>
                        <div class="guide-step-tip">
                            <strong>Tip:</strong> Keep your registered email updated to receive appointment confirmations and electronic prescription notices.
                        </div>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="guide-step-card">
                    <div class="guide-step-number-col">
                        <div class="guide-step-num">02</div>
                    </div>
                    <div>
                        <h2 class="guide-step-title">
                            Find Doctors &amp; Compare Ratings
                        </h2>
                        <p class="guide-step-desc">
                            Browse verified doctors across hospital departments with transparent credentials, consultation fees, and patient reviews.
                        </p>
                        <div class="guide-step-points">
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Open the <strong><a href="doctors.php" style="color: var(--primary); text-decoration: underline;">Doctors Directory</a></strong> from the top navigation.</span>
                            </div>
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Filter by department (Cardiology, Neurology, Pediatrics, Orthopedics, etc.) or search by doctor name.</span>
                            </div>
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Click <strong>"View Patient Reviews"</strong> to read real testimonials and star ratings from verified patients.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="guide-step-card">
                    <div class="guide-step-number-col">
                        <div class="guide-step-num">03</div>
                    </div>
                    <div>
                        <h2 class="guide-step-title">
                            Book an In-Person or Online Consultation
                        </h2>
                        <p class="guide-step-desc">
                            Select your preferred doctor and reserve an available appointment slot in just a few clicks.
                        </p>
                        <div class="guide-step-points">
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Click <strong>"Book Visit"</strong> on your chosen doctor's card.</span>
                            </div>
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>If you are not logged in, you will be redirected to log in or create an account, then automatically returned to complete the booking.</span>
                            </div>
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Select your consultation date, choose available time slots, specify your symptoms, and confirm.</span>
                            </div>
                        </div>
                        <div class="guide-step-tip">
                            <strong>Note:</strong> You can choose between <strong>Physical (Clinic Visit)</strong> or <strong>Online Consultation</strong> based on your medical requirements.
                        </div>
                    </div>
                </div>

                <!-- Step 4 -->
                <div class="guide-step-card">
                    <div class="guide-step-number-col">
                        <div class="guide-step-num">04</div>
                    </div>
                    <div>
                        <h2 class="guide-step-title">
                            View Prescriptions &amp; Health History
                        </h2>
                        <p class="guide-step-desc">
                            All diagnoses, dosage schedules, and doctor instructions are digitally archived in your Patient Medical Timeline.
                        </p>
                        <div class="guide-step-points">
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Log in to your <strong>Patient Dashboard</strong>.</span>
                            </div>
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Navigate to <strong>Medical Records &amp; Prescriptions</strong> to download or print your treatment records.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 5 -->
                <div class="guide-step-card">
                    <div class="guide-step-number-col">
                        <div class="guide-step-num">05</div>
                    </div>
                    <div>
                        <h2 class="guide-step-title">
                            Rate &amp; Review Your Consulting Physician
                        </h2>
                        <p class="guide-step-desc">
                            After completing an appointment, rate your physician and share your experience to assist other community members.
                        </p>
                        <div class="guide-step-points">
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Once your appointment status changes to <em>Completed</em>, click <strong>"Rate Doctor"</strong> in your appointment list.</span>
                            </div>
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Select 1 to 5 stars and add an optional comment. Reviews publish instantly to the doctor's verified rating profile.</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Patient Quick CTA -->
            <div class="guide-cta-card">
                <h3>Ready to Consult a Medical Specialist?</h3>
                <p>Browse our directory of verified physicians and book your first appointment today.</p>
                <div class="guide-cta-actions">
                    <a href="doctors.php" class="btn btn-primary" style="padding: 11px 24px; font-weight: 700; border-radius: 50px;">
                        <i class="fi fi-rr-stethoscope" style="margin-right: 6px;"></i> Browse Doctors
                    </a>
                    <a href="patient/register.php" class="btn btn-outline" style="padding: 11px 24px; font-weight: 700; border-radius: 50px;">
                        <i class="fi fi-rr-user" style="margin-right: 6px;"></i> Create Patient Account
                    </a>
                </div>
            </div>
        </section>

        <!-- ================= DOCTOR GUIDE ================= -->
        <section id="guide-doctor" class="guide-content-section">
            <div class="guide-steps-list">

                <!-- Step 1 -->
                <div class="guide-step-card">
                    <div class="guide-step-number-col">
                        <div class="guide-step-num">01</div>
                    </div>
                    <div>
                        <h2 class="guide-step-title">
                            Doctor Registration &amp; Credential Verification
                        </h2>
                        <p class="guide-step-desc">
                            Join the Medi-Care physician network by submitting your professional credentials for hospital approval.
                        </p>
                        <div class="guide-step-points">
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Visit <strong>Register &gt; Doctor</strong> and provide your NMC Medical Licence Number, specialty, and department.</span>
                            </div>
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Specify your years of clinical experience, academic qualifications, and upload your profile photo.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="guide-step-card">
                    <div class="guide-step-number-col">
                        <div class="guide-step-num">02</div>
                    </div>
                    <div>
                        <h2 class="guide-step-title">
                            Set Weekly Availability &amp; Consultation Fees
                        </h2>
                        <p class="guide-step-desc">
                            Customize your clinic visiting hours, available time slots, and consultation pricing in the Doctor Portal.
                        </p>
                        <div class="guide-step-points">
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Access <strong>Doctor Dashboard &gt; Schedule &amp; Timings</strong>.</span>
                            </div>
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Set your daily consultation hours (e.g. 09:00 AM - 05:00 PM) and toggle your availability status.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="guide-step-card">
                    <div class="guide-step-number-col">
                        <div class="guide-step-num">03</div>
                    </div>
                    <div>
                        <h2 class="guide-step-title">
                            Manage Patient Appointments &amp; Issue Prescriptions
                        </h2>
                        <p class="guide-step-desc">
                            Review patient queues, check clinical history, and issue structured electronic prescriptions.
                        </p>
                        <div class="guide-step-points">
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>View scheduled patient consultations in your live appointment queue.</span>
                            </div>
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Input medications, dosages, frequency, and diagnostic recommendations directly into the digital prescription system.</span>
                            </div>
                            <div class="guide-step-point">
                                <i class="fi fi-rr-check"></i>
                                <span>Mark appointments as <em>Completed</em> upon concluding the consultation.</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Doctor Quick CTA -->
            <div class="guide-cta-card">
                <h3>Doctor Portal Access</h3>
                <p>Log in to manage your appointments, schedule, and patient prescriptions.</p>
                <div class="guide-cta-actions">
                    <a href="doctor/login.php" class="btn btn-primary" style="padding: 11px 24px; font-weight: 700; border-radius: 50px;">
                        <i class="fi fi-rr-stethoscope" style="margin-right: 6px;"></i> Doctor Login
                    </a>
                    <a href="doctor/register.php" class="btn btn-outline" style="padding: 11px 24px; font-weight: 700; border-radius: 50px;">
                        <i class="fi fi-rr-user-add" style="margin-right: 6px;"></i> Register as Doctor
                    </a>
                </div>
            </div>
        </section>

        <!-- ================= ADMIN GUIDE ================= -->
        <section id="guide-admin" class="guide-content-section">
            <div class="guide-steps-list">

                <!-- Step 1 -->
                <div class="guide-step-card">
                    <div class="guide-step-number-col">
                        <div class="guide-step-num">01</div>
                    </div>
                    <div>
                        <h2 class="guide-step-title">
                            Hospital Department &amp; Specialty Management
                        </h2>
                        <p class="guide-step-desc">
                            Add, edit, and organize clinical departments (Cardiology, Neurology, Pediatrics, Orthopedics, etc.) across the hospital network.
                        </p>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="guide-step-card">
                    <div class="guide-step-number-col">
                        <div class="guide-step-num">02</div>
                    </div>
                    <div>
                        <h2 class="guide-step-title">
                            Physician Verification &amp; Profile Oversight
                        </h2>
                        <p class="guide-step-desc">
                            Review newly registered doctors, verify medical licensing credentials, adjust consultation parameters, and archive inactive profiles.
                        </p>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="guide-step-card">
                    <div class="guide-step-number-col">
                        <div class="guide-step-num">03</div>
                    </div>
                    <div>
                        <h2 class="guide-step-title">
                            Bug Report &amp; Issue Ticket Triage
                        </h2>
                        <p class="guide-step-desc">
                            Monitor incoming user bug reports from the Bug Tracker (`report_bug.php`), inspect uploaded screenshots and environment details, update ticket statuses (Under Review, In Progress, Resolved), and append admin notes.
                        </p>
                    </div>
                </div>

            </div>
        </section>

    </main>

    <!-- ===== FOOTER ===== -->
    <footer class="footer">
        <div class="footer-nav-links">
            <a href="index.php">Home</a>
            <a href="doctors.php">Doctors Directory</a>
            <a href="blog.php">Blog</a>
            <a href="how_to_use.php">How to Use</a>
            <a href="faq.php">FAQ</a>
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
        function switchGuideRole(role, btn) {
            // Update active buttons
            document.querySelectorAll('.guide-role-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Switch active section
            document.querySelectorAll('.guide-content-section').forEach(sec => sec.classList.remove('active'));
            const targetSec = document.getElementById('guide-' + role);
            if (targetSec) {
                targetSec.classList.add('active');
            }
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
