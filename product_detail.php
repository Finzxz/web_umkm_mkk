<?php
require_once __DIR__ . '/includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$product = mysqli_stmt_get_result($stmt)->fetch_assoc();

if (!$product) {
    set_flash('error', 'Produk tidak ditemukan.');
    header("Location: products.php");
    exit;
}

$pageTitle = $product['name'];

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = 'product_detail.php?id=' . $id;
        set_flash('error', 'Silakan login terlebih dahulu untuk menambahkan ke keranjang.');
        header("Location: login.php");
        exit;
    }
    $qty = max(1, (int) ($_POST['quantity'] ?? 1));
    $qty = min($qty, $product['stock']);

    if ($product['stock'] <= 0) {
        set_flash('error', 'Stok produk habis.');
    } else {
        $uid = current_user_id();
        $stmt = mysqli_prepare($conn, "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), ?)");
        mysqli_stmt_bind_param($stmt, "iiii", $uid, $id, $qty, $product['stock']);
        mysqli_stmt_execute($stmt);
        set_flash('success', 'Produk ditambahkan ke keranjang.');
        header("Location: product_detail.php?id=$id");
        exit;
    }
}

// Handle beli sekarang (direct checkout)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_now'])) {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = 'checkout.php?buy_now=' . $id . '&qty=' . max(1, (int) ($_POST['quantity'] ?? 1));
        set_flash('error', 'Silakan login terlebih dahulu untuk melakukan pembelian.');
        header("Location: login.php");
        exit;
    }
    $qty = max(1, (int) ($_POST['quantity'] ?? 1));
    $qty = min($qty, $product['stock']);
    if ($product['stock'] <= 0) {
        set_flash('error', 'Stok produk habis.');
        header("Location: product_detail.php?id=$id");
        exit;
    }
    header("Location: checkout.php?buy_now=$id&qty=$qty");
    exit;
}

// Ambil gambar tambahan dari database
$imgStmt = mysqli_prepare($conn, "SELECT image_url FROM product_images WHERE product_id = ?");
mysqli_stmt_bind_param($imgStmt, "i", $id);
mysqli_stmt_execute($imgStmt);
$additionalImages = mysqli_stmt_get_result($imgStmt)->fetch_all(MYSQLI_ASSOC);

// Gabungkan gambar utama dan gambar tambahan ke dalam satu array
$all_images = [];
if (!empty($product['image'])) {
    $all_images[] = $product['image'];
} else {
    $all_images[] = 'assets/img/placeholder.png';
}
foreach ($additionalImages as $img) {
    if (!in_array($img['image_url'], $all_images)) {
        $all_images[] = $img['image_url'];
    }
}

// ---- Produk Lainnya (rekomendasi) ----
$relatedLimit = 8;
$related = [];

// 1) Coba ambil dari kategori yang sama (exclude produk saat ini)
$relStmt = mysqli_prepare($conn, "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.category_id = ? AND p.id != ? AND p.stock > 0 ORDER BY RAND() LIMIT ?");
mysqli_stmt_bind_param($relStmt, "iii", $product['category_id'], $id, $relatedLimit);
mysqli_stmt_execute($relStmt);
$related = mysqli_stmt_get_result($relStmt)->fetch_all(MYSQLI_ASSOC);

