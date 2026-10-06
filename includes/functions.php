<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

// -------------------------------------------
// AUTH HELPERS
// -------------------------------------------
// Data login pelanggan disimpan di $_SESSION['customer'] dan admin di
// $_SESSION['admin'] secara terpisah. Ini memungkinkan satu browser login
// sebagai pelanggan di satu tab DAN sebagai admin di tab lain pada waktu
// yang sama, tanpa saling menimpa (karena keduanya tetap berbagi session
// PHP yang sama / satu cookie, hanya namespace datanya yang dipisah).

function is_logged_in()
{
    return isset($_SESSION['customer']['user_id']);
}

function is_admin()
{
    return isset($_SESSION['admin']['user_id']);
}

function current_user_id()
{
    return $_SESSION['customer']['user_id'] ?? null;
}

function current_admin_id()
{
    return $_SESSION['admin']['user_id'] ?? null;
}

// Paksa harus login (pelanggan), redirect ke login jika belum
function require_login($redirect = 'login.php')
{
    if (!is_logged_in()) {
        set_flash('error', 'Silakan login terlebih dahulu.');
        header("Location: $redirect");
        exit;
    }
}

// Paksa harus admin
function require_admin($redirect = '../login.php')
{
    if (!is_admin()) {
        set_flash('error', 'Akses ditolak. Silakan login sebagai admin.');
        header("Location: $redirect");
        exit;
    }
}

// -------------------------------------------
// FLASH MESSAGES
// -------------------------------------------
function set_flash($type, $message)
{
    $_SESSION['flash'][$type] = $message;
}

