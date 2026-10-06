<?php
$pageTitle = 'Kelola Voucher';
require_once __DIR__ . '/includes/admin_header.php';

// -------- HANDLE CRUD --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Tambah / Edit Voucher
    if (isset($_POST['save_voucher'])) {
        $id = (int) ($_POST['voucher_id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $type = $_POST['type'] ?? 'discount';
        $value = (float) ($_POST['value'] ?? 0);
        $min_order = (float) ($_POST['min_order'] ?? 0);
        $max_discount = !empty($_POST['max_discount']) ? (float) $_POST['max_discount'] : null;
        $usage_limit = !empty($_POST['usage_limit']) ? (int) $_POST['usage_limit'] : null;
        $valid_from = $_POST['valid_from'] ?? date('Y-m-d') . ' 00:00:00';
        $valid_until = $_POST['valid_until'] ?? date('Y-m-d', strtotime('+1 year')) . ' 23:59:59';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($code === '') {
            set_flash('error', 'Kode voucher wajib diisi.');
        } else {
            if ($id > 0) {
                $stmt = mysqli_prepare($conn, "UPDATE vouchers SET code=?, type=?, value=?, min_order=?, max_discount=?, usage_limit=?, valid_from=?, valid_until=?, is_active=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, "sdddissii", $code, $type, $value, $min_order, $max_discount, $usage_limit, $valid_from, $valid_until, $is_active, $id);
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO vouchers (code, type, value, min_order, max_discount, usage_limit, valid_from, valid_until, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "sdddissii", $code, $type, $value, $min_order, $max_discount, $usage_limit, $valid_from, $valid_until, $is_active);
            }
            mysqli_stmt_execute($stmt);
            set_flash('success', $id > 0 ? 'Voucher berhasil diperbarui.' : 'Voucher berhasil ditambahkan.');
            header('Location: vouchers.php');
            exit;
        }
    }

    // Toggle active
    if (isset($_POST['toggle_voucher'])) {
        $id = (int) $_POST['voucher_id'];
        mysqli_query($conn, "UPDATE vouchers SET is_active = IF(is_active=1,0,1) WHERE id=$id");
        header('Location: vouchers.php');
        exit;
    }

    // Hapus
    if (isset($_POST['delete_voucher'])) {
        $id = (int) $_POST['voucher_id'];
        mysqli_query($conn, "DELETE FROM vouchers WHERE id=$id");
        set_flash('info', 'Voucher berhasil dihapus.');
        header('Location: vouchers.php');
        exit;
    }
}

// -------- FETCH VOUCHERS --------
$vouchers = mysqli_query($conn, "SELECT * FROM vouchers ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// -------- EDIT MODE --------
$editVoucher = null;
if (isset($_GET['edit'])) {
    $eid = (int) $_GET['edit'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM vouchers WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $eid);
    mysqli_stmt_execute($stmt);
    $editVoucher = mysqli_stmt_get_result($stmt)->fetch_assoc();
}
?>

<div class="admin-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
        <h2 style="margin:0;">Voucher (<?= count($vouchers) ?>)</h2>
        <button class="btn btn-gold" onclick="toggleForm()" id="toggleFormBtn">+ Tambah Voucher</button>
    </div>

    <!-- FORM TAMBAH/EDIT -->
    <div id="voucherFormWrap" class="admin-card" style="display:<?= $editVoucher ? 'block' : 'none' ?>; margin-bottom:24px;">
        <h3 style="margin-bottom:16px;" id="formTitle"><?= $editVoucher ? 'Edit Voucher' : 'Tambah Voucher Baru' ?></h3>
        <form method="POST" action="vouchers.php">
            <input type="hidden" name="voucher_id" value="<?= $editVoucher['id'] ?? 0 ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Kode Voucher</label>
                    <input type="text" name="code" value="<?= h($editVoucher['code'] ?? '') ?>" placeholder="DISKON10" required style="text-transform:uppercase;">
                </div>
                <div class="form-group">
                    <label>Tipe Voucher</label>
                    <select name="type" required onchange="document.getElementById('valueLabel').textContent = this.value === 'discount' ? 'Nilai Diskon (%)' : 'Nilai (isi 0)'; document.getElementById('maxDiscountWrap').style.display = this.value === 'discount' ? 'block' : 'none';">
                        <option value="discount" <?= ($editVoucher['type'] ?? '') === 'discount' ? 'selected' : '' ?>>Diskon</option>
                        <option value="free_shipping" <?= ($editVoucher['type'] ?? '') === 'free_shipping' ? 'selected' : '' ?>>Gratis Ongkir</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label id="valueLabel">Nilai Diskon (%)</label>
                    <input type="number" name="value" value="<?= $editVoucher['value'] ?? '10' ?>" min="0" step="any" required>
                </div>
                <div class="form-group" id="maxDiscountWrap" style="<?= ($editVoucher['type'] ?? 'discount') === 'free_shipping' ? 'display:none' : '' ?>">
                    <label>Maks. Potongan (Rp, kosongkan = tanpa batas)</label>
                    <input type="number" name="max_discount" value="<?= $editVoucher['max_discount'] ?? '' ?>" min="0" step="any">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Min. Total Order (Rp)</label>
                    <input type="number" name="min_order" value="<?= $editVoucher['min_order'] ?? '0' ?>" min="0" step="any">
                </div>
                <div class="form-group">
                    <label>Batas Pemakaian (kosongkan = unlimited)</label>
                    <input type="number" name="usage_limit" value="<?= $editVoucher['usage_limit'] ?? '' ?>" min="1">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Berlaku Mulai</label>
                    <input type="datetime-local" name="valid_from" value="<?= str_replace(' ', 'T', $editVoucher['valid_from'] ?? date('Y-m-d') . ' 00:00:00') ?>" required>
                </div>
                <div class="form-group">
                    <label>Berlaku Sampai</label>
                    <input type="datetime-local" name="valid_until" value="<?= str_replace(' ', 'T', $editVoucher['valid_until'] ?? date('Y-m-d', strtotime('+1 year')) . ' 23:59:59') ?>" required>
                </div>
            </div>
            <div class="form-group" style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="is_active" id="isActiveVoucher" value="1" <?= ($editVoucher['is_active'] ?? 1) ? 'checked' : '' ?> style="width:auto;">
                <label for="isActiveVoucher" style="margin:0;font-size:13px;cursor:pointer;">Aktif</label>
            </div>
            <div style="display:flex;gap:10px;margin-top:16px;">
                <button type="submit" name="save_voucher" class="btn btn-gold">Simpan</button>
                <button type="button" class="btn btn-outline" onclick="toggleForm()">Batal</button>
            </div>
        </form>
    </div>

    <!-- DAFTAR VOUCHER -->
    <div class="admin-card">
        <table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Tipe</th>
                    <th>Nilai</th>
                    <th>Min. Order</th>
                    <th>Dipakai</th>
                    <th>Status</th>
                    <th>Masa Berlaku</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vouchers)): ?>
                    <tr><td colspan="8" style="text-align:center;color:var(--text-muted);">Belum ada voucher.</td></tr>
                <?php else: ?>
                    <?php foreach ($vouchers as $v): ?>
                    <tr>
                        <td><strong style="color:var(--gold);font-family:monospace;font-size:14px;">"><?= h($v['code']) ?></strong></td>
                        <td>
                            <span class="badge <?= $v['type'] === 'discount' ? 'badge-waiting' : 'badge-done' ?>">
                                <?= $v['type'] === 'discount' ? 'Diskon' : 'Gratis Ongkir' ?>
                            </span>
                        </td>
                        <td>
                            <?= $v['type'] === 'discount' ? (int)$v['value'] . '%' : '-' ?>
                            <?php if ($v['type'] === 'discount' && $v['max_discount']): ?>
                                <br><small style="color:var(--text-muted);">maks. <?= rupiah($v['max_discount']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= rupiah($v['min_order']) ?></td>
                        <td><?= $v['used_count'] ?><?= $v['usage_limit'] ? '/' . $v['usage_limit'] : '' ?></td>
                        <td>
                            <?php $now = date('Y-m-d H:i:s'); ?>
                            <?php if (!$v['is_active']): ?>
                                <span class="badge badge-cancel">Nonaktif</span>
                            <?php elseif ($now > $v['valid_until']): ?>
                                <span class="badge badge-cancel">Expired</span>
                            <?php elseif ($v['usage_limit'] && $v['used_count'] >= $v['usage_limit']): ?>
                                <span class="badge badge-cancel">Habis</span>
                            <?php else: ?>
                                <span class="badge badge-done">Aktif</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;">
                            <?= date('d M Y', strtotime($v['valid_from'])) ?> -<br>
                            <?= date('d M Y', strtotime($v['valid_until'])) ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <a href="vouchers.php?edit=<?= $v['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                            <form method="POST" style="display:inline-flex;gap:6px;">
                                <input type="hidden" name="voucher_id" value="<?= $v['id'] ?>">
                                <button type="submit" name="toggle_voucher" class="btn btn-outline btn-sm" title="Toggle aktif/nonaktif">
                                    <?= $v['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                </button>
                                <button type="submit" name="delete_voucher" class="btn btn-red btn-sm" onclick="return confirm('Hapus voucher <?= h(addslashes($v['code'])) ?>?')">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleForm() {
    const wrap = document.getElementById('voucherFormWrap');
    const btn = document.getElementById('toggleFormBtn');
    if (wrap.style.display === 'none') {
        wrap.style.display = 'block';
        btn.textContent = 'Tutup Form';
    } else {
        wrap.style.display = 'none';
        btn.textContent = '+ Tambah Voucher';
    }
}
<?php if ($editVoucher): ?>
// Auto-open form if editing
toggleForm();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
