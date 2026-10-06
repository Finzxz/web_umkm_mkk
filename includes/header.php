<?php
require_once __DIR__ . '/functions.php';
$cartCount = is_logged_in() ? cart_count($conn, current_user_id()) : 0;
$userNotifs = is_logged_in() ? get_notifications($conn, 'all_users', 10) : [];
$userUnread = is_logged_in() ? unread_notification_count($conn, 'all_users') : 0;
$currentPage = basename($_SERVER['PHP_SELF']);
$bp = isset($basePath) ? $basePath : '';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= isset($pageTitle) ? h($pageTitle) . ' - Gundshop.id' : 'Gundshop.id - Toko Gunpla Terpercaya' ?></title>
    <link rel="stylesheet" href="<?= $bp ?>assets/css/style.css">
</head>

<body class="<?= empty($hideBottomNav) ? 'has-bottom-nav' : '' ?>">

    <nav class="navbar">
        <div class="container">
            <a href="<?= $bp ?>index.php" class="brand">
                <img src="<?= $bp ?>assets/img/gundshop.png" alt="Gundshop.id"
                    class="brand-logo" width="40" height="40">
                Gund<span>shop</span>.id
            </a>
            <ul class="nav-links">
                <li><a href="<?= $bp ?>index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Beranda</a></li>
                <li><a href="<?= $bp ?>products.php" class="<?= $currentPage === 'products.php' ? 'active' : '' ?>">Produk</a></li>
                <?php if (is_logged_in()): ?>
                    <li><a href="#tentang">Tentang</a></li>
                <?php endif; ?>
            </ul>
            <div class="nav-cta">
                <?php if (is_logged_in()): ?>
                    <?php if ($userUnread > 0): ?>
                    <div class="notif-bell-wrap">
                        <button class="btn btn-outline btn-sm notif-bell-btn" onclick="this.nextElementSibling.classList.toggle('show')">
                            🔔<span class="notif-bell-count"><?= $userUnread ?></span>
                        </button>
                        <div class="notif-dropdown">
                            <?php foreach (array_slice($userNotifs, 0, 5) as $n): ?>
                            <a href="<?= $bp . (h($n['link'] ?? '#')) ?>" class="notif-dropdown-item <?= empty($n['is_read']) ? 'notif-unread' : '' ?>" onclick="markUserNotifRead()">
                                <div>
                                    <strong><?= h($n['title']) ?></strong>
                                    <p><?= h($n['message']) ?></p>
                                    <small><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></small>
                                </div>
                            </a>
                            <?php endforeach; ?>
                            <?php if (empty($userNotifs)): ?>
                                <div style="padding:12px; color:var(--text-muted); font-size:13px;">Belum ada notifikasi.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <a href="<?= $bp ?>cart.php" class="btn btn-outline btn-sm cart-badge">
                        Keranjang
                        <?php if ($cartCount > 0): ?><span class="count"><?= $cartCount ?></span><?php endif; ?>
                    </a>
                    <a href="<?= $bp ?>profile.php"
                        class="btn btn-outline btn-sm"><?= h($_SESSION['customer']['full_name']) ?></a>
                    <a href="<?= $bp ?>logout.php" class="btn btn-red btn-sm" onclick="return confirm('Yakin ingin logout?')">Logout</a>
                <?php else: ?>
                    <a href="<?= $bp ?>login.php" class="btn btn-outline btn-sm">Login</a>
                    <a href="<?= $bp ?>register.php" class="btn btn-gold btn-sm">Daftar</a>
                <?php endif; ?>
            </div>
            <button class="mobile-hamburger" id="mobileHamburger" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </nav>

    <!-- Mobile Nav Drawer -->
    <div class="mobile-nav-overlay" id="mobileNavOverlay"></div>
    <div class="mobile-nav-drawer" id="mobileNavDrawer">
        <div class="mobile-drawer-header">
            <?php if (is_logged_in()): ?>
                <div class="drawer-greeting">Halo,</div>
                <div class="drawer-name"><?= h($_SESSION['customer']['full_name']) ?></div>
            <?php else: ?>
                <div class="drawer-name">Gund<span style="color:var(--gold)">shop</span>.id</div>
                <div class="drawer-greeting" style="margin-top:4px; margin-bottom:0;">Toko Gunpla Terpercaya</div>
            <?php endif; ?>
        </div>
        <nav class="mobile-drawer-nav">
            <a href="<?= $bp ?>index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                <span class="nav-icon">🏠</span> Beranda
            </a>
            <a href="<?= $bp ?>products.php" class="<?= $currentPage === 'products.php' ? 'active' : '' ?>">
                <span class="nav-icon">📦</span> Produk
            </a>
            <?php if (is_logged_in()): ?>
                <a href="<?= $bp ?>profile.php" class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                    <span class="nav-icon">👤</span> Profil Saya
                </a>
            <?php endif; ?>
            <div class="drawer-divider"></div>
            <?php if (is_logged_in()): ?>
                <a href="<?= $bp ?>cart.php" class="<?= $currentPage === 'cart.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🛒</span> Keranjang
                    <?php if ($cartCount > 0): ?><small style="background:var(--red);color:#fff;border-radius:50%;padding:1px 6px;font-size:10px;margin-left:4px;"><?= $cartCount ?></small><?php endif; ?>
                </a>
            <?php endif; ?>
        </nav>
        <div class="mobile-drawer-footer">
            <?php if (is_logged_in()): ?>
                <a href="<?= $bp ?>logout.php" class="btn btn-outline" onclick="return confirm('Yakin ingin logout?')">Logout</a>
            <?php else: ?>
                <a href="<?= $bp ?>login.php" class="btn btn-gold">Login</a>
                <a href="<?= $bp ?>register.php" class="btn btn-outline">Daftar Akun</a>
            <?php endif; ?>
        </div>
    </div>

    <main>
        <div class="container">
            <?php show_flashes(); ?>
        </div>