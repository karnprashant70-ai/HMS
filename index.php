<?php
require_once __DIR__ . '/db-connection/db_conn.php';

// Fetch top 3 featured doctors with rating
$topDoctorsQuery = "
    SELECT d.*, 
           COALESCE(AVG(r.rating_stars), 0) AS avg_rating,
           COUNT(r.rating_id) AS total_reviews
    FROM tbl_doctor d
    LEFT JOIN tbl_rating r ON d.doctor_id = r.doctor_id
    WHERE (d.is_archived = 0 OR d.is_archived IS NULL)
      AND (d.verification_status = 'Verified' OR d.verification_status IS NULL)
    GROUP BY d.doctor_id
    ORDER BY avg_rating DESC, total_reviews DESC, d.first_name ASC
    LIMIT 3
";
$topDoctors = $conn->query($topDoctorsQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Medi-Care Hospital Management System — Streamlined healthcare management for doctors and patients. Register or login to access your dashboard.">
    <title>Medi-Care | Hospital Management System</title>
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
            <li><a href="index.php" class="nav-link active">Home</a></li>
            <li><a href="doctors.php" class="nav-link">Doctors</a></li>
            <li><a href="blog.php" class="nav-link">Blog</a></li>
            <li><a href="how_to_use.php" class="nav-link">How to Use</a></li>
            <li><a href="#features" class="nav-link">Features</a></li>
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

    <!-- ===== HERO SECTION ===== -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-badge">
                <span class="dot"></span>
                Trusted by 500+ Healthcare Professionals
            </div>
            <h1>
                Smart Healthcare<br>
                <span class="gradient-text">Management System</span>
            </h1>
            <p>
                Streamline appointments, manage patient records, and enhance healthcare delivery with our modern hospital management platform.
            </p>
            <div class="hero-actions">
                <a href="patient/register.php" class="btn btn-primary">
                    Get Started
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </a>
                <a href="#features" class="btn btn-outline">Learn More</a>
            </div>
        </div>
    </section>

    <!-- ===== FEATURES SECTION ===== -->
    <section class="features" id="features">
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon purple"><i class="fi fi-rr-calendar"></i></div>
                <h3>Appointment Scheduling</h3>
                <p>Book, reschedule, and manage appointments effortlessly with our smart scheduling system.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon teal"><i class="fi fi-rr-shield-check"></i></div>
                <h3>Secure Health Records</h3>
                <p>Your medical data is encrypted and accessible only to authorized healthcare providers.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon orange"><i class="fi fi-rr-stats"></i></div>
                <h3>Real-time Analytics</h3>
                <p>Doctors and administrators get actionable insights and reports from patient data.</p>
            </div>
        </div>
    </section>

    <!-- ===== FEATURED DOCTORS SECTION ===== -->
    <section class="doctors-container" style="margin-top: 40px; margin-bottom: 60px;">
        <div style="text-align: center; margin-bottom: 40px;">
            <div class="doctors-page-badge">
                <i class="fi fi-rr-badge-check"></i> Certified Medical Staff
            </div>
            <h2 style="font-size: var(--font-2xl); font-weight: 800; color: var(--text-primary); margin-bottom: 10px;">
                Meet Our Top <span class="gradient-text">Doctors</span>
            </h2>
            <p style="color: var(--text-secondary); max-width: 540px; margin: 0 auto; font-size: var(--font-sm);">
                Consult with highly experienced medical professionals trusted by thousands of patients.
            </p>
        </div>

        <div class="doctors-grid">
            <?php if ($topDoctors && $topDoctors->num_rows > 0): ?>
                <?php while ($doc = $topDoctors->fetch_assoc()): ?>
                    <?php
                    $docId = $doc['doctor_id'];
                    $fullName = 'Dr. ' . trim($doc['first_name'] . ' ' . ($doc['middle_name'] ? $doc['middle_name'] . ' ' : '') . $doc['last_name']);
                    $initials = strtoupper(substr($doc['first_name'], 0, 1) . substr($doc['last_name'], 0, 1));
                    $dept = $doc['department'] ?: 'General Physician';
                    $avgRating = round(floatval($doc['avg_rating']), 1);
                    $totalRev = intval($doc['total_reviews']);
                    ?>
                    <article class="doctor-card">
                        <div class="doctor-card-top">
                            <div class="doctor-card-avatar">
                                <?php if (!empty($doc['profile_photo']) && file_exists(__DIR__ . '/' . $doc['profile_photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($doc['profile_photo']); ?>" alt="<?php echo htmlspecialchars($fullName); ?>">
                                <?php else: ?>
                                    <?php echo $initials; ?>
                                <?php endif; ?>
                            </div>
                            <div class="doctor-card-info">
                                <h3 class="doctor-card-name"><?php echo htmlspecialchars($fullName); ?></h3>
                                <span class="doctor-card-dept"><?php echo htmlspecialchars($dept); ?></span>
                            </div>
                        </div>

                        <div class="doctor-rating-summary">
                            <div class="doctor-stars-row">
                                <span class="star-icons">
                                    <?php
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo ($i <= round($avgRating)) ? '★' : '☆';
                                    }
                                    ?>
                                </span>
                                <span class="star-score"><?php echo $avgRating > 0 ? number_format($avgRating, 1) : 'New'; ?></span>
                            </div>
                            <span style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600;">
                                <?php echo $totalRev; ?> <?php echo $totalRev === 1 ? 'review' : 'reviews'; ?>
                            </span>
                        </div>

                        <div class="doctor-details-list">
                            <div class="doctor-detail-item">
                                <i class="fi fi-rr-stethoscope"></i>
                                <span><strong>Specialty:</strong> <?php echo htmlspecialchars($doc['specialization'] ?: 'General Medicine'); ?></span>
                            </div>
                            <div class="doctor-detail-item">
                                <i class="fi fi-rr-briefcase"></i>
                                <span><strong>Experience:</strong> <?php echo htmlspecialchars($doc['years_experience'] ? $doc['years_experience'] . ' yrs exp' : 'Experienced'); ?></span>
                            </div>
                        </div>

                        <div class="doctor-card-actions">
                            <a href="doctors.php" class="btn-doc-reviews">
                                <i class="fi fi-rr-comment"></i> View Reviews
                            </a>
                            <a href="patient/book_appointment.php?doctor_id=<?php echo $docId; ?>" class="btn-doc-book">
                                <i class="fi fi-rr-calendar"></i> Book Visit
                            </a>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 36px;">
            <a href="doctors.php" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700;">
                Explore All Doctors & Reviews
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </a>
        </div>
    </section>

    <!-- ===== FOOTER ===== -->
    <footer class="footer">
        <div class="footer-nav-links">
            <a href="index.php">Home</a>
            <a href="doctors.php">Doctors Directory</a>
            <a href="blog.php">Blog</a>
            <a href="how_to_use.php">How to Use</a>
            <a href="#features">Features</a>
            <a href="faq.php">FAQ</a>
            <a href="privacy_policy.php">Privacy & Policy</a>
            <a href="cookie_policy.php">Cookie Policy</a>
            <a href="report_bug.php">Report a Bug</a>
            <a href="patient/login.php">Patient Login</a>
            <a href="doctor/login.php">Doctor Login</a>
        </div>
        <p>&copy; <?php echo date('Y'); ?> Medi-Care Hospital Management System. All rights reserved.</p>
    </footer>

    <!-- ===== JavaScript ===== -->
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
            // Animate hamburger to X
            mobileToggle.classList.toggle('active');
        });

        // Mobile dropdown toggle
        const dropdowns = document.querySelectorAll('.nav-dropdown');

        dropdowns.forEach(dropdown => {
            const trigger = dropdown.querySelector('.dropdown-trigger');
            trigger.addEventListener('click', (e) => {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    // Close other dropdowns
                    dropdowns.forEach(d => {
                        if (d !== dropdown) d.classList.remove('active');
                    });
                    dropdown.classList.toggle('active');
                }
            });
        });

        // Close mobile menu on link click
        document.querySelectorAll('.dropdown-item').forEach(item => {
            item.addEventListener('click', () => {
                navLinks.classList.remove('open');
            });
        });

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>

</body>
</html>
