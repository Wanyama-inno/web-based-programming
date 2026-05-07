<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getCurrentUser();
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $name        = trim($_POST['name'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $studentId   = trim($_POST['student_id'] ?? '');
$avatarColor = trim($_POST['avatar_color'] ?? '#FFFF00');

        if (empty($name)) {
            setFlash('error', 'Name cannot be empty.');
        } else {
            $stmt = $conn->prepare(
                "UPDATE users SET name=:name, phone=:phone, student_id=:student_id, avatar_color=:avatar_color WHERE id=:id"
            );
            $ok = $stmt->execute([
                ':name' => $name,
                ':phone' => $phone,
                ':student_id' => $studentId,
                ':avatar_color' => $avatarColor,
                ':id' => $user['id'],
            ]);

            if ($ok) {
                $_SESSION['user_name'] = $name;
                logActivity((int)$user['id'], 'profile_update', 'Profile details updated');
                setFlash('success', 'Profile updated successfully!');
            } else {
                setFlash('error', 'Failed to update profile.');
            }
        }
    } elseif ($_POST['action'] === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $conn->prepare("SELECT password FROM users WHERE id=:id");
        $stmt->execute([':id' => $user['id']]);
        $row = $stmt->fetch();

        if (!$row || empty($row['password']) || !hash_equals((string)$row['password'], (string)$current)) {

            setFlash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            setFlash('error', 'New password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            setFlash('error', 'New passwords do not match.');
        } else {
            // Store raw password (no hashing)
            $stmt = $conn->prepare("UPDATE users SET password=:password WHERE id=:id");
            $stmt->execute([':password' => $new, ':id' => $user['id']]);

            logActivity((int)$user['id'], 'password_change', 'Password changed');
            setFlash('success', 'Password changed successfully!');
        }
    }
    header('Location: profile.php'); exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id=:id");
$stmt->execute([':id' => $user['id']]);
$profile = $stmt->fetch();
$avatarColor = $profile['avatar_color'] ?? '#FFFF00';

$activeBorrowed = (int)($conn->query(
    "SELECT COUNT(*) AS c FROM borrow_records WHERE user_id={$user['id']} AND status IN ('Borrowed','Overdue')"
)->fetch()['c'] ?? 0);


$totalFines = (float)$conn->query(
    "SELECT COALESCE(SUM(fine_amount),0) AS t FROM borrow_records WHERE user_id={$user['id']}"
)->fetch()['t'];


$unpaidFines = (float)$conn->query(
    "SELECT COALESCE(SUM(fine_amount),0) AS t FROM borrow_records WHERE user_id={$user['id']} AND fine_paid=0 AND fine_amount>0 AND status='Returned'"
)->fetch()['t'] ?? 0.0;


$recentActivity = $conn->query(
    "SELECT action, details, created_at FROM activity_log WHERE user_id={$user['id']} ORDER BY created_at DESC LIMIT 8"
);
$borrowHistory  = $conn->query(
    "SELECT br.*, b.title, b.author, b.cover_color FROM borrow_records br JOIN books b ON br.book_id=b.id WHERE br.user_id={$user['id']} ORDER BY br.borrow_date DESC LIMIT 5"
);

// PDO doesn't provide mysqli num_rows/fetch_assoc; we normalize to arrays for templates
$recentActivityRows = $recentActivity ? $recentActivity->fetchAll(PDO::FETCH_ASSOC) : [];
$borrowHistoryRows  = $borrowHistory ? $borrowHistory->fetchAll(PDO::FETCH_ASSOC) : [];

$totalBorrowed = (int)$conn->query(
    "SELECT COUNT(*) AS c FROM borrow_records WHERE user_id={$user['id']}"
)->fetch()['c'];

$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/header.php';
?>


<div class="page-header">
    <div class="page-header-left">
        <h1>My Profile</h1>
        <p>Manage your account settings and view your library history</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:300px 1fr;gap:24px;align-items:start">

    <div style="display:flex;flex-direction:column;gap:20px">
        <div class="card">
            <div class="card-body" style="text-align:center;padding:32px 24px">
                <div style="width:80px;height:80px;border-radius:50%;background:<?= sanitize($avatarColor) ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:600;margin:0 auto 16px;box-shadow:0 4px 16px <?= sanitize($avatarColor) ?>60">
                    <?= strtoupper(substr($profile['name'],0,2)) ?>
                </div>
                <h2 style="font-family:var(--font-serif);font-size:1.3rem;margin-bottom:4px"><?= sanitize($profile['name']) ?></h2>
                <p style="color:var(--ink-muted);font-size:.875rem;margin-bottom:12px"><?= sanitize($profile['email']) ?></p>
                <span class="badge <?= $profile['role']==='admin'?'badge-warning':'badge-info' ?>" style="font-size:.8rem;padding:4px 12px">
                    <?= $profile['role'] === 'admin' ? '⚙️ Admin' : '🎓 Student' ?>
                </span>
                <?php if(!empty($profile['student_id'])): ?>
                    <p style="font-size:.8rem;color:var(--ink-muted);margin-top:8px;font-family:var(--font-mono)">ID: <?= sanitize($profile['student_id']) ?></p>
                <?php endif; ?>
                <?php if(!empty($profile['phone'])): ?>
                    <p style="font-size:.85rem;color:var(--ink-soft);margin-top:4px">📞 <?= sanitize($profile['phone']) ?></p>
                <?php endif; ?>
                <p style="font-size:.78rem;color:var(--ink-muted);margin-top:10px">Member since <?= date('F Y', strtotime($profile['created_at'])) ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">📊 My Stats</h3></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:14px;padding:16px 20px">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:.875rem;color:var(--ink-muted)">Total Borrowed</span>
                    <strong style="font-family:var(--font-mono)"><?= $totalBorrowed ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:.875rem;color:var(--ink-muted)">Currently Active</span>
                    <strong style="font-family:var(--font-mono);color:var(--accent)"><?= $activeBorrowed ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:.875rem;color:var(--ink-muted)">Total Fines Incurred</span>
                    <strong style="font-family:var(--font-mono);color:<?= $totalFines>0?'var(--danger)':'var(--success)' ?>">$<?= number_format($totalFines,2) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:.875rem;color:var(--ink-muted)">Unpaid Fines</span>
                    <strong style="font-family:var(--font-mono);color:<?= $unpaidFines>0?'var(--danger)':'var(--success)' ?>">$<?= number_format($unpaidFines,2) ?></strong>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">🕒 Recent Activity</h3></div>
            <?php
            $actionLabels = [
                'borrow'=>['⬇️','Borrowed a book'],
                'return'=>['⬆️','Returned a book'],
                'profile_update'=>['✏️','Updated profile'],
                'password_change'=>['🔐','Changed password'],
                'reservation'=>['🔖','Made a reservation'],
                'reservation_cancel'=>['❌','Cancelled reservation'],
            ];
if(count($recentActivityRows) === 0): ?>
                <div class="empty-state" style="padding:20px"><p>No activity yet</p></div>
            <?php else: foreach($recentActivityRows as $a):
                $lbl = $actionLabels[$a['action']] ?? ['📋', $a['action']]; ?>
                <div style="padding:10px 16px;border-bottom:1px solid var(--border);font-size:.82rem">
                    <div style="display:flex;gap:8px;align-items:flex-start">
                        <span style="flex-shrink:0"><?= $lbl[0] ?></span>
                        <div>
                            <div style="color:var(--ink-soft)"><?= $a['details'] ? sanitize($a['details']) : $lbl[1] ?></div>
                            <div style="color:var(--ink-muted);font-size:.75rem"><?= date('M d, Y g:i A', strtotime($a['created_at'])) ?></div>
                        </div>
                    </div>
                </div>
<?php endforeach; endif; ?>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:20px">
        <div class="card">
            <div class="card-header"><h3 class="card-title">✏️ Edit Profile</h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-grid">
                        <div class="form-group" style="grid-column:1/-1">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" value="<?= sanitize($profile['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" value="<?= sanitize($profile['email']) ?>" disabled style="opacity:.6;cursor:not-allowed">
                            <div class="form-hint">Email cannot be changed</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" placeholder="+256 700 000000" value="<?= sanitize($profile['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Student / Staff ID</label>
                            <input type="text" name="student_id" class="form-control" placeholder="e.g. STU-2024-001" value="<?= sanitize($profile['student_id'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Avatar Color</label>
                            <div style="display:flex;gap:10px;align-items:center">
                                <input type="color" name="avatar_color" value="<?= sanitize($avatarColor) ?>" style="width:48px;height:38px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;padding:2px">
                                <span style="font-size:.85rem;color:var(--ink-muted)">Personalise your avatar colour</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">🔐 Change Password</h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label class="form-label">Current Password *</label>
                        <input type="password" name="current_password" class="form-control" placeholder="Enter your current password" required>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">New Password *</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Min. 6 characters" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password *</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-warning">🔑 Update Password</button>

                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📚 Recent Borrowing</h3>
                <a href="dashboard.php" class="btn btn-ghost btn-sm">View all</a>
            </div>
<?php if(count($borrowHistoryRows) === 0): ?>
                <div class="empty-state" style="padding:28px"><div class="empty-icon">📖</div><p>No borrowing history yet</p></div>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Book</th><th>Borrowed</th><th>Due</th><th>Status</th><th>Fine</th></tr></thead>
                    <tbody>
                    <?php foreach($borrowHistoryRows as $h): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <div style="width:28px;height:36px;border-radius:4px;background:<?= sanitize($h['cover_color']) ?>30;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0">📖</div>
                                <div><strong><?= sanitize($h['title']) ?></strong><br><small style="color:var(--ink-muted)"><?= sanitize($h['author']) ?></small></div>
                            </div>
                        </td>
                        <td style="font-size:.82rem"><?= date('M d, Y', strtotime($h['borrow_date'])) ?></td>
                        <td style="font-size:.82rem"><?= date('M d, Y', strtotime($h['due_date'])) ?></td>
                        <td><span class="badge <?= $h['status']==='Returned'?'badge-success':($h['status']==='Overdue'?'badge-danger':'badge-info') ?>"><?= $h['status'] ?></span></td>
                        <td style="font-family:var(--font-mono);font-size:.82rem;color:<?= $h['fine_amount']>0?'var(--danger)':'var(--ink-muted)' ?>">
                            <?= $h['fine_amount']>0 ? '$'.number_format($h['fine_amount'],2) : '—' ?>
                        </td>
                    </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

