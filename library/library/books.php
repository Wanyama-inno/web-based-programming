<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$conn = getDBConnection();
$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$viewId   = intval($_GET['id'] ?? 0);

// View single book
$bookDetail = null;
if ($viewId > 0) {
    $stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->bind_param("i", $viewId);
    $stmt->execute();
    $bookDetail = $stmt->fetch();
    $stmt->close();
}

// Categories
$cats = $conn->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != '' ORDER BY category");

// Build query
$where = "1=1";
$params = [];
$types  = "";
if ($search) {
    $like = "%$search%";
    $where .= " AND (title LIKE ? OR author LIKE ? OR category LIKE ?)";
    $params = [$like, $like, $like];
    $types  = "sss";
}
if ($category) {
    $where .= " AND category = ?";
    $params[] = $category;
    $types   .= "s";
}

if ($types) {
    $stmt = $conn->prepare("SELECT * FROM books WHERE $where ORDER BY title ASC");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $books = $stmt;
} else {
    $books = $conn->query("SELECT * FROM books ORDER BY title ASC");
}

$pageTitle = 'Books';
require_once 'includes/header.php';

// Fetch all books and paginate
$allBooks   = [];
while($b = $books->fetch()) $allBooks[] = $b;
$totalBooks = count($allBooks);
$perPage    = 12;
$totalPages = max(1, ceil($totalBooks / $perPage));
$page       = max(1, min($totalPages, intval($_GET['page'] ?? 1)));
$offset     = ($page - 1) * $perPage;
$pagedBooks = array_slice($allBooks, $offset, $perPage);
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Book Collection</h1>
        <p><?= $totalBooks ?> book<?= $totalBooks !== 1 ? 's' : '' ?> found<?= $search ? " for \"" . sanitize($search) . '"' : '' ?></p>
    </div>
    <?php if(isAdmin()): ?>
        <a href="add_book.php" class="btn btn-primary">➕ Add Book</a>
    <?php endif; ?>
</div>

<!-- Search & Filter -->
<div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap">
    <form method="GET" style="display:flex;gap:10px;flex:1;min-width:260px">
        <div class="search-bar" style="flex:1">
            <span>🔍</span>
            <input type="text" name="search" placeholder="Search by title, author, or category..." value="<?= sanitize($search) ?>">
        </div>
        <select name="category" class="form-control" style="width:auto;min-width:140px">
            <option value="">All Categories</option>
            <?php while($c = $cats->fetch_assoc()): ?>
                <option value="<?= sanitize($c['category']) ?>" <?= $category===$c['category']?'selected':'' ?>>
                    <?= sanitize($c['category']) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if($search || $category): ?>
            <a href="books.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>
</div>

<?php if($bookDetail): ?>
<!-- Book Detail Modal-style view -->
<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <h3 class="card-title">Book Details</h3>
        <a href="books.php" class="btn btn-ghost btn-sm">← Back to list</a>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:24px;flex-wrap:wrap">
            <div style="width:120px;height:160px;background:<?= sanitize($bookDetail['cover_color']) ?>20;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:3rem;flex-shrink:0;border:1px solid var(--border)">📖</div>
            <div style="flex:1;min-width:200px">
                <h2 style="font-family:var(--font-serif);font-size:1.6rem;margin-bottom:4px"><?= sanitize($bookDetail['title']) ?></h2>
                <p style="color:var(--ink-muted);margin-bottom:12px">by <?= sanitize($bookDetail['author']) ?></p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
                    <?php if($bookDetail['category']): ?><span class="badge badge-info"><?= sanitize($bookDetail['category']) ?></span><?php endif; ?>
                    <span class="badge <?= $bookDetail['available_copies']>0?'badge-success':'badge-danger' ?>">
                        <?= $bookDetail['available_copies']>0 ? '✅ Available' : '❌ Unavailable' ?>
                    </span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;font-size:.875rem">
                    <div><span style="color:var(--ink-muted)">ISBN:</span> <span style="font-family:var(--font-mono)"><?= sanitize($bookDetail['isbn'] ?? '—') ?></span></div>
                    <div><span style="color:var(--ink-muted)">Quantity:</span> <?= $bookDetail['quantity'] ?> copies</div>
                    <div><span style="color:var(--ink-muted)">Available:</span> <?= $bookDetail['available_copies'] ?> copies</div>
                </div>
                <?php if($bookDetail['description']): ?>
                    <p style="font-size:.9rem;color:var(--ink-soft);line-height:1.7"><?= sanitize($bookDetail['description']) ?></p>
                <?php endif; ?>
                <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap">
                    <?php if(isLoggedIn() && $bookDetail['available_copies'] > 0): ?>
                        <a href="borrow.php?book_id=<?= $bookDetail['id'] ?>" class="btn btn-primary">⬇️ Borrow This Book</a>
                    <?php elseif(isLoggedIn() && $bookDetail['available_copies'] === 0): ?>
                        <a href="reservations.php?book_id=<?= $bookDetail['id'] ?>" class="btn btn-warning">🔖 Reserve This Book</a>
                    <?php elseif(!isLoggedIn()): ?>
                        <a href="login.php" class="btn btn-primary">🔐 Login to Borrow</a>
                    <?php endif; ?>
                    <?php if(isAdmin()): ?>
                        <a href="add_book.php?edit=<?= $bookDetail['id'] ?>" class="btn btn-warning">✏️ Edit</a>
                        <a href="add_book.php?delete=<?= $bookDetail['id'] ?>" class="btn btn-danger" data-confirm="Delete this book permanently?">🗑️ Delete</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Books Grid -->
