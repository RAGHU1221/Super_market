<?php
require_once __DIR__ . '/includes/auth.php';
do_logout();
header('Location: ' . base_url('login.php'));
exit;
