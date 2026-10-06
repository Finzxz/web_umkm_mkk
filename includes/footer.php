</main>

<!-- Mobile Bottom Navigation -->
<?php if (empty($hideBottomNav)):
$bp = isset($basePath) ? $basePath : '';
$cp = basename($_SERVER['PHP_SELF']);
$cc = is_logged_in() ? cart_count($conn, current_user_id()) : 0;
?>
<nav class="mobile-bottom-nav">
    <a href="<?= $bp ?>index.php" class="<?= $cp === 'index.php' ? 'active' : '' ?>">
        <span class="bnav-icon">🏠</span>
        <span>Beranda</span>
    </a>
    <a href="<?= $bp ?>products.php" class="<?= $cp === 'products.php' ? 'active' : '' ?>">
        <span class="bnav-icon">📦</span>
        <span>Produk</span>
    </a>
    <a href="<?= $bp ?>cart.php" class="<?= $cp === 'cart.php' ? 'active' : '' ?>">
        <span class="bnav-icon">🛒</span>
        <span>Keranjang</span>
        <?php if ($cc > 0): ?><span class="bnav-badge"><?= $cc ?></span><?php endif; ?>
    </a>
    <a href="<?= $bp . (is_logged_in() ? 'profile.php' : 'login.php') ?>" class="<?= in_array($cp, ['profile.php','login.php']) ? 'active' : '' ?>">
        <span class="bnav-icon">👤</span>
        <span>Akun</span>
    </a>
</nav>
<?php endif; ?>

<footer class="site-footer" id="tentang">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="footer-logo">
                    <img src="<?= isset($basePath) ? $basePath : '' ?>assets/img/gundshop.png" alt="Gundshop.id" width="36" height="36">
                    Gund<span>shop</span>.id
                </div>
                <p>Toko dan komunitas Gunpla terpercaya di Indonesia. Menyediakan berbagai kit Gundam original dari Bandai dengan harga bersaing dan pelayanan terbaik.</p>
            </div>
            <div class="footer-col">
                <h4>Navigasi</h4>
                <a href="<?= $bp ?>index.php">Beranda</a>
                <a href="<?= $bp ?>products.php">Produk</a>
                <?php if (is_logged_in()): ?>
                <a href="<?= $bp ?>cart.php">Keranjang</a>
                <?php endif; ?>
            </div>
            <div class="footer-col">
                <h4>Layanan</h4>
                <span>Pengiriman Seluruh Indonesia</span>
                <span>Pembayaran Aman & Terpercaya</span>
                <span>Garansi Produk Original</span>
                <span>Customer Support 7 Hari</span>
            </div>
            <div class="footer-col">
                <h4>Kontak</h4>
                <span>support@gundshop.id</span>
                <span>+62 812-3456-7890</span>
                <span>Senin - Sabtu, 09:00 - 21:00 WIB</span>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> Gundshop.id &mdash; Toko & Komunitas Gunpla Indonesia. Semua hak dilindungi.
        </div>
    </div>
</footer>

<script src="<?= isset($basePath) ? $basePath : '' ?>assets/js/script.js"></script>
<?php if (is_logged_in()): ?>
<script>
function markUserNotifRead() {
    fetch('mark_notif_read.php').catch(() => {});
}
</script>
<?php endif; ?>
</body>

</html>