function get_flash($type)
{
    if (!empty($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

function show_flashes()
{
    $types = ['success' => 'flash-success', 'error' => 'flash-error', 'info' => 'flash-info'];
    foreach ($types as $type => $class) {
        $msg = get_flash($type);
        if ($msg) {
            echo '<div class="flash-message ' . $class . '">' . htmlspecialchars($msg) . '</div>';
        }
    }
}

// -------------------------------------------
// FORMATTING
// -------------------------------------------
function rupiah($angka)
{
    return "Rp " . number_format((float) $angka, 0, ',', '.');
}

function h($str)
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function generate_invoice_no()
{
    return "INV-" . date("Ymd") . "-" . strtoupper(substr(uniqid(), -6));
}

// Daftar grade kit yang tetap (dropdown), terpisah dari kategori (jenis produk: Model Kit/Figure/Tools)
function grade_options()
{
    return ['EG', 'HG', 'RG', 'MG', 'PG', 'SD', 'Non-Grade'];
}

// Daftar kategori yang tetap (dropdown), berupa jenis produk
function category_options()
{
    return ['Model Kit', 'Figure', 'Tools & Accessories'];
}

// -------------------------------------------
// SHIPPING COST (berdasarkan jarak kota)
// -------------------------------------------
// Lokasi toko: Jakarta Pusat (-6.1753, 106.8272)
// Daftar kota besar Indonesia + jarak estimasi dari Jakarta (km)
function get_city_distance_map()
{
    return [
        'Jakarta' => 5,
        'Tangerang' => 25,
        'Tangerang Selatan' => 30,
        'Bekasi' => 35,
        'Depok' => 30,
        'Bogor' => 60,
        'Karawang' => 70,
        'Cikarang' => 50,
        'Bandung' => 150,
        'Cirebon' => 250,
        'Semarang' => 450,
        'Surabaya' => 780,
        'Yogyakarta' => 520,
        'Solo' => 570,
        'Malang' => 810,
        'Medan' => 1400,
        'Palembang' => 600,
        'Lampung' => 350,
        'Makassar' => 1500,
        'Denpasar' => 1200,
        'Balikpapan' => 1300,
        'Manado' => 2200,
        'Pontianak' => 900,
        'Banjarmasin' => 1100,
        'Padang' => 950,
        'Pekanbaru' => 1100,
        'Batam' => 900,
        'Lainnya' => 1500,
    ];
}

function calculate_shipping_cost($city, $method)
{
    if ($method === 'Ambil di Toko') {
        return 0;
    }
    $map = get_city_distance_map();
    $distance = $map[$city] ?? $map['Lainnya'];
    
    // Base rate per km
    $perKm = 15;
    $baseCost = 8000;
    $cost = $baseCost + ($distance * $perKm);
    
    // Express = 1.8x
    if ($method === 'Express') {
        $cost = $cost * 1.8;
    }
    
    return (int) ceil($cost);
}

function get_city_options()
{
    $map = get_city_distance_map();
    return array_keys($map);
}

// -------------------------------------------
// VOUCHER HELPERS
// -------------------------------------------
function validate_voucher($conn, $code, $subtotal)
{
    $code = strtoupper(trim($code));
    $stmt = mysqli_prepare($conn, "SELECT * FROM vouchers WHERE code = ? AND is_active = 1");
    mysqli_stmt_bind_param($stmt, "s", $code);
    mysqli_stmt_execute($stmt);
    $v = mysqli_stmt_get_result($stmt)->fetch_assoc();
    
    if (!$v) {
        return ['valid' => false, 'message' => 'Kode voucher tidak ditemukan.'];
    }
    
    $now = date('Y-m-d H:i:s');
    if ($now < $v['valid_from'] || $now > $v['valid_until']) {
        return ['valid' => false, 'message' => 'Voucher sudah kadaluarsa atau belum berlaku.'];
    }
    
    if ($v['usage_limit'] !== null && $v['used_count'] >= $v['usage_limit']) {
        return ['valid' => false, 'message' => 'Kuota voucher sudah habis.'];
    }
    
    if ($subtotal < (float) $v['min_order']) {
        return ['valid' => false, 'message' => 'Minimal order ' . rupiah($v['min_order']) . ' untuk menggunakan voucher ini.'];
    }
    
    $discount = 0;
    if ($v['type'] === 'discount') {
        $percent = (float) $v['value'];
        $discount = $subtotal * $percent / 100;
        if ($v['max_discount'] !== null && $discount > (float) $v['max_discount']) {
            $discount = (float) $v['max_discount'];
        }
    }
    
    return [
        'valid' => true,
        'voucher' => $v,
        'discount' => $discount,
        'message' => $v['type'] === 'discount'
            ? 'Diskon ' . (int)$v['value'] . '% (maks. ' . rupiah($discount) . ')'
            : 'Gratis ongkir!',
    ];
}

function use_voucher($conn, $code)
{
    $code = strtoupper(trim($code));
    $stmt = mysqli_prepare($conn, "UPDATE vouchers SET used_count = used_count + 1 WHERE code = ?");
    mysqli_stmt_bind_param($stmt, "s", $code);
    mysqli_stmt_execute($stmt);
}

// -------------------------------------------
// CART HELPERS
// -------------------------------------------
function cart_count($conn, $user_id)
{
    $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(quantity),0) AS total FROM cart WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return (int) $row['total'];
}

// -------------------------------------------
// DISCOUNT / PRICE HELPERS
// -------------------------------------------
function get_discounted_price($p)
{
    $dp = (int)($p['discount_percent'] ?? 0);
    if ($dp > 0 && $dp <= 100) {
        return $p['price'] * (100 - $dp) / 100;
    }
    return null; // no discount
}

function render_price($p, $class = 'product-price')
{
    $discounted = get_discounted_price($p);
    $dp = (int)($p['discount_percent'] ?? 0);
    if ($discounted !== null) {
        return '<span class="' . $class . '">
            <span class="price-discounted">' . rupiah($discounted) . '</span>
            <span class="price-original">' . rupiah($p['price']) . '</span>
        </span>';
    }
    return '<span class="' . $class . '">' . rupiah($p['price']) . '</span>';
}

// -------------------------------------------
// CSRF (simple token)
// -------------------------------------------
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// -------------------------------------------
// NOTIFICATIONS
// -------------------------------------------
function create_notification($conn, $target_type, $title, $message, $link = null)
{
    $stmt = mysqli_prepare($conn, "INSERT INTO notifications (target_type, title, message, link) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssss", $target_type, $title, $message, $link);
    mysqli_stmt_execute($stmt);
}

function get_notifications($conn, $target_type, $limit = 10)
{
    $stmt = mysqli_prepare($conn, "SELECT * FROM notifications WHERE target_type = ? ORDER BY created_at DESC LIMIT ?");
    mysqli_stmt_bind_param($stmt, "si", $target_type, $limit);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
}

function unread_notification_count($conn, $target_type)
{
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) c FROM notifications WHERE target_type = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, "s", $target_type);
    mysqli_stmt_execute($stmt);
    return (int) mysqli_stmt_get_result($stmt)->fetch_assoc()['c'];
}

function mark_notifications_read($conn, $target_type)
{
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE target_type = '$target_type' AND is_read = 0");
}

function csrf_check($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
