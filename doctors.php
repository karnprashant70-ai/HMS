<?php
require_once __DIR__ . '/db-connection/db_conn.php';

// Fetch all active & verified doctors with average rating & review count
$doctorsQuery = "
    SELECT d.*, 
           COALESCE(AVG(r.rating_stars), 0) AS avg_rating,
           COUNT(r.rating_id) AS total_reviews
    FROM tbl_doctor d
    LEFT JOIN tbl_rating r ON d.doctor_id = r.doctor_id
    WHERE (d.is_archived = 0 OR d.is_archived IS NULL)
      AND (d.verification_status = 'Verified' OR d.verification_status IS NULL)
    GROUP BY d.doctor_id
    ORDER BY avg_rating DESC, d.first_name ASC
";
$doctorsResult = $conn->query($doctorsQuery);

$doctorsList = [];
$departmentsSet = [];

if ($doctorsResult) {
    while ($doc = $doctorsResult->fetch_assoc()) {
        $doctorsList[] = $doc;
        if (!empty($doc['department']) && $doc['department'] !== 'Unassigned') {
            $departmentsSet[$doc['department']] = true;
        }
    }
}

// Fetch all reviews for all doctors to embed in JSON for fast client-side modal rendering
$reviewsQuery = "
    SELECT r.rating_id, r.doctor_id, r.rating_stars, r.comment, r.created_at,
           p.first_name AS patient_first, p.last_name AS patient_last, p.profile_photo AS patient_photo
    FROM tbl_rating r
    LEFT JOIN tbl_patient p ON r.patient_id = p.patient_id
    ORDER BY r.created_at DESC
";
$reviewsResult = $conn->query($reviewsQuery);
$reviewsByDoctor = [];

