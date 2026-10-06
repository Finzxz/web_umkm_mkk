<?php
$pageTitle = "Invoice";
require_once __DIR__ . '/includes/functions.php';

// Invoice bisa diakses oleh admin (dari tabel transaksi) ATAU pelanggan pemilik transaksi
if (!is_admin() && !is_logged_in()) {
    set_flash('error', 'Silakan login terlebih dahulu.');
    header("Location: login.php");
    exit;
}

$id = (int) ($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT t.*, u.full_name, u.email, u.phone, u.username
    FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$trx = mysqli_stmt_get_result($stmt)->fetch_assoc();

if (!$trx) {
    set_flash('error', 'Transaksi tidak ditemukan.');
    header("Location: profile.php?page=history");
    exit;
}

// Hanya pemilik transaksi atau admin yang boleh melihat
if (!is_admin() && $trx['user_id'] != current_user_id()) {
    set_flash('error', 'Anda tidak memiliki akses ke invoice ini.');
    header("Location: profile.php?page=history");
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT * FROM transaction_items WHERE transaction_id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$items = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

$statusClass = [
    'Menunggu Pembayaran' => 'badge-waiting',
    'Diproses' => 'badge-process',
    'Dikirim' => 'badge-shipped',
    'Selesai' => 'badge-done',
    'Dibatalkan' => 'badge-cancel',
][$trx['status']] ?? 'badge-process';

require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
    <div class="invoice-actions no-print">
        <a href="<?= is_admin() ? 'admin/transactions.php' : 'profile.php?page=history' ?>"
            class="btn btn-outline">&larr; Kembali</a>
        <button onclick="printInvoice()" class="btn btn-gold">🖨️ Cetak / Download Invoice</button>
    </div>

    <div class="invoice-box">
        <div class="inv-head">
            <div>
                <h2>Gundshop.id</h2>
                <p style="color:#666; font-size:13px;">Toko & Komunitas Gunpla Indonesia</p>
            </div>
            <div style="text-align:right;">
                <h3>INVOICE</h3>
                <p style="font-size:13px; color:#666;"><?= h($trx['invoice_no']) ?></p>
                <p style="font-size:13px; color:#666;"><?= date('d M Y, H:i', strtotime($trx['created_at'])) ?></p>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; margin-bottom:20px; font-size:14px;">
            <div>
                <strong>Ditagihkan kepada:</strong><br>
                <?= h($trx['full_name']) ?><br>
                <?= h($trx['email']) ?><br>
                <?= h($trx['phone']) ?>
            </div>
            <div style="text-align:right;">
                <strong>Status:</strong> <span class="badge <?= $statusClass ?>"><?= h($trx['status']) ?></span><br><br>
                <strong>Pembayaran:</strong> <?= h($trx['payment_method']) ?><br>
                <strong>Pengiriman:</strong> <?= h($trx['shipping_method']) ?>
            </div>
        </div>

        <?php if ($trx['shipping_address']): ?>
            <p style="font-size:13px; margin-bottom:16px;"><strong>Alamat:</strong>
                <?= nl2br(h($trx['shipping_address'])) ?></p>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Harga</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
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

        <?php
        $itemsTotal = 0;
        foreach ($items as $it) $itemsTotal += (float)$it['subtotal'];
        $shippingCost = (float)($trx['shipping_cost'] ?? 0);
        $voucherDiscount = (float)($trx['voucher_discount'] ?? 0);
        $voucherCode = $trx['voucher_code'] ?? '';
        $totalAmount = (float)$trx['total_amount'];
        ?>
        <div class="inv-totals">
            <div class="inv-total-row"><span>Subtotal Produk</span><span><?= rupiah($itemsTotal) ?></span></div>
            <div class="inv-total-row"><span>Ongkos Kirim (<?= h($trx['shipping_method']) ?>)</span><span><?= $shippingCost > 0 ? rupiah($shippingCost) : 'GRATIS' ?></span></div>
            <?php if ($voucherDiscount > 0): ?>
            <div class="inv-total-row" style="color:#16a34a;"><span>Diskon Voucher <?= $voucherCode ? '(' . h($voucherCode) . ')' : '' ?></span><span>- <?= rupiah($voucherDiscount) ?></span></div>
            <?php elseif ($voucherCode && $shippingCost == 0 && $trx['shipping_method'] !== 'Ambil di Toko'): ?>
            <div class="inv-total-row" style="color:#16a34a;"><span>Gratis Ongkir (<?= h($voucherCode) ?>)</span><span>GRATIS</span></div>
            <?php endif; ?>
            <div class="inv-total-row inv-grand-total"><span>Total Bayar</span><span><?= rupiah($totalAmount) ?></span></div>
        </div>

        <p style="margin-top:30px; font-size:12px; color:#888; text-align:center;">
            Terima kasih telah berbelanja di Gundshop.id!
        </p>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>