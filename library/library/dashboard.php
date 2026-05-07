<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireLogin();
checkOverdueBooks();

$user = getCurrentUser();
$conn = getDBConnection();

if (isAdmin()) {
    $totalBooks   = $conn->query("SELECT COUNT(*) c FROM books")->fetch()['c'];
    $totalUsers   = $conn->query("SELECT COUNT(*) c FROM users WHERE role='student'")->fetch()['c'];
    $totalBorrow  = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE status='Borrowed'")->fetch()['c'];
    $overdueCount = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE status='Overdue'")->fetch()['c'];
    $returnedToday= $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE DATE(return_date)=CURDATE()")->fetch()['c'];

    // Recent borrow records
    $records = $conn->query("
        SELECT br.*, u.name as user_name, b.title as book_title, b.author
        FROM borrow_records br
        JOIN users u ON br.user_id=u.id
        JOIN books b ON br.book_id=b.id
        ORDER BY br.borrow_date DESC LIMIT 10
    ")->fetchAll();

    // Top borrowed books
    $topBooks = $conn->query("
        SELECT b.title, b.author, COUNT(*) as borrow_count
        FROM borrow_records br JOIN books b ON br.book_id=b.id
        GROUP BY br.book_id ORDER BY borrow_count DESC LIMIT 5
    ")->fetchAll();

    // Overdue records
    $overdueRec = $conn->query("
        SELECT br.*, u.name as user_name, b.title as book_title,
               DATEDIFF(CURDATE(), br.due_date) as days_overdue
        FROM borrow_records br
        JOIN users u ON br.user_id=u.id
        JOIN books b ON br.book_id=b.id
        WHERE br.status='Overdue'
        ORDER BY days_overdue DESC LIMIT 5
    ")->fetchAll();
} else {
    // Student stats
    $borrowed  = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE user_id={$user['id']} AND status='Borrowed'")->fetch()['c'];
    $overdue   = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE user_id={$user['id']} AND status='Overdue'")->fetch()['c'];
    $returned  = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE user_id={$user['id']} AND status='Returned'")->fetch()['c'];
    $totalBorrowed = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE user_id={$user['id']}")->fetch()['c'];

    // Active loans
    $activeLoans = $conn->query("
        SELECT br.*, b.title, b.author, b.cover_color,
               DATEDIFF(br.due_date, CURDATE()) as days_left
        FROM borrow_records br JOIN books b ON br.book_id=b.id
        WHERE br.user_id={$user['id']} AND br.status IN ('Borrowed','Overdue')
        ORDER BY br.due_date ASC
    ")->fetchAll();

    // History
    $history = $conn->query("
        SELECT br.*, b.title, b.author
        FROM borrow_records br JOIN books b ON br.book_id=b.id
        WHERE br.user_id={$user['id']} AND br.status='Returned'
        ORDER BY br.return_date DESC LIMIT 8
    ")->fetchAll();
}
$pageTitle = 'Dashboard';
require_once 'includes/header.php';
?>

<?php if(isAdmin()): ?>
<!-- ===== ADMIN DASHBOARD ===== -->
<div class="page-header">
    <div class="page-header-left">
        <h1>Admin Dashboard</h1>
        <p>Library overview and management center</p>
    </div>
    <a href="add_book.php" class="btn btn-primary">➕ Add New Book</a>
</div>

<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-label">Total Books</div>
        <div class="stat-value"><?= $totalBooks ?></div>
        <div class="stat-icon">📚</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Registered Students</div>
        <div class="stat-value"><?= $totalUsers ?></div>
        <div class="stat-icon">👥</div>
    </div>
    <div class="stat-card gold">
        <div class="stat-label">Currently Borrowed</div>
        <div class="stat-value"><?= $totalBorrow ?></div>
        <div class="stat-icon">📤</div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Overdue Books</div>
        <div class="stat-value"><?= $overdueCount ?></div>
        <div class="stat-icon">⚠️</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px;align-items:start">

    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Borrowing Activity</h3>
            <a href="borrow.php?view=all" class="btn btn-ghost btn-sm">View all</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Book</th>
                        <th>Borrowed</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($records) === 0): ?>
                        <tr><td colspan="5" style="text-align:center;color:var(--ink-muted);padding:24px">No records yet</td></tr>
                    <?php else: ?>
                    <?php foreach($records as $r): ?>

                    <tr>
                        <td><strong><?= sanitize($r['user_name']) ?></strong></td>
                        <td><?= sanitize($r['book_title']) ?><br><small style="color:var(--ink-muted)"><?= sanitize($r['author']) ?></small></td>
                        <td><?= date('M d, Y', strtotime($r['borrow_date'])) ?></td>
                        <td><?= date('M d, Y', strtotime($r['due_date'])) ?></td>
                        <td>
                            <span class="badge <?= $r['status']==='Returned'?'badge-success':($r['status']==='Overdue'?'badge-danger':'badge-info') ?>">
                                <?= $r['status'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right column -->
    <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Top Books -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">📈 Top Borrowed</h3></div>
            <div class="card-body" style="padding:0">
                <?php if(count($topBooks) === 0): ?>
                    <p style="padding:16px;color:var(--ink-muted);font-size:.85rem">No data yet</p>
                <?php else: ?>
                <?php $rank=1; foreach($topBooks as $b): ?>

                <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border)">
                    <span style="font-family:var(--font-mono);font-size:.75rem;color:var(--ink-muted);width:16px">#<?= $rank++ ?></span>
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:600;font-size:.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= sanitize($b['title']) ?></div>
                        <div style="font-size:.78rem;color:var(--ink-muted)"><?= $b['borrow_count'] ?> borrows</div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Overdue Alert -->
        <?php if($overdueCount > 0): ?>
<div class="card" style="border-color:var(--danger-light)">
<div class="card-header" style="background:var(--danger-light)">
                <h3 class="card-title" style="color:var(--danger)">⚠️ Overdue Books</h3>
            </div>
            <div class="card-body" style="padding:0">
                <?php foreach($overdueRec as $r): ?>
                <div style="padding:12px 16px;border-bottom:1px solid var(--border)">
                    <div style="font-weight:600;font-size:.875rem"><?= sanitize($r['user_name']) ?></div>
                    <div style="font-size:.8rem;color:var(--ink-muted)"><?= sanitize($r['book_title']) ?></div>
                    <div style="font-size:.78rem;color:var(--danger);margin-top:2px"><?= $r['days_overdue'] ?> days overdue</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">Quick Actions</h3></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
                <a href="books.php" class="btn btn-ghost" style="justify-content:flex-start">📚 Manage Books</a>
                <a href="users.php" class="btn btn-ghost" style="justify-content:flex-start">👥 Manage Users</a>
                <a href="return.php" class="btn btn-ghost" style="justify-content:flex-start">⬆️ Process Returns</a>
                <a href="borrow.php" class="btn btn-ghost" style="justify-content:flex-start">⬇️ Borrow Records</a>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ===== STUDENT DASHBOARD ===== -->
<div class="page-header">
    <div class="page-header-left">
        <h1>My Library</h1>
        <p>Welcome back, <?= sanitize($user['name']) ?>! Here's your library activity.</p>
    </div>
    <a href="books.php" class="btn btn-primary">📖 Browse Books</a>
</div>

<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-label">Active Loans</div>
        <div class="stat-value"><?= $borrowed ?></div>
        <div class="stat-icon">📤</div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Overdue</div>
        <div class="stat-value"><?= $overdue ?></div>
        <div class="stat-icon">⚠️</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Books Returned</div>
        <div class="stat-value"><?= $returned ?></div>
        <div class="stat-icon">✅</div>
    </div>
    <div class="stat-card gold">
        <div class="stat-label">Total Borrowed</div>
        <div class="stat-value"><?= $totalBorrowed ?></div>
        <div class="stat-icon">📚</div>
    </div>
</div>

<!-- Active Loans -->
<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <h3 class="card-title">📚 Active Loans</h3>
        <a href="return.php" class="btn btn-success btn-sm">⬆️ Return a Book</a>
    </div>
    <?php if(count($activeLoans) === 0): ?>
        <div class="empty-state">
            <div class="empty-icon">📖</div>
            <p>No active loans. <a href="books.php" style="color:var(--accent)">Browse books to borrow one!</a></p>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Book</th><th>Borrowed</th><th>Due Date</th><th>Days Left</th><th>Fine</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php
            $loansArr = $activeLoans;
            $totalFines = 0;
            foreach($loansArr as $loan):
                $fine = calculateFine($loan['due_date']);
                $totalFines += $fine;
            ?>
            <tr>
                <td>
                    <strong><?= sanitize($loan['title']) ?></strong><br>
                    <small style="color:var(--ink-muted)"><?= sanitize($loan['author']) ?></small>
                </td>
                <td><?= date('M d, Y', strtotime($loan['borrow_date'])) ?></td>
                <td><?= date('M d, Y', strtotime($loan['due_date'])) ?></td>
                <td>
                    <?php $dl = $loan['days_left']; ?>
                    <span style="font-family:var(--font-mono);font-size:.85rem;color:<?= $dl<0?'var(--danger)':($dl<=3?'var(--warning)':'var(--success)') ?>">
                        <?= $dl<0 ? abs($dl).' overdue' : $dl.' days' ?>
                    </span>
                </td>
                <td style="font-family:var(--font-mono);font-size:.85rem;color:<?= $fine>0?'var(--danger)':'var(--ink-muted)' ?>">
                    <?= $fine > 0 ? formatMoney($fine) : '—' ?>
                </td>
                <td><span class="badge <?= $loan['status']==='Overdue'?'badge-danger':'badge-info' ?>"><?= $loan['status'] ?></span></td>
                <td><a href="return.php?record_id=<?= $loan['id'] ?>" class="btn btn-warning btn-sm">Return</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if($totalFines > 0): ?>
            <tfoot>
                <tr style="background:var(--danger-light)">
                    <td colspan="4" style="font-weight:600;color:var(--danger);padding:10px 14px">Total Outstanding Fines</td>
                    <td style="font-weight:700;color:var(--danger);font-family:var(--font-mono)"><?= formatMoney($totalFines) ?></td>
                    <td colspan="2" style="font-size:.78rem;color:var(--danger)">at <?= formatMoney(FINE_PER_DAY) ?>/day</td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- History -->
<div class="card">
    <div class="card-header"><h3 class="card-title">🕒 Borrowing History</h3></div>
    <?php if(count($history) === 0): ?>
        <div class="empty-state"><div class="empty-icon">📋</div><p>No borrowing history yet.</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Book</th><th>Borrowed</th><th>Due</th><th>Returned</th><th>Fine</th></tr></thead>
            <tbody>
            <?php foreach($history as $h): ?>
            <tr>
                <td><strong><?= sanitize($h['title']) ?></strong><br><small style="color:var(--ink-muted)"><?= sanitize($h['author']) ?></small></td>
                <td><?= date('M d, Y', strtotime($h['borrow_date'])) ?></td>
                <td><?= date('M d, Y', strtotime($h['due_date'])) ?></td>
                <td><?= $h['return_date'] ? date('M d, Y', strtotime($h['return_date'])) : '—' ?></td>
                <td style="font-family:var(--font-mono);font-size:.85rem;color:<?= $h['fine_amount']>0?'var(--danger)':'var(--ink-muted)' ?>">
                    <?= $h['fine_amount'] > 0 ? formatMoney($h['fine_amount']) : '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
