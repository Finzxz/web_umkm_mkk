<?php
$pageTitle = "Detail Transaksi";
require_once __DIR__ . '/includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT t.*, u.full_name, u.email, u.phone, u.username FROM transactions t
    JOIN users u ON t.user_id = u.id WHERE t.id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$trx = mysqli_stmt_get_result($stmt)->fetch_assoc();

if (!$trx) {
    set_flash('error', 'Transaksi tidak ditemukan.');
    header("Location: transactions.php"); exit;
}

$stmt = mysqli_prepare($conn, "SELECT * FROM transaction_items WHERE transaction_id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$items = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

$statusClass = [
    'Menunggu Pembayaran' => 'badge-waiting', 'Diproses' => 'badge-process',
    'Dikirim' => 'badge-shipped', 'Selesai' => 'badge-done', 'Dibatalkan' => 'badge-cancel',
][$trx['status']] ?? '';
?>
<a href="transactions.php" class="btn btn-outline" style="margin-bottom:18px; display:inline-flex;">&larr; Kembali ke Data Transaksi</a>

<div class="admin-grid">
    <div class="card">
        <h3>Informasi Transaksi</h3>
        <p class="mono" style="margin-bottom:10px;">No. Invoice: <strong><?= h($trx['invoice_no']) ?></strong></p>
        <p style="margin-bottom:6px;"><strong>Pelanggan:</strong> <?= h($trx['full_name']) ?> (@<?= h($trx['username']) ?>)</p>
        <p style="margin-bottom:6px;"><strong>Kontak:</strong> <?= h($trx['email']) ?> &middot; <?= h($trx['phone']) ?></p>
        <p style="margin-bottom:6px;"><strong>Tanggal:</strong> <?= date('d M Y, H:i', strtotime($trx['created_at'])) ?></p>
        <p style="margin-bottom:6px;"><strong>Pembayaran:</strong> <?= h($trx['payment_method']) ?></p>
        <p style="margin-bottom:6px;"><strong>Pengiriman:</strong> <?= h($trx['shipping_method']) ?></p>
        <?php if ((float)($trx['shipping_cost'] ?? 0) > 0): ?>
        <p style="margin-bottom:6px;"><strong>Ongkir:</strong> <?= rupiah($trx['shipping_cost']) ?></p>
        <?php endif; ?>
        <?php if ($trx['shipping_address']): ?>
        <p style="margin-bottom:6px;"><strong>Alamat:</strong> <?= nl2br(h($trx['shipping_address'])) ?></p>
        <?php endif; ?>
        <?php if ($trx['voucher_code']): ?>
        <p style="margin-bottom:6px;"><strong>Voucher:</strong> <span style="color:var(--gold);font-family:monospace;"><?= h($trx['voucher_code']) ?></span>
        <?php if ((float)($trx['voucher_discount'] ?? 0) > 0): ?> (potongan <?= rupiah($trx['voucher_discount']) ?>)
        <?php else: ?> (Gratis Ongkir)
        <?php endif; ?>
        </p>
        <?php endif; ?>
        <p style="margin-bottom:6px;"><strong>Status:</strong> <span class="badge <?= $statusClass ?>"><?= h($trx['status']) ?></span></p>
        <a href="../invoice.php?id=<?= $id ?>" target="_blank" class="btn btn-gold" style="margin-top:12px;">🖨️ Cetak Invoice</a>
    </div>

    <div class="card">
        <h3>Produk yang Dibeli</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Produk</th><th>Harga</th><th>Qty</th><th>Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= h($it['product_name']) ?></td>
                        <td><?= rupiah($it['price']) ?></td>
                        <td><?= $it['quantity'] ?></td>
                        <td><?= rupiah($it['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="text-align:right; margin-top:16px;">
            <?php
            $itemsTotal = 0;
            foreach ($items as $it) $itemsTotal += (float)$it['subtotal'];
            $shipCost = (float)($trx['shipping_cost'] ?? 0);
            $vDisc = (float)($trx['voucher_discount'] ?? 0);
            ?>
            <div style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">Subtotal: <?= rupiah($itemsTotal) ?></div>
            <?php if ($shipCost > 0): ?><div style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">Ongkir: <?= rupiah($shipCost) ?></div><?php endif; ?>
            <?php if ($vDisc > 0): ?><div style="font-size:13px;color:#7de3a4;margin-bottom:4px;">Voucher: -<?= rupiah($vDisc) ?></div><?php endif; ?>
            <div style="font-weight:700; font-size:16px;">Total: <?= rupiah($trx['total_amount']) ?></div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
