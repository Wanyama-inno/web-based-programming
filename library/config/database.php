<?php
// Project DB bootstrap (used by pages in /library)
// Uses PDO and provides $conn plus lightweight query helpers.

declare(strict_types=1);

// If you prefer to configure via environment variables, edit these here.
// Update DB credentials to match your local MySQL setup.
$dbHost = 'localhost';
$dbName = 'library';
$dbUser = 'root';
$dbPass = '';

// Recommended charset for MySQL
$dbCharset = 'utf8mb4';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";

// PDO options for reliability
$pdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
} catch (PDOException $e) {
    // Avoid leaking credentials; show generic error.
    http_response_code(500);
    die('Database connection failed.');
}

// --- Helpers ---
function db_fetch_all(PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_fetch_one(PDO $pdo, string $sql, array $params = []): ?array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_execute(PDO $pdo, string $sql, array $params = []): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

