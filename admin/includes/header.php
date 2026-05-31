<?php
require_once __DIR__ . '/auth.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

if (isset($conn) && $conn instanceof mysqli) {
    dashboard_enforce_active_user($conn);
}
?>
<?php $current_admin_page = basename($_SERVER['PHP_SELF']); ?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>LikeHome Admin | Dashboard</title>
    <link rel="stylesheet" href="../assets/css/tailwind.build.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; background-color: rgb(var(--color-surface)); }
        .sidebar-link.active {
            background: rgb(var(--color-brand-100));
            color: rgb(var(--color-ink));
            border-right: 4px solid rgb(var(--color-logo));
        }
        .lh-gallery-sort-ghost { opacity: 0.55 !important; }
        .lh-gallery-sort-chosen { z-index: 5; position: relative; box-shadow: 0 0 0 2px rgb(var(--color-logo)); }
    </style>
</head>
<body class="flex min-h-screen text-ink">
    <aside class="w-64 bg-white border-r border-black/8 flex flex-col sticky top-0 h-screen shrink-0">
        <div class="p-8">
            <a href="dashboard.php" class="block min-w-0">
                <span class="text-xl font-black tracking-tight text-ink">LikeHome</span>
            </a>
        </div>
        <nav class="flex-grow px-4 space-y-2 mt-2">
            <a href="dashboard.php" class="sidebar-link <?php echo $current_admin_page === 'dashboard.php' ? 'active' : ''; ?> flex items-center gap-3 px-4 py-3 text-blue-grey font-bold rounded-2xl hover:bg-brand-100 hover:text-ink transition-all">
                <i data-lucide="layout-grid" class="w-5 h-5"></i> Dashboard
            </a>
            <a href="add-property.php" class="sidebar-link <?php echo $current_admin_page === 'add-property.php' ? 'active' : ''; ?> flex items-center gap-3 px-4 py-3 text-blue-grey font-bold rounded-2xl hover:bg-brand-100 hover:text-ink transition-all">
                <i data-lucide="plus-circle" class="w-5 h-5"></i> Adaugă Locuință
            </a>
            <a href="bookings.php" class="sidebar-link <?php echo $current_admin_page === 'bookings.php' ? 'active' : ''; ?> flex items-center gap-3 px-4 py-3 text-blue-grey font-bold rounded-2xl hover:bg-brand-100 hover:text-ink transition-all">
                <i data-lucide="calendar-check" class="w-5 h-5"></i> Rezervări
            </a>
            <a href="coupons.php" class="sidebar-link <?php echo $current_admin_page === 'coupons.php' ? 'active' : ''; ?> flex items-center gap-3 px-4 py-3 text-blue-grey font-bold rounded-2xl hover:bg-brand-100 hover:text-ink transition-all">
                <i data-lucide="percent" class="w-5 h-5"></i> Reduceri
            </a>
            <a href="calendar.php" class="sidebar-link <?php echo $current_admin_page === 'calendar.php' ? 'active' : ''; ?> flex items-center gap-3 px-4 py-3 text-blue-grey font-bold rounded-2xl hover:bg-brand-100 hover:text-ink transition-all">
                <i data-lucide="calendar-range" class="w-5 h-5"></i> Calendar
            </a>
            <?php if (dashboard_user_is_admin()): ?>
            <a href="users.php" class="sidebar-link <?php echo $current_admin_page === 'users.php' ? 'active' : ''; ?> flex items-center gap-3 px-4 py-3 text-blue-grey font-bold rounded-2xl hover:bg-brand-100 hover:text-ink transition-all">
                <i data-lucide="users" class="w-5 h-5"></i> Utilizatori
            </a>
            <a href="activity-log.php" class="sidebar-link <?php echo $current_admin_page === 'activity-log.php' ? 'active' : ''; ?> flex items-center gap-3 px-4 py-3 text-blue-grey font-bold rounded-2xl hover:bg-brand-100 hover:text-ink transition-all">
                <i data-lucide="scroll-text" class="w-5 h-5"></i> Jurnal activitate
            </a>
            <?php endif; ?>
            <a href="<?= htmlspecialchars(lh_public_url('index.php'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="flex items-center gap-3 px-4 py-3 text-blue-grey font-bold rounded-2xl hover:bg-brand-100 hover:text-ink transition-all">
                <i data-lucide="external-link" class="w-5 h-5"></i> Vezi Site-ul
            </a>
        </nav>
        <div class="p-4 border-t border-black/6">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-red-600 font-bold rounded-2xl hover:bg-red-50 transition-all">
                <i data-lucide="log-out" class="w-5 h-5"></i> Deconectare
            </a>
        </div>
    </aside>
    <main class="flex-grow p-10 overflow-y-auto bg-surface">
