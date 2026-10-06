<?php
$pageTitle = "Data Pelanggan";
require_once __DIR__ . '/includes/admin_header.php';

// RESET PASSWORD PELANGGAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'];

    if (strlen($new_password) < 6) {
        set_flash('error', 'Password baru minimal 6 karakter.');
    } else {
        $hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare($conn, "UPDATE users SET password=? WHERE id=? AND role='customer'");
        mysqli_stmt_bind_param($stmt, "si", $hash, $id);
        mysqli_stmt_execute($stmt);
        set_flash('success', 'Password pelanggan berhasil direset.');
    }
    header("Location: customers.php"); exit;
}

$customers = mysqli_query($conn, "SELECT u.*,
    (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.id) AS total_orders,
    (SELECT COALESCE(SUM(total_amount),0) FROM transactions t WHERE t.user_id = u.id AND t.status != 'Dibatalkan') AS total_spent
    FROM users u WHERE u.role = 'customer' ORDER BY u.created_at DESC");
$customersArr = mysqli_fetch_all($customers, MYSQLI_ASSOC);
?>
<div class="card">
    <h3>👥 Data Pelanggan</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nama</th><th>Username</th><th>Email</th><th>No. HP</th><th>Total Order</th><th>Total Belanja</th><th>Bergabung</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if (empty($customersArr)): ?>
                <tr><td colspan="8">Belum ada pelanggan terdaftar.</td></tr>
            <?php endif; ?>
            <?php foreach ($customersArr as $c): ?>
                <tr>
                    <td><?= h($c['full_name']) ?></td>
                    <td>@<?= h($c['username']) ?></td>
                    <td><?= h($c['email']) ?></td>
                    <td><?= h($c['phone'] ?: '-') ?></td>
                    <td><?= $c['total_orders'] ?></td>
                    <td><?= rupiah($c['total_spent']) ?></td>
                    <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                    <td><button class="btn btn-outline btn-sm" onclick="openResetModal(<?= $c['id'] ?>, '<?= h(addslashes($c['full_name'])) ?>')">Reset Password</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL RESET PASSWORD -->
<?php foreach ($customersArr as $c): ?>
<div class="modal-overlay" id="resetModal<?= $c['id'] ?>">
    <div class="modal-box">
        <span class="modal-close" onclick="closeModal('resetModal<?= $c['id'] ?>')">&times;</span>
        <h3 style="margin-bottom:16px;">Reset Password: <?= h($c['full_name']) ?></h3>
        <form method="POST" action="customers.php">
            <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
            <div class="form-group">
                <label>Password Baru</label>
                <input type="password" name="new_password" minlength="6" required>
            </div>
            <button type="submit" name="reset_password" class="btn btn-gold btn-block">Reset Password</button>
        </form>
    </div>
</div>
<?php endforeach; ?>

<script>
function openResetModal(id) { document.getElementById('resetModal' + id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
window.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('active');
});
</script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
