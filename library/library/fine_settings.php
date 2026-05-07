<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$conn = getDBConnection();

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_settings') {
    $rate      = max(0, (float)($_POST['rate_per_day'] ?? 0.50));
    $maxFine   = max(0, (float)($_POST['max_fine']     ?? 50.00));
    $graceDays = max(0, (int)($_POST['grace_days']     ?? 0));

    $stmt = $conn->prepare("UPDATE fine_settings SET daily_fine = :rate, max_fine = :max, grace_period_days = :grace WHERE id = 1");
    $ok = $stmt->execute([':rate' => $rate, ':max' => $maxFine, ':grace' => $graceDays]);
    if ($ok) {
        // Recalculate all outstanding fines
        $conn->query("UPDATE borrow_records
            SET fine_amount = GREATEST(0, LEAST(
                DATEDIFF(CURDATE(), due_date) - $graceDays, 9999
            ) * $rate)
            WHERE status='Overdue' AND fine_paid=0");
        setFlash('success', 'Fine settings updated and all overdue fines recalculated.');
        logActivity($_SESSION['user_id'], 'fine_settings_updated',
            "Rate: \${$rate}/day, Max: \${$maxFine}, Grace: {$graceDays} days");
    } else {
        setFlash('error', 'Failed to update settings.');
    }
    header('Location: fine_settings.php'); exit();
}

// Handle mark fine as paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_paid') {
    $recId = intval($_POST['record_id'] ?? 0);
    $stmt  = $conn->prepare("UPDATE borrow_records SET fine_paid=1 WHERE id = :id");
    $ok = $stmt->execute([':id' => $recId]);
    if ($ok) {
        setFlash('success', 'Fine marked as paid.');
        logActivity($_SESSION['user_id'], 'fine_paid', "Record #$recId");
    } else {
        setFlash('error', 'Failed to update fine.');
    }
    header('Location: fine_settings.php'); exit();
}

// Handle waive fine
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'waive') {
    $recId = intval($_POST['record_id'] ?? 0);
    $stmt  = $conn->prepare("UPDATE borrow_records SET fine_amount=0, fine_paid=1 WHERE id = :id");
    $ok = $stmt->execute([':id' => $recId]);
    if ($ok) {
        setFlash('success', 'Fine waived successfully.');
        logActivity($_SESSION['user_id'], 'fine_waived', "Record #$recId");
    } else {
        setFlash('error', 'Failed to waive fine.');
    }
    header('Location: fine_settings.php'); exit();
}

$settings = db_fetch_one($conn, "SELECT daily_fine, max_fine, grace_period_days FROM fine_settings ORDER BY id DESC LIMIT 1");
$settings = $settings ?? ['daily_fine' => 0.50, 'max_fine' => 50.00, 'grace_period_days' => 7];

// Fines summary
$totalUnpaid   = $conn->query("SELECT COALESCE(SUM(fine_amount),0) s FROM borrow_records WHERE fine_paid=0 AND fine_amount>0")->fetch()['s'];

$totalPaid     = $conn->query("SELECT COALESCE(SUM(fine_amount),0) s FROM borrow_records WHERE fine_paid=1 AND fine_amount>0")->fetch()['s'];
$countUnpaid   = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE fine_paid=0 AND fine_amount>0")->fetch()['c'];
$countPaid     = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE fine_paid=1 AND fine_amount>0")->fetch()['c'];

// Records with fines
$fineFilter = $_GET['filter'] ?? 'unpaid';
$whereClause = $fineFilter === 'paid'
    ? "br.fine_paid=1 AND br.fine_amount>0"
    : ($fineFilter === 'all' ? "br.fine_amount>0" : "br.fine_paid=0 AND br.fine_amount>0");

