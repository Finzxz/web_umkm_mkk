<?php
$pageTitle = "Daftar Akun";
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if ($full_name === '' || $username === '' || $email === '' || $password === '') {
        set_flash('error', 'Semua field wajib diisi kecuali no HP.');
    } elseif ($password !== $confirm) {
        set_flash('error', 'Konfirmasi password tidak cocok.');
    } elseif (strlen($password) < 6) {
        set_flash('error', 'Password minimal 6 karakter.');
    } else {
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ?");
        mysqli_stmt_bind_param($check, "ss", $username, $email);
        mysqli_stmt_execute($check);
        $res = mysqli_stmt_get_result($check);

        if (mysqli_num_rows($res) > 0) {
            set_flash('error', 'Username atau email sudah terdaftar.');
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, username, email, phone, password, role) VALUES (?, ?, ?, ?, ?, 'customer')");
            mysqli_stmt_bind_param($stmt, "sssss", $full_name, $username, $email, $phone, $hash);
            if (mysqli_stmt_execute($stmt)) {
                set_flash('success', 'Registrasi berhasil! Silakan login.');
                header("Location: login.php");
                exit;
            } else {
                set_flash('error', 'Terjadi kesalahan, coba lagi.');
            }
        }
    }
}

$hideBottomNav = true;
require_once __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
    <div class="auth-card">
        <h2>Buat Akun Baru</h2>
        <p class="subtitle">Gabung dengan komunitas Gunpla Indonesia</p>

        <form method="POST" action="register.php">
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="full_name" required value="<?= h($_POST['full_name'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required value="<?= h($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>No. HP</label>
                    <input type="text" name="phone" value="<?= h($_POST['phone'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Konfirmasi Password</label>
                    <input type="password" name="confirm_password" required minlength="6">
                </div>
            </div>
            <button type="submit" class="btn btn-gold btn-block">Daftar</button>
        </form>
        <div class="auth-footer">Sudah punya akun? <a href="login.php">Login di sini</a></div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
