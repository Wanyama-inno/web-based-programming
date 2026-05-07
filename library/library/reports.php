<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();
checkOverdueBooks();

$conn = getDBConnection();

// ── Core KPIs ──────────────────────────────────────────────────────────
$kpis = [
    'total_books'    => $conn->query("SELECT COUNT(*) c FROM books")->fetch()['c'],
    'total_copies'   => $conn->query("SELECT SUM(total_copies) c FROM books")->fetch()['c'],
    'avail_copies'   => $conn->query("SELECT SUM(available_copies) c FROM books")->fetch()['c'],
    'total_students' => $conn->query("SELECT COUNT(*) c FROM users WHERE role='student'")->fetch()['c'],
    'active_borrows' => $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE status='Borrowed'")->fetch()['c'],
    'overdue'        => $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE status='Overdue'")->fetch()['c'],
    'returned_total' => $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE status='Returned'")->fetch()['c'],
    'total_fines'    => $conn->query("SELECT COALESCE(SUM(fine_amount),0) c FROM borrow_records WHERE status='Overdue' AND fine_paid=0")->fetch()['c'],
    'fines_collected'=> $conn->query("SELECT COALESCE(SUM(fine_amount),0) c FROM borrow_records WHERE fine_paid=1")->fetch()['c'],
    'reservations'   => $conn->query("SELECT COUNT(*) c FROM reservations WHERE status='Active'")->fetch()['c'],
];

