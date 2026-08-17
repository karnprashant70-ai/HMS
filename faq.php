<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Frequently Asked Questions (FAQ) | Medi-Care Hospital Management System. Get answers about appointments, patient records, doctor ratings, and technical support.">
    <title>Frequently Asked Questions | Medi-Care</title>
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
            <li><a href="faq.php" class="nav-link active" style="color: var(--primary); font-weight: 700;">FAQ</a></li>
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
    <header class="faq-page-header">
        <div class="faq-badge">
            <i class="fi fi-rr-interrogation"></i> Help Center & Knowledge Base
        </div>
        <h1 class="doctors-page-title">
            Frequently Asked <span class="gradient-text">Questions</span>
        </h1>
        <p class="doctors-page-subtitle">
            Find instant answers to common questions about appointments, patient accounts, doctor ratings, and medical records.
        </p>
    </header>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="faq-container">

        <!-- Search Toolbar -->
        <div class="faq-search-toolbar">
            <div class="faq-search-box">
                <i class="fi fi-rr-search"></i>
                <input type="text" id="faqSearchInput" placeholder="Search questions (e.g. how to book, cancel appointment, prescriptions)..." oninput="filterFaqs()">
            </div>

            <!-- Category Filter Pills -->
            <div class="faq-category-pills" id="faqCategoryPills">
                <button type="button" class="faq-pill active" onclick="setFaqCategory('all', this)">
                    <i class="fi fi-rr-apps"></i> All Questions
                </button>
                <button type="button" class="faq-pill" onclick="setFaqCategory('appointments', this)">
                    <i class="fi fi-rr-calendar"></i> Appointments
                </button>
                <button type="button" class="faq-pill" onclick="setFaqCategory('patient', this)">
                    <i class="fi fi-rr-user"></i> Patient Portal
                </button>
                <button type="button" class="faq-pill" onclick="setFaqCategory('doctors', this)">
                    <i class="fi fi-rr-stethoscope"></i> Doctors & Ratings
                </button>
                <button type="button" class="faq-pill" onclick="setFaqCategory('security', this)">
                    <i class="fi fi-rr-shield-check"></i> Security & Privacy
                </button>
            </div>
        </div>

        <!-- FAQ Accordion List -->
        <div class="faq-accordion-list" id="faqList">

            <!-- Item 1 -->
            <div class="faq-item" data-category="appointments" data-keywords="how to book appointment schedule doctor visit consultation">
                <button type="button" class="faq-question" onclick="toggleFaq(this)">
                    <span>How do I book an appointment with a doctor?</span>
                    <div class="faq-icon-arrow"><i class="fi fi-rr-angle-small-down"></i></div>
                </button>
                <div class="faq-answer">
                    <p>Booking an appointment is simple and takes less than a minute:</p>
                    <ul>
                        <li>Visit our <strong><a href="doctors.php" style="color: var(--primary); text-decoration: underline;">Doctors Directory</a></strong> or homepage.</li>
                        <li>Find your desired specialist and click <strong>"Book Visit"</strong>.</li>
                        <li>If you are not logged in, you will be prompted to sign in or create a patient account.</li>
                        <li>Select your preferred appointment date, choose an available time slot, select In-Person or Online, and confirm!</li>
                    </ul>
                </div>
            </div>

            <!-- Item 2 -->
            <div class="faq-item" data-category="appointments" data-keywords="cancel reschedule appointment change date time pending confirmed">
                <button type="button" class="faq-question" onclick="toggleFaq(this)">
                    <span>Can I cancel or reschedule my booked appointment?</span>
                    <div class="faq-icon-arrow"><i class="fi fi-rr-angle-small-down"></i></div>
                </button>
                <div class="faq-answer">
                    <p>
                        Yes. Log in to your <strong>Patient Dashboard</strong>, navigate to <strong>My Appointments</strong>, and locate your scheduled consultation. While the status is <em>Pending</em> or <em>Confirmed</em>, you can click <strong>Cancel Appointment</strong> or submit a rescheduling request.
                    </p>
                </div>
            </div>

            <!-- Item 3 -->
            <div class="faq-item" data-category="appointments" data-keywords="physical in-person online consultation difference video clinic">
                <button type="button" class="faq-question" onclick="toggleFaq(this)">
                    <span>What is the difference between In-Person and Online Consultations?</span>
                    <div class="faq-icon-arrow"><i class="fi fi-rr-angle-small-down"></i></div>
                </button>
                <div class="faq-answer">
                    <p>
                        <strong>Physical (In-Person):</strong> You visit the hospital clinic at the scheduled slot for physical examinations and diagnostic tests.
                    </p>
                    <p>
                        <strong>Online Consultation:</strong> You consult with the physician virtually from the comfort of your home. The doctor provides digital prescriptions and follow-up guidance directly to your portal.
                    </p>
                </div>
            </div>

            <!-- Item 4 -->
            <div class="faq-item" data-category="patient" data-keywords="register patient account signup profile emergency contact">
                <button type="button" class="faq-question" onclick="toggleFaq(this)">
                    <span>How do I create a new patient account?</span>
                    <div class="faq-icon-arrow"><i class="fi fi-rr-angle-small-down"></i></div>
                </button>
                <div class="faq-answer">
                    <p>
                        Click <strong>Register &gt; Patient</strong> in the top navigation bar or go to <strong><a href="patient/register.php" style="color: var(--primary); text-decoration: underline;">Patient Registration</a></strong>. Complete the quick 3-step form with your demographic details, phone number, and a secure password. Once registered, you can immediately book appointments.
                    </p>
                </div>
            </div>

            <!-- Item 5 -->
            <div class="faq-item" data-category="patient" data-keywords="prescriptions medical records history timeline download pdf doctor notes">
                <button type="button" class="faq-question" onclick="toggleFaq(this)">
                    <span>Where can I access my prescriptions and medical history?</span>
                    <div class="faq-icon-arrow"><i class="fi fi-rr-angle-small-down"></i></div>
                </button>
                <div class="faq-answer">
                    <p>
                        All your medical records, diagnostic suggestions, and doctor-prescribed medications are saved in your <strong>Patient Medical Timeline</strong>. Log in to the Patient Portal and click <strong>Prescriptions &amp; Records</strong> to view, print, or download them at any time.
                    </p>
                </div>
            </div>

            <!-- Item 6 -->
            <div class="faq-item" data-category="doctors" data-keywords="ratings reviews feedback stars verified patient doctor experience">
                <button type="button" class="faq-question" onclick="toggleFaq(this)">
                    <span>How do patient ratings and doctor reviews work?</span>
                    <div class="faq-icon-arrow"><i class="fi fi-rr-angle-small-down"></i></div>
                </button>
                <div class="faq-answer">
                    <p>
                        Only patients with a <strong>Completed</strong> appointment can rate and review their consulting doctor. This guarantees that all ratings and testimonials in our public <strong><a href="doctors.php" style="color: var(--primary); text-decoration: underline;">Doctors Directory</a></strong> are 100% authentic and verified.
                    </p>
                </div>
            </div>

            <!-- Item 7 -->
            <div class="faq-item" data-category="doctors" data-keywords="doctor verification nmc licence qualification credentials screening">
                <button type="button" class="faq-question" onclick="toggleFaq(this)">
                    <span>How are doctors on Medi-Care verified?</span>
                    <div class="faq-icon-arrow"><i class="fi fi-rr-angle-small-down"></i></div>
                </button>
                <div class="faq-answer">
                    <p>
                        Every registered physician must submit their official medical council licensing number (NMC), academic qualifications, and specialization certificates. Our hospital administrative team manually verifies credentials before approving a doctor's profile for appointment bookings.
                    </p>
                </div>
            </div>

            <!-- Item 8 -->
            <div class="faq-item" data-category="security" data-keywords="privacy security hipaa encryption protected health information password safe">
                <button type="button" class="faq-question" onclick="toggleFaq(this)">
                    <span>Is my health data and personal information secure?</span>
                    <div class="faq-icon-arrow"><i class="fi fi-rr-angle-small-down"></i></div>
                </button>
                <div class="faq-answer">
                    <p>
                        Yes. Medi-Care follows strict healthcare data protection standards:
                    </p>
                    <ul>
                        <li>All user passwords are encrypted using adaptive <code>bcrypt</code> cryptographic hashing.</li>
                        <li>Client-server communication is encrypted over <strong>TLS / HTTPS</strong>.</li>
                        <li>Strict Role-Based Access Control (RBAC) ensures only your assigned doctor can review your consultation records.</li>
                        <li>We never sell or monetize medical data. Read our full <strong><a href="privacy_policy.php" style="color: var(--primary); text-decoration: underline;">Privacy Policy</a></strong>.</li>
                    </ul>
                </div>
            </div>

        </div>

        <!-- Help Banner Card -->
        <div class="faq-help-card">
            <i class="fi fi-rr-messages" style="font-size: 2.2rem; color: var(--primary); margin-bottom: 10px; display: inline-block;"></i>
            <h3>Still Have Questions?</h3>
            <p>
                Cannot find the answer you are looking for? Our hospital helpdesk and support engineering team are here to assist you.
            </p>
            <div class="faq-help-actions">
                <a href="report_bug.php" class="btn btn-primary" style="padding: 10px 22px; font-weight: 700; border-radius: 50px;">
                    <i class="fi fi-rr-bug" style="margin-right: 6px;"></i> Submit a Ticket
                </a>
                <a href="privacy_policy.php" class="btn btn-outline" style="padding: 10px 22px; font-weight: 700; border-radius: 50px;">
                    <i class="fi fi-rr-shield-check" style="margin-right: 6px;"></i> Privacy &amp; Policy
                </a>
            </div>
        </div>

    </main>

    <!-- ===== FOOTER ===== -->
    <footer class="footer">
        <div class="footer-nav-links">
            <a href="index.php">Home</a>
            <a href="doctors.php">Doctors Directory</a>
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
        let activeCategory = 'all';

        // Toggle Accordion Item
        function toggleFaq(button) {
            const item = button.parentElement;
            const isOpen = item.classList.contains('active');

            // Close other items for single-accordion UX (or allow multiple)
            document.querySelectorAll('.faq-item').forEach(el => {
                if (el !== item) el.classList.remove('active');
            });

            if (isOpen) {
                item.classList.remove('active');
            } else {
                item.classList.add('active');
            }
        }

        // Filter FAQs by Category and Search Term
        function filterFaqs() {
            const query = document.getElementById('faqSearchInput').value.toLowerCase().trim();
            const items = document.querySelectorAll('.faq-item');
            let matchCount = 0;

            items.forEach(item => {
                const category = item.getAttribute('data-category') || '';
                const keywords = item.getAttribute('data-keywords') || '';
                const text = item.textContent.toLowerCase();

                const matchesCategory = (activeCategory === 'all') || (category === activeCategory);
                const matchesQuery = !query || text.includes(query) || keywords.includes(query);

                if (matchesCategory && matchesQuery) {
                    item.style.display = 'block';
                    matchCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            let noMatchEl = document.getElementById('noFaqMatch');
            if (matchCount === 0) {
                if (!noMatchEl) {
                    noMatchEl = document.createElement('div');
                    noMatchEl.id = 'noFaqMatch';
                    noMatchEl.className = 'no-faq-match';
                    noMatchEl.innerHTML = `
                        <i class="fi fi-rr-search"></i>
                        <h4 style="font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">No Matching Questions Found</h4>
                        <p style="font-size: 0.88rem; color: var(--text-secondary); max-width: 400px; margin: 0 auto 16px;">
                            Try using different keywords or browse through our categories above.
                        </p>
                        <a href="report_bug.php" class="btn btn-outline" style="font-size: 0.85rem; padding: 8px 18px;">
                            Contact Support
                        </a>
                    `;
                    document.getElementById('faqList').appendChild(noMatchEl);
                }
            } else if (noMatchEl) {
                noMatchEl.remove();
            }
        }

        // Set Category Filter
        function setFaqCategory(cat, pillBtn) {
            activeCategory = cat;
            document.querySelectorAll('.faq-pill').forEach(p => p.classList.remove('active'));
            pillBtn.classList.add('active');
            filterFaqs();
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
