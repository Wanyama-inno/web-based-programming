<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
$conn = getDBConnection();
requireAdmin();
checkOverdueBooks();
echo "All functions successful\n";
?>