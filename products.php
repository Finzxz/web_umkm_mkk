<?php
$pageTitle = "Produk";
require_once __DIR__ . '/includes/functions.php';

$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY id");
$selectedCategory = isset($_GET['category']) ? (int) $_GET['category'] : 0;
$search = trim($_GET['q'] ?? '');
$discountOnly = isset($_GET['discount']) ? true : false;

$sql = "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE 1=1";
$params = [];
$types = "";

if ($selectedCategory > 0) {
    $sql .= " AND p.category_id = ?";
    $types .= "i";
    $params[] = $selectedCategory;
}
if ($discountOnly) {
    $sql .= " AND p.discount_percent > 0";
}
if ($search !== '') {
    $sql .= " AND p.name LIKE ?";
    $types .= "s";
    $params[] = "%$search%";
}
$sql .= " ORDER BY p.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($types)
    mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$products = mysqli_stmt_get_result($stmt);

// Helper: render badge HTML untuk produk card (multi-badge support)
function render_badge($p)
{
    $html = '';
    $html .= '<div class="product-badges">';
    if (!empty($p['badge_new']))
        $html .= '<span class="product-badge badge-type-new">New</span>';
    if (!empty($p['badge_restocked']))
        $html .= '<span class="product-badge badge-type-restocked">Restocked</span>';
    if (!empty($p['badge_limited']))
        $html .= '<span class="product-badge badge-type-limited">Limited</span>';
    $dp = (int) ($p['discount_percent'] ?? 0);
    if ($dp > 0)
        $html .= '<span class="product-badge badge-type-discount">-' . $dp . '%</span>';
    if ((int) ($p['stock'] ?? 0) === 0)
        $html .= '<span class="product-badge badge-type-soldout">Sold Out</span>';
    $html .= '</div>';
    return $html;
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
    <div class="page-header">
        <h1><?= $discountOnly ? 'Produk Diskon' : 'Katalog Produk' ?></h1>
        <p><?= $discountOnly ? 'Temukan produk dengan harga spesial' : 'Temukan Gunpla impianmu' ?></p>
    </div>

    <div class="section" style="padding-top:16px;">
        <form method="GET" action="products.php" style="margin-bottom:20px; display:flex; gap:10px;">
            <?php if ($selectedCategory): ?><input type="hidden" name="category"
                    value="<?= $selectedCategory ?>"><?php endif; ?>
            <?php if ($discountOnly): ?><input type="hidden" name="discount" value="1"><?php endif; ?>
            <input type="text" name="q" placeholder="Cari produk..." value="<?= h($search) ?>"
                style="flex:1; padding:12px 16px; border-radius:10px; border:1px solid var(--glass-border); background:var(--navy-900); color:#fff;">
            <button class="btn btn-gold" type="submit">Cari</button>
        </form>

        <div class="category-scroller">
            <a href="products.php"
                class="category-chip <?= $selectedCategory === 0 && !$discountOnly ? 'active' : '' ?>">Semua</a>
            <?php
            $hasDiscountChip = mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM products WHERE discount_percent > 0 LIMIT 1"));
            ?>
            <?php if ($hasDiscountChip): ?>
                <a href="products.php?discount=1"
                    class="category-chip category-chip-discount <?= $discountOnly ? 'active' : '' ?>">Diskon</a>
            <?php endif; ?>
            <?php mysqli_data_seek($categories, 0);
            while ($cat = mysqli_fetch_assoc($categories)): ?>
                <a href="products.php?category=<?= $cat['id'] ?>"
                    class="category-chip <?= $selectedCategory === (int) $cat['id'] && !$discountOnly ? 'active' : '' ?>"><?= h($cat['name']) ?></a>
            <?php endwhile; ?>
        </div>
    </div>

    <div class="section" style="padding-top:8px;">
        <div class="product-grid">
            <?php if (mysqli_num_rows($products) === 0): ?>
                <p>Produk tidak ditemukan.</p>
            <?php endif; ?>
            <?php while ($p = mysqli_fetch_assoc($products)): ?>
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
                            <?= $p['stock'] > 0 ? "Stok: {$p['stock']}" : "Stok habis" ?>
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