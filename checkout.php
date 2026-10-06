<?php
$pageTitle = "Checkout";
require_once __DIR__ . '/includes/functions.php';
require_login('login.php');

$uid = current_user_id();

// -------- AMBIL DATA USER (termasuk alamat) --------
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $uid);
mysqli_stmt_execute($stmt);
$user = mysqli_stmt_get_result($stmt)->fetch_assoc();

// -------- SIAPKAN ITEMS (dari keranjang ATAU beli langsung) --------
$buyNow = isset($_GET['buy_now']) ? (int) $_GET['buy_now'] : 0;
$buyNowQty = isset($_GET['qty']) ? max(1, (int) $_GET['qty']) : 1;
$items = [];
$checkoutMode = 'cart'; // default: dari keranjang

if ($buyNow > 0) {
    // Mode: Beli Sekarang (langsung dari detail produk)
    $checkoutMode = 'buy_now';
    $stmt = mysqli_prepare($conn, "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.stock >= ?");
    mysqli_stmt_bind_param($stmt, "ii", $buyNow, $buyNowQty);
    mysqli_stmt_execute($stmt);
    $p = mysqli_stmt_get_result($stmt)->fetch_assoc();
    if (!$p) {
        set_flash('error', 'Produk tidak ditemukan atau stok tidak mencukupi.');
        header('Location: products.php');
        exit;
    }
    $items[] = [
        'product_id' => $p['id'],
        'name' => $p['name'],
        'price' => $p['price'],
        'discount_percent' => $p['discount_percent'],
        'quantity' => $buyNowQty,
        'image' => $p['image'],
        'stock' => $p['stock'],
    ];
} else {
    // Mode: dari Keranjang
    $stmt = mysqli_prepare($conn, "SELECT c.id AS cart_id, c.quantity, p.id AS product_id, p.name, p.price, p.stock, p.image, p.discount_percent
        FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? ORDER BY c.added_at DESC");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    $items = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

    if (empty($items)) {
        set_flash('error', 'Keranjang kamu kosong.');
        header('Location: cart.php');
        exit;
    }
}

// -------- HITUNG SUBTOTAL --------
$subtotal = 0;
foreach ($items as $it) {
    $dp = (int)($it['discount_percent'] ?? 0);
    $ep = ($dp > 0 && $dp <= 100) ? $it['price'] * (100 - $dp) / 100 : $it['price'];
    $subtotal += $ep * $it['quantity'];
}

// -------- VOUCHER (via AJAX atau POST) --------
$voucherResult = null;
$voucherDiscount = 0;
$voucherCode = '';

// Proses form checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $payment_method = $_POST['payment_method'] ?? '';
    $shipping_method = $_POST['shipping_method'] ?? '';
    $city = $_POST['city'] ?? '';
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $voucher_input = strtoupper(trim($_POST['voucher_code'] ?? ''));

    $valid_payment = ['Transfer Bank', 'COD', 'E-Wallet', 'QRIS'];
    $valid_shipping = ['Reguler', 'Express', 'Ambil di Toko'];

    if (!in_array($payment_method, $valid_payment) || !in_array($shipping_method, $valid_shipping)) {
        set_flash('error', 'Pilih metode pembayaran dan pengiriman yang valid.');
    } elseif ($shipping_method !== 'Ambil di Toko' && ($city === '' || $shipping_address === '')) {
        set_flash('error', 'Kota dan alamat pengiriman wajib diisi.');
    } else {
        // Hitung ongkir
        $shippingCost = calculate_shipping_cost($city, $shipping_method);

        // Validasi & terapkan voucher
        $voucherDiscount = 0;
        $voucherCode = '';
        if ($voucher_input !== '') {
            $vr = validate_voucher($conn, $voucher_input, $subtotal);
            if (!$vr['valid']) {
                set_flash('error', $vr['message']);
            } else {
                $voucherCode = $voucher_input;
                if ($vr['voucher']['type'] === 'discount') {
                    $voucherDiscount = $vr['discount'];
                } elseif ($vr['voucher']['type'] === 'free_shipping') {
                    $shippingCost = 0;
                }
            }
        }

        if (!isset($_SESSION['flash']['error'])) {
            // Simpan alamat ke profil user jika centang
            if (isset($_POST['save_address']) && $shipping_address !== '') {
                $stmt = mysqli_prepare($conn, "UPDATE users SET address = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $shipping_address, $uid);
                mysqli_stmt_execute($stmt);
            }

            $totalAmount = $subtotal - $voucherDiscount + $shippingCost;

            mysqli_begin_transaction($conn);
            try {
                // Validasi stok & hitung ulang
                $finalItems = [];
                foreach ($items as $it) {
                    $check = mysqli_query($conn, "SELECT stock, price, discount_percent FROM products WHERE id = {$it['product_id']} FOR UPDATE");
                    $pRow = mysqli_fetch_assoc($check);
                    if (!$pRow || $it['quantity'] > $pRow['stock']) {
                        throw new Exception("Stok " . h($it['name']) . " tidak mencukupi.");
                    }
                    $dp = (int)$pRow['discount_percent'];
                    $ep = ($dp > 0 && $dp <= 100) ? $pRow['price'] * (100 - $dp) / 100 : $pRow['price'];
                    $finalItems[] = [
                        'product_id' => $it['product_id'],
                        'name' => $it['name'],
                        'price' => $pRow['price'],
                        'quantity' => $it['quantity'],
                        'subtotal' => $ep * $it['quantity'],
                    ];
                }

                $invoice_no = generate_invoice_no();
                $stmt = mysqli_prepare($conn, "INSERT INTO transactions (invoice_no, user_id, total_amount, payment_method, shipping_method, shipping_address, shipping_cost, voucher_code, voucher_discount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Diproses')");
                mysqli_stmt_bind_param($stmt, "sidsssdds", $invoice_no, $uid, $totalAmount, $payment_method, $shipping_method, $shipping_address, $shippingCost, $voucherCode, $voucherDiscount);
                mysqli_stmt_execute($stmt);
                $transaction_id = mysqli_insert_id($conn);

                foreach ($finalItems as $fi) {
                    $stmt = mysqli_prepare($conn, "INSERT INTO transaction_items (transaction_id, product_id, product_name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "iisdid", $transaction_id, $fi['product_id'], $fi['name'], $fi['price'], $fi['quantity'], $fi['subtotal']);
                    mysqli_stmt_execute($stmt);

                    $stmt = mysqli_prepare($conn, "UPDATE products SET stock = stock - ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "ii", $fi['quantity'], $fi['product_id']);
                    mysqli_stmt_execute($stmt);
                }

                // Hapus dari keranjang jika mode cart
                if ($checkoutMode === 'cart') {
                    $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $uid);
                    mysqli_stmt_execute($stmt);
                }

                // Gunakan voucher
                if ($voucherCode !== '') {
                    use_voucher($conn, $voucherCode);
                }

                // Notifikasi admin
                $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT full_name FROM users WHERE id = $uid"));
                $user_name = $user ? $user['full_name'] : 'Pelanggan';
                create_notification($conn, 'admin', 'Checkout Baru!',
                    "$user_name baru saja melakukan checkout senilai " . rupiah($totalAmount) . " ($invoice_no).",
                    "transactions.php");

                mysqli_commit($conn);
                header("Location: invoice.php?id=$transaction_id");
                exit;
            } catch (Exception $e) {
                mysqli_rollback($conn);
                set_flash('error', $e->getMessage());
            }
        }
    }
}

$cityOptions = get_city_options();
$userAddress = $user['address'] ?? '';

require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
    <a href="<?= $checkoutMode === 'buy_now' ? 'product_detail.php?id=' . $buyNow : 'cart.php' ?>" class="back-btn"><span class="back-icon">&larr;</span> Kembali</a>

    <div class="page-header" style="padding-top:8px;">
        <h1>Checkout</h1>
        <p><?= $checkoutMode === 'buy_now' ? 'Pembelian langsung' : 'Pembayaran dari keranjang' ?></p>
    </div>

    <form method="POST" action="checkout.php<?= $checkoutMode === 'buy_now' ? '?buy_now='.$buyNow.'&qty='.$buyNowQty : '' ?>" id="checkoutForm">
    <div class="checkout-layout">

        <!-- KOLOM KIRI: Detail Pesanan -->
        <div class="checkout-left">

            <!-- Daftar Produk -->
            <div class="checkout-section">
                <h3 class="checkout-section-title">Detail Pesanan</h3>
                <div class="checkout-items">
                    <?php foreach ($items as $it): 
                        $dp = (int)($it['discount_percent'] ?? 0);
                        $ep = ($dp > 0 && $dp <= 100) ? $it['price'] * (100 - $dp) / 100 : $it['price'];
                    ?>
                    <div class="checkout-item">
                        <img src="<?= h($it['image'] ?: 'assets/img/placeholder.png') ?>" alt="">
                        <div class="checkout-item-info">
                            <span class="checkout-item-name"><?= h($it['name']) ?></span>
                            <span class="checkout-item-price">
                                <?php if ($dp > 0): ?>
                                    <span style="color:var(--red-soft);font-weight:700;"><?= rupiah($ep) ?></span>
                                    <span style="color:var(--text-muted);font-size:12px;text-decoration:line-through;margin-left:6px;"><?= rupiah($it['price']) ?></span>
                                <?php else: ?>
                                    <?= rupiah($it['price']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="checkout-item-qty">x<?= $it['quantity'] ?></div>
                        <div class="checkout-item-subtotal"><?= rupiah($ep * $it['quantity']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Alamat Pengiriman -->
            <div class="checkout-section">
                <h3 class="checkout-section-title">Alamat Pengiriman</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Kota / Kabupaten</label>
                        <select name="city" id="citySelect" required>
                            <option value="">-- Pilih Kota --</option>
                            <?php foreach ($cityOptions as $c): ?>
                                <option value="<?= h($c) ?>" <?= (isset($_POST['city']) && $_POST['city'] === $c) ? 'selected' : '' ?>><?= h($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Metode Pengiriman</label>
                        <select name="shipping_method" id="shippingMethod" required onchange="updateShipping()">
                            <option value="Reguler">Reguler (2-4 hari)</option>
                            <option value="Express">Express (1 hari)</option>
                            <option value="Ambil di Toko">Ambil di Toko (Gratis)</option>
                        </select>
                    </div>
                </div>
                <div id="addrWrap">
                    <div class="form-group">
                        <label>Alamat Lengkap</label>
                        <textarea name="shipping_address" id="addressField" rows="3" placeholder="Jl. Contoh No. 123, RT/RW, Kelurahan, Kecamatan"><?= h($_POST['shipping_address'] ?? $userAddress) ?></textarea>
                    </div>
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="save_address" id="saveAddress" value="1" style="width:auto;" checked>
                    <label for="saveAddress" style="margin:0;font-size:13px;cursor:pointer;">Simpan alamat untuk transaksi berikutnya</label>
                </div>

                <!-- Info Jarak & Ongkir -->
                <div class="shipping-info-box" id="shippingInfoBox" style="display:none;">
                    <div class="shipping-info-row">
                        <span>Jarak dari toko (Jakarta):</span>
                        <strong id="distanceDisplay">-</strong>
                    </div>
                    <div class="shipping-info-row">
                        <span>Estimasi ongkir:</span>
                        <strong id="shippingCostDisplay" class="text-gold">-</strong>
                    </div>
                </div>
            </div>

            <!-- Metode Pembayaran -->
            <div class="checkout-section">
                <h3 class="checkout-section-title">Metode Pembayaran</h3>
                <div class="payment-options">
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="Transfer Bank" required checked>
                        <span class="payment-option-card">
                            <span class="payment-icon">🏦</span>
                            <span class="payment-label">Transfer Bank</span>
                            <span class="payment-desc">BCA, BNI, BRI, Mandiri</span>
                        </span>
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="E-Wallet">
                        <span class="payment-option-card">
                            <span class="payment-icon">📱</span>
                            <span class="payment-label">E-Wallet</span>
                            <span class="payment-desc">GoPay, OVO, DANA, ShopeePay</span>
                        </span>
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="QRIS">
                        <span class="payment-option-card">
                            <span class="payment-icon">📲</span>
                            <span class="payment-label">QRIS</span>
                            <span class="payment-desc">Scan QR dari aplikasi apapun</span>
                        </span>
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="COD">
                        <span class="payment-option-card">
                            <span class="payment-icon">🤝</span>
                            <span class="payment-label">COD</span>
                            <span class="payment-desc">Bayar saat barang diterima</span>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN: Ringkasan -->
        <div class="checkout-right">
            <div class="checkout-summary">
                <h3>Ringkasan Pembayaran</h3>

                <div class="summary-detail-list">
                    <div class="summary-detail-row">
                        <span>Subtotal (<?= count($items) ?> produk)</span>
                        <span id="subtotalDisplay"><?= rupiah($subtotal) ?></span>
                    </div>
                    <div class="summary-detail-row" id="shippingRow">
                        <span>Ongkos Kirim</span>
                        <span id="checkoutShippingDisplay">Pilih kota & metode pengiriman</span>
                    </div>
                    <div class="summary-detail-row" id="voucherRow" style="display:none;">
                        <span id="voucherLabel">Voucher</span>
                        <span id="voucherDiscountDisplay" style="color:#7de3a4;">-</span>
                    </div>
                </div>

                <div class="summary-total-row">
                    <span>Total</span>
                    <span id="totalDisplay"><?= rupiah($subtotal) ?></span>
                </div>

                <!-- Voucher Input -->
                <div class="voucher-section">
                    <div class="voucher-input-wrap">
                        <input type="text" name="voucher_code" id="voucherInput" placeholder="Masukkan kode voucher" value="<?= h($_POST['voucher_code'] ?? '') ?>" autocomplete="off">
                        <button type="button" class="btn btn-outline btn-sm" onclick="applyVoucher()">Pakai</button>
                    </div>
                    <div id="voucherMessage" class="voucher-message"></div>
                    <div id="voucherApplied" class="voucher-applied" style="display:none;"></div>
                </div>

                <button type="submit" name="place_order" class="btn btn-gold btn-block" style="margin-top:18px;font-size:16px;padding:14px;">
                    Bayar Sekarang
                </button>

                <p class="checkout-note">Dengan menekan tombol di atas, kamu menyetujui syarat dan ketentuan berbelanja di Gundshop.id</p>
            </div>
        </div>

    </div>
    </form>
</div>

<script>
const cityDistances = <?= json_encode(get_city_distance_map()) ?>;

function updateShipping() {
    const city = document.getElementById('citySelect').value;
    const method = document.getElementById('shippingMethod').value;
    const infoBox = document.getElementById('shippingInfoBox');
    const addrWrap = document.getElementById('addrWrap');
    const distDisplay = document.getElementById('distanceDisplay');
    const shipCostDisplay = document.getElementById('shippingCostDisplay');
    const checkoutShipDisplay = document.getElementById('checkoutShippingDisplay');

    if (method === 'Ambil di Toko') {
        addrWrap.style.display = 'none';
        infoBox.style.display = 'flex';
        distDisplay.textContent = '0 km';
        shipCostDisplay.textContent = 'GRATIS';
        checkoutShipDisplay.textContent = 'Rp 0';
        checkoutShipDisplay.style.color = '#7de3a4';
        recalculate(0);
        return;
    }

    if (!city) {
        addrWrap.style.display = 'block';
        infoBox.style.display = 'none';
        checkoutShipDisplay.textContent = 'Pilih kota & metode pengiriman';
        checkoutShipDisplay.style.color = '';
        recalculate(0);
        return;
    }

    addrWrap.style.display = 'block';
    infoBox.style.display = 'flex';

    const distance = cityDistances[city] || cityDistances['Lainnya'];
    const baseCost = 8000;
    const perKm = 15;
    let cost = baseCost + (distance * perKm);
    if (method === 'Express') cost = cost * 1.8;
    cost = Math.ceil(cost);

    distDisplay.textContent = distance + ' km';
    shipCostDisplay.textContent = 'Rp ' + cost.toLocaleString('id-ID');
    checkoutShipDisplay.textContent = 'Rp ' + cost.toLocaleString('id-ID');
    checkoutShipDisplay.style.color = '';
    recalculate(cost);
}

let currentShipping = 0;
let currentVoucherDiscount = 0;
let isFreeShippingVoucher = false;

function recalculate(shippingCost) {
    currentShipping = shippingCost;
    const subtotalText = document.getElementById('subtotalDisplay').textContent.replace(/[^0-9]/g, '');
    const subtotal = parseInt(subtotalText) || 0;
    const voucherRow = document.getElementById('voucherRow');
    let total = subtotal + shippingCost;

    if (isFreeShippingVoucher) {
        // Ongkir sudah 0 dari updateShipping logic
        document.getElementById('checkoutShippingDisplay').textContent = 'Rp 0 (Gratis Ongkir)';
        document.getElementById('checkoutShippingDisplay').style.color = '#7de3a4';
    }

    if (currentVoucherDiscount > 0) {
        voucherRow.style.display = 'flex';
        document.getElementById('voucherDiscountDisplay').textContent = '- Rp ' + currentVoucherDiscount.toLocaleString('id-ID');
        total = subtotal + shippingCost - currentVoucherDiscount;
    }

    if (total < 0) total = 0;
    document.getElementById('totalDisplay').textContent = 'Rp ' + total.toLocaleString('id-ID');
}

function applyVoucher() {
    const code = document.getElementById('voucherInput').value.trim();
    const msgEl = document.getElementById('voucherMessage');
    const appliedEl = document.getElementById('voucherApplied');
    const voucherRow = document.getElementById('voucherRow');

    if (!code) {
        msgEl.textContent = 'Masukkan kode voucher.';
        msgEl.className = 'voucher-message voucher-error';
        appliedEl.style.display = 'none';
        voucherRow.style.display = 'none';
        currentVoucherDiscount = 0;
        isFreeShippingVoucher = false;
        recalculate(currentShipping);
        return;
    }

    msgEl.textContent = 'Memvalidasi...';
    msgEl.className = 'voucher-message';
    appliedEl.style.display = 'none';

    const subtotalText = document.getElementById('subtotalDisplay').textContent.replace(/[^0-9]/g, '');
    const subtotal = parseInt(subtotalText) || 0;

    // AJAX call untuk validasi voucher
    fetch('ajax_validate_voucher.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'code=' + encodeURIComponent(code) + '&subtotal=' + subtotal
    })
    .then(r => r.json())
    .then(data => {
        if (data.valid) {
            msgEl.textContent = '';
            appliedEl.style.display = 'block';
            appliedEl.innerHTML = '<span class="voucher-tick">\u2713</span> ' + data.message;
            appliedEl.className = 'voucher-applied voucher-success';

            if (data.type === 'free_shipping') {
                isFreeShippingVoucher = true;
                currentVoucherDiscount = 0;
                document.getElementById('checkoutShippingDisplay').textContent = 'Rp 0 (Gratis Ongkir)';
                document.getElementById('checkoutShippingDisplay').style.color = '#7de3a4';
                voucherRow.style.display = 'flex';
                document.getElementById('voucherLabel').textContent = 'Gratis Ongkir';
                document.getElementById('voucherDiscountDisplay').textContent = data.message;
            } else {
                isFreeShippingVoucher = false;
                currentVoucherDiscount = data.discount;
                voucherRow.style.display = 'flex';
                document.getElementById('voucherLabel').textContent = 'Diskon Voucher';
            }
            recalculate(currentShipping);
        } else {
            msgEl.textContent = data.message;
            msgEl.className = 'voucher-message voucher-error';
            appliedEl.style.display = 'none';
            voucherRow.style.display = 'none';
            currentVoucherDiscount = 0;
            isFreeShippingVoucher = false;
            recalculate(currentShipping);
        }
    })
    .catch(() => {
        msgEl.textContent = 'Gagal memvalidasi voucher. Coba lagi.';
        msgEl.className = 'voucher-message voucher-error';
    });
}

// Event listeners
document.getElementById('citySelect').addEventListener('change', updateShipping);
document.getElementById('shippingMethod').addEventListener('change', updateShipping);

// Inisialisasi ongkir jika kota sudah terpilih (saat reload)
if (document.getElementById('citySelect').value) {
    updateShipping();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