$fineRecords = $conn->query("
    SELECT br.*, u.name AS user_name, u.email, b.title AS book_title, b.author,
           DATEDIFF(COALESCE(br.return_date, CURDATE()), br.due_date) AS days_late
    FROM borrow_records br
    JOIN users u ON br.user_id = u.id
    JOIN books  b ON br.book_id = b.id
    WHERE $whereClause
    ORDER BY br.fine_amount DESC, br.due_date ASC
");

$pageTitle = 'Fine Management';
require_once 'includes/header.php';
?>

<style>
.settings-form-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.fine-tab { padding: 7px 18px; border-radius: 7px; font-size: .875rem; font-weight: 500;
            text-decoration: none; color: var(--ink-muted); border: 1px solid var(--border);
            background: var(--surface); transition: all .15s; }
.fine-tab.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.fine-tab:hover:not(.active) { background: var(--surface-3); }
</style>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb"><a href="dashboard.php">Dashboard</a> › Fine Management</div>
        <h1>Fine Management</h1>
        <p>Configure late-return fines and track payments</p>
    </div>
</div>

<!-- KPIs -->
<div class="stats-grid" style="grid-template-columns: repeat(4,1fr); margin-bottom: 24px;">
    <div class="stat-card red">
        <div class="stat-label">Unpaid Fines</div>
        <div class="stat-value"><?= formatMoney($totalUnpaid) ?></div>
        <div class="stat-icon">💸</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Fines Collected</div>
        <div class="stat-value"><?= formatMoney($totalPaid) ?></div>
        <div class="stat-icon">💵</div>
    </div>
    <div class="stat-card gold">
        <div class="stat-label">Unpaid Records</div>
        <div class="stat-value"><?= $countUnpaid ?></div>
        <div class="stat-icon">📋</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Paid Records</div>
        <div class="stat-value"><?= $countPaid ?></div>
        <div class="stat-icon">✅</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 320px 1fr; gap: 24px; align-items: start;">

    <!-- Settings Panel -->
    <div style="display: flex; flex-direction: column; gap: 16px;">

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">⚙️ Fine Settings</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_settings">

                    <div class="form-group">
                        <label class="form-label">Fine Rate (per day)</label>
                        <div style="position: relative;">
                            <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--ink-muted);font-weight:600">$</span>
                            <input type="number" name="rate_per_day" class="form-control"
                                   style="padding-left: 28px;"
                                   step="0.01" min="0" max="100"
                                   value="<?= number_format($settings['daily_fine'], 2) ?>">
                        </div>
                        <div class="form-hint">Charged per day after due date</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Maximum Fine Cap</label>
                        <div style="position: relative;">
                            <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--ink-muted);font-weight:600">$</span>
                            <input type="number" name="max_fine" class="form-control"
                                   style="padding-left: 28px;"
                                   step="0.50" min="0"
                                   value="<?= number_format($settings['max_fine'], 2) ?>">
                        </div>
                        <div class="form-hint">Fine will never exceed this amount per book</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Grace Period (days)</label>
                        <input type="number" name="grace_days" class="form-control"
                               min="0" max="30"
                               value="<?= intval($settings['grace_period_days']) ?>">
                        <div class="form-hint">Days after due date before fines begin</div>
                    </div>

                    <!-- Live Preview -->
                    <div style="background: var(--surface-2); border-radius: var(--radius); padding: 14px; margin-bottom: 16px; font-size: .82rem;">
                        <div style="font-weight: 600; margin-bottom: 8px; color: var(--ink);">📊 Fine Preview</div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span style="color: var(--ink-muted);">7 days late</span>
                            <span id="prev7" style="font-family: var(--font-mono); font-weight: 600;"></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span style="color: var(--ink-muted);">14 days late</span>
                            <span id="prev14" style="font-family: var(--font-mono); font-weight: 600;"></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--ink-muted);">30 days late</span>
                            <span id="prev30" style="font-family: var(--font-mono); font-weight: 600;"></span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;"
                            data-confirm="This will recalculate ALL outstanding fines. Proceed?">
                        💾 Save &amp; Recalculate
                    </button>
                </form>
            </div>
        </div>

        <!-- Current policy summary -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">📜 Current Policy</h3></div>
            <div class="card-body" style="font-size: .875rem; display: flex; flex-direction: column; gap: 10px; color: var(--ink-soft);">
                <div>💰 <strong><?= formatMoney($settings['daily_fine']) ?></strong> per day after due date</div>
                <div>🔒 Maximum fine capped at <strong><?= formatMoney($settings['max_fine']) ?></strong> per book</div>
                <div>⏳ <strong><?= $settings['grace_period_days'] ?> day<?= $settings['grace_period_days'] != 1 ? 's' : '' ?></strong> grace period</div>
                <div>📅 Loan period: <strong>14 days</strong></div>
            </div>
        </div>
    </div>

    <!-- Fine Records Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Fine Records</h3>
            <div style="display: flex; gap: 8px;">
                <a href="fine_settings.php?filter=unpaid" class="fine-tab <?= $fineFilter==='unpaid'?'active':'' ?>">Unpaid</a>
                <a href="fine_settings.php?filter=paid"   class="fine-tab <?= $fineFilter==='paid'  ?'active':'' ?>">Paid</a>
                <a href="fine_settings.php?filter=all"    class="fine-tab <?= $fineFilter==='all'   ?'active':'' ?>">All</a>
            </div>
        </div>

