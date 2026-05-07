<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$type = $_GET['type'] ?? 'borrows';
$conn = getDBConnection();

// Helper: send CSV headers and stream rows
function csvRow(array $cols): string {
    return implode(',', array_map(fn($c) => '"' . str_replace('"', '""', $c ?? '') . '"', $cols)) . "\r\n";
}

switch ($type) {

    // ── All Borrow Records ────────────────────────────────────────────
    case 'borrows':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="borrow_records_' . date('Ymd_His') . '.csv"');
        $rows = $conn->query("
            SELECT br.id, u.name AS student, u.email, b.title, b.author, b.isbn,
                   br.borrow_date, br.due_date, br.return_date, br.status,
                   br.fine_amount, br.fine_paid
            FROM borrow_records br
            JOIN users u ON br.user_id=u.id
            JOIN books  b ON br.book_id=b.id
            ORDER BY br.borrow_date DESC
        ");
        echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
        echo csvRow(['ID','Student','Email','Book Title','Author','ISBN',
                     'Borrow Date','Due Date','Return Date','Status','Fine','Fine Paid']);
        while ($r = $rows->fetch()) {
            echo csvRow([$r['id'],$r['student'],$r['email'],$r['title'],$r['author'],$r['isbn'],
                         $r['borrow_date'],$r['due_date'],$r['return_date']??'',$r['status'],
                         number_format($r['fine_amount'],2),$r['fine_paid']?'Yes':'No']);
        }
        break;

    // ── Books Catalog ─────────────────────────────────────────────────
    case 'books':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="books_catalog_' . date('Ymd_His') . '.csv"');
        $rows = $conn->query("
            SELECT b.id, b.title, b.author, b.category, b.isbn,
                   b.quantity, b.available_copies,
                   (b.quantity - b.available_copies) AS checked_out,
                   COUNT(br.id) AS total_borrows
            FROM books b
            LEFT JOIN borrow_records br ON b.id=br.book_id
            GROUP BY b.id
            ORDER BY b.title
        ");
        echo "\xEF\xBB\xBF";
        echo csvRow(['ID','Title','Author','Category','ISBN','Total Copies',
                     'Available','Checked Out','Total Borrows']);
        while ($r = $rows->fetch()) {
            echo csvRow([$r['id'],$r['title'],$r['author'],$r['category']??'',$r['isbn']??'',
                         $r['quantity'],$r['available_copies'],$r['checked_out'],$r['total_borrows']]);
        }
        break;

    // ── Users ─────────────────────────────────────────────────────────
    case 'users':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="users_' . date('Ymd_His') . '.csv"');
        $rows = $conn->query("
            SELECT u.id, u.name, u.email, u.role, u.student_id, u.phone,
                   u.created_at,
                   COUNT(br.id)                              AS total_borrows,
                   SUM(br.status IN ('Borrowed','Overdue'))  AS active_loans,
                   COALESCE(SUM(br.fine_amount),0)           AS total_fines
            FROM users u
            LEFT JOIN borrow_records br ON u.id=br.user_id
            GROUP BY u.id
            ORDER BY u.name
        ");
        echo "\xEF\xBB\xBF";
        echo csvRow(['ID','Name','Email','Role','Student ID','Phone','Joined',
                     'Total Borrows','Active Loans','Total Fines']);
        while ($r = $rows->fetch()) {
            echo csvRow([$r['id'],$r['name'],$r['email'],$r['role'],$r['student_id']??'',
                         $r['phone']??'',date('Y-m-d',strtotime($r['created_at'])),
                         $r['total_borrows'],$r['active_loans'],
                         number_format($r['total_fines'],2)]);
        }
        break;

    // ── Overdue Books ─────────────────────────────────────────────────
    case 'overdue':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="overdue_books_' . date('Ymd_His') . '.csv"');
        $rows = $conn->query("
            SELECT u.name, u.email, u.phone, b.title, b.isbn,
                   br.borrow_date, br.due_date,
                   DATEDIFF(CURDATE(), br.due_date) AS days_overdue,
                   br.fine_amount
            FROM borrow_records br
            JOIN users u ON br.user_id=u.id
            JOIN books  b ON br.book_id=b.id
            WHERE br.status='Overdue'
            ORDER BY days_overdue DESC
        ");
        echo "\xEF\xBB\xBF";
        echo csvRow(['Student','Email','Phone','Book','ISBN',
                     'Borrow Date','Due Date','Days Overdue','Fine Owed']);
        while ($r = $rows->fetch()) {
            echo csvRow([$r['name'],$r['email'],$r['phone']??'',$r['title'],$r['isbn']??'',
                         $r['borrow_date'],$r['due_date'],$r['days_overdue'],
                         number_format($r['fine_amount'],2)]);
        }
        break;

    // ── Fines ─────────────────────────────────────────────────────────
    case 'fines':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="fines_' . date('Ymd_His') . '.csv"');
        $rows = $conn->query("
            SELECT u.name, u.email, b.title,
                   br.due_date, br.return_date, br.status,
                   br.fine_amount, br.fine_paid
            FROM borrow_records br
            JOIN users u ON br.user_id=u.id
            JOIN books  b ON br.book_id=b.id
            WHERE br.fine_amount > 0
            ORDER BY br.fine_paid ASC, br.fine_amount DESC
        ");
        echo "\xEF\xBB\xBF";
        echo csvRow(['Student','Email','Book','Due Date','Return Date','Loan Status','Fine','Paid']);
        while ($r = $rows->fetch()) {
            echo csvRow([$r['name'],$r['email'],$r['title'],$r['due_date'],
                         $r['return_date']??'',$r['status'],
                         number_format($r['fine_amount'],2),$r['fine_paid']?'Yes':'No']);
        }
        break;

    // ── Activity Log ──────────────────────────────────────────────────
    case 'activity':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="activity_log_' . date('Ymd_His') . '.csv"');
        $rows = $conn->query("
            SELECT al.id, al.created_at, u.name AS user_name, u.email,
                   al.action, al.details
            FROM activity_log al
            LEFT JOIN users u ON al.user_id=u.id
            ORDER BY al.created_at DESC
        ");
        echo "\xEF\xBB\xBF";
        echo csvRow(['ID','Timestamp','User','Email','Action','Detail']);
        while ($r = $rows->fetch()) {
            echo csvRow([$r['id'],$r['created_at'],$r['user_name']??'System',
                         $r['email']??'',$r['action'],$r['details']??'']);
        }
        break;

    default:
        http_response_code(400);
        echo 'Unknown export type.';
}

$conn->close();
exit();
?>
