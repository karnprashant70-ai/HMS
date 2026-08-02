<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fallback in case $doctor is not defined or is empty
if (!isset($doctor) || empty($doctor)) {
    if (isset($_SESSION['doctor_id']) && isset($conn)) {
        $sidebarDoctorId = intval($_SESSION['doctor_id']);
        $sidebarStmt = $conn->prepare('SELECT * FROM tbl_doctor WHERE doctor_id = ? LIMIT 1');
        if ($sidebarStmt) {
            $sidebarStmt->bind_param('i', $sidebarDoctorId);
            $sidebarStmt->execute();
            $sidebarResult = $sidebarStmt->get_result();
            $doctor = $sidebarResult->fetch_assoc() ?: [];
            $sidebarStmt->close();
        }
    }
}

// Compute initials and profile photo path
$sidebarInitials = '';
if (isset($doctor['first_name']) && !empty($doctor['first_name'])) $sidebarInitials .= strtoupper($doctor['first_name'][0]);
if (isset($doctor['last_name']) && !empty($doctor['last_name'])) $sidebarInitials .= strtoupper($doctor['last_name'][0]);
if (empty($sidebarInitials)) $sidebarInitials = 'DR';

$sidebarProfilePhoto = '';
if (isset($doctor['profile_photo']) && !empty($doctor['profile_photo'])) {
    $sidebarProfilePhoto = '../uploads/doctors/' . $doctor['profile_photo'];
}

$sidebarDoctorName = $_SESSION['doctor_name'] ?? ($doctor['first_name'] ?? 'Doctor');
if (isset($doctor['first_name']) && isset($doctor['last_name'])) {
    $sidebarDoctorName = trim($doctor['first_name'] . ' ' . ($doctor['middle_name'] ?? '') . ' ' . $doctor['last_name']);
}
$sidebarDepartment = $doctor['department'] ?? 'General';

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
        <a href="appointments.php" class="sidebar-link <?php echo $currentPage === 'appointments.php' ? 'active' : ''; ?>" data-tooltip="Appointments">
            <span class="sidebar-link-icon">📅</span>
            <span class="sidebar-link-text">Appointments</span>
        </a>
        <a href="my_patients.php" class="sidebar-link <?php echo $currentPage === 'my_patients.php' ? 'active' : ''; ?>" data-tooltip="My Patients">
            <span class="sidebar-link-icon">🧑‍🤝‍🧑</span>
            <span class="sidebar-link-text">My Patients</span>
        </a>
        <a href="follow_ups.php" class="sidebar-link <?php echo $currentPage === 'follow_ups.php' ? 'active' : ''; ?>" data-tooltip="Follow Ups">
            <span class="sidebar-link-icon">🔄</span>
            <span class="sidebar-link-text">Follow Ups</span>
        </a>
        <a href="#" class="sidebar-link" data-tooltip="Schedule">
            <span class="sidebar-link-icon">🕐</span>
            <span class="sidebar-link-text">Schedule</span>
        </a>

        <div class="sidebar-nav-label">Management</div>
        <a href="#" class="sidebar-link" data-tooltip="Prescriptions">
            <span class="sidebar-link-icon">💊</span>
            <span class="sidebar-link-text">Prescriptions</span>
        </a>
        <a href="#" class="sidebar-link" data-tooltip="Medical Records">
            <span class="sidebar-link-icon">📋</span>
            <span class="sidebar-link-text">Medical Records</span>
        </a>
        <a href="#" class="sidebar-link" data-tooltip="Reports">
            <span class="sidebar-link-icon">📈</span>
            <span class="sidebar-link-text">Reports</span>
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

    <!-- Footer: Doctor Info -->
    <div class="sidebar-footer">
        <div class="sidebar-avatar">
            <?php if ($sidebarProfilePhoto): ?>
                <img src="<?php echo htmlspecialchars($sidebarProfilePhoto); ?>" alt="Avatar">
            <?php else: ?>
                <?php echo $sidebarInitials; ?>
            <?php endif; ?>
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name">Dr. <?php echo htmlspecialchars($sidebarDoctorName); ?></div>
            <div class="sidebar-user-role"><?php echo htmlspecialchars($sidebarDepartment); ?></div>
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

        // Load saved state
        if (sidebar && localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }

        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
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
    });
</script>
