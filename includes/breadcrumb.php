<?php
/**
 * Reusable Breadcrumb Component for Medi-Care HMS
 * Automatically determines or renders custom breadcrumb navigation paths.
 */

// If custom $breadcrumbItems is not provided, detect automatically
if (!isset($breadcrumbItems) || !is_array($breadcrumbItems) || empty($breadcrumbItems)) {
    $scriptName = basename($_SERVER['PHP_SELF'] ?? '');
    $scriptDir  = dirname($_SERVER['PHP_SELF'] ?? '');

    $breadcrumbItems = [
        ['title' => 'Dashboard', 'url' => 'dashboard.php']
    ];

    switch ($scriptName) {
        // --- Doctor Pages ---
        case 'appointments.php':
            $action = $_GET['action'] ?? '';
            $sub = $_GET['sub'] ?? '';
            if ($action === 'timeline' || $sub === 'timeline' || isset($_GET['timeline'])) {
                $breadcrumbItems[] = ['title' => 'Appointments', 'url' => 'appointments.php'];
                $breadcrumbItems[] = ['title' => 'Timeline', 'url' => ''];
            } else {
                $breadcrumbItems[] = ['title' => 'Appointments', 'url' => ''];
            }
            break;

        case 'my_patients.php':
            $breadcrumbItems[] = ['title' => 'My Patients', 'url' => ''];
            break;

        case 'follow_ups.php':
            $breadcrumbItems[] = ['title' => 'Follow Up', 'url' => ''];
            break;

        case 'schedule.php':
            $breadcrumbItems[] = ['title' => 'Doctor Schedule', 'url' => ''];
            break;

        // --- Patient Pages ---
        case 'book_appointment.php':
            $breadcrumbItems[] = ['title' => 'Appointments', 'url' => 'appointments.php'];
            $breadcrumbItems[] = ['title' => 'Book Appointment', 'url' => ''];
            break;

        case 'timeline.php':
            $breadcrumbItems[] = ['title' => 'Appointments', 'url' => 'appointments.php'];
            $breadcrumbItems[] = ['title' => 'Timeline', 'url' => ''];
            break;

        case 'view_prescription.php':
            $breadcrumbItems[] = ['title' => 'Medical Records', 'url' => 'timeline.php'];
            $breadcrumbItems[] = ['title' => 'Prescription Details', 'url' => ''];
            break;

        // --- Shared / Account Pages ---
        case 'profile.php':
            $breadcrumbItems[] = ['title' => 'My Profile', 'url' => ''];
            break;

        case 'reset_password.php':
            $breadcrumbItems[] = ['title' => 'Reset Password', 'url' => ''];
            break;

        // --- Admin Pages ---
        case 'dashboard.php':
            if (strpos($scriptDir, '/admin') !== false || strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false) {
                $breadcrumbItems[] = ['title' => 'Overview', 'url' => '', 'id' => 'breadcrumbAdminSectionText'];
            }
            break;

        default:
            // Default: Dashboard only (active)
            break;
    }
}
?>
<!-- Breadcrumb Styles -->
<style>
    .hms-breadcrumb {
        display: flex;
        align-items: center;
        margin-bottom: 4px;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }
    .hms-breadcrumb-list {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .hms-breadcrumb-item {
        display: inline-flex;
        align-items: center;
        font-size: 0.8rem;
        font-weight: 500;
        color: #64748b;
        line-height: 1.2;
    }
    .hms-breadcrumb-item.active {
        color: #1e293b;
        font-weight: 600;
    }
    .hms-breadcrumb-link {
        color: #4f46e5;
        text-decoration: none;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: color 0.15s ease;
    }
    .hms-breadcrumb-link:hover {
        color: #3730a3;
        text-decoration: underline;
    }
    .hms-breadcrumb-current {
        color: #0f172a;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .hms-breadcrumb-separator {
        display: inline-flex;
        align-items: center;
        color: #94a3b8;
        font-size: 0.78rem;
        user-select: none;
    }
</style>

<nav class="hms-breadcrumb" aria-label="Breadcrumb">
    <ol class="hms-breadcrumb-list">
        <?php 
        $totalItems = count($breadcrumbItems);
        foreach ($breadcrumbItems as $index => $item): 
            $isLast = ($index === $totalItems - 1);
            $itemTitle = htmlspecialchars($item['title']);
            $itemUrl = $item['url'] ?? '';
            $itemIdAttr = !empty($item['id']) ? ' id="' . htmlspecialchars($item['id']) . '"' : '';
        ?>
            <li class="hms-breadcrumb-item<?php echo $isLast ? ' active' : ''; ?>">
                <?php if (!$isLast && !empty($itemUrl)): ?>
                    <a href="<?php echo htmlspecialchars($itemUrl); ?>" class="hms-breadcrumb-link">
                        <span><?php echo $itemTitle; ?></span>
                    </a>
                <?php else: ?>
                    <span class="hms-breadcrumb-current"<?php echo $itemIdAttr; ?>>
                        <span><?php echo $itemTitle; ?></span>
                    </span>
                <?php endif; ?>
            </li>
            <?php if (!$isLast): ?>
                <li class="hms-breadcrumb-separator" aria-hidden="true">
                    <span>&rsaquo;</span>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>
