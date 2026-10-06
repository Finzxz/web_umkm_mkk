<?php
$pageTitle = "Profil Saya";
require_once __DIR__ . '/includes/functions.php';
require_login('login.php');

$uid = current_user_id();
$page = $_GET['page'] ?? 'edit';

// -------- HANDLE CANCEL TRANSACTION --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_transaction'])) {
    $tx_id = (int) $_POST['transaction_id'];

    // Verifikasi transaksi milik user ini & status masih bisa dibatalkan
    $stmt = mysqli_prepare($conn, "SELECT id, status FROM transactions WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $tx_id, $uid);
    mysqli_stmt_execute($stmt);
    $tx = mysqli_stmt_get_result($stmt)->fetch_assoc();

    if (!$tx) {
        set_flash('error', 'Transaksi tidak ditemukan.');
    } elseif (!in_array($tx['status'], ['Menunggu Pembayaran', 'Diproses'])) {
        set_flash('error', 'Transaksi dengan status "' . h($tx['status']) . '" tidak dapat dibatalkan.');
    } else {
        // Kembalikan stok untuk setiap item
        $items = mysqli_query($conn, "SELECT product_id, quantity FROM transaction_items WHERE transaction_id = $tx_id");
        while ($item = mysqli_fetch_assoc($items)) {
            if ($item['product_id']) {
                mysqli_query($conn, "UPDATE products SET stock = stock + {$item['quantity']} WHERE id = {$item['product_id']}");
            }
        }

        // Update status transaksi
        mysqli_query($conn, "UPDATE transactions SET status = 'Dibatalkan' WHERE id = $tx_id");

        set_flash('success', 'Pesanan berhasil dibatalkan. Stok produk telah dikembalikan.');
    }
    header("Location: profile.php?page=history");
    exit;
}

// -------- HANDLE PROFILE UPDATE --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $username  = trim($_POST['username']);
    $phone     = trim($_POST['phone']);
    $address   = trim($_POST['address'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    $check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? AND id != ?");
    mysqli_stmt_bind_param($check, "si", $username, $uid);
    mysqli_stmt_execute($check);

    if (mysqli_stmt_get_result($check)->num_rows > 0) {
        set_flash('error', 'Username sudah digunakan pengguna lain.');
    } else {
        if ($new_password !== '') {
            if (strlen($new_password) < 6) {
                set_flash('error', 'Password baru minimal 6 karakter.');
                header("Location: profile.php?page=edit"); exit;
            }
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET full_name=?, username=?, phone=?, address=?, password=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "sssssi", $full_name, $username, $phone, $address, $hash, $uid);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET full_name=?, username=?, phone=?, address=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "ssssi", $full_name, $username, $phone, $address, $uid);
        }
        mysqli_stmt_execute($stmt);
        $_SESSION['customer']['full_name'] = $full_name;
        $_SESSION['customer']['username'] = $username;
        set_flash('success', 'Profil berhasil diperbarui.');
    }
    header("Location: profile.php?page=edit");
    exit;
}

// -------- FETCH USER DATA --------
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $uid);
mysqli_stmt_execute($stmt);
$user = mysqli_stmt_get_result($stmt)->fetch_assoc();

// -------- FETCH TRANSACTION HISTORY --------
$history = [];
if ($page === 'history') {
    $stmt = mysqli_prepare($conn, "SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    $history = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
}

$statusClass = [
    'Menunggu Pembayaran' => 'badge-waiting', 'Diproses' => 'badge-process',
    'Dikirim' => 'badge-shipped', 'Selesai' => 'badge-done', 'Dibatalkan' => 'badge-cancel',
];

// Status yang bisa dibatalkan oleh user
$canCancel = ['Menunggu Pembayaran', 'Diproses'];

require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
    <div class="page-header"><h1>Profil Saya</h1></div>

    <div class="profile-layout">
        <div class="profile-sidebar">
            <a href="profile.php?page=edit" class="<?= $page === 'edit' ? 'active' : '' ?>">✏️ Edit Profil</a>
            <a href="profile.php?page=history" class="<?= $page === 'history' ? 'active' : '' ?>">🧾 Riwayat Transaksi</a>
        </div>

        <div class="profile-content">
            <?php if ($page === 'edit'): ?>
                <h3 style="margin-bottom:20px;">Edit Profil</h3>
                <form method="POST" action="profile.php?page=edit">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Lengkap</label>
                            <input type="text" name="full_name" value="<?= h($user['full_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" value="<?= h($user['username']) ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" value="<?= h($user['email']) ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>No. HP</label>
                            <input type="text" name="phone" value="<?= h($user['phone']) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Alamat (digunakan untuk checkout)</label>
                        <textarea name="address" rows="3" placeholder="Tulis alamat lengkap kamu..."><?= h($user['address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Password Baru (kosongkan jika tidak ingin mengubah)</label>
                        <input type="password" name="new_password" minlength="6">
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-gold">Simpan Perubahan</button>
                </form>

            <?php else: ?>
                <h3 style="margin-bottom:20px;">Riwayat Transaksi</h3>
                <?php if (empty($history)): ?>
                    <p>Belum ada transaksi. <a href="products.php" style="color:var(--gold); font-weight:700;">Mulai belanja</a></p>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>No. Invoice</th><th>Tanggal</th><th>Total</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($history as $t): ?>
                            <tr>
                                <td><?= h($t['invoice_no']) ?></td>
                                <td><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                                <td><?= rupiah($t['total_amount']) ?></td>
                                <td><span class="badge <?= $statusClass[$t['status']] ?? '' ?>"><?= h($t['status']) ?></span></td>
                                <td class="profile-tx-actions">
                                    <a href="invoice.php?id=<?= $t['id'] ?>" class="btn btn-outline btn-sm">Lihat Invoice</a>
                                    <?php if (in_array($t['status'], $canCancel)): ?>
                                    <form method="POST" action="profile.php?page=history" style="display:inline-flex;"
                                        onsubmit="return confirm('Yakin ingin membatalkan pesanan <?= h(addslashes($t['invoice_no'])) ?>? Stok produk akan dikembalikan.')">
                                        <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
                                        <button type="submit" name="cancel_transaction" class="btn btn-red btn-sm">Batalkan</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
