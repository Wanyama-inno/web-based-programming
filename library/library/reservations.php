<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireLogin();
checkOverdueBooks();

$conn = getDBConnection();
$user = getCurrentUser();

// Handle new reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reserve') {
    $bookId = intval($_POST['book_id'] ?? 0);

    // Book must exist
    $stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();

    if (!$book) {
        setFlash('error', 'Book not found.');
    } elseif ($book['available_copies'] > 0) {
        setFlash('warning', 'This book is currently available — you can borrow it directly!');
        header('Location: borrow.php?book_id=' . $bookId); exit();
    } else {
        // Check for duplicate reservation
        $stmt = $conn->prepare("SELECT id FROM reservations WHERE user_id = ? AND book_id = ? AND status = 'Pending'");
        $stmt->execute([$user['id'], $bookId]);
        $exists = $stmt->fetchColumn() !== false;

        if ($exists) {
            setFlash('warning', 'You already have a pending reservation for this book.');
        } else {
            $expires = date('Y-m-d', strtotime('+7 days'));
            $stmt = $conn->prepare("INSERT INTO reservations (user_id, book_id, expires_at, status) VALUES (?, ?, ?, 'Pending')");
            $stmt->execute([$user['id'], $bookId, $expires])
                ? setFlash('success', 'Reservation placed! We\'ll notify you when the book becomes available (expires in 7 days).')
                : setFlash('error', 'Failed to place reservation.');
        }
    }
    header('Location: reservations.php'); exit();
}

// Handle cancel reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $resId = intval($_POST['res_id'] ?? 0);
    if (isAdmin()) {
        $stmt = $conn->prepare("UPDATE reservations SET status = 'Cancelled' WHERE id = ?");
        $stmt->execute([$resId]);
    } else {
        $stmt = $conn->prepare("UPDATE reservations SET status = 'Cancelled' WHERE id = ? AND user_id = ?");
        $stmt->execute([$resId, $user['id']]);
    }
    $stmt->rowCount() ? setFlash('success', 'Reservation cancelled.') : setFlash('error', 'Failed to cancel.');
    header('Location: reservations.php'); exit();
}

// Pre-select book from URL
$preBook = null;
$bookId  = intval($_GET['book_id'] ?? 0);
if ($bookId > 0) {
    $stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->execute([$bookId]);
    $preBook = $stmt->fetch();
}

