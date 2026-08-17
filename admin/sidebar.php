<?php
// Ensure session and admin credentials exist
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminEmail = $_SESSION['admin_email'] ?? 'admin@hospital.com';
$adminInitials = strtoupper(substr($adminName, 0, 1));

// Pending Verification count for doctors
$pendingVerCount = 0;
if (isset($conn)) {
    $verRes = $conn->query("SELECT COUNT(*) AS total FROM tbl_doctor WHERE verification_status = 'Unverified' AND is_archived = 0");
    if ($verRes) {
        $pendingVerCount = intval($verRes->fetch_assoc()['total'] ?? 0);
    }
}
?>
<!-- Sidebar Navigation -->
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Collapse Toggle Button matching Doctor/Patient Dashboard -->
    <button class="sidebar-toggle" id="sidebarCollapseBtn" onclick="toggleSidebarCollapse()" aria-label="Toggle sidebar" title="Toggle Sidebar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </button>

    <div class="admin-sidebar-header">
        <div class="admin-sidebar-logo">
            <i class="fi fi-rr-hospital"></i>
        </div>
        <div class="admin-sidebar-title">
            Medi-Care <span>Admin</span>
        </div>
    </div>

    <div class="admin-sidebar-user">
        <div class="admin-avatar">
            <?php echo $adminInitials; ?>
        </div>
        <div class="admin-user-info">
            <h4><?php echo htmlspecialchars($adminName); ?></h4>
            <span>Super Administrator</span>
        </div>
    </div>

    <nav class="admin-sidebar-nav">
        <button type="button" class="admin-nav-item active" data-section="overview" onclick="switchAdminSection('overview')">
            <i class="fi fi-rr-dashboard"></i>
            <span>Dashboard</span>
        </button>

        <button type="button" class="admin-nav-item" data-section="doctors" onclick="switchAdminSection('doctors')">
            <i class="fi fi-rr-stethoscope"></i>
            <span>Doctor Management</span>
            <?php if ($pendingVerCount > 0): ?>
                <span class="badge-count" title="<?php echo $pendingVerCount; ?> pending approval"><?php echo $pendingVerCount; ?></span>
            <?php endif; ?>
        </button>

        <button type="button" class="admin-nav-item" data-section="patients" onclick="switchAdminSection('patients')">
            <i class="fi fi-rr-users-alt"></i>
            <span>Patient Directory</span>
        </button>

        <button type="button" class="admin-nav-item" data-section="departments" onclick="switchAdminSection('departments')">
            <i class="fi fi-rr-building"></i>
            <span>Departments</span>
        </button>

        <button type="button" class="admin-nav-item" data-section="appointments" onclick="switchAdminSection('appointments')">
            <i class="fi fi-rr-calendar"></i>
            <span>Appointments Master</span>
        </button>

        <button type="button" class="admin-nav-item" data-section="profile" onclick="switchAdminSection('profile')">
            <i class="fi fi-rr-user"></i>
            <span>Admin Profile</span>
        </button>

        <button type="button" class="admin-nav-item" data-section="security" onclick="switchAdminSection('security')">
            <i class="fi fi-rr-lock"></i>
            <span>Change Password</span>
        </button>
    </nav>

    <div class="admin-sidebar-footer">
        <a href="logout.php" class="admin-logout-btn" onclick="return confirm('Are you sure you want to logout?');">
            <i class="fi fi-rr-exit"></i>
            <span>Logout Account</span>
        </a>
    </div>
</aside>