<?php if(empty($pagedBooks)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon">📭</div>
            <p>No books found. <?php if($search || $category): ?><a href="books.php" style="color:var(--accent)">Clear filters</a><?php endif; ?></p>
        </div>
    </div>
<?php else: ?>
<div class="books-grid">
    <?php foreach($pagedBooks as $book): ?>
    <div class="book-card">
        <div class="book-cover" style="background:linear-gradient(135deg,<?= sanitize($book['cover_color']) ?>30,<?= sanitize($book['cover_color']) ?>60)">
            <span style="font-size:2rem">📖</span>
        </div>
        <div class="book-info">
            <div class="book-title"><?= sanitize($book['title']) ?></div>
            <div class="book-author">by <?= sanitize($book['author']) ?></div>
            <?php if($book['category']): ?>
                <span class="badge badge-info" style="font-size:.68rem"><?= sanitize($book['category']) ?></span>
            <?php endif; ?>
            <?php if($book['isbn']): ?>
                <div style="font-size:.75rem;font-family:var(--font-mono);color:var(--ink-muted)">ISBN: <?= sanitize($book['isbn']) ?></div>
            <?php endif; ?>
            <div class="book-meta">
                <span class="book-avail" style="color:<?= $book['available_copies']>0?'var(--success)':'var(--danger)' ?>">
                    <?= $book['available_copies'] ?>/<?= $book['quantity'] ?> copies
                </span>
                <span class="badge <?= $book['available_copies']>0?'badge-success':'badge-danger' ?>">
                    <?= $book['available_copies']>0?'Available':'Unavailable' ?>
                </span>
            </div>
        </div>
        <div class="book-actions">
            <a href="books.php?id=<?= $book['id'] ?>" class="btn btn-ghost btn-sm" style="flex:1;justify-content:center">Details</a>
            <?php if(isLoggedIn() && $book['available_copies'] > 0): ?>
                <a href="borrow.php?book_id=<?= $book['id'] ?>" class="btn btn-primary btn-sm" style="flex:1;justify-content:center">Borrow</a>
            <?php elseif(isLoggedIn() && $book['available_copies'] === 0): ?>
                <a href="reservations.php?book_id=<?= $book['id'] ?>" class="btn btn-warning btn-sm" style="flex:1;justify-content:center">🔖 Reserve</a>
            <?php elseif(isAdmin()): ?>
                <a href="add_book.php?edit=<?= $book['id'] ?>" class="btn btn-warning btn-sm" style="flex:1;justify-content:center">Edit</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if($totalPages > 1): ?>
<div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:28px;flex-wrap:wrap">
    <?php
    $qs = http_build_query(array_filter(['search'=>$search,'category'=>$category]));
    $qs = $qs ? '&'.$qs : '';
    ?>
    <?php if($page > 1): ?>
        <a href="books.php?page=<?= $page-1 ?><?= $qs ?>" class="btn btn-ghost btn-sm">← Prev</a>
    <?php endif; ?>
    <?php for($p=1;$p<=$totalPages;$p++): ?>
        <a href="books.php?page=<?= $p ?><?= $qs ?>"
           class="btn btn-sm <?= $p===$page?'btn-primary':'btn-ghost' ?>"
           style="min-width:36px;justify-content:center"><?= $p ?></a>
    <?php endfor; ?>
    <?php if($page < $totalPages): ?>
        <a href="books.php?page=<?= $page+1 ?><?= $qs ?>" class="btn btn-ghost btn-sm">Next →</a>
    <?php endif; ?>
</div>
<p style="text-align:center;font-size:.8rem;color:var(--ink-muted);margin-top:10px">
    Showing <?= $offset+1 ?>–<?= min($offset+$perPage,$totalBooks) ?> of <?= $totalBooks ?> books
</p>
<?php endif; ?>
<?php endif; ?>

<?php $conn->close(); require_once 'includes/footer.php'; ?>
