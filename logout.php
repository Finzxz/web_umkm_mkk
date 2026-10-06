<?php
require_once __DIR__ . '/includes/functions.php';
unset($_SESSION['customer']);
set_flash('success', 'Anda telah berhasil logout.');
header("Location: login.php");
exit;
