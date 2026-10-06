<?php
$pageTitle = "Login";
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim($_POST['identity'] ?? ''); // username atau email
    $password = $_POST['password'] ?? '';

    if ($identity === '' || $password === '') {
        set_flash('error', 'Username/email dan password wajib diisi.');
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "ss", $identity, $identity);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if ($user && password_verify($password, $user['password'])) {
            $sessionData = [
                'user_id' => $user['id'],
                'full_name' => $user['full_name'],
                'username' => $user['username'],
            ];

            if ($user['role'] === 'admin') {
                $_SESSION['admin'] = $sessionData;
            } else {
                $_SESSION['customer'] = $sessionData;
            }

            set_flash('success', 'Selamat datang kembali, ' . $user['full_name'] . '!');

            if ($user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                // Redirect ke halaman sebelumnya jika ada (misal setelah klik Beli Sekarang)
                $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
                unset($_SESSION['redirect_after_login']);
                header("Location: $redirect");
            }
            exit;
        } else {
            set_flash('error', 'Username/email atau password salah.');
        }
    }
}

$hideBottomNav = true;
require_once __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
    <div class="auth-card">
        <h2>Masuk ke Akun</h2>
        <p class="subtitle">Login untuk melanjutkan belanja Gunpla favoritmu</p>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label>Username atau Email</label>
                <input type="text" name="identity" required value="<?= h($_POST['identity'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-gold btn-block">Login</button>
        </form>
        <div class="auth-footer">Belum punya akun? <a href="register.php">Daftar di sini</a></div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>