if ($reviewsResult) {
    while ($rev = $reviewsResult->fetch_assoc()) {
        $docId = $rev['doctor_id'];
        if (!isset($reviewsByDoctor[$docId])) {
            $reviewsByDoctor[$docId] = [];
        }
        $reviewsByDoctor[$docId][] = [
            'rating'     => intval($rev['rating_stars']),
            'comment'    => $rev['comment'],
            'date'       => date('M d, Y', strtotime($rev['created_at'])),
            'patient'    => trim(($rev['patient_first'] ?? 'Verified') . ' ' . ($rev['patient_last'] ?? 'Patient')),
            'photo'      => $rev['patient_photo'] ?? ''
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Find expert doctors, specialists, patient ratings, and verified reviews at Medi-Care Hospital Management System.">
    <title>Our Doctors & Patient Reviews | Medi-Care</title>
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
            <li><a href="doctors.php" class="nav-link active" style="color: var(--primary); font-weight: 700;">Doctors</a></li>
            <li><a href="blog.php" class="nav-link">Blog</a></li>
            <li><a href="how_to_use.php" class="nav-link">How to Use</a></li>
            <li><a href="index.php#features" class="nav-link">Features</a></li>
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
    <header class="doctors-page-header">
        <div class="doctors-page-badge">
            <i class="fi fi-rr-badge-check"></i> Verified Specialists & Top Care
        </div>
        <h1 class="doctors-page-title">
            Our Medical <span class="gradient-text">Specialists</span>
        </h1>
        <p class="doctors-page-subtitle">
            Browse our licensed doctors, review verified patient ratings and feedback, and easily schedule your next consultation.
        </p>
    </header>

    <!-- ===== SEARCH & FILTERS ===== -->
    <section class="doctors-filter-toolbar">
        <div class="doctors-search-box">
            <i class="fi fi-rr-search"></i>
            <input type="text" id="doctorSearchInput" placeholder="Search doctor by name, specialty, or department..." oninput="filterDoctors()">
        </div>

        <div class="department-filter-pills" id="deptFilterPills">
            <button type="button" class="dept-pill active" onclick="setDepartmentFilter('all', this)">
                <i class="fi fi-rr-apps"></i> All Specialties
            </button>
            <?php foreach (array_keys($departmentsSet) as $dept): ?>
                <button type="button" class="dept-pill" onclick="setDepartmentFilter('<?php echo htmlspecialchars($dept); ?>', this)">
                    <?php echo htmlspecialchars($dept); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ===== DOCTORS GRID ===== -->
    <main class="doctors-container">
        <div class="doctors-grid" id="doctorsGrid">
            <?php if (empty($doctorsList)): ?>
                <div class="no-doctors-found">
                    <i class="fi fi-rr-user-md"></i>
                    <h3>No Doctors Found</h3>
                    <p>There are currently no registered doctors available.</p>
                </div>
            <?php else: ?>
                <?php foreach ($doctorsList as $doc): ?>
                    <?php
                    $docId = $doc['doctor_id'];
                    $fullName = 'Dr. ' . trim($doc['first_name'] . ' ' . ($doc['middle_name'] ? $doc['middle_name'] . ' ' : '') . $doc['last_name']);
                    $initials = strtoupper(substr($doc['first_name'], 0, 1) . substr($doc['last_name'], 0, 1));
                    $dept = $doc['department'] ?: 'General Physician';
                    $specialty = $doc['specialization'] ?: 'General Practice';
                    $qualification = $doc['qualification'] ?: 'MBBS';
                    $fee = floatval($doc['consultation_fee'] ?? 0);
                    $experience = $doc['years_experience'] ? $doc['years_experience'] . ' yrs exp' : 'Experienced';
                    $avgRating = round(floatval($doc['avg_rating']), 1);
                    $totalRev = intval($doc['total_reviews']);

                    $reviewsData = $reviewsByDoctor[$docId] ?? [];
                    ?>
                    <article class="doctor-card" 
                             data-name="<?php echo htmlspecialchars(strtolower($fullName)); ?>" 
                             data-dept="<?php echo htmlspecialchars(strtolower($dept)); ?>" 
                             data-specialty="<?php echo htmlspecialchars(strtolower($specialty)); ?>"
                             data-qualification="<?php echo htmlspecialchars(strtolower($qualification)); ?>">
                        
                        <div class="doctor-card-top">
                            <div class="doctor-card-avatar">
                                <?php if (!empty($doc['profile_photo']) && file_exists(__DIR__ . '/' . $doc['profile_photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($doc['profile_photo']); ?>" alt="<?php echo htmlspecialchars($fullName); ?>">
                                <?php else: ?>
                                    <?php echo $initials; ?>
                                <?php endif; ?>
                            </div>
                            <div class="doctor-card-info">
                                <h2 class="doctor-card-name" title="<?php echo htmlspecialchars($fullName); ?>"><?php echo htmlspecialchars($fullName); ?></h2>
                                <span class="doctor-card-dept"><?php echo htmlspecialchars($dept); ?></span>
                            </div>
                        </div>

                        <!-- Rating summary -->
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
                            <button type="button" class="review-count-btn" onclick="openReviewsModal(<?php echo $docId; ?>)">
                                <i class="fi fi-rr-comment-alt"></i> <?php echo $totalRev; ?> <?php echo $totalRev === 1 ? 'review' : 'reviews'; ?>
                            </button>
                        </div>

                        <!-- Details List -->
                        <div class="doctor-details-list">
                            <div class="doctor-detail-item">
                                <i class="fi fi-rr-graduation-cap"></i>
                                <span><strong>Qualification:</strong> <?php echo htmlspecialchars($qualification); ?></span>
                            </div>
                            <div class="doctor-detail-item">
                                <i class="fi fi-rr-stethoscope"></i>
                                <span><strong>Specialty:</strong> <?php echo htmlspecialchars($specialty); ?></span>
                            </div>
                            <div class="doctor-detail-item">
                                <i class="fi fi-rr-briefcase"></i>
                                <span><strong>Experience:</strong> <?php echo htmlspecialchars($experience); ?></span>
                            </div>
                            <?php if ($fee > 0): ?>
                                <div class="doctor-detail-item">
                                    <i class="fi fi-rr-receipt"></i>
                                    <span><strong>Consultation Fee:</strong> Rs. <?php echo number_format($fee, 2); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($doc['available_time'])): ?>
                                <div class="doctor-detail-item">
                                    <i class="fi fi-rr-clock"></i>
                                    <span><strong>Timing:</strong> <?php echo htmlspecialchars($doc['available_time']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons -->
                        <div class="doctor-card-actions">
                            <button type="button" class="btn-doc-reviews" onclick="openReviewsModal(<?php echo $docId; ?>)">
                                <i class="fi fi-rr-comment"></i> Reviews (<?php echo $totalRev; ?>)
                            </button>
                            <a href="patient/book_appointment.php?doctor_id=<?php echo $docId; ?>" class="btn-doc-book">
                                <i class="fi fi-rr-calendar"></i> Book Visit
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- ===== REVIEWS MODAL ===== -->
    <div class="reviews-modal-backdrop" id="reviewsModalBackdrop" onclick="handleBackdropClick(event)">
        <div class="reviews-modal-container" role="dialog" aria-modal="true">
            <div class="reviews-modal-header">
                <div class="reviews-modal-header-info">
                    <div class="modal-doc-avatar" id="modalDocAvatar">DR</div>
                    <div>
                        <h3 id="modalDocName">Dr. Doctor Name</h3>
                        <p id="modalDocDept">Specialty & Department</p>
                    </div>
                </div>
                <button type="button" class="reviews-modal-close" onclick="closeReviewsModal()" aria-label="Close modal">&times;</button>
            </div>
            <div class="reviews-modal-body" id="modalReviewsBody">
                <!-- Dynamically populated via JS -->
            </div>
        </div>
    </div>

    <!-- ===== FOOTER ===== -->
    <footer class="footer">
        <div class="footer-nav-links">
            <a href="index.php">Home</a>
            <a href="doctors.php">Doctors Directory</a>
            <a href="blog.php">Blog</a>
            <a href="how_to_use.php">How to Use</a>
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

    <!-- Pass Doctor Data to JS for Interactive Reviews -->
    <script>
        const doctorsData = <?php echo json_encode(array_column($doctorsList, null, 'doctor_id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const reviewsData = <?php echo json_encode($reviewsByDoctor, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        let activeDepartment = 'all';

        // Filter doctors by search input & department
        function filterDoctors() {
            const query = document.getElementById('doctorSearchInput').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.doctor-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const name = card.getAttribute('data-name') || '';
                const dept = card.getAttribute('data-dept') || '';
                const specialty = card.getAttribute('data-specialty') || '';
                const qualification = card.getAttribute('data-qualification') || '';

                const matchesDept = (activeDepartment === 'all') || (dept === activeDepartment.toLowerCase());
                const matchesQuery = !query || 
                                     name.includes(query) || 
                                     dept.includes(query) || 
                                     specialty.includes(query) || 
                                     qualification.includes(query);

                if (matchesDept && matchesQuery) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Handle empty state
            let noResultMsg = document.getElementById('noResultsMsg');
            if (visibleCount === 0) {
                if (!noResultMsg) {
                    noResultMsg = document.createElement('div');
                    noResultMsg.id = 'noResultsMsg';
                    noResultMsg.className = 'no-doctors-found';
                    noResultMsg.innerHTML = `
                        <i class="fi fi-rr-search"></i>
                        <h3>No Matching Doctors Found</h3>
                        <p>Try refining your search terms or clearing department filters.</p>
                    `;
                    document.getElementById('doctorsGrid').appendChild(noResultMsg);
                }
            } else if (noResultMsg) {
                noResultMsg.remove();
            }
        }

        // Set Department Filter
        function setDepartmentFilter(dept, btn) {
            activeDepartment = dept;
            document.querySelectorAll('.dept-pill').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            filterDoctors();
        }

        // Open Reviews Modal
        function openReviewsModal(docId) {
            const doctor = doctorsData[docId];
            if (!doctor) return;

            const modalDocAvatar = document.getElementById('modalDocAvatar');
            const modalDocName = document.getElementById('modalDocName');
            const modalDocDept = document.getElementById('modalDocDept');
            const modalReviewsBody = document.getElementById('modalReviewsBody');

            const fullName = 'Dr. ' + (doctor.first_name + ' ' + (doctor.middle_name ? doctor.middle_name + ' ' : '') + doctor.last_name).trim();
            const initials = (doctor.first_name.charAt(0) + doctor.last_name.charAt(0)).toUpperCase();
            
            if (doctor.profile_photo) {
                modalDocAvatar.innerHTML = `<img src="${doctor.profile_photo}" alt="${fullName}">`;
            } else {
                modalDocAvatar.textContent = initials;
            }

            modalDocName.textContent = fullName;
            modalDocDept.textContent = (doctor.department || 'General') + ' • ' + (doctor.specialization || 'Physician');

            const reviews = reviewsData[docId] || [];
            const avgRating = parseFloat(doctor.avg_rating || 0).toFixed(1);

            let bodyHtml = '';

            // Rating banner
            bodyHtml += `
                <div class="modal-rating-banner">
                    <div class="modal-big-score">${avgRating > 0 ? avgRating : '—'}</div>
                    <div class="modal-score-meta">
                        <div class="star-icons">${getStarsHtml(Math.round(avgRating))}</div>
                        <div style="font-size: 0.82rem; color: var(--text-secondary); font-weight: 600;">
                            ${reviews.length} Verified Patient ${reviews.length === 1 ? 'Review' : 'Reviews'}
                        </div>
                    </div>
                </div>
            `;

            // List reviews
            if (reviews.length === 0) {
                bodyHtml += `
                    <div class="no-reviews-box">
                        <i class="fi fi-rr-comment-alt-dots"></i>
                        <h4 style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">No Patient Reviews Yet</h4>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); max-width: 360px; margin: 0 auto 16px;">
                            This doctor has not received any patient reviews yet. Be the first to share your experience!
                        </p>
                        <a href="patient/book_appointment.php?doctor_id=${docId}" class="btn-doc-book" style="display: inline-flex; width: auto; padding: 10px 20px;">
                            <i class="fi fi-rr-calendar"></i> Book an Appointment
                        </a>
                    </div>
                `;
            } else {
                reviews.forEach(rev => {
                    bodyHtml += `
                        <div class="patient-review-card">
                            <div class="review-card-header">
                                <div class="reviewer-name">
                                    <i class="fi fi-rr-user" style="color: var(--primary); font-size: 0.85rem;"></i>
                                    ${escapeHtml(rev.patient)}
                                </div>
                                <div class="review-date">${escapeHtml(rev.date)}</div>
                            </div>
                            <div class="star-icons" style="margin-bottom: 8px; font-size: 0.85rem;">
                                ${getStarsHtml(rev.rating)}
                            </div>
                            <div class="review-comment">
                                "${escapeHtml(rev.comment || 'Great consultation and care.')}"
                            </div>
                        </div>
                    `;
                });
            }

            modalReviewsBody.innerHTML = bodyHtml;
            document.getElementById('reviewsModalBackdrop').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeReviewsModal() {
            document.getElementById('reviewsModalBackdrop').classList.remove('active');
            document.body.style.overflow = '';
        }

        function handleBackdropClick(e) {
            if (e.target.id === 'reviewsModalBackdrop') {
                closeReviewsModal();
            }
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeReviewsModal();
        });

        function getStarsHtml(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += (i <= rating) ? '★' : '☆';
            }
            return stars;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
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
