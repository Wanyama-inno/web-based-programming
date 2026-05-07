<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireLogin();
checkOverdueBooks();

$conn = getDBConnection();
$user = getCurrentUser();

// Handle return POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recId = intval($_POST['record_id'] ?? 0);

    // Verify record belongs to user (or admin)
    if (isAdmin()) {
        $stmt = $conn->prepare("SELECT br.*, b.id as bid FROM borrow_records br JOIN books b ON br.book_id=b.id WHERE br.id=? AND br.status IN ('Borrowed','Overdue')");
        $stmt->bind_param("i", $recId);
    } else {
        $uid  = $user['id'];
        $stmt = $conn->prepare("SELECT br.*, b.id as bid FROM borrow_records br JOIN books b ON br.book_id=b.id WHERE br.id=? AND br.user_id=? AND br.status IN ('Borrowed','Overdue')");
        $stmt->bind_param("ii", $recId, $uid);
    }
    $stmt->execute();
    $record = $stmt->fetch();

    if (!$record) {
        setFlash('error', 'Return record not found or already returned.');
    } else {
        $conn->begin_transaction();
        try {
            $returnDate  = date('Y-m-d');
            $fineAmount  = calculateFine($record['due_date'], $returnDate);

            // Student return requires admin confirmation.
            // For students, mark record as PendingReturnApproval.
            // For admins (processing directly), mark as Returned.
            $newStatus = isAdmin() ? 'Returned' : 'PendingReturnApproval';

            // Update borrow record (PDO + helper style)
            db_execute(
                $conn,
                "UPDATE borrow_records SET status=?, return_date=?, fine_amount=? WHERE id=?",
                [$newStatus, $returnDate, $fineAmount, $recId]
            );


            // Only make the book available when admin confirms (or admin is processing directly).
            if (isAdmin()) {
                $stmt = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id=?");
                $stmt->bind_param("i", $record['book_id']);
                $stmt->execute();
            }


            // Fulfil oldest pending reservation for this book if any
            $stmt = $conn->prepare("SELECT id FROM reservations WHERE book_id=? AND status='Pending' ORDER BY reserved_at ASC LIMIT 1");
            $stmt->bind_param("i", $record['book_id']);
            $stmt->execute();
            $res = $stmt->fetch();
            $stmt->close();
            if ($res) {
                $stmt = $conn->prepare("UPDATE reservations SET status='Fulfilled' WHERE id=?");
                $stmt->bind_param("i", $res['id']);
                $stmt->execute();
            }

            $conn->commit();
            $msg = 'Book returned successfully!';
            if ($fineAmount > 0) $msg .= ' Fine incurred: ' . formatMoney($fineAmount);
            logActivity($user['id'], 'book_returned',
                "Returned record #{$recId}" . ($fineAmount > 0 ? " — fine: " . formatMoney($fineAmount) : ''));
            setFlash('success', $msg);
            header('Location: dashboard.php'); exit();
        } catch (Exception $e) {
            $conn->rollback();
            setFlash('error', 'Failed to process return. Please try again.');
        }
    }
}

// Pre-select record
$preRecord = null;
$preId = intval($_GET['record_id'] ?? 0);
if ($preId > 0) {
    $stmt = $conn->prepare("SELECT br.*, b.title, b.author FROM borrow_records br JOIN books b ON br.book_id=b.id WHERE br.id=?");
    $stmt->bind_param("i", $preId);
    $stmt->execute();
    $preRecord = $stmt->fetch();
    $stmt->close();
}

