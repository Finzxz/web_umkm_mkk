<?php
$pageTitle = "Data Transaksi";
require_once __DIR__ . '/includes/admin_header.php';

// HAPUS TRANSAKSI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_transaction'])) {
    $id = (int)$_POST['transaction_id'];
    $stmt = mysqli_prepare($conn, "DELETE FROM transactions WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    set_flash('success', 'Transaksi berhasil dihapus.');
    header("Location: transactions.php"); exit;
}

// UPDATE STATUS TRANSAKSI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id = (int)$_POST['transaction_id'];
    $status = $_POST['status'];
    $valid = ['Menunggu Pembayaran','Diproses','Dikirim','Selesai','Dibatalkan'];
    if (in_array($status, $valid)) {
        $stmt = mysqli_prepare($conn, "UPDATE transactions SET status=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "si", $status, $id);
        mysqli_stmt_execute($stmt);
        set_flash('success', 'Status transaksi diperbarui.');
    }
    header("Location: transactions.php"); exit;
}

$transactions = mysqli_query($conn, "SELECT t.*, u.full_name, u.username FROM transactions t
    JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC");
$transactionsArr = mysqli_fetch_all($transactions, MYSQLI_ASSOC);

$statusClass = [
    'Menunggu Pembayaran' => 'badge-waiting', 'Diproses' => 'badge-process',
    'Dikirim' => 'badge-shipped', 'Selesai' => 'badge-done', 'Dibatalkan' => 'badge-cancel',
];
?>
<div class="card">
    <h3>🧾 Semua Transaksi</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Invoice</th><th>Pelanggan</th><th>Tanggal</th><th>Total</th><th>Pembayaran</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if (empty($transactionsArr)): ?>
                <tr><td colspan="7">Belum ada transaksi.</td></tr>
            <?php endif; ?>
            <?php foreach ($transactionsArr as $t): ?>
                <tr>
                    <td class="mono"><?= h($t['invoice_no']) ?></td>
                    <td><?= h($t['full_name']) ?> <br><small style="color:var(--text-muted);">@<?= h($t['username']) ?></small></td>
                    <td><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></td>
                    <td><?= rupiah($t['total_amount']) ?></td>
                    <td><?= h($t['payment_method']) ?></td>
                    <td>
                        <form method="POST" action="transactions.php" style="display:flex; gap:6px; align-items:center;">
                            <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
                            <select name="status" onchange="this.form.submit()" style="padding:4px 8px; border-radius:6px; background:var(--navy-900); color:#fff; border:1px solid var(--glass-border); font-size:12px;">
                                <?php foreach (array_keys($statusClass) as $s): ?>
                                    <option value="<?= $s ?>" <?= $t['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="update_status" value="1">
                        </form>
                    </td>
                    <td style="display:flex; gap:6px;">
                        <a href="transaction_detail.php?id=<?= $t['id'] ?>" class="btn btn-outline btn-sm">Detail</a>
                        <a href="../invoice.php?id=<?= $t['id'] ?>" target="_blank" class="btn btn-gold btn-sm">Cetak Invoice</a>
                        <form method="POST" action="transactions.php" onsubmit="return confirmDelete('Hapus transaksi ini?')">
                            <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
                            <button type="submit" name="delete_transaction" class="btn btn-red btn-sm">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
