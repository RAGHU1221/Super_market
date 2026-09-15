<?php
require_once __DIR__ . '/includes/auth.php';
header('Location: ' . base_url(is_logged_in() ? 'dashboard.php' : 'login.php'));
exit;
