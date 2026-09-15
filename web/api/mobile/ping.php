<?php
/** GET -> simple connectivity/health check, no auth required */
require_once __DIR__ . '/_bootstrap.php';
api_json(['ok' => true, 'app' => 'Supermarket Suite API', 'server_time' => date('Y-m-d H:i:s')]);