// 2) Jika kurang dari 4, tambahkan dari kategori lain
if (count($related) < 4) {
    $remain = $relatedLimit - count($related);
    $excludeIds = array_merge([$id], array_column($related, 'id'));
    $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
    $types2 = str_repeat('i', count($excludeIds));
    $fillStmt = mysqli_prepare($conn, "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id NOT IN ($placeholders) AND p.stock > 0 ORDER BY RAND() LIMIT ?");
    $bindParams = array_merge($excludeIds, [$remain]);
    $bindTypes = $types2 . 'i';
    mysqli_stmt_bind_param($fillStmt, $bindTypes, ...$bindParams);
    mysqli_stmt_execute($fillStmt);
    $extra = mysqli_stmt_get_result($fillStmt)->fetch_all(MYSQLI_ASSOC);
    $related = array_merge($related, $extra);
}

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
    <!-- align-items: flex-start agar kolom kanan tidak memanjang ke bawah mengikuti tinggi teks -->
    <div class="detail-wrap"
        style="display: flex; flex-wrap: wrap; gap: 40px; align-items: flex-start; margin-top: 20px;">

        <!-- BAGIAN GAMBAR (KIRI) -->
        <!-- Tambahkan height: auto !important dan overflow: visible !important untuk mengatasi batasan dari style.css -->
        <div class="detail-image"
            style="flex: 1 1 40%; min-width: 300px; max-width: 500px; height: auto !important; overflow: visible !important; display: block !important;">

            <!-- aspect-ratio: 1 / 1 membuat kotak gambar selalu berbentuk persegi sempurna -->
            <div
                style="position: relative; width: 100%; aspect-ratio: 1 / 1; background: #f8f8f8; border: 1px solid #eaeaea; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden;">

                <!-- Gambar Utama yang akan berubah -->
                <img id="mainProductImage" src="<?= h($all_images[0]) ?>" alt="<?= h($product['name']) ?>"
                    style="max-width: 95%; max-height: 95%; width: auto; height: auto; object-fit: contain; display: block; transition: opacity 0.3s;">

                <!-- Tombol Navigasi (Panah) - Muncul jika ada lebih dari 1 gambar -->
                <?php if (count($all_images) > 1): ?>
                    <!-- Tombol Kiri (Bulat Putih Bergaya Shopee) -->
                    <button type="button" onclick="changeImage(-1)"
                        style="position: absolute; top: 50%; left: 10px; transform: translateY(-50%); background: rgba(255,255,255,0.9); color: #555; border: none; padding: 8px 14px; cursor: pointer; border-radius: 50%; font-size: 18px; z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        &laquo;
                    </button>
                    <!-- Tombol Kanan -->
                    <button type="button" onclick="changeImage(1)"
                        style="position: absolute; top: 50%; right: 10px; transform: translateY(-50%); background: rgba(255,255,255,0.9); color: #555; border: none; padding: 8px 14px; cursor: pointer; border-radius: 50%; font-size: 18px; z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        &raquo;
                    </button>
                <?php endif; ?>
            </div>

            <!-- Thumbnail Gambar (Slider) -->
            <?php if (count($all_images) > 1): ?>
                <!-- Tambahkan position: relative dan z-index agar berada di lapisan paling depan -->
                <div class="image-thumbnails"
                    style="display: flex !important; gap: 8px; margin-top: 12px; overflow-x: auto; padding-bottom: 5px; position: relative; z-index: 5;">
                    <?php foreach ($all_images as $idx => $img_url): ?>
                        <img src="<?= h($img_url) ?>" onclick="setMainImageByIndex(<?= $idx ?>)"
                            style="width: 72px; height: 72px; object-fit: contain; cursor: pointer; border: <?= $idx === 0 ? '2px solid var(--gold)' : '1px solid #ddd' ?>; border-radius: 4px; background: #f8f8f8; padding: 2px; box-sizing: border-box; flex-shrink: 0;">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- BAGIAN INFO PRODUK (KANAN) -->
        <div class="detail-info" style="flex: 1 1 50%; min-width: 300px;">
            <span
                class="product-grade"><?= h($product['category_name']) ?><?= ($product['grade'] && $product['grade'] !== 'Non-Grade') ? ' · ' . h($product['grade']) : '' ?></span>
            <h1><?= h($product['name']) ?></h1>
            <?= render_price($product, 'detail-price') ?>
            <p class="product-stock <?= $product['stock'] <= 5 ? 'stock-low' : '' ?>">
                <?= $product['stock'] > 0 ? "Stok tersedia: {$product['stock']}" : "Stok habis" ?>
            </p>
            <p style="color:var(--text-muted); margin-top:16px; line-height:1.7;">
                <?= nl2br(h($product['description'])) ?>
            </p>

            <?php if ($product['stock'] > 0): ?>
                <form method="POST" action="product_detail.php?id=<?= $id ?>">
                    <div class="qty-selector" data-qty-wrap>
                        <button type="button" data-qty-minus>&minus;</button>
                        <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock'] ?>">
                        <button type="button" data-qty-plus>&plus;</button>
                    </div>
                    <div class="detail-btn-group">
                        <button type="submit" name="add_to_cart" class="btn btn-gold">Tambah ke Keranjang</button>
                        <button type="submit" name="buy_now" class="btn btn-buy-now">Beli Sekarang</button>
                    </div>
                </form>
            <?php else: ?>
                <button class="btn btn-outline" disabled>Stok Habis</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($related)): ?>
<!-- Produk Lainnya -->
<div class="related-products-section">
    <div class="related-divider">
        <span>Produk Lainnya</span>
    </div>
    <div class="product-grid">
        <?php foreach ($related as $rp): ?>
            <div class="product-card">
                <a href="product_detail.php?id=<?= $rp['id'] ?>">
                    <div class="product-thumb">
                        <?= render_badge($rp) ?>
                        <img src="<?= h($rp['image'] ?: 'assets/img/placeholder.png') ?>" alt="<?= h($rp['name']) ?>" loading="lazy">
                    </div>
                </a>
                <div class="product-body">
                    <span class="product-grade"><?= h($rp['category_name']) ?><?= ($rp['grade'] && $rp['grade'] !== 'Non-Grade') ? ' · ' . h($rp['grade']) : '' ?></span>
                    <a href="product_detail.php?id=<?= $rp['id'] ?>"><span class="product-name"><?= h($rp['name']) ?></span></a>
                    <?= render_price($rp) ?>
                    <span class="product-stock <?= $rp['stock'] <= 5 ? 'stock-low' : '' ?>">
                        <?= $rp['stock'] > 0 ? "Stok: {$rp['stock']}" : "Stok habis" ?>
                    </span>
                </div>
                <div class="product-actions">
                    <a href="product_detail.php?id=<?= $rp['id'] ?>" class="btn btn-gold btn-block btn-sm">Lihat Detail</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
    // Mengubah array PHP menjadi array JavaScript
    const images = <?= json_encode($all_images) ?>;
    let currentIndex = 0;

    function updateMainImage() {
        const mainImg = document.getElementById('mainProductImage');

        // Efek fade sederhana saat gambar diganti
        mainImg.style.opacity = '0';
        setTimeout(() => {
            mainImg.src = images[currentIndex];
            mainImg.style.opacity = '1';
        }, 150);

        // Update border pada thumbnail yang aktif
        let thumbs = document.querySelectorAll('.image-thumbnails img');
        thumbs.forEach((t, index) => {
            t.style.border = (index === currentIndex) ? '2px solid var(--gold)' : '1px solid #ddd';
        });
    }

    // Fungsi untuk tombol panah kiri (-1) dan kanan (+1)
    function changeImage(direction) {
        currentIndex += direction;

        // Jika melebihi batas akhir, kembali ke awal
        if (currentIndex >= images.length) {
            currentIndex = 0;
        }
        // Jika kurang dari awal, kembali ke akhir
        if (currentIndex < 0) {
            currentIndex = images.length - 1;
        }

        updateMainImage();
    }

    // Fungsi saat thumbnail diklik langsung
    function setMainImageByIndex(index) {
        currentIndex = index;
        updateMainImage();
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>