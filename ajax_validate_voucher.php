<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

header('Content-Type: application/json');

$code = strtoupper(trim($_POST['code'] ?? ''));
$subtotal = (float) ($_POST['subtotal'] ?? 0);

if ($code === '') {
    echo json_encode(['valid' => false, 'message' => 'Masukkan kode voucher.']);
    exit;
}

$result = validate_voucher($conn, $code, $subtotal);

echo json_encode([
    'valid' => $result['valid'],
    'message' => $result['message'],
    'type' => $result['valid'] ? $result['voucher']['type'] : '',
    'discount' => $result['valid'] ? (int) $result['discount'] : 0,
]);
