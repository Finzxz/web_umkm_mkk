<?php
require_once __DIR__ . '/../../includes/functions.php';
require_admin('../login.php');
$currentPage = basename($_SERVER['PHP_SELF']);
$adminUnread = unread_notification_count($conn, 'admin');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? h($pageTitle) . ' - Admin Gundshop.id' : 'Admin Gundshop.id' ?></title>
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-shell">

    <!-- Desktop Sidebar -->
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <img src="../assets/img/gundshop.png" alt="Gundshop.id" class="brand-logo" width="45" height="45">
            <span class="admin-brand-text">Gund<span>shop</span>.id<br><small style="font-weight:400; color:var(--text-muted); font-size:12px;">Admin Panel</small></span>
        </div>
        <nav class="admin-nav">
            <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">📊 Dashboard & Produk</a>
            <a href="transactions.php" class="<?= $currentPage === 'transactions.php' ? 'active' : '' ?>">🧾 Data Transaksi</a>
            <a href="customers.php" class="<?= $currentPage === 'customers.php' ? 'active' : '' ?>">👥 Data Pelanggan</a>
            <a href="vouchers.php" class="<?= $currentPage === 'vouchers.php' ? 'active' : '' ?>">🎫 Voucher</a>
            <div class="divider">Akun</div>
            <a href="logout.php" onclick="return confirm('Yakin ingin logout?')">🚪 Logout</a>
        </nav>
    </aside>

    <!-- Mobile Top Bar -->
    <div class="admin-mobile-topbar">
        <div class="topbar-left">
            <span class="topbar-brand">Gund<span>shop</span>.id</span>
        </div>
        <span class="admin-mobile-user"><?= h($_SESSION['admin']['full_name']) ?></span>
    </div>

    <!-- Mobile Scrollable Nav -->
    <div class="admin-mobile-nav">
        <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">📊 Dashboard</a>
        <a href="transactions.php" class="<?= $currentPage === 'transactions.php' ? 'active' : '' ?>">🧾 Transaksi</a>
        <a href="customers.php" class="<?= $currentPage === 'customers.php' ? 'active' : '' ?>">👥 Pelanggan</a>
        <a href="vouchers.php" class="<?= $currentPage === 'vouchers.php' ? 'active' : '' ?>">🎫 Voucher</a>
        <a href="logout.php" onclick="return confirm('Yakin ingin logout?')">🚪 Logout</a>
    </div>

    <main class="admin-main">
        <div class="admin-topbar">
            <h1><?= isset($pageTitle) ? h($pageTitle) : 'Dashboard' ?></h1>
            <div class="admin-user">
                <?php if ($adminUnread > 0): ?>
                <a href="dashboard.php#notifications" class="admin-notif-badge" title="<?= $adminUnread ?> notifikasi baru">
                    🔔 <span class="admin-notif-count"><?= $adminUnread ?></span>
                </a>
                <?php endif; ?>
                Halo, <strong><?= h($_SESSION['admin']['full_name']) ?></strong> (Admin)
            </div>
        </div>
        <?php show_flashes(); ?>