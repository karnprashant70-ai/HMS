<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fallback in case $patient is not defined or is empty
if (!isset($patient) || empty($patient)) {
    if (isset($_SESSION['patient_id']) && isset($conn)) {
        $sidebarPatientId = intval($_SESSION['patient_id']);
        $sidebarStmt = $conn->prepare('SELECT * FROM tbl_patient WHERE patient_id = ? LIMIT 1');
        if ($sidebarStmt) {
            $sidebarStmt->bind_param('i', $sidebarPatientId);
            $sidebarStmt->execute();
            $sidebarResult = $sidebarStmt->get_result();
            $patient = $sidebarResult->fetch_assoc() ?: [];
            $sidebarStmt->close();
        }
    }
}

// Compute initials and profile photo path
$sidebarInitials = '';
if (isset($patient['first_name']) && !empty($patient['first_name'])) $sidebarInitials .= strtoupper($patient['first_name'][0]);
if (isset($patient['last_name']) && !empty($patient['last_name'])) $sidebarInitials .= strtoupper($patient['last_name'][0]);
if (empty($sidebarInitials)) $sidebarInitials = 'PT';

$sidebarProfilePhoto = '';
if (isset($patient['profile_photo']) && !empty($patient['profile_photo'])) {
    $sidebarProfilePhoto = '../uploads/patients/' . $patient['profile_photo'];
}

$sidebarPatientName = $_SESSION['patient_name'] ?? ($patient['first_name'] ?? 'Patient');
if (isset($patient['first_name']) && isset($patient['last_name'])) {
    $sidebarPatientName = trim($patient['first_name'] . ' ' . ($patient['middle_name'] ?? '') . ' ' . $patient['last_name']);
}

// Determine active page
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
    <!-- Collapse Toggle -->
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </button>

    <!-- Brand -->
    <div class="sidebar-header">
        <div class="sidebar-brand-icon">M+</div>
        <div class="sidebar-brand-text">Medi-<span>Care</span></div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="sidebar-nav-label">Main</div>
        <a href="dashboard.php" class="sidebar-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" data-tooltip="Dashboard">
            <span class="sidebar-link-icon">📊</span>
            <span class="sidebar-link-text">Dashboard</span>
        </a>
        <a href="book_appointment.php" class="sidebar-link <?php echo $currentPage === 'book_appointment.php' ? 'active' : ''; ?>" data-tooltip="Book Appointment">
            <span class="sidebar-link-icon">➕</span>
            <span class="sidebar-link-text">Book Appointment</span>
        </a>
        <a href="appointments.php" class="sidebar-link <?php echo $currentPage === 'appointments.php' ? 'active' : ''; ?>" data-tooltip="My Appointments">
            <span class="sidebar-link-icon">📅</span>
            <span class="sidebar-link-text">My Appointments</span>
        </a>
        <a href="dashboard.php#timeline" class="sidebar-link" data-tooltip="Timeline / Appointment History">
            <span class="sidebar-link-icon">⏳</span>
            <span class="sidebar-link-text">Timeline / History</span>
        </a>

        <div class="sidebar-nav-label">Medical</div>
        <a href="dashboard.php#timeline" class="sidebar-link" data-tooltip="Medical Records">
            <span class="sidebar-link-icon">📋</span>
            <span class="sidebar-link-text">Medical Records</span>
        </a>
        <a href="dashboard.php#timeline" class="sidebar-link" data-tooltip="Follow Up">
            <span class="sidebar-link-icon">🔄</span>
            <span class="sidebar-link-text">Follow Up</span>
        </a>

        <div class="sidebar-nav-label">Account</div>
        <details class="sidebar-dropdown" <?php echo in_array($currentPage, ['profile.php', 'reset_password.php']) ? 'open' : ''; ?>>
            <summary class="sidebar-link" data-tooltip="Settings">
                <span class="sidebar-link-icon">⚙️</span>
                <span class="sidebar-link-text">Settings</span>
                <span class="dropdown-arrow">▼</span>
            </summary>
            <div class="sidebar-submenu">
                <a href="profile.php" class="sidebar-link <?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>" data-tooltip="My Profile">
                    <span class="sidebar-link-icon">👤</span>
                    <span class="sidebar-link-text">My Profile</span>
                </a>
                <a href="reset_password.php" class="sidebar-link <?php echo $currentPage === 'reset_password.php' ? 'active' : ''; ?>" data-tooltip="Reset Password">
                    <span class="sidebar-link-icon">🔐</span>
                    <span class="sidebar-link-text">Reset Password</span>
                </a>
                <a href="logout.php" class="sidebar-link" data-tooltip="Logout" onclick="return confirm('Are you sure you want to logout?');">
                    <span class="sidebar-link-icon">🚪</span>
                    <span class="sidebar-link-text">Logout</span>
                </a>
            </div>
        </details>
    </nav>

    <!-- Footer: Patient Info -->
    <div class="sidebar-footer">
        <div class="sidebar-avatar">
            <?php if ($sidebarProfilePhoto): ?>
                <img src="<?php echo htmlspecialchars($sidebarProfilePhoto); ?>" alt="Avatar">
            <?php else: ?>
                <?php echo $sidebarInitials; ?>
            <?php endif; ?>
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($sidebarPatientName); ?></div>
            <div class="sidebar-user-role">Patient</div>
        </div>
    </div>
</aside>

<!-- JavaScript for Sidebar Interactions -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebarLinks = document.querySelectorAll('.sidebar-link');

        // Load saved state
        if (sidebar && localStorage.getItem('patientSidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }

        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('patientSidebarCollapsed', sidebar.classList.contains('collapsed'));
            });
        }

        if (mobileMenuBtn && sidebar && sidebarOverlay) {
            mobileMenuBtn.addEventListener('click', () => {
                sidebar.classList.toggle('mobile-open');
                sidebarOverlay.classList.toggle('active');
            });
        }

        if (sidebarOverlay && sidebar) {
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('active');
            });
        }

        // Dynamic active state resolution with Hash support
        function updateActiveLink() {
            const currentPath = window.location.pathname.split('/').pop() || 'dashboard.php';
            const currentHash = window.location.hash;

            sidebarLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (!href || href === '#') return;

                // Resolve full link URL
                const tempAnchor = document.createElement('a');
                tempAnchor.href = href;
                const targetPath = tempAnchor.pathname.split('/').pop();
                const targetHash = tempAnchor.hash;

                // Exact match: matching path and hash, or matching path and no target/current hash
                if (targetPath === currentPath && (targetHash === currentHash || (!targetHash && !currentHash))) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        }

        updateActiveLink();
        window.addEventListener('hashchange', updateActiveLink);
    });
</script>
