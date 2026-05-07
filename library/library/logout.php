<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isLoggedIn()) {
        logActivity($_SESSION['user_id'], 'user_logout', 'Logged out');
    }
    session_destroy();
    header('Location: login.php');
    exit();
}
header('Location: index.php');
exit();
?>
