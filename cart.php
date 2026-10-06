<?php
$pageTitle = "Keranjang";
require_once __DIR__ . '/includes/functions.php';
require_login('login.php');

$uid = current_user_id();

// -------- HANDLE ACTIONS --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Update quantity
    if (isset($_POST['update_qty'])) {
        $cart_id = (int) $_POST['cart_id'];
        $qty = max(1, (int) $_POST['quantity']);

        $check = mysqli_prepare($conn, "SELECT c.id, p.stock FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND c.user_id = ?");
        mysqli_stmt_bind_param($check, "ii", $cart_id, $uid);
        mysqli_stmt_execute($check);
        $row = mysqli_stmt_get_result($check)->fetch_assoc();

        if ($row) {
            $qty = min($qty, $row['stock']);
            $stmt = mysqli_prepare($conn, "UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "iii", $qty, $cart_id, $uid);
            mysqli_stmt_execute($stmt);
        }
        header("Location: cart.php");
        exit;
    }

    // Remove item
    if (isset($_POST['remove'])) {
        $cart_id = (int) $_POST['cart_id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $cart_id, $uid);
        mysqli_stmt_execute($stmt);
        set_flash('info', 'Produk dihapus dari keranjang.');
        header("Location: cart.php");
        exit;
    }
}

// -------- FETCH CART FOR DISPLAY --------
$stmt = mysqli_prepare($conn, "SELECT c.id AS cart_id, c.quantity, p.id AS product_id, p.name, p.price, p.stock, p.image, p.discount_percent
    FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? ORDER BY c.added_at DESC");
mysqli_stmt_bind_param($stmt, "i", $uid);
mysqli_stmt_execute($stmt);
$cartItems = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

$grandTotal = 0;
foreach ($cartItems as $it) {
    $dp = (int)$it['discount_percent'];
    $effective_price = ($dp > 0 && $dp <= 100) ? $it['price'] * (100 - $dp) / 100 : $it['price'];
    $grandTotal += $effective_price * $it['quantity'];
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
    <div class="page-header">
        <h1>Keranjang Belanja</h1>
    </div>

    <?php if (empty($cartItems)): ?>
        <div class="section">
            <p>Keranjang kamu masih kosong. <a href="products.php" style="color:var(--gold); font-weight:700;">Yuk mulai
                    belanja!</a></p>
        </div>
    <?php else: ?>
        <div class="section cart-layout" style="padding-top:16px;">
            <div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Harga Satuan</th>
                                <th>Kuantitas</th>
                                <th>Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $it): ?>
                                <tr>
                                    <td>
                                        <div class="cart-product">
                                            <img src="<?= h($it['image'] ?: 'assets/img/placeholder.png') ?>" alt="">
                                            <span><?= h($it['name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php $dp = (int)$it['discount_percent']; ?>
                                        <?php if ($dp > 0): ?>
                                            <span style="color:var(--red-soft);font-weight:700;"><?= rupiah($it['price'] * (100 - $dp) / 100) ?></span>
                                            <br><span style="color:var(--text-muted);font-size:12px;text-decoration:line-through;"><?= rupiah($it['price']) ?></span>
                                        <?php else: ?>
                                            <?= rupiah($it['price']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="cart.php" class="cart-qty-form" onchange="submitQtyForm(this)">
                                            <input type="hidden" name="cart_id" value="<?= $it['cart_id'] ?>">
                                            <input type="number" name="quantity" value="<?= $it['quantity'] ?>" min="1"
                                                max="<?= $it['stock'] ?>">
                                            <input type="hidden" name="update_qty" value="1">
                                            <button type="submit" class="btn btn-outline btn-sm">Update</button>
                                        </form>
                                    </td>
                                    <td>
                                        <?php $dp = (int)$it['discount_percent']; $ep = ($dp > 0 && $dp <= 100) ? $it['price'] * (100 - $dp) / 100 : $it['price']; ?>
                                        <?= rupiah($ep * $it['quantity']) ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="cart.php"
                                            onsubmit="return confirmDelete('Hapus produk ini dari keranjang?')">
                                            <input type="hidden" name="cart_id" value="<?= $it['cart_id'] ?>">
                                            <button type="submit" name="remove" class="btn btn-red btn-sm">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="cart-summary">
                <h3>Ringkasan Belanja</h3>
                <div class="summary-row"><span>Total Item</span><span><?= count($cartItems) ?> produk</span></div>
                <div class="summary-row total"><span>Subtotal</span><span><?= rupiah($grandTotal) ?></span></div>
                <p style="color:var(--text-muted);font-size:12px;margin-top:8px;">* Ongkir & voucher akan dihitung di halaman checkout</p>

                <a href="checkout.php" class="btn btn-gold btn-block" style="margin-top:18px;">Checkout</a>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
