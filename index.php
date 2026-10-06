<?php
$pageTitle = "Beranda";
require_once __DIR__ . '/includes/functions.php';

// Ambil kategori
$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY id");

// Ambil produk unggulan (8 produk terbaru, termasuk yang stoknya habis)
$featured = mysqli_query($conn, "SELECT p.*, c.name AS category_name FROM products p
    JOIN categories c ON p.category_id = c.id
    ORDER BY p.created_at DESC LIMIT 8");

// Ambil produk diskon
$discounted = mysqli_query($conn, "SELECT p.*, c.name AS category_name FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.discount_percent > 0
    ORDER BY p.created_at DESC LIMIT 8");

// Helper: render badge HTML untuk produk card (multi-badge support)
function render_badge($p) {
    $html = '';
    $html .= '<div class="product-badges">';
    if (!empty($p['badge_new'])) $html .= '<span class="product-badge badge-type-new">New</span>';
    if (!empty($p['badge_restocked'])) $html .= '<span class="product-badge badge-type-restocked">Restocked</span>';
    if (!empty($p['badge_limited'])) $html .= '<span class="product-badge badge-type-limited">Limited</span>';
    $dp = (int)($p['discount_percent'] ?? 0);
    if ($dp > 0) $html .= '<span class="product-badge badge-type-discount">-' . $dp . '%</span>';
    if ((int)($p['stock'] ?? 0) === 0) $html .= '<span class="product-badge badge-type-soldout">Sold Out</span>';
    $html .= '</div>';
    return $html;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">

    <!-- ================= CAROUSEL ================= -->
    <div class="carousel">
        <div class="carousel-track">

            <!-- Slide 1: Dengan Gambar -->
            <div class="carousel-slide"
                style="background-image: linear-gradient(to right, rgba(0,0,0,0.1), rgba(0,0,0,0.1)), url('assets/img/banner1.png'); background-size: cover; background-position: center;">
                <div class="carousel-caption">
                    <span class="tag">Koleksi Terbaru</span>
                    <h2>Rakit Impianmu Bersama Gundshop.id</h2>
                    <p>Ribuan Gunpla original dari HG, RG, MG, hingga PG. Garansi keaslian & pengiriman aman ke seluruh
                        Indonesia.</p>
                    <a href="products.php" class="btn btn-gold">Belanja Sekarang</a>
                </div>
            </div>

            <!-- Slide 2: Dengan Gambar -->
            <div class="carousel-slide"
                style="background-image: linear-gradient(to right, rgba(0,0,0,0.3), rgba(0,0,0,0.1)), url('assets/img/banner2.png'); background-size: cover; background-position: center;">
                <div class="carousel-caption">
                    <span class="tag">Promo Spesial</span>
                    <h2>Diskon Tools & Aksesoris Finishing</h2>
                    <p>Lengkapi bengkel gunpla-mu dengan nipper, marker panel line, dan cat model kit pilihan.</p>
                    <a href="products.php" class="btn btn-gold">Lihat Promo</a>
                </div>
            </div>

            <!-- Slide 3: Dengan Gambar -->
            <div class="carousel-slide"
                style="background-image: linear-gradient(to right, rgba(0,0,0,0.6), rgba(0,0,0,0.1)), url('assets/img/banner3.png'); background-size: cover; background-position: center;">
                <div class="carousel-caption">
                    <span class="tag">Member Baru</span>
                    <h2>Daftar & Dapatkan Pengalaman Belanja Terbaik</h2>
                    <p>Simpan wishlist, lacak riwayat transaksi, dan cetak invoice kapan saja lewat halaman profil.</p>
                    <a href="register.php" class="btn btn-outline">Daftar Sekarang</a>
                </div>
            </div>

        </div>
        <button class="carousel-arrow prev">&#8249;</button>
        <button class="carousel-arrow next">&#8250;</button>
        <div class="carousel-dots"></div>
    </div>

    <!-- ================= INFO ================= -->
    <div class="section">
        <div class="info-grid">
            <div class="info-card">
                <div class="icon">🚚</div>
                <h4>Pengiriman Aman</h4>
                <p>Packing berlapis khusus model kit ke seluruh Indonesia.</p>
            </div>
            <div class="info-card">
                <div class="icon">✅</div>
                <h4>100% Original</h4>
                <p>Semua produk resmi dari Bandai, tidak ada KW/bootleg.</p>
            </div>
            <div class="info-card">
                <div class="icon">💳</div>
                <h4>Pembayaran Fleksibel</h4>
                <p>Transfer bank, e-wallet, QRIS, hingga COD area tertentu.</p>
            </div>
            <div class="info-card">
                <div class="icon">🧾</div>
                <h4>Invoice Digital</h4>
                <p>Riwayat transaksi & invoice bisa dicetak kapan saja.</p>
            </div>
        </div>
    </div>

    <!-- ================= KATEGORI ================= -->
    <div class="section">
        <div class="section-title">
            <h2>Kategori Produk</h2>
        </div>
        <div class="category-scroller">
            <a href="products.php" class="category-chip active">Semua</a>
            <?php
            // Cek apakah ada produk diskon
            $hasDiscount = mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM products WHERE discount_percent > 0 LIMIT 1"));
            ?>
            <?php if ($hasDiscount): ?>
                <a href="products.php?discount=1" class="category-chip category-chip-discount">Diskon</a>
            <?php endif; ?>
            <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                <a href="products.php?category=<?= $cat['id'] ?>" class="category-chip"><?= h($cat['name']) ?></a>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- ================= PRODUK DISKON ================= -->
    <?php if (mysqli_num_rows($discounted) > 0): ?>
    <div class="section">
        <div class="section-title">
            <h2>🔥 Promo Diskon</h2>
            <a href="products.php?discount=1">Lihat Semua &rarr;</a>
        </div>
        <div class="product-grid">
            <?php while ($p = mysqli_fetch_assoc($discounted)): ?>
                <div class="product-card">
                    <a href="product_detail.php?id=<?= $p['id'] ?>">
                        <div class="product-thumb">
                            <?= render_badge($p) ?>
                            <img src="<?= h($p['image'] ?: 'assets/img/placeholder.png') ?>" alt="<?= h($p['name']) ?>">
                        </div>
                    </a>
                    <div class="product-body">
                        <span
                            class="product-grade"><?= h($p['category_name']) ?><?= ($p['grade'] && $p['grade'] !== 'Non-Grade') ? ' · ' . h($p['grade']) : '' ?></span>
                        <a href="product_detail.php?id=<?= $p['id'] ?>"><span
                                class="product-name"><?= h($p['name']) ?></span></a>
                        <?= render_price($p) ?>
                        <span class="product-stock <?= $p['stock'] <= 5 ? 'stock-low' : '' ?>">
                            <?= $p['stock'] > 0 ? "Stok: {$p['stock']}" : "Stok Habis" ?>
                        </span>
                    </div>
                    <div class="product-actions">
                        <a href="product_detail.php?id=<?= $p['id'] ?>" class="btn btn-gold btn-block btn-sm">Lihat
                            Detail</a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================= PRODUK UNGGULAN ================= -->
    <div class="section">
        <div class="section-title">
            <h2>Produk Unggulan</h2>
            <a href="products.php">Lihat Semua &rarr;</a>
        </div>
        <div class="product-grid">
            <?php if (mysqli_num_rows($featured) === 0): ?>
                <p>Belum ada produk tersedia.</p>
            <?php endif; ?>
            <?php while ($p = mysqli_fetch_assoc($featured)): ?>
                <div class="product-card">
                    <a href="product_detail.php?id=<?= $p['id'] ?>">
                        <div class="product-thumb">
                            <?= render_badge($p) ?>
                            <img src="<?= h($p['image'] ?: 'assets/img/placeholder.png') ?>" alt="<?= h($p['name']) ?>">
                        </div>
                    </a>
                    <div class="product-body">
                        <span
                            class="product-grade"><?= h($p['category_name']) ?><?= ($p['grade'] && $p['grade'] !== 'Non-Grade') ? ' · ' . h($p['grade']) : '' ?></span>
                        <a href="product_detail.php?id=<?= $p['id'] ?>"><span
                                class="product-name"><?= h($p['name']) ?></span></a>
                        <?= render_price($p) ?>
                        <span class="product-stock <?= $p['stock'] <= 5 ? 'stock-low' : '' ?>">
                            <?= $p['stock'] > 0 ? "Stok: {$p['stock']}" : "Stok Habis" ?>
                        </span>
                    </div>
                    <div class="product-actions">
                        <a href="product_detail.php?id=<?= $p['id'] ?>" class="btn btn-gold btn-block btn-sm">Lihat
                            Detail</a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>