// Fetch reservations
if (isAdmin()) {
    $reservations = $conn->query("
        SELECT r.*, u.name as user_name, b.title, b.author, b.available_copies
        FROM reservations r
        JOIN users u ON r.user_id=u.id
        JOIN books b ON r.book_id=b.id
        ORDER BY r.reservation_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    // Stats
    $totalPending   = $conn->query("SELECT COUNT(*) c FROM reservations WHERE status='Pending'")->fetch()['c'];
    $totalFulfilled = $conn->query("SELECT COUNT(*) c FROM reservations WHERE status='Fulfilled'")->fetch()['c'];
    $totalCancelled = $conn->query("SELECT COUNT(*) c FROM reservations WHERE status IN ('Cancelled','Expired')")->fetch()['c'];
} else {
    $uid = $user['id'];
    $reservations = $conn->query("
        SELECT r.*, b.title, b.author, b.available_copies, b.cover_color
        FROM reservations r
        JOIN books b ON r.book_id = b.id
        WHERE r.user_id = $uid
        ORDER BY r.reservation_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// All unavailable books for dropdown
$unavailBooks = $conn->query("SELECT id, title, author FROM books WHERE available_copies = 0 ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Reservations';
require_once 'includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Book Reservations</h1>
        <p><?= isAdmin() ? 'Manage all reservation requests' : 'Reserve unavailable books and be notified first' ?></p>
    </div>
</div>

<?php if(isAdmin()): ?>
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
    <div class="stat-card gold"><div class="stat-label">Pending</div><div class="stat-value"><?= $totalPending ?></div><div class="stat-icon">🔖</div></div>
    <div class="stat-card green"><div class="stat-label">Fulfilled</div><div class="stat-value"><?= $totalFulfilled ?></div><div class="stat-icon">✅</div></div>
    <div class="stat-card red"><div class="stat-label">Cancelled/Expired</div><div class="stat-value"><?= $totalCancelled ?></div><div class="stat-icon">❌</div></div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:<?= isAdmin()?'1fr':'1fr 1fr' ?>;gap:24px;align-items:start">

    <?php if(!isAdmin()): ?>
    <!-- Reserve Form (student) -->
    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><h3 class="card-title">🔖 Place a Reservation</h3></div>
            <div class="card-body">
                <?php if(count($unavailBooks) === 0): ?>
                    <div style="text-align:center;padding:20px;color:var(--ink-muted)">
                        <div style="font-size:2rem;margin-bottom:8px">✅</div>
                        <p>All books are currently available — no reservations needed!</p>
                        <a href="books.php" class="btn btn-primary" style="margin-top:12px">Browse &amp; Borrow</a>
                    </div>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="reserve">
                    <div class="form-group">
                        <label class="form-label">Select Unavailable Book</label>
                        <select name="book_id" class="form-control" required>
                            <option value="">— Choose a book to reserve —</option>
                            <?php foreach($unavailBooks as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $preBook && $preBook['id']==$b['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($b['title']) ?> — <?= sanitize($b['author']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="background:var(--warning-light);border-radius:var(--radius);padding:12px;font-size:.85rem;color:#92400E;margin-bottom:16px">
                        ℹ️ Reservations expire after <strong>7 days</strong>. When a copy becomes available, your reservation is automatically fulfilled and you can borrow it.
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%">🔖 Reserve This Book</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">ℹ️ How Reservations Work</h3></div>
            <div class="card-body" style="font-size:.875rem;display:flex;flex-direction:column;gap:10px">
                <div style="display:flex;gap:10px"><span>1️⃣</span><span>Find a book that's currently all checked out</span></div>
                <div style="display:flex;gap:10px"><span>2️⃣</span><span>Place a reservation — it's valid for 7 days</span></div>
                <div style="display:flex;gap:10px"><span>3️⃣</span><span>When someone returns a copy, your reservation is marked Fulfilled</span></div>
                <div style="display:flex;gap:10px"><span>4️⃣</span><span>Visit the library to borrow your reserved book</span></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Reservations List -->
    <div class="card" <?= isAdmin() ? '' : '' ?>>
        <div class="card-header">
            <h3 class="card-title"><?= isAdmin() ? 'All Reservations' : 'My Reservations' ?></h3>
            <span style="font-size:.85rem;color:var(--ink-muted)"><?= number_format(count($reservations)) ?> total</span>
        </div>
        <?php if(count($reservations) === 0): ?>
            <div class="empty-state"><div class="empty-icon">🔖</div><p>No reservations yet.</p></div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                        <?php if(isAdmin()): ?><th>Student</th><?php endif; ?>
                        <th>Book</th>
                        <th>Reserved</th>
                        <th>Expires</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($reservations as $r): ?>
                <tr>
                    <?php if(isAdmin()): ?>
                        <td><strong><?= sanitize($r['user_name']) ?></strong></td>
                    <?php endif; ?>
                    <td>
                        <strong><?= sanitize($r['title']) ?></strong><br>
                        <small style="color:var(--ink-muted)"><?= sanitize($r['author']) ?></small>
                        <?php if($r['available_copies'] > 0 && $r['status']==='Pending'): ?>
                            <br><span style="font-size:.75rem;color:var(--success)">✅ Now available!</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.82rem"><?= date('M d, Y', strtotime($r['reservation_date'])) ?></td>
                    <td style="font-size:.82rem"><?= date('M d, Y', strtotime($r['expires_at'])) ?></td>
                    <td>
                        <span class="badge <?=
                            $r['status']==='Pending'    ? 'badge-warning' :
                           ($r['status']==='Fulfilled'  ? 'badge-success' :
                           ($r['status']==='Expired'    ? 'badge-gray'    : 'badge-danger'))
                        ?>"><?= $r['status'] ?></span>
                    </td>
                    <td>
                        <?php if($r['status']==='Pending'): ?>
                            <?php if(!isAdmin() && $r['available_copies'] > 0): ?>
                                <a href="borrow.php?book_id=<?= $r['book_id'] ?>" class="btn btn-success btn-sm">Borrow now</a>
                            <?php else: ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="res_id" value="<?= $r['id'] ?>">
                                    <button class="btn btn-danger btn-sm" data-confirm="Cancel this reservation?">Cancel</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:var(--ink-muted);font-size:.8rem">—</span>
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

    <div style="display:grid;grid-template-columns:<?= isAdmin()?'1fr':'1fr 1fr' ?>;gap:24px;align-items:start">
