<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireLogin();
checkOverdueBooks();

$conn   = getDBConnection();
$user   = getCurrentUser();
$bookId = intval($_GET['book_id'] ?? 0);

// Handle borrow POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bid = intval($_POST['book_id'] ?? 0);

    // Check book availability
    $book = db_fetch_one($conn, "SELECT * FROM books WHERE id=? AND available_copies > 0 FOR UPDATE", [$bid]);

    if (!$book) {
        setFlash('error', 'Book is not available for borrowing.');
    } else {
        // Check if student already has this book borrowed
        $alreadyBorrowedRecords = db_fetch_all($conn, "SELECT id FROM borrow_records WHERE user_id=? AND book_id=? AND status IN ('Borrowed','Overdue')", [$user['id'], $bid]);
        $alreadyBorrowed = count($alreadyBorrowedRecords) > 0;

        if ($alreadyBorrowed) {
            setFlash('error', 'You have already borrowed this book and not returned it yet.');
        } else {
            $conn->beginTransaction();
            try {
                $borrowDate = date('Y-m-d');
                $dueDate    = date('Y-m-d', strtotime('+14 days'));

                db_execute($conn, "INSERT INTO borrow_records (user_id,book_id,borrow_date,due_date,status) VALUES (?,?,?,?,'Borrowed')", [$user['id'], $bid, $borrowDate, $dueDate]);

                db_execute($conn, "UPDATE books SET available_copies = available_copies - 1 WHERE id=? AND available_copies > 0", [$bid]);

                $conn->commit();

                // Student borrow request requires admin approval.
                // Create a pending borrow record instead of directly marking Borrowed.
                // Switch the just-inserted record to PendingApproval.
                // Use the known record order (latest matching row).
                db_execute(
                    $conn,
                    "UPDATE borrow_records
                     SET status='PendingApproval'
                     WHERE user_id=? AND book_id=? AND borrow_date=? AND due_date=?
                     ORDER BY id DESC
                     LIMIT 1",
                    [$user['id'], $bid, $borrowDate, $dueDate]
                );


                logActivity($user['id'], 'borrow_requested', "Requested: \"{$book['title']}\" (ID:{$bid}) — due $dueDate");
                setFlash('success', 'Borrow request sent to admin for approval. Due date: ' . date('M d, Y', strtotime($dueDate)));
                header('Location: dashboard.php'); exit();

            } catch (Exception $e) {
                $conn->rollback();
                setFlash('error', 'Failed to borrow book. Please try again.');
            }
        }
    }
}

// Pre-select book if book_id provided
$selectedBook = null;
if ($bookId > 0) {
    $stmt = $conn->prepare("SELECT * FROM books WHERE id=?");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $selectedBook = $stmt->fetch();
    $stmt->close();
}

// Admin actions: approve/dismiss borrow requests
if (isAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim($_POST['action'] ?? '');
    $recId  = intval($_POST['record_id'] ?? 0);
    $bookId = intval($_POST['book_id'] ?? 0);

    if ($recId > 0 && in_array($action, ['approve_borrow','dismiss_borrow'], true)) {
        if ($action === 'approve_borrow') {
            // Approve: mark as Borrowed, set fine_paid fields untouched, release book availability already handled in student flow.
            db_execute($conn, "UPDATE borrow_records SET status='Borrowed' WHERE id=?", [$recId]);
            if ($bookId > 0) {
                // If book availability was reduced earlier, do nothing; if your flow differs, you can adjust here.
                // Fulfill oldest pending reservation for the book if any (optional).
                db_execute($conn, "DELETE FROM reservations WHERE book_id=? AND status='Pending' AND id NOT IN (SELECT id FROM (SELECT id FROM reservations WHERE book_id=? AND status='Pending' ORDER BY reserved_at ASC LIMIT 1) t)" , [$bookId,$bookId]);
            }
            // Reservation fulfillment uses logic in return.php; keep it consistent by not duplicating.
            logActivity($_SESSION['user_id'], 'borrow_approved', "Approved borrow request record #{$recId}");
            setFlash('success', 'Borrow request approved.');
        } else {
            // Dismiss: mark as Returned (or a distinct status if you prefer).
            db_execute($conn, "UPDATE borrow_records SET status='Dismissed' WHERE id=?", [$recId]);
            logActivity($_SESSION['user_id'], 'borrow_dismissed', "Dismissed borrow request record #{$recId}");
            setFlash('warning', 'Borrow request dismissed.');
        }
    }
    header('Location: borrow.php'); exit();
}