<?php if ($fineRecords === false || $fineRecords->rowCount() === 0): ?>
            <div class="empty-state">
                <div class="empty-icon">🎉</div>
                <p><?= $fineFilter === 'unpaid' ? 'No unpaid fines — great job!' : 'No records in this category.' ?></p>
            </div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Book</th>
                        <th>Due Date</th>
                        <th>Days Late</th>
                        <th>Fine</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($fineRecords as $r): ?>
                <tr>
                    <td>
                        <strong><?= sanitize($r['user_name']) ?></strong><br>
                        <small style="color:var(--ink-muted)"><?= sanitize($r['email']) ?></small>
                    </td>
                    <td>
                        <?= sanitize($r['book_title']) ?><br>
                        <small style="color:var(--ink-muted)"><?= sanitize($r['author']) ?></small>
                    </td>
                    <td style="font-size:.85rem"><?= date('M d, Y', strtotime($r['due_date'])) ?></td>
                    <td style="font-family:var(--font-mono);color:var(--danger);font-weight:600">
                        +<?= max(0, $r['days_late']) ?>d
                    </td>
                    <td style="font-family:var(--font-mono);font-weight:700;color:<?= $r['fine_paid']?'var(--success)':'var(--danger)' ?>">
                        <?= formatMoney($r['fine_amount']) ?>
                    </td>
                    <td>
                        <span class="badge <?= $r['fine_paid']?'badge-success':'badge-danger' ?>">
                            <?= $r['fine_paid'] ? 'Paid' : 'Unpaid' ?>
                        </span>
                        <?php if (!$r['fine_paid'] && $r['status'] === 'Overdue'): ?>
                            <br><span class="badge badge-danger" style="margin-top:3px">Overdue</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$r['fine_paid']): ?>
                        <div style="display:flex;gap:5px;flex-wrap:wrap">
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action"    value="mark_paid">
                                <input type="hidden" name="record_id" value="<?= $r['id'] ?>">
                                <button class="btn btn-success btn-sm"
                                        data-confirm="Mark <?= sanitize($r['user_name']) ?>'s fine of <?= formatMoney($r['fine_amount']) ?> as paid?">
                                    ✅ Paid
                                </button>
                            </form>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action"    value="waive">
                                <input type="hidden" name="record_id" value="<?= $r['id'] ?>">
                                <button class="btn btn-ghost btn-sm"
                                        data-confirm="Waive (forgive) this fine for <?= sanitize($r['user_name']) ?>?">
                                    🙏 Waive
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                            <span style="color:var(--ink-muted);font-size:.8rem">Settled</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updatePreviews() {
    const rate      = parseFloat(document.querySelector('[name="rate_per_day"]').value) || 0;
    const maxFine   = parseFloat(document.querySelector('[name="max_fine"]').value) || 0;
    const grace     = parseInt(document.querySelector('[name="grace_days"]').value) || 0;

    [7, 14, 30].forEach(days => {
        const charged  = Math.max(0, days - grace);
        const fine     = Math.min(charged * rate, maxFine);
        const el       = document.getElementById('prev' + days);
        if (el) el.textContent = '$' + fine.toFixed(2);
    });
}

// Run on load and on input change
document.querySelectorAll('[name="rate_per_day"],[name="max_fine"],[name="grace_days"]')
    .forEach(el => el.addEventListener('input', updatePreviews));
updatePreviews();
</script>

<?php require_once 'includes/footer.php'; ?>
