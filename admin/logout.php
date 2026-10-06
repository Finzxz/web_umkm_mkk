<?php
require_once __DIR__ . '/../includes/functions.php';
unset($_SESSION['admin']);
set_flash('success', 'Anda telah berhasil logout dari Admin Panel.');
header("Location: ../login.php");
exit;
