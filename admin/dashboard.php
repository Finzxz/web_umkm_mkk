<?php
$pageTitle = "Dashboard";
require_once __DIR__ . '/includes/admin_header.php';

// -------- HANDLE ACTIONS --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // TAMBAH PRODUK
    if (isset($_POST['add_product'])) {
        $name = trim($_POST['name']);
        $category_id = (int) $_POST['category_id'];
        $grade = trim($_POST['grade']);
        $price = (float) $_POST['price'];
        $stock = (int) $_POST['stock'];
        $discount_percent = min(100, max(0, (int) ($_POST['discount_percent'] ?? 0)));
        $description = trim($_POST['description']);
        $image = trim($_POST['image']) ?: 'assets/img/placeholder.png';
        $set_limited = isset($_POST['set_limited']) ? 1 : 0;

        if ($name === '' || $price <= 0) {
            set_flash('error', 'Nama dan harga produk wajib diisi dengan benar.');
        } else {
            // Hapus badge_new dari produk lama, lalu set badge_new=1 pada produk baru
            // Badge limited dan restocked TIDAK terpengaruh
            mysqli_query($conn, "UPDATE products SET badge_new = 0");
            $stmt = mysqli_prepare($conn, "INSERT INTO products (category_id, name, grade, price, stock, image, description, badge_new, badge_limited, discount_percent) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");
            mysqli_stmt_bind_param($stmt, "issdissii", $category_id, $name, $grade, $price, $stock, $image, $description, $set_limited, $discount_percent);
            mysqli_stmt_execute($stmt);
            $new_product_id = mysqli_insert_id($conn);

            // Simpan gambar tambahan (hingga 5)
            for ($i = 2; $i <= 6; $i++) {
                $img_url = trim($_POST["image_$i"] ?? '');
                if ($img_url !== '') {
                    $stmt2 = mysqli_prepare($conn, "INSERT INTO product_images (product_id, image_url) VALUES (?, ?)");
                    mysqli_stmt_bind_param($stmt2, "is", $new_product_id, $img_url);
                    mysqli_stmt_execute($stmt2);
                }
            }

            set_flash('success', $set_limited ? 'Produk baru berhasil ditambahkan dengan badge Limited.' : 'Produk baru berhasil ditambahkan.');
        }
        header("Location: dashboard.php");
        exit;
    }

    // EDIT PRODUK
    if (isset($_POST['edit_product'])) {
        $id = (int) $_POST['product_id'];
        $name = trim($_POST['name']);
        $category_id = (int) $_POST['category_id'];
        $grade = trim($_POST['grade']);
        $price = (float) $_POST['price'];
        $stock = (int) $_POST['stock'];
        $discount_percent = min(100, max(0, (int) ($_POST['discount_percent'] ?? 0)));
        $description = trim($_POST['description']);
        $image = trim($_POST['image']) ?: 'assets/img/placeholder.png';

        $stmt = mysqli_prepare($conn, "UPDATE products SET category_id=?, name=?, grade=?, price=?, stock=?, image=?, description=?, discount_percent=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "issdissii", $category_id, $name, $grade, $price, $stock, $image, $description, $discount_percent, $id);
        mysqli_stmt_execute($stmt);
        set_flash('success', 'Produk berhasil diperbarui.');
        header("Location: dashboard.php");
        exit;
    }

    // HAPUS PRODUK
    if (isset($_POST['delete_product'])) {
        $id = (int) $_POST['product_id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        set_flash('success', 'Produk berhasil dihapus.');
        header("Location: dashboard.php");
        exit;
    }

    // RESTOCK PRODUK
    if (isset($_POST['restock_product'])) {
        $id = (int) $_POST['product_id'];
        $qty = (int) $_POST['restock_qty'];
        $note = trim($_POST['restock_note'] ?? '');

        if ($qty > 0) {
            // Ambil nama produk untuk notifikasi
            $prod = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM products WHERE id = $id"));
            $prod_name = $prod ? $prod['name'] : 'Produk';

            $stmt = mysqli_prepare($conn, "UPDATE products SET stock = stock + ? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "ii", $qty, $id);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($conn, "INSERT INTO restock_log (product_id, quantity_added, note) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iis", $id, $qty, $note);
            mysqli_stmt_execute($stmt);

            // Set badge_restocked pada produk ini (TIDAK menghapus badge produk lain)
            mysqli_query($conn, "UPDATE products SET badge_restocked = 1 WHERE id = $id");

            // Kirim notifikasi ke semua user
            create_notification($conn, 'all_users', 'Stok Tersedia Kembali!',
                "$prod_name baru saja di-restock sebanyak $qty unit. Buruan beli sebelum kehabisan!",
                "../product_detail.php?id=$id");

            set_flash('success', "Stok berhasil ditambahkan sebanyak $qty unit. Notifikasi dikirim ke pelanggan.");
        }
        header("Location: dashboard.php");
        exit;
    }

    // TOGGLE BADGE LIMITED
    if (isset($_POST['toggle_limited'])) {
        $id = (int) $_POST['product_id'];
        $action = $_POST['limited_action'] === 'set' ? 1 : 0;
        mysqli_query($conn, "UPDATE products SET badge_limited = $action WHERE id = $id");
        set_flash('success', $action ? 'Badge Limited berhasil diterapkan.' : 'Badge Limited berhasil dihapus.');
        header("Location: dashboard.php");
        exit;
    }

    // TOGGLE BADGE RESTOCKED
    if (isset($_POST['toggle_restocked'])) {
        $id = (int) $_POST['product_id'];
        $action = $_POST['restocked_action'] === 'set' ? 1 : 0;
        mysqli_query($conn, "UPDATE products SET badge_restocked = $action WHERE id = $id");
        set_flash('success', $action ? 'Badge Restocked berhasil diterapkan.' : 'Badge Restocked berhasil dihapus.');
        header("Location: dashboard.php");
        exit;
    }

    // TAMBAH GAMBAR
    if (isset($_POST['add_image'])) {
        $pid = (int) $_POST['product_id'];
        $img_url = trim($_POST['image_url']);
        if ($img_url !== '') {
            $stmt = mysqli_prepare($conn, "INSERT INTO product_images (product_id, image_url) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "is", $pid, $img_url);
            mysqli_stmt_execute($stmt);
            set_flash('success', 'Gambar tambahan berhasil ditambahkan.');
        }
        header("Location: dashboard.php");
        exit;
    }

    // HAPUS GAMBAR
    if (isset($_POST['delete_image'])) {
        $img_id = (int) $_POST['image_id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM product_images WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $img_id);
        mysqli_stmt_execute($stmt);
        set_flash('success', 'Gambar tambahan berhasil dihapus.');
        header("Location: dashboard.php");
        exit;
    }

    // MARK ADMIN NOTIFICATIONS READ
    if (isset($_POST['mark_admin_notif_read'])) {
        mark_notifications_read($conn, 'admin');
        header("Location: dashboard.php");
        exit;
    }
}

// -------- DATA FOR STATS --------
$totalProducts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM products"))['c'];
$totalStock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(stock),0) c FROM products"))['c'];
$totalTransactions = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM transactions"))['c'];
$totalRevenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) c FROM transactions WHERE status != 'Dibatalkan'"))['c'];

$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY id");
$products = mysqli_query($conn, "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
$productsArr = mysqli_fetch_all($products, MYSQLI_ASSOC);
$categoriesArr = mysqli_fetch_all($categories, MYSQLI_ASSOC);

// Ambil semua gambar tambahan dan kelompokkan berdasarkan product_id
$imagesResult = mysqli_query($conn, "SELECT * FROM product_images ORDER BY id ASC");
$imagesByProduct = [];
while ($img = mysqli_fetch_assoc($imagesResult)) {
    $imagesByProduct[$img['product_id']][] = $img;
}

// Admin notifications
$adminNotifs = get_notifications($conn, 'admin', 20);
$adminUnread = unread_notification_count($conn, 'admin');
?>

<?php if ($adminUnread > 0): ?>
<form method="POST" action="dashboard.php" style="margin-bottom:18px;">
    <button type="submit" name="mark_admin_notif_read" class="btn btn-outline btn-sm" style="margin-bottom:8px;">Tandai Semua Dibaca</button>
</form>
<?php endif; ?>

<?php if (!empty($adminNotifs)): ?>
<div class="card" style="margin-bottom:20px;" id="notifications">
    <h3>🔔 Notifikasi (<?= $adminUnread > 0 ? "$adminUnread baru" : 'semua dibaca' ?>)</h3>
    <div class="notif-list">
        <?php foreach ($adminNotifs as $n): ?>
            <a href="<?= h($n['link'] ?? '#') ?>" class="notif-item <?= empty($n['is_read']) ? 'notif-unread' : '' ?>">
                <div class="notif-body">
                    <strong><?= h($n['title']) ?></strong>
                    <p><?= h($n['message']) ?></p>
                    <small><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></small>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="stat-row">
    <div class="stat-card">
        <div class="val"><?= $totalProducts ?></div>
        <div class="label">Total Produk</div>
    </div>
    <div class="stat-card">
        <div class="val"><?= $totalStock ?></div>
        <div class="label">Total Stok Unit</div>
    </div>
    <div class="stat-card">
        <div class="val"><?= $totalTransactions ?></div>
        <div class="label">Total Transaksi</div>
    </div>
    <div class="stat-card">
        <div class="val"><?= rupiah($totalRevenue) ?></div>
        <div class="label">Total Pendapatan</div>
    </div>
</div>

<div class="admin-grid">
    <div class="card">
        <h3>🏷️ Kelola Kategori Produk</h3>
        <?php
        $existingCatNames = array_column($categoriesArr, 'name');
        $availableCats = array_diff(category_options(), $existingCatNames);
        ?>
        <?php if (!empty($availableCats)): ?>
            <form method="POST" action="dashboard.php" style="display:flex; gap:10px; margin-bottom:16px;">
                <select name="category_name" required
                    style="flex:1; padding:10px 12px; border-radius:8px; border:1px solid var(--glass-border); background:var(--navy-900); color:#fff;">
                    <?php foreach ($availableCats as $catName): ?>
                        <option value="<?= h($catName) ?>"><?= h($catName) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="add_category" class="btn btn-gold">Tambah Kategori</button>
            </form>
        <?php else: ?>
            <p style="color:var(--text-muted); font-size:13px; margin-bottom:16px;">Semua kategori jenis produk (Model Kit,
                Figure, Tools & Accessories) sudah ditambahkan.</p>
        <?php endif; ?>
        <?php if (empty($categoriesArr)): ?>
            <p style="color:var(--text-muted); font-size:13px;">Belum ada kategori. Tambahkan minimal satu kategori sebelum
                menambah produk.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Kategori</th>
                            <th>Jumlah Produk</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categoriesArr as $cat):
                            $prodCount = 0;
                            foreach ($productsArr as $pp) {
                                if ($pp['category_id'] == $cat['id'])
                                    $prodCount++;
                            }
                            ?>
                            <tr>
                                <td><?= h($cat['name']) ?></td>
                                <td><?= $prodCount ?></td>
                                <td>
                                    <form method="POST" action="dashboard.php"
                                        onsubmit="return confirmDelete('Hapus kategori <?= h(addslashes($cat['name'])) ?>?')">
                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                        <button type="submit" name="delete_category" class="btn btn-red btn-sm">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>➕ Tambah Produk Baru</h3>
        <?php if (empty($categoriesArr)): ?>
            <p style="color:var(--red-soft); font-size:13px; margin-bottom:12px;">⚠️ Tambahkan minimal satu kategori
                terlebih dahulu (lihat kartu "Kelola Kategori Produk" di atas) sebelum bisa menambah produk.</p>
        <?php endif; ?>
        <form method="POST" action="dashboard.php">
            <div class="form-row">
                <div class="form-group">
                    <label>Nama Produk</label>
                    <input type="text" name="name" required <?= empty($categoriesArr) ? 'disabled' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Kategori</label>
                    <select name="category_id" required <?= empty($categoriesArr) ? 'disabled' : '' ?>>
                        <?php foreach ($categoriesArr as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Grade</label>
                    <select name="grade" required <?= empty($categoriesArr) ? 'disabled' : '' ?>>
                        <?php foreach (grade_options() as $g): ?>
                            <option value="<?= $g ?>"><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Harga (Rp)</label>
                    <input type="number" name="price" min="0" step="1000" required>
                </div>
                <div class="form-group">
                    <label>Diskon (%)</label>
                    <input type="number" name="discount_percent" min="0" max="100" value="0" <?= empty($categoriesArr) ? 'disabled' : '' ?>>
                    <span style="color:var(--text-muted);font-size:11px;">0 = tanpa diskon</span>
                </div>
                <div class="form-group">
                    <label>Stok Awal</label>
                    <input type="number" name="stock" min="0" value="0" required>
                </div>
            </div>
            <div class="form-group">
                <label>URL Gambar Utama *</label>
                <input type="text" name="image" placeholder="assets/img/products/nama-file.jpg" <?= empty($categoriesArr) ? 'disabled' : '' ?>>
            </div>
            <?php for ($i = 2; $i <= 6; $i++): ?>
            <div class="form-group">
                <label>URL Gambar Tambahan <?= $i - 1 ?> <span style="color:var(--text-muted);font-weight:400;">(opsional)</span></label>
                <input type="text" name="image_<?= $i ?>" placeholder="assets/img/products/nama-file-<?= $i - 1 ?>.jpg" <?= empty($categoriesArr) ? 'disabled' : '' ?>>
            </div>
            <?php endfor; ?>
            <div class="form-group">
                <label>Deskripsi</label>
                <textarea name="description" rows="3" <?= empty($categoriesArr) ? 'disabled' : '' ?>></textarea>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="set_limited" id="set_limited" value="1" <?= empty($categoriesArr) ? 'disabled' : '' ?>>
                    <span>Jadikan Limited</span>
                </label>
            </div>
            <button type="submit" name="add_product" class="btn btn-gold" <?= empty($categoriesArr) ? 'disabled' : '' ?>>Tambah Produk</button>
        </form>
    </div>

    <div class="card">
        <h3>📦 Data Produk</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th></th>
                        <th>Nama</th>
                        <th>Kategori</th>
                        <th>Grade</th>
                        <th>Harga</th>
                        <th>Diskon</th>
                        <th>Stok</th>
                        <th>Badge</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productsArr as $p): 
                        $isNew = !empty($p['badge_new']);
                        $isRestocked = !empty($p['badge_restocked']);
                        $isLimited = !empty($p['badge_limited']);
                        $isSoldOut = ((int)$p['stock']) === 0;
                    ?>
                        <tr>
                            <td><img class="prod-thumb" src="<?= h($p['image'] ?: '../assets/img/placeholder.png') ?>"
                                    alt=""></td>
                            <td><?= h($p['name']) ?></td>
                            <td><?= h($p['category_name']) ?></td>
                            <td><?= h($p['grade'] ?: '-') ?></td>
                            <td>
                                <?php $dp = (int)$p['discount_percent']; ?>
                                <?php if ($dp > 0): ?>
                                    <span style="color:var(--red-soft);font-weight:700;"><?= rupiah($p['price'] * (100 - $dp) / 100) ?></span>
                                    <br><span style="color:var(--text-muted);font-size:11px;text-decoration:line-through;"><?= rupiah($p['price']) ?></span>
                                <?php else: ?>
                                    <?= rupiah($p['price']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $dp > 0 ? "<span style=\"color:var(--red-soft);font-weight:700;\">" . $dp . '%</span>' : '<span style="color:var(--text-muted);">-</span>' ?></td>
                            <td><span
                                    class="badge <?= $p['stock'] <= 5 ? 'badge-low' : 'badge-ok' ?>"><?= $p['stock'] ?></span>
                            </td>
                            <td class="admin-badge-cell">
                                <?php if ($isNew): ?><span class="badge badge-new">New</span><?php endif; ?>
                                <?php if ($isRestocked): ?><span class="badge badge-restocked">Restocked</span><?php endif; ?>
                                <?php if ($isLimited): ?><span class="badge badge-limited">Limited</span><?php endif; ?>
                                <?php if ($isSoldOut): ?><span class="badge badge-soldout">Sold Out</span><?php endif; ?>
                                <?php if (!$isNew && !$isRestocked && !$isLimited && !$isSoldOut): ?><span style="color:var(--text-muted);">-</span><?php endif; ?>
                            </td>
                            <td class="action-cell">
                                <button class="btn btn-outline btn-sm"
                                    onclick="openEditModal(<?= $p['id'] ?>)">Edit</button>
                                <form method="POST" action="dashboard.php"
                                    onsubmit="return confirmDelete('Hapus produk <?= h(addslashes($p['name'])) ?>?')">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <button type="submit" name="delete_product" class="btn btn-red btn-sm">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============ MODAL: EDIT PRODUK (GABUNGAN) ============ -->
<?php foreach ($productsArr as $p): 
    $isNew = !empty($p['badge_new']);
    $isRestocked = !empty($p['badge_restocked']);
    $isLimited = !empty($p['badge_limited']);
    $isSoldOut = ((int)$p['stock']) === 0;
?>
    <div class="modal-overlay" id="editModal<?= $p['id'] ?>">
        <div class="modal-box modal-edit-gabungan">
            <span class="modal-close" onclick="closeModal('editModal<?= $p['id'] ?>')">&times;</span>

            <h3 style="margin-bottom:4px;">Edit Produk</h3>
            <p style="color:var(--text-muted); font-size:13px; margin-bottom:16px;">"><?= h($p['name']) ?></p>

            <!-- Tab Navigation -->
            <div class="edit-tabs">
                <button type="button" class="edit-tab active" onclick="switchTab(this, 'info-<?= $p['id'] ?>')">📋 Info Produk</button>
                <button type="button" class="edit-tab" onclick="switchTab(this, 'restock-<?= $p['id'] ?>')">📦 Restock</button>
                <button type="button" class="edit-tab" onclick="switchTab(this, 'badge-<?= $p['id'] ?>')">🏷️ Badge</button>
                <button type="button" class="edit-tab" onclick="switchTab(this, 'images-<?= $p['id'] ?>')">🖼️ Gambar</button>
            </div>

            <!-- TAB 1: Info Produk -->
            <div class="edit-tab-content active" id="info-<?= $p['id'] ?>">
                <form method="POST" action="dashboard.php">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <div class="form-group"><label>Nama Produk</label><input type="text" name="name"
                            value="<?= h($p['name']) ?>" required></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kategori</label>
                            <select name="category_id" required>
                                <?php foreach ($categoriesArr as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $p['category_id'] ? 'selected' : '' ?>>
                                        <?= h($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Grade</label>
                            <select name="grade" required>
                                <?php foreach (grade_options() as $g): ?>
                                    <option value="<?= $g ?>" <?= $p['grade'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Harga (Rp)</label><input type="number" name="price"
                                value="<?= $p['price'] ?>" min="0" step="1000" required></div>
                        <div class="form-group"><label>Diskon (%)</label><input type="number" name="discount_percent"
                                value="<?= (int)$p['discount_percent'] ?>" min="0" max="100">
                            <span style="color:var(--text-muted);font-size:11px;">0 = tanpa diskon</span>
                        </div>
                        <div class="form-group"><label>Stok</label><input type="number" name="stock" value="<?= $p['stock'] ?>"
                                min="0" required></div>
                    </div>
                    <div class="form-group"><label>URL Gambar Utama</label><input type="text" name="image"
                            value="<?= h($p['image']) ?>" placeholder="assets/img/products/nama-file.jpg"></div>
                    <div class="form-group"><label>Deskripsi</label><textarea name="description"
                            rows="3"><?= h($p['description']) ?></textarea></div>
                    <button type="submit" name="edit_product" class="btn btn-gold btn-block">Simpan Perubahan</button>
                </form>
            </div>

            <!-- TAB 2: Restock -->
            <div class="edit-tab-content" id="restock-<?= $p['id'] ?>">
                <p style="color:var(--text-muted); font-size:13px; margin-bottom:14px;">
                    Stok saat ini: <strong style="color:var(--gold-soft);"><?= $p['stock'] ?></strong> unit
                </p>
                <form method="POST" action="dashboard.php">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <div class="form-group"><label>Jumlah Tambahan Stok</label><input type="number" name="restock_qty" min="1"
                            required placeholder="Masukkan jumlah"></div>
                    <div class="form-group"><label>Catatan (opsional)</label><input type="text" name="restock_note"
                            placeholder="mis. Restock dari supplier X"></div>
                    <button type="submit" name="restock_product" class="btn btn-gold btn-block">Tambahkan Stok</button>
                </form>
                <p style="color:var(--text-muted); font-size:11px; margin-top:12px; line-height:1.5;">
                    ⚡ Restock akan memberi badge <strong>Restocked</strong> pada produk ini dan mengirim notifikasi ke pelanggan. Badge New & Limited tidak terpengaruh.
                </p>
            </div>

            <!-- TAB 3: Badge -->
            <div class="edit-tab-content" id="badge-<?= $p['id'] ?>">
                <div style="margin-bottom:16px;">
                    <h4 style="font-size:13px; color:var(--text-muted); margin-bottom:10px; text-transform:uppercase; letter-spacing:.5px;">Status Badge Saat Ini</h4>
                    <div style="display:flex; flex-wrap:wrap; gap:6px;">
                        <?php if ($isNew): ?><span class="badge badge-new" style="font-size:12px; padding:5px 14px;">New</span><?php endif; ?>
                        <?php if ($isRestocked): ?><span class="badge badge-restocked" style="font-size:12px; padding:5px 14px;">Restocked</span><?php endif; ?>
                        <?php if ($isLimited): ?><span class="badge badge-limited" style="font-size:12px; padding:5px 14px;">Limited</span><?php endif; ?>
                        <?php if ($isSoldOut): ?><span class="badge badge-soldout" style="font-size:12px; padding:5px 14px;">Sold Out</span><?php endif; ?>
                        <?php if (!$isNew && !$isRestocked && !$isLimited && !$isSoldOut): ?><span style="color:var(--text-muted); font-size:13px;">Tidak ada badge</span><?php endif; ?>
                    </div>
                </div>

                <div style="background:var(--glass-bg); border:1px solid var(--glass-border); border-radius:10px; padding:14px; margin-bottom:14px;">
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                        <span class="badge badge-new" style="font-size:12px;">New</span>
                        <span style="font-size:13px;">Otomatis saat produk ditambahkan. Hilang saat produk baru lain ditambahkan.</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                        <span class="badge badge-restocked" style="font-size:12px;">Restocked</span>
                        <span style="font-size:13px;">Otomatis/manual. Tidak hilang otomatis kecuali dihapus manual.</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                        <span class="badge badge-limited" style="font-size:12px;">Limited</span>
                        <span style="font-size:13px;">Manual. Tidak hilang otomatis.</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="badge badge-soldout" style="font-size:12px;">Sold Out</span>
                        <span style="font-size:13px;">Otomatis saat stok = 0. Hilang saat stok > 0.</span>
                    </div>
                </div>

                <div style="display:flex; gap:8px;">
                    <form method="POST" action="dashboard.php" style="flex:1;">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <?php if ($isLimited): ?>
                            <input type="hidden" name="limited_action" value="remove">
                            <button type="submit" name="toggle_limited" class="btn btn-red btn-block">Hapus Limited</button>
                        <?php else: ?>
                            <input type="hidden" name="limited_action" value="set">
                            <button type="submit" name="toggle_limited" class="btn btn-gold btn-block">Beri Limited</button>
                        <?php endif; ?>
                    </form>
                    <form method="POST" action="dashboard.php" style="flex:1;">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <?php if ($isRestocked): ?>
                            <input type="hidden" name="restocked_action" value="remove">
                            <button type="submit" name="toggle_restocked" class="btn btn-red btn-block">Hapus Restocked</button>
                        <?php else: ?>
                            <input type="hidden" name="restocked_action" value="set">
                            <button type="submit" name="toggle_restocked" class="btn btn-outline btn-block">Beri Restocked</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- TAB 4: Gambar -->
            <div class="edit-tab-content" id="images-<?= $p['id'] ?>">
                <form method="POST" action="dashboard.php" style="margin-bottom:20px;">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <div class="form-group">
                        <label>URL Gambar Baru</label>
                        <div style="display:flex; gap:8px;">
                            <input type="text" name="image_url" placeholder="assets/img/products/nama-file.jpg" required style="flex:1;">
                            <button type="submit" name="add_image" class="btn btn-gold" style="white-space:nowrap;">Tambah</button>
                        </div>
                    </div>
                </form>

                <?php if (!empty($imagesByProduct[$p['id']])): ?>
                    <h4 style="margin-bottom:10px; font-size:14px;">Gambar Tersimpan:</h4>
                    <div class="edit-images-grid">
                        <?php foreach ($imagesByProduct[$p['id']] as $img): ?>
                            <div class="edit-img-item">
                                <img src="<?= h($img['image_url']) ?>" alt="">
                                <form method="POST" action="dashboard.php">
                                    <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                    <button type="submit" name="delete_image" class="edit-img-delete" title="Hapus gambar">&times;</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:var(--text-muted); font-size:13px;">Belum ada gambar tambahan untuk produk ini.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>
<?php endforeach; ?>

<script>
    function openEditModal(id) { document.getElementById('editModal' + id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }
    window.addEventListener('click', function (e) {
        if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('active');
    });

    function switchTab(btn, tabId) {
        const box = btn.closest('.modal-edit-gabungan');
        box.querySelectorAll('.edit-tab').forEach(t => t.classList.remove('active'));
        box.querySelectorAll('.edit-tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(tabId).classList.add('active');
    }
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>