// ── Top 10 Most Borrowed Books ──────────────────────────────────────────
$topBooks = $conn->query("
    SELECT b.title, b.author, b.category, COUNT(br.id) as borrow_count,
           b.available_copies, b.total_copies
    FROM borrow_records br JOIN books b ON br.book_id=b.id
    GROUP BY br.book_id ORDER BY borrow_count DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// ── Most Active Students ────────────────────────────────────────────────
$topStudents = $conn->query("
    SELECT u.name, u.email,
           COUNT(br.id) as total_borrows,
           SUM(br.status IN ('Borrowed','Overdue')) as active,
           SUM(br.fine_amount) as fines
    FROM borrow_records br JOIN users u ON br.user_id=u.id
    GROUP BY br.user_id ORDER BY total_borrows DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// ── Overdue Details ─────────────────────────────────────────────────────
$overdueList = $conn->query("
    SELECT br.*, u.name as user_name, u.email,
           b.title as book_title,
           DATEDIFF(CURDATE(), br.due_date) as days_overdue,
           DATEDIFF(CURDATE(), br.due_date) * " . FINE_PER_DAY . " as current_fine
    FROM borrow_records br
    JOIN users u ON br.user_id=u.id
    JOIN books b ON br.book_id=b.id
    WHERE br.status='Overdue'
    ORDER BY days_overdue DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Borrowing by Category ───────────────────────────────────────────────
$byCategory = $conn->query("
    SELECT b.category, COUNT(br.id) as borrow_count
    FROM borrow_records br JOIN books b ON br.book_id=b.id
    WHERE b.category IS NOT NULL AND b.category != ''
    GROUP BY b.category ORDER BY borrow_count DESC
")->fetchAll(PDO::FETCH_ASSOC);
$catData = $byCategory; $catMax = 1;
foreach($catData as $c) {
    if ($c['borrow_count'] > $catMax) $catMax = $c['borrow_count'];
}

// ── Monthly Borrows (last 6 months) ────────────────────────────────────
$monthly = $conn->query("
    SELECT DATE_FORMAT(borrow_date,'%b %Y') as month,
           DATE_FORMAT(borrow_date,'%Y-%m') as month_key,
           COUNT(*) as cnt
    FROM borrow_records
    WHERE borrow_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key ORDER BY month_key ASC
")->fetchAll(PDO::FETCH_ASSOC);
$monthlyData = $monthly; $monthMax = 1;
foreach($monthlyData as $m) {
    if ($m['cnt'] > $monthMax) $monthMax = $m['cnt'];
}

// ── Books with zero borrows ─────────────────────────────────────────────
$unborrowedCount = $conn->query("
    SELECT COUNT(*) c FROM books b
    LEFT JOIN borrow_records br ON b.id=br.book_id
    WHERE br.id IS NULL
")->fetch()['c'];

$pageTitle = 'Reports & Analytics';
require_once 'includes/header.php';
?>

<style>
.bar-wrap{display:flex;align-items:center;gap:10px}
.bar-bg{flex:1;height:10px;background:var(--surface-3);border-radius:99px;overflow:hidden}
.bar-fill{height:100%;border-radius:99px;transition:width .4s ease}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:28px}
.report-section{margin-bottom:28px}
</style>

<div class="page-header">
    <div class="page-header-left">
        <h1>Reports & Analytics</h1>
        <p>Library performance metrics and insights</p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="export.php?type=borrows" class="btn btn-ghost btn-sm">⬇️ Borrows CSV</a>
        <a href="export.php?type=fines"   class="btn btn-ghost btn-sm">⬇️ Fines CSV</a>
        <a href="export.php?type=overdue" class="btn btn-ghost btn-sm">⬇️ Overdue CSV</a>
        <a href="activity_log.php"        class="btn btn-primary btn-sm">📋 Activity Log</a>
    </div>
</div>

<!-- ── KPI Grid ── -->
<div class="kpi-grid">
    <?php $kpiCards = [
        ['📚', 'Book Titles', $kpis['total_books'], 'blue'],
        ['📖', 'Total Copies', $kpis['total_copies'], 'green'],
        ['✅', 'Available Copies', $kpis['avail_copies'], 'emerald'],
        ['👥', 'Students', $kpis['total_students'], 'purple'],
        ['⬇️', 'Active Borrows', $kpis['active_borrows'], 'orange'],
        ['⚠️', 'Overdue', $kpis['overdue'], 'red'],
        ['↩️', 'Total Returns', $kpis['returned_total'], 'gray'],
        ['💰', 'Total Fines', '$' . number_format($kpis['total_fines'], 2), 'gold'],
        ['💵', 'Fines Collected', '$' . number_format($kpis['fines_collected'], 2), 'success'],
        ['🔖', 'Reservations', $kpis['reservations'], 'warning'],
    ];
    foreach($kpiCards as $card): ?>
        <div class="stat-card <?= $card[3] ?>">
            <div class="stat-value"><?= $card[2] ?></div>
            <div class="stat-label"><?= $card[1] ?></div>
            <div class="stat-icon"><?= $card[0] ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
    <!-- ── Category Distribution ── -->
    <div class="card report-section">
        <div class="card-header">
            <h3 class="card-title">📊 Borrowing by Category</h3>
        </div>
        <?php if(count($catData) === 0): ?>
            <div class="empty-state"><p>No category data available</p></div>
        <?php else: ?>
        <div class="card-body" style="display:flex;flex-direction:column;gap:12px">
            <?php foreach($catData as $cat): $pct = $catMax > 0 ? round($cat['borrow_count'] / $catMax * 100) : 0; ?>
                <div class="bar-wrap">
                    <span style="min-width:120px;font-size:.875rem"><?= sanitize($cat['category'] ?: 'Uncategorized') ?></span>
                    <div class="bar-bg">
                        <div class="bar-fill" style="width:<?= $pct ?>%;background:var(--accent)"></div>
                    </div>
                    <span style="font-family:var(--font-mono);font-size:.8rem;color:var(--ink-muted)"><?= $cat['borrow_count'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Monthly Trends ── -->
    <div class="card report-section">
        <div class="card-header">
            <h3 class="card-title">📈 Monthly Borrowing Trends</h3>
        </div>
        <?php if(count($monthlyData) === 0): ?>
            <div class="empty-state"><p>No trend data available</p></div>
        <?php else: ?>
        <div class="card-body" style="display:flex;flex-direction:column;gap:12px">
            <?php foreach($monthlyData as $month): $pct = $monthMax > 0 ? round($month['cnt'] / $monthMax * 100) : 0; ?>
                <div class="bar-wrap">
                    <span style="min-width:100px;font-size:.875rem"><?= $month['month'] ?></span>
                    <div class="bar-bg">
                        <div class="bar-fill" style="width:<?= $pct ?>%;background:var(--success)"></div>
                    </div>
                    <span style="font-family:var(--font-mono);font-size:.8rem;color:var(--ink-muted)"><?= $month['cnt'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Top 10 Most Borrowed Books ── -->
<div class="card report-section">
    <div class="card-header">
        <h3 class="card-title">🏆 Top 10 Most Borrowed Books</h3>
        <span style="font-size:.85rem;color:var(--ink-muted)"><?= $unborrowedCount ?> books never borrowed</span>
    </div>
    <?php if(count($topBooks) === 0): ?>
        <div class="empty-state"><p>No borrowing data yet</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Title</th><th>Author</th><th>Category</th><th>Borrows</th><th>Availability</th></tr></thead>
            <tbody>
            <?php $rank=1; foreach($topBooks as $b): ?>
            <tr>
                <td style="font-family:var(--font-mono);font-weight:600;text-align:center"><?= $rank++ ?></td>
                <td><strong><?= sanitize($b['title']) ?></strong></td>
                <td style="color:var(--ink-muted)"><?= sanitize($b['author']) ?></td>
                <td><span class="badge badge-info" style="font-size:.75rem"><?= sanitize($b['category'] ?: '—') ?></span></td>
                <td style="font-family:var(--font-mono);font-weight:600;text-align:center"><?= $b['borrow_count'] ?></td>
                <td>
                    <div class="bar-wrap">
                        <span style="font-family:var(--font-mono);font-size:.8rem;color:<?= $b['available_copies']>0?'var(--success)':'var(--danger)' ?>"><?= $b['available_copies'] ?>/<?= $b['total_copies'] ?></span>
                        <div class="bar-bg" style="max-width:60px">
                            <div class="bar-fill" style="width:<?= $b['total_copies']>0?round($b['available_copies']/$b['total_copies']*100):0 ?>%;background:<?= $b['available_copies']>0?'var(--success)':'var(--danger)' ?>"></div>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Overdue Detail ── -->
<?php if(count($overdueList) > 0): ?>
<div class="card report-section">
    <div class="card-header">
        <h3 class="card-title" style="color:var(--danger)">⚠️ Overdue Books — Action Required</h3>
        <span class="badge badge-danger"><?= count($overdueList) ?> overdue</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Student</th><th>Email</th><th>Book</th><th>Due Date</th><th>Days Overdue</th><th>Fine Accrued</th></tr></thead>
            <tbody>
            <?php foreach($overdueList as $r): ?>
            <tr>
                <td style="font-weight:600"><?= sanitize($r['user_name']) ?></td>
                <td style="font-size:.85rem;color:var(--ink-muted)"><?= sanitize($r['email']) ?></td>
                <td><strong><?= sanitize($r['book_title']) ?></strong></td>
                <td style="font-size:.82rem"><?= date('M d, Y', strtotime($r['due_date'])) ?></td>
                <td style="font-family:var(--font-mono);color:var(--danger);font-weight:600">+<?= $r['days_overdue'] ?> days</td>
                <td style="font-family:var(--font-mono);color:var(--danger);font-weight:700"><?= formatMoney($r['current_fine']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ── Most Active Students ── -->
<div class="card report-section">
    <div class="card-header">
        <h3 class="card-title">👥 Most Active Students</h3>
    </div>
    <?php if(count($topStudents) === 0): ?>
        <div class="empty-state"><p>No student activity data yet</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Student</th><th>Total Borrows</th><th>Active Loans</th><th>Total Fines</th></tr></thead>
            <tbody>
            <?php $r=1; foreach($topStudents as $s): ?>
            <tr>
                <td>
                    <div style="font-weight:600"><?= sanitize($s['name']) ?></div>
                    <div style="font-size:.8rem;color:var(--ink-muted)"><?= sanitize($s['email']) ?></div>
                </td>
                <td style="font-family:var(--font-mono);font-weight:600;text-align:center"><?= $s['total_borrows'] ?></td>
                <td style="font-family:var(--font-mono);text-align:center;color:<?= $s['active']>0?'var(--accent)':'var(--ink-muted)' ?>"><?= $s['active'] ?: '—' ?></td>
                <td style="font-family:var(--font-mono);color:<?= $s['fines']>0?'var(--danger)':'var(--ink-muted)' ?>">
                    <?= $s['fines'] > 0 ? formatMoney($s['fines']) : '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>

        <div style="font-size:.78rem;color:var(--ink-muted);text-align:right;margin-right:6px">
            Fine: <?= formatMoney(FINE_PER_DAY) ?>/day &bull; Loan: 14 days
        </div>
        <a href="export.php?type=books"   class="btn btn-ghost btn-sm">⬇️ Books CSV</a>
        <a href="export.php?type=borrows" class="btn btn-ghost btn-sm">⬇️ Borrows CSV</a>
        <a href="export.php?type=fines"   class="btn btn-ghost btn-sm">⬇️ Fines CSV</a>
        <a href="export.php?type=overdue" class="btn btn-ghost btn-sm">⬇️ Overdue CSV</a>
        <a href="activity_log.php"        class="btn btn-primary btn-sm">📋 Activity Log</a>
    </div>
</div>

<!-- ── KPI Grid ── -->
<div class="kpi-grid">
    <?php $kpiCards = [
        ['📚', 'Book Titles', $kpis['total_books'], 'blue'],
        ['🗂️', 'Total Copies', $kpis['total_copies'], 'blue'],
        ['✅', 'Available', $kpis['avail_copies'], 'green'],
        ['🎓', 'Students', $kpis['total_students'], 'green'],
        ['📤', 'Active Loans', $kpis['active_borrows'], 'gold'],
        ['⚠️', 'Overdue', $kpis['overdue'], 'red'],
        ['🔄', 'All-time Returns', $kpis['returned_total'], 'green'],
        ['💰', 'Unpaid Fines', formatMoney($kpis['total_fines']), 'red'],
        ['💵', 'Fines Collected', formatMoney($kpis['fines_collected']), 'green'],
        ['🔖', 'Reservations', $kpis['reservations'], 'gold'],
    ]; foreach($kpiCards as $k): ?>
    <div class="stat-card <?= $k[3] ?>">
        <div class="stat-label"><?= $k[1] ?></div>
        <div class="stat-value" style="font-size:1.6rem"><?= $k[2] ?></div>
        <div class="stat-icon"><?= $k[0] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Two column ── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">

    <!-- Monthly Borrow Chart -->
    <div class="card report-section" style="margin-bottom:0">
        <div class="card-header"><h3 class="card-title">📅 Monthly Borrows (Last 6 Months)</h3></div>
        <div class="card-body">
            <?php if(empty($monthlyData)): ?>
                <div class="empty-state" style="padding:24px"><p>No data yet</p></div>
            <?php else: foreach($monthlyData as $m): $pct = round($m['cnt']/$monthMax*100); ?>
            <div style="margin-bottom:14px">
                <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:5px">
                    <span style="color:var(--ink-soft)"><?= $m['month'] ?></span>
                    <span style="font-family:var(--font-mono);font-weight:600"><?= $m['cnt'] ?></span>
                </div>
                <div class="bar-bg">
                    <div class="bar-fill" style="width:<?= $pct ?>%;background:var(--accent)"></div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- By Category -->
    <div class="card report-section" style="margin-bottom:0">
        <div class="card-header"><h3 class="card-title">🏷️ Borrows by Category</h3></div>
        <div class="card-body">
            <?php if(empty($catData)): ?>
                <div class="empty-state" style="padding:24px"><p>No data yet</p></div>
            <?php else:
            $colors = ['#6366F1','#10B981','#F59E0B','#EF4444','#8B5CF6','#06B6D4','#F97316','#EC4899'];
            foreach($catData as $i => $c):
                $pct = round($c['borrow_count']/$catMax*100);
                $color = $colors[$i % count($colors)];
            ?>
            <div style="margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:4px">
                    <span style="color:var(--ink-soft)"><?= sanitize($c['category']) ?></span>
                    <span style="font-family:var(--font-mono);font-weight:600"><?= $c['borrow_count'] ?></span>
                </div>
                <div class="bar-bg">
                    <div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- ── Top Books ── -->
<div class="card report-section">
    <div class="card-header">
        <h3 class="card-title">🏆 Top 10 Most Borrowed Books</h3>
        <span style="font-size:.8rem;color:var(--ink-muted)"><?= $unborrowedCount ?> books never borrowed</span>
    </div>
    <?php if(count($topBooks) === 0): ?>
        <div class="empty-state"><p>No borrowing data yet</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Title</th><th>Author</th><th>Category</th><th>Borrows</th><th>Availability</th></tr></thead>
            <tbody>
            <?php $rank=1; foreach($topBooks as $b): ?>
            <tr>
                <td style="font-family:var(--font-mono);color:var(--ink-muted)"><?= $rank++ ?></td>
                <td><strong><?= sanitize($b['title']) ?></strong></td>
                <td><?= sanitize($b['author']) ?></td>
                <td><?= $b['category'] ? '<span class="badge badge-info">'.sanitize($b['category']).'</span>' : '—' ?></td>
                <td style="font-family:var(--font-mono);font-weight:600;color:var(--accent)"><?= $b['borrow_count'] ?></td>
                <td>
                    <div class="bar-wrap">
                        <span style="font-family:var(--font-mono);font-size:.8rem;color:<?= $b['available_copies']>0?'var(--success)':'var(--danger)' ?>"><?= $b['available_copies'] ?>/<?= $b['total_copies'] ?></span>
                        <div class="bar-bg" style="max-width:60px">
                            <div class="bar-fill" style="width:<?= $b['total_copies']>0?round($b['available_copies']/$b['total_copies']*100):0 ?>%;background:<?= $b['available_copies']>0?'var(--success)':'var(--danger)' ?>"></div>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Overdue Detail ── -->
<?php if(count($overdueList) > 0): ?>
<div class="card report-section">
    <div class="card-header">
        <h3 class="card-title" style="color:var(--danger)">⚠️ Overdue Books — Action Required</h3>
        <span class="badge badge-danger"><?= count($overdueList) ?> overdue</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Student</th><th>Email</th><th>Book</th><th>Due Date</th><th>Days Overdue</th><th>Fine Accrued</th></tr></thead>
            <tbody>
            <?php foreach($overdueList as $r): ?>
            <tr style="background:<?= $r['days_overdue']>=14?'#FFF5F5':'' ?>">
                <td><strong><?= sanitize($r['user_name']) ?></strong></td>
                <td style="font-size:.82rem"><?= sanitize($r['email']) ?></td>
                <td><?= sanitize($r['book_title']) ?></td>
                <td><?= date('M d, Y', strtotime($r['due_date'])) ?></td>
                <td style="font-family:var(--font-mono);color:var(--danger);font-weight:600">+<?= $r['days_overdue'] ?> days</td>
                <td style="font-family:var(--font-mono);color:var(--danger);font-weight:700"><?= formatMoney($r['current_fine']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ── Top Students ── -->
<div class="card report-section">
    <div class="card-header"><h3 class="card-title">🎓 Most Active Students</h3></div>
    <?php if(count($topStudents) === 0): ?>
        <div class="empty-state"><p>No data yet</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Total Borrows</th><th>Active Loans</th><th>Fines</th></tr></thead>
            <tbody>
            <?php $r=1; foreach($topStudents as $s): ?>
            <tr>
                <td style="font-family:var(--font-mono);color:var(--ink-muted)"><?= $r++ ?></td>
                <td><strong><?= sanitize($s['name']) ?></strong></td>
                <td style="font-size:.82rem"><?= sanitize($s['email']) ?></td>
                <td style="font-family:var(--font-mono);font-weight:600;color:var(--accent)"><?= $s['total_borrows'] ?></td>
                <td><?= intval($s['active']) > 0 ? '<span class="badge badge-info">'.intval($s['active']).'</span>' : '—' ?></td>
                <td style="font-family:var(--font-mono);color:<?= $s['fines']>0?'var(--danger)':'var(--ink-muted)' ?>">
                    <?= $s['fines'] > 0 ? formatMoney($s['fines']) : '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>


