<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$conn = getDBConnection();

// Handle delete user
if (isset($_GET['delete']) && intval($_GET['delete']) > 0) {
    $delId = intval($_GET['delete']);
    if ($delId === $_SESSION['user_id']) {
        setFlash('error', 'You cannot delete your own account.');
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
        $ok = $stmt->execute([':id' => $delId]);
        $ok ? setFlash('success', 'User deleted.') : setFlash('error', 'Failed to delete user.');
    }
    header('Location: users.php'); exit();
}

// Handle role toggle
if (isset($_GET['toggle_role']) && intval($_GET['toggle_role']) > 0) {
    $tid = intval($_GET['toggle_role']);
    $stmt = $conn->prepare("UPDATE users SET role = IF(role='admin','student','admin') WHERE id = :id");
    $stmt->execute([':id' => $tid]);
    setFlash('success', 'User role updated.');
    header('Location: users.php'); exit();
}

$search = trim($_GET['search'] ?? '');
$sql = "SELECT u.*, COUNT(br.id) as total_borrows, SUM(br.status IN ('Borrowed','Overdue')) as active_borrows FROM users u LEFT JOIN borrow_records br ON u.id=br.user_id";
$params = [];
if ($search) {
    $sql .= " WHERE u.name LIKE :like OR u.email LIKE :like";
    $params[':like'] = "%$search%";
}
$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
$userCount = count($users);

$totalUsers   = $conn->query("SELECT COUNT(*) c FROM users")->fetch(PDO::FETCH_ASSOC)['c'];
$totalStudents= $conn->query("SELECT COUNT(*) c FROM users WHERE role='student'")->fetch(PDO::FETCH_ASSOC)['c'];
$totalAdmins  = $conn->query("SELECT COUNT(*) c FROM users WHERE role='admin'")->fetch(PDO::FETCH_ASSOC)['c'];

$pageTitle = 'Manage Users';
require_once 'includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>User Management</h1>
        <p>View and manage all registered library members</p>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
    <div class="stat-card blue"><div class="stat-label">Total Users</div><div class="stat-value"><?= $totalUsers ?></div><div class="stat-icon">👤</div></div>
    <div class="stat-card green"><div class="stat-label">Students</div><div class="stat-value"><?= $totalStudents ?></div><div class="stat-icon">🎓</div></div>
    <div class="stat-card gold"><div class="stat-label">Admins</div><div class="stat-value"><?= $totalAdmins ?></div><div class="stat-icon">⚙️</div></div>
</div>

<!-- Search -->
<form method="GET" style="display:flex;gap:10px;margin-bottom:20px">
    <div class="search-bar" style="flex:1;max-width:400px">
        <span>🔍</span>
        <input type="text" name="search" placeholder="Search by name or email..." value="<?= sanitize($search) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if($search): ?><a href="users.php" class="btn btn-ghost">Clear</a><?php endif; ?>
</form>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">All Users</h3>
        <span style="font-size:.85rem;color:var(--ink-muted)"><?= $userCount ?> result<?= $userCount !== 1 ? 's' : '' ?></span>
    </div>
    <?php if ($userCount === 0): ?>
        <div class="empty-state"><div class="empty-icon">👥</div><p>No users found.</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Active Loans</th>
                    <th>Total Borrows</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td style="font-family:var(--font-mono);font-size:.8rem;color:var(--ink-muted)"><?= $u['id'] ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px">
                        <div style="width:30px;height:30px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;flex-shrink:0">
                            <?= strtoupper(substr($u['name'],0,2)) ?>
                        </div>
                        <strong><?= sanitize($u['name']) ?></strong>
                        <?php if($u['id'] == $_SESSION['user_id']): ?><span class="badge badge-info">You</span><?php endif; ?>
                    </div>
                </td>
                <td style="font-size:.875rem"><?= sanitize($u['email']) ?></td>
                <td>
                    <span class="badge <?= $u['role']==='admin'?'badge-warning':'badge-info' ?>">
                        <?= $u['role'] ?>
                    </span>
                </td>
                <td style="text-align:center;font-weight:600;color:<?= $u['active_borrows']>0?'var(--accent)':'var(--ink-muted)' ?>">
                    <?= intval($u['active_borrows']) ?>
                </td>
                <td style="text-align:center;color:var(--ink-muted)"><?= intval($u['total_borrows']) ?></td>
                <td style="font-size:.82rem;color:var(--ink-muted)"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:6px">
                        <?php if($u['id'] !== $_SESSION['user_id']): ?>
                            <a href="users.php?toggle_role=<?= $u['id'] ?>"
                               class="btn btn-warning btn-sm"
                               data-confirm="Toggle role for <?= sanitize($u['name']) ?>?">
                               ↔ Role
                            </a>
                            <a href="users.php?delete=<?= $u['id'] ?>"
                               class="btn btn-danger btn-sm"
                               data-confirm="Permanently delete user <?= sanitize($u['name']) ?>? This will also delete their borrow records.">
                               🗑
                            </a>
                        <?php else: ?>
                            <span style="font-size:.78rem;color:var(--ink-muted)">Current user</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