// Admin: all borrow records
$allRecords = null;
if (isAdmin() && isset($_GET['view'])) {
    $allRecords = $conn->query("
        SELECT br.*, u.name as user_name, b.title as book_title
        FROM borrow_records br
        JOIN users u ON br.user_id=u.id
        JOIN books b ON br.book_id=b.id
        ORDER BY br.borrow_date DESC
    ");
}


// Available books for dropdown
$availBooks = db_fetch_all($conn, "SELECT id, title, author, available_copies FROM books WHERE available_copies > 0 ORDER BY title");

$pageTitle = 'Borrow a Book';
require_once 'includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Borrow a Book</h1>
        <p>Select a book and confirm to borrow for 14 days</p>
    </div>
    <a href="books.php" class="btn btn-ghost">📚 Browse Collection</a>
</div>

<?php if($selectedBook && $selectedBook['available_copies'] === 0): ?>
    <div class="flash flash-error">❌ Sorry, "<?= sanitize($selectedBook['title']) ?>" is currently unavailable.</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

    <!-- Borrow Form -->
    <div class="card">
        <div class="card-header"><h3 class="card-title">📋 Borrow Request</h3></div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Select Book *</label>
                    <select name="book_id" class="form-control" required onchange="updatePreview(this)">
                        <option value="">— Choose a book —</option>
                        <?php foreach($availBooks as $ab): ?>
                            <option value="<?= $ab['id'] ?>"
                                data-title="<?= sanitize($ab['title']) ?>"
                                data-author="<?= sanitize($ab['author']) ?>"
                                data-avail="<?= $ab['available_copies'] ?>"
                                <?= ($selectedBook && $selectedBook['id']==$ab['id']) ? 'selected' : '' ?>>
                                <?= sanitize($ab['title']) ?> — <?= sanitize($ab['author']) ?> (<?= $ab['available_copies'] ?> avail.)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if(count($availBooks) === 0): ?>
                        <div class="form-hint" style="color:var(--danger)">No books are currently available for borrowing.</div>
                    <?php endif; ?>
                </div>

                <div style="background:var(--surface-2);border-radius:var(--radius);padding:14px;margin-bottom:18px;font-size:.875rem">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <div><span style="color:var(--ink-muted)">Borrower:</span><br><strong><?= sanitize($user['name']) ?></strong></div>
                        <div><span style="color:var(--ink-muted)">Borrow Date:</span><br><strong><?= date('M d, Y') ?></strong></div>
                        <div><span style="color:var(--ink-muted)">Due Date:</span><br><strong><?= date('M d, Y', strtotime('+14 days')) ?></strong></div>
                        <div><span style="color:var(--ink-muted)">Loan Period:</span><br><strong>14 days</strong></div>
                    </div>
                </div>

                <div id="bookPreview" style="display:<?= $selectedBook?'block':'none' ?>;background:var(--accent-light);border-radius:var(--radius);padding:14px;margin-bottom:18px">
                    <strong id="prevTitle"><?= $selectedBook ? sanitize($selectedBook['title']) : '' ?></strong><br>
                    <span id="prevAuthor" style="font-size:.85rem;color:var(--ink-muted)"><?= $selectedBook ? 'by '.sanitize($selectedBook['author']) : '' ?></span><br>
                    <span id="prevAvail" style="font-size:.8rem;font-family:var(--font-mono);color:var(--success)"><?= $selectedBook ? $selectedBook['available_copies'].' copies available' : '' ?></span>
                </div>

                <div class="form-actions" style="justify-content:flex-start">
                    <button type="submit" class="btn btn-primary" <?= count($availBooks)===0?'disabled':'' ?>>⬇️ Confirm Borrow</button>
                    <a href="dashboard.php" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Info Panel -->
    <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
            <div class="card-header"><h3 class="card-title">ℹ️ Borrowing Policy</h3></div>
            <div class="card-body" style="font-size:.875rem;color:var(--ink-soft);display:flex;flex-direction:column;gap:10px">
                <div style="display:flex;gap:8px;align-items:flex-start">📅 <span>Loan period is <strong>14 days</strong> from borrow date.</span></div>
                <div style="display:flex;gap:8px;align-items:flex-start">⚠️ <span>Books not returned by due date are marked <strong>Overdue</strong>.</span></div>
                <div style="display:flex;gap:8px;align-items:flex-start">📚 <span>You can only borrow one copy of each book at a time.</span></div>
                <div style="display:flex;gap:8px;align-items:flex-start">🔄 <span>Return books promptly to keep them available for others.</span></div>
            </div>
        </div>

        <?php if(isAdmin()): ?>
        <div class="card">
            <div class="card-header"><h3 class="card-title">Admin: All Borrow Records</h3></div>
            <div class="card-body" style="padding:0">
                <?php
                $adminRecords = db_fetch_all($conn, "SELECT br.*, u.name as user_name, b.title as book_title FROM borrow_records br JOIN users u ON br.user_id=u.id JOIN books b ON br.book_id=b.id ORDER BY br.borrow_date DESC LIMIT 8");
                if(count($adminRecords) === 0): ?>
                    <p style="padding:16px;color:var(--ink-muted);font-size:.85rem">No records yet</p>
                <?php else: ?>
                <?php foreach($adminRecords as $r): ?>
                        <div style="padding:10px 16px;border-bottom:1px solid var(--border);font-size:.82rem">
                            <strong><?= sanitize($r['user_name']) ?></strong> → <?= sanitize($r['book_title']) ?><br>
                            <span style="color:var(--ink-muted)">Due: <?= date('M d', strtotime($r['due_date'])) ?></span>
                            <span class="badge <?= $r['status']==='Returned'?'badge-success':($r['status']==='Overdue'?'badge-danger':($r['status']==='PendingApproval'?'badge-warning':'badge-info')) ?>" style="float:right"><?= $r['status'] ?></span>

                            <?php if(($r['status'] ?? '') === 'PendingApproval'): ?>
                                <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Approve this borrow request?')">
                                        <input type="hidden" name="action" value="approve_borrow">
                                        <input type="hidden" name="record_id" value="<?= (int)$r['id'] ?>">
                                        <input type="hidden" name="book_id" value="<?= (int)$r['book_id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm">✅ Approve</button>
                                    </form>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Dismiss this borrow request?')">
                                        <input type="hidden" name="action" value="dismiss_borrow">
                                        <input type="hidden" name="record_id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">✖️ Dismiss</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updatePreview(sel){
    const opt = sel.options[sel.selectedIndex];
    const preview = document.getElementById('bookPreview');
    if(opt.value){
        document.getElementById('prevTitle').textContent = opt.dataset.title;
        document.getElementById('prevAuthor').textContent = 'by ' + opt.dataset.author;
        document.getElementById('prevAvail').textContent = opt.dataset.avail + ' copies available';
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}
</script>

<?php $conn->close(); require_once 'includes/footer.php'; ?>
