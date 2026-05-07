<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$conn = getDBConnection();

// Filter
$filterAction = trim($_GET['action_filter'] ?? '');
$filterUser   = trim($_GET['user_filter']   ?? '');
$page         = max(1, intval($_GET['page'] ?? 1));
$perPage      = 40;

// Build WHERE
$where  = ['1=1'];
$params = [];
$types  = '';

if ($filterAction) {
    $where[]  = 'al.action = ?';
    $params[] = $filterAction;
    $types   .= 's';
}
if ($filterUser) {
    $like     = "%$filterUser%";
    $where[]  = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

$whereSQL = implode(' AND ', $where);

// Count total
if ($types) {
    $total = (int) db_fetch_one($conn, "SELECT COUNT(*) c FROM activity_log al LEFT JOIN users u ON al.user_id=u.id WHERE $whereSQL", $params)['c'] ?? 0;
} else {
    $total = (int) ($conn->query("SELECT COUNT(*) c FROM activity_log")->fetch()['c'] ?? 0);
}

$totalPages = max(1, ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// Fetch logs
if ($types) {
    $logs = db_fetch_all($conn, "
        SELECT al.*, u.name AS user_name, u.email, u.role
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE $whereSQL
        ORDER BY al.created_at DESC
        LIMIT $perPage OFFSET $offset
    ", $params);
} else {
    $logs = db_fetch_all($conn, "
        SELECT al.*, u.name AS user_name, u.email, u.role
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
}

// Distinct actions for filter dropdown
$actions = $conn->query("SELECT DISTINCT action FROM activity_log ORDER BY action");

// Recent stats
$todayCount  = $conn->query("SELECT COUNT(*) c FROM activity_log WHERE DATE(created_at)=CURDATE()")->fetch()['c'];
$weekCount   = $conn->query("SELECT COUNT(*) c FROM activity_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch()['c'];
$totalCount  = $conn->query("SELECT COUNT(*) c FROM activity_log")->fetch()['c'];

$pageTitle = 'Activity Log';
require_once 'includes/header.php';

// Map action keys to icons and labels
$actionMeta = [
    'user_login'             => ['🔐', 'Login',          'badge-info'],
    'user_logout'            => ['🚪', 'Logout',         'badge-gray'],
    'book_borrowed'          => ['📤', 'Borrowed',       'badge-info'],
    'book_returned'          => ['📥', 'Returned',       'badge-success'],
    'book_added'             => ['➕', 'Book Added',     'badge-success'],
    'book_edited'            => ['✏️',  'Book Edited',    'badge-warning'],
    'book_deleted'           => ['🗑️',  'Book Deleted',   'badge-danger'],
    'reservation_placed'     => ['🔖', 'Reserved',      'badge-warning'],
    'reservation_cancelled'  => ['❌', 'Res. Cancelled', 'badge-danger'],
    'fine_paid'              => ['💵', 'Fine Paid',      'badge-success'],
    'fine_waived'            => ['🙏', 'Fine Waived',    'badge-gray'],
    'fine_settings_updated'  => ['⚙️',  'Settings',      'badge-gray'],
    'profile_updated'        => ['👤', 'Profile Update', 'badge-gray'],
    'password_changed'       => ['🔑', 'Pwd Changed',   'badge-warning'],
    'user_registered'        => ['✨', 'Registered',    'badge-success'],
];
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb"><a href="dashboard.php">Dashboard</a> › Activity Log</div>
        <h1>Activity Log</h1>
        <p>Full audit trail of all system actions</p>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(3,1fr); margin-bottom: 24px;">
    <div class="stat-card blue">
        <div class="stat-label">Total Events</div>
        <div class="stat-value"><?= number_format($totalCount) ?></div>
        <div class="stat-icon">📋</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">This Week</div>
        <div class="stat-value"><?= number_format($weekCount) ?></div>
        <div class="stat-icon">📅</div>
    </div>
    <div class="stat-card gold">
        <div class="stat-label">Today</div>
        <div class="stat-value"><?= number_format($todayCount) ?></div>
        <div class="stat-icon">⚡</div>
    </div>
</div>

<!-- Filters -->
<form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
    <select name="action_filter" class="form-control" style="width:200px">
        <option value="">All Actions</option>
        <?php while ($a = $actions->fetch_assoc()): ?>
            <?php $meta = $actionMeta[$a['action']] ?? ['•', $a['action'], '']; ?>
            <option value="<?= sanitize($a['action']) ?>" <?= $filterAction===$a['action']?'selected':'' ?>>
                <?= $meta[0] ?> <?= $meta[1] ?>
            </option>
        <?php endwhile; ?>
    </select>
    <div class="search-bar" style="flex:1;max-width:300px">
        <span>🔍</span>
        <input type="text" name="user_filter" placeholder="Filter by user name or email…"
               value="<?= sanitize($filterUser) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if ($filterAction || $filterUser): ?>
        <a href="activity_log.php" class="btn btn-ghost">Clear</a>
    <?php endif; ?>
    <a href="reports.php?export=activity" class="btn btn-ghost" style="margin-left:auto">⬇️ Export CSV</a>
</form>

<!-- Log Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Events</h3>
        <span style="font-size:.85rem;color:var(--ink-muted)">
            <?= number_format($total) ?> total • showing <?= $offset+1 ?>–<?= min($offset+$perPage,$total) ?>
        </span>
    </div>

    <?php if ($logs->num_rows === 0): ?>
        <div class="empty-state"><div class="empty-icon">📭</div><p>No log entries found.</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($log = $logs->fetch_assoc()):
                $meta = $actionMeta[$log['action']] ?? ['📌', $log['action'], 'badge-gray'];
            ?>
            <tr>
                <td style="font-size:.8rem;white-space:nowrap;color:var(--ink-muted);font-family:var(--font-mono)">
                    <?= date('M d, Y', strtotime($log['created_at'])) ?><br>
                    <?= date('H:i:s', strtotime($log['created_at'])) ?>
                </td>
                <td>
                    <?php if ($log['user_name']): ?>
                        <strong><?= sanitize($log['user_name']) ?></strong><br>
                        <small style="color:var(--ink-muted)"><?= sanitize($log['email']) ?></small>
                        <?php if ($log['role']): ?>
                            <br><span class="badge <?= $log['role']==='admin'?'badge-warning':'badge-info' ?>" style="font-size:.65rem"><?= $log['role'] ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:var(--ink-muted);font-size:.85rem">System</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge <?= $meta[2] ?>" style="font-size:.75rem;">
                        <?= $meta[0] ?> <?= $meta[1] ?>
                    </span>
                </td>
                <td style="font-size:.85rem;color:var(--ink-soft);max-width:320px;word-break:break-word">
                    <?= sanitize($log['details'] ?? '—') ?>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:center;align-items:center;gap:6px;padding:16px;flex-wrap:wrap">
        <?php
        $qs = http_build_query(array_filter(['action_filter'=>$filterAction,'user_filter'=>$filterUser]));
        $qs = $qs ? '&'.$qs : '';
        ?>
        <?php if ($page > 1): ?>
            <a href="activity_log.php?page=<?= $page-1 ?><?= $qs ?>" class="btn btn-ghost btn-sm">← Prev</a>
        <?php endif; ?>
        <?php
        $start = max(1, $page-2); $end = min($totalPages, $page+2);
        if ($start > 1) echo '<span style="color:var(--ink-muted)">…</span>';
        for ($p=$start; $p<=$end; $p++):
        ?>
            <a href="activity_log.php?page=<?= $p ?><?= $qs ?>"
               class="btn btn-sm <?= $p===$page?'btn-primary':'btn-ghost' ?>"
               style="min-width:36px;justify-content:center"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($end < $totalPages) echo '<span style="color:var(--ink-muted)">…</span>'; ?>
        <?php if ($page < $totalPages): ?>
            <a href="activity_log.php?page=<?= $page+1 ?><?= $qs ?>" class="btn btn-ghost btn-sm">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php $conn->close(); require_once 'includes/footer.php'; ?>