// Active loans for current user (or all if admin)
if (isAdmin()) {
    $activeLoans = $conn->query("
        SELECT br.*, u.name as user_name, b.title, b.author,
               DATEDIFF(CURDATE(), br.due_date) as days_overdue
        FROM borrow_records br
        JOIN users u ON br.user_id=u.id
        JOIN books b ON br.book_id=b.id
        WHERE br.status IN ('Borrowed','Overdue')
        ORDER BY br.status DESC, br.due_date ASC
    ")->fetchAll();
} else {
    $uid = $user['id'];
    $activeLoans = $conn->query("
        SELECT br.*, u.name as user_name, b.title, b.author,
               DATEDIFF(br.due_date, CURDATE()) as days_overdue
        FROM borrow_records br
        JOIN users u ON br.user_id=u.id
        JOIN books b ON br.book_id=b.id
        WHERE br.user_id=$uid AND br.status IN ('Borrowed','Overdue')
        ORDER BY br.due_date ASC
    ")->fetchAll();
}

$pageTitle = 'Return a Book';
require_once 'includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1><?= isAdmin() ? 'Process Returns' : 'Return a Book' ?></h1>
        <p><?= isAdmin() ? 'Manage all active loans and process returns' : 'Return your borrowed books below' ?></p>
    </div>
</div>

<?php if(count($activeLoans) === 0): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-icon">✅</div>
        <p>No active loans to return. <a href="books.php" style="color:var(--accent)">Browse books to borrow one!</a></p>
    </div>
</div>
<?php else: ?>

<!-- Quick return for direct link -->
<?php if($preRecord && in_array($preRecord['status'], ['Borrowed','Overdue'])): ?>
<div class="card" style="margin-bottom:24px;border:2px solid var(--accent)">
    <div class="card-header" style="background:var(--accent-light)">
        <h3 class="card-title" style="color:var(--accent)">⬆️ Quick Return</h3>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap">
            <div style="flex:1">
                <div style="font-size:1.1rem;font-weight:600"><?= sanitize($preRecord['title']) ?></div>
                <div style="color:var(--ink-muted)">by <?= sanitize($preRecord['author']) ?></div>
                <div style="margin-top:8px;font-size:.875rem">
                    Borrowed: <?= date('M d, Y', strtotime($preRecord['borrow_date'])) ?> •
                    Due: <?= date('M d, Y', strtotime($preRecord['due_date'])) ?>
                    <?php if($preRecord['status']==='Overdue'): ?>
                        <span style="color:var(--danger);font-weight:600"> — OVERDUE</span>
                    <?php endif; ?>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="record_id" value="<?= $preRecord['id'] ?>">
                <button type="submit" class="btn btn-success">✅ Confirm Return</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">📋 Active Loans<?= isAdmin() ? ' (All Students)' : '' ?></h3>
        <span style="font-size:.85rem;color:var(--ink-muted)"><?= count($activeLoans) ?> active loan<?= count($activeLoans)!==1?'s':'' ?></span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <?php if(isAdmin()): ?><th>Student</th><?php endif; ?>
                    <th>Book</th>
                    <th>Borrowed</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Overdue</th>
                    <th>Fine</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php while($loan = $activeLoans->fetch_assoc()): ?>
            <tr style="<?= $loan['status']==='Overdue'?'background:#FFF5F5':'' ?>">
                <?php if(isAdmin()): ?>
                    <td><strong><?= sanitize($loan['user_name']) ?></strong></td>
                <?php endif; ?>
                <td>
                    <strong><?= sanitize($loan['title']) ?></strong><br>
                    <small style="color:var(--ink-muted)"><?= sanitize($loan['author']) ?></small>
                </td>
                <td><?= date('M d, Y', strtotime($loan['borrow_date'])) ?></td>
                <td><?= date('M d, Y', strtotime($loan['due_date'])) ?></td>
                <td>
                    <span class="badge <?= $loan['status']==='Overdue'?'badge-danger':'badge-info' ?>">
                        <?= $loan['status'] ?>
                    </span>
                </td>
                <td>
                    <?php if($loan['days_overdue'] > 0): ?>
                        <span style="color:var(--danger);font-weight:600;font-family:var(--font-mono);font-size:.85rem">
                            +<?= $loan['days_overdue'] ?> days
                        </span>
                    <?php else: ?>
                        <span style="color:var(--success);font-size:.85rem;font-family:var(--font-mono)">On time</span>
                    <?php endif; ?>
                </td>
                <td style="font-family:var(--font-mono);font-size:.85rem;color:<?= $loan['days_overdue']>0?'var(--danger)':'var(--ink-muted)' ?>">
                    <?= $loan['days_overdue'] > 0 ? formatMoney(calculateFine($loan['due_date'])) : '—' ?>
                </td>
                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="record_id" value="<?= $loan['id'] ?>">
                        <button type="submit" class="btn btn-success btn-sm"
                            data-confirm="Return '<?= addslashes($loan['title']) ?>'?">
                            ⬆️ Return
                        </button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
