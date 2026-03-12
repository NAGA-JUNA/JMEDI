<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header('Location: /admin/login.php');
    exit;
}

header('Location: /admin/dashboard.php');
exit;
