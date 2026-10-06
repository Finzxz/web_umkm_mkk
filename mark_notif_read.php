<?php
require_once __DIR__ . '/includes/functions.php';
if (is_logged_in()) {
    mark_notifications_read($conn, 'all_users');
}
http_response_code(204);
