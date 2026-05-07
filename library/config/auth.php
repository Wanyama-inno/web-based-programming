<?php
// Session/auth + small helpers used across /library pages.

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function sanitize($value): string {
    // Normalize scalars to string.
    if ($value === null) return '';
    if (is_bool($value)) return $value ? '1' : '0';
    if (is_scalar($value)) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        setFlash('error', 'Please sign in to continue.');
        header('Location: login.php');
        exit;
    }
}

function requireAdmin(): void {
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        setFlash('error', 'Admin access required.');
        header('Location: login.php');
        exit;
    }
}

function isAdmin(): bool {
    return !empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin';
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

// --- Fine / overdue helpers (used by multiple pages) ---

// Fallback fine-per-day constant to avoid undefined constant errors in views.
// Actual fine calculations should rely on DB fine_settings where possible.
if (!defined('FINE_PER_DAY')) {
    define('FINE_PER_DAY', 0.50);
}

function formatMoney($amount): string {
    $n = is_numeric($amount) ? (float)$amount : 0.0;
    return 'UGX ' . number_format($n, 2, '.', '');
}


function calculateFine(string $dueDate, ?string $returnDate = null): float {
    $returnDate = $returnDate ?? date('Y-m-d');
    $dueTs = strtotime($dueDate);
    $retTs = strtotime($returnDate);
    if ($dueTs === false || $retTs === false) return 0.0;

    // Overdue days: return date - due date
    $daysOver = (int)floor(($retTs - $dueTs) / 86400);
    if ($daysOver <= 0) return 0.0;

    // Use fallback constants; checkOverdueBooks will update fine_amount accurately.
    $perDay = (float)FINE_PER_DAY;
    $fine = $daysOver * $perDay;

    // Cap (fallback)
    $maxFine = 50.00;
    if ($fine > $maxFine) $fine = $maxFine;

    // Grace period (fallback)
    $grace = 7;
    $billableDays = max(0, $daysOver - $grace);
    $fine = $billableDays * $perDay;
    if ($fine > $maxFine) $fine = $maxFine;

    return (float)$fine;
}

function logActivity(int $userId, string $action, string $details): void {
    // Requires $conn in scope; most pages call after getDBConnection().
    // If $conn isn't available, silently skip to avoid fatals.
    if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof PDO)) {
        return;
    }

    $pdo = $GLOBALS['conn'];
    $sql = "INSERT INTO activity_log (user_id, action, details, ip_address, user_agent)
            VALUES (:user_id, :action, :details, :ip, :ua)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':action'  => $action,
        ':details' => $details,
        ':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
}

function getDBConnection(): PDO {
    // Prefer PDO $conn from config/database.php
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO) {
        return $GLOBALS['conn'];
    }
    throw new RuntimeException('DB connection not initialized. Ensure config/database.php is loaded before auth.php.');
}

function getCurrentUser(): array|false {
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    return [
        'id' => (int)($_SESSION['user_id'] ?? 0),
        'name' => (string)($_SESSION['user_name'] ?? ''),
        'role' => (string)($_SESSION['role'] ?? 'student'),
    ];
}


// Fine / overdue helpers

function checkOverdueBooks(): void {

    // Called at page load; updates borrow_records.status/fine_amount for Borrowed records past due.
    // Implementation uses DB values from fine_settings.
    $conn = getDBConnection();


    // Load fine settings (pick first row)
    $settings = db_fetch_one($conn, "SELECT daily_fine, max_fine, grace_period_days FROM fine_settings ORDER BY id DESC LIMIT 1");
    $dailyFine = $settings ? (float)$settings['daily_fine'] : FINE_PER_DAY;
    $maxFine   = $settings ? (float)$settings['max_fine'] : 50.00;
    $graceDays = $settings ? (int)$settings['grace_period_days'] : 7;

    // Compute and update overdue status for Borrowed items.
    // Using a conservative update: only where status='Borrowed' and due_date < CURDATE().
    $sql = "UPDATE borrow_records br
            SET 
              status = 'Overdue',
              fine_amount = LEAST(
                GREATEST(DATEDIFF(CURDATE(), br.due_date) - :grace, 0) * :daily,
                :maxf
              )
            WHERE br.status='Borrowed' AND br.due_date < CURDATE()";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':grace' => $graceDays,
        ':daily' => $dailyFine,
        ':maxf'  => $maxFine,
    ]);
}



