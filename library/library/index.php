<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
$pageTitle = 'Welcome';
require_once __DIR__ . '/includes/header.php';

$conn = getDBConnection();
$totalBooks = (int) ($conn->query("SELECT COUNT(*) as c FROM books")->fetch()['c'] ?? 0);
$availBooks = (int) ($conn->query("SELECT SUM(available_copies) as c FROM books")->fetch()['c'] ?? 0);
$totalUsers = (int) ($conn->query("SELECT COUNT(*) as c FROM users WHERE role='student'")->fetch()['c'] ?? 0);
$activeBorrows = (int) ($conn->query("SELECT COUNT(*) as c FROM borrow_records WHERE status IN ('Borrowed','Overdue')")->fetch()['c'] ?? 0);

// Recent books
$recent = $conn->query("SELECT * FROM books ORDER BY created_at DESC LIMIT 6");
?>

<style>
.hero {
    background: linear-gradient(135deg, #000000 0%, #FFFF00 55%, #FF0000 100%);
    border-radius: var(--radius-lg); padding: 56px 48px; margin-bottom: 40px;
    position: relative; overflow: hidden; color: #fff;
}
.hero::before {
    content:''; position:absolute; top:-40px; right:-40px;
    width:300px; height:300px; border-radius:50%;
    background:rgba(255,255,255,.06); pointer-events:none;
}
.hero::after {
    content:''; position:absolute; bottom:-60px; right:80px;
    width:200px; height:200px; border-radius:50%;
    background:rgba(255,255,255,.04); pointer-events:none;
}
.hero h1 { font-family:var(--font-serif); font-size:2.8rem; margin-bottom:12px; line-height:1.2; }
.hero p { font-size:1.1rem; opacity:.85; max-width:500px; margin-bottom:28px; }
.hero-actions { display:flex; gap:12px; flex-wrap:wrap; }
.btn-hero-primary {
    padding:12px 24px; background:#fff; color:var(--accent);
    border-radius:var(--radius); font-weight:600; text-decoration:none;
    font-family:var(--font-sans); transition:all .2s; display:inline-flex; align-items:center; gap:8px;
}
.btn-hero-primary:hover { background:var(--accent-light); }
.btn-hero-ghost {
    padding:12px 24px; background:rgba(255,255,255,.15); color:#fff;
    border-radius:var(--radius); font-weight:600; text-decoration:none;
    font-family:var(--font-sans); border:1px solid rgba(255,255,255,.3); transition:all .2s;
    display:inline-flex; align-items:center; gap:8px;
}
.btn-hero-ghost:hover { background:rgba(255,255,255,.25); }
.section-title { font-family:var(--font-serif); font-size:1.6rem; margin-bottom:6px; }
.section-sub { color:var(--ink-muted); font-size:.9rem; margin-bottom:22px; }
.features-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin-bottom:40px; }
.feature-card {
    background:var(--surface); border:1px solid var(--border);
    border-radius:var(--radius-lg); padding:22px; box-shadow:var(--shadow-sm);
}
.feature-icon { font-size:1.8rem; margin-bottom:10px; }
.feature-card h3 { font-size:1rem; font-weight:600; margin-bottom:6px; }
.feature-card p { font-size:.85rem; color:var(--ink-muted); line-height:1.5; }
</style>

<!-- Hero -->
<div class="hero">
    <h1>Your Digital Library,<br><em>Reimagined.</em></h1>
    <p>Discover, borrow, and manage thousands of books with ease. LibraryOS brings the library to your fingertips.</p>
    <div class="hero-actions">
        <a href="books.php" class="btn-hero-primary">📖 Browse Books</a>
        <?php if(!$user): ?>
            <a href="register.php" class="btn-hero-ghost">✨ Create Account</a>
        <?php else: ?>
            <a href="dashboard.php" class="btn-hero-ghost">📊 My Dashboard</a>
        <?php endif; ?>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-label">Total Books</div>
        <div class="stat-value"><?= number_format($totalBooks) ?></div>
        <div class="stat-icon">📚</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Available Now</div>
        <div class="stat-value"><?= number_format($availBooks) ?></div>
        <div class="stat-icon">✅</div>
    </div>
    <div class="stat-card gold">
        <div class="stat-label">Active Members</div>
        <div class="stat-value"><?= number_format($totalUsers) ?></div>
        <div class="stat-icon">👥</div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Books Borrowed</div>
        <div class="stat-value"><?= number_format($activeBorrows) ?></div>
        <div class="stat-icon">📤</div>
    </div>
</div>

<!-- Features -->
<h2 class="section-title">Why LibraryOS?</h2>
<p class="section-sub">Everything you need to manage and enjoy your library experience</p>
<div class="features-grid">
    <div class="feature-card">
        <div class="feature-icon">🔍</div>
        <h3>Smart Search</h3>
        <p>Find books instantly by title, author, or category with our powerful search engine.</p>
    </div>
    <div class="feature-card">
        <div class="feature-icon">⚡</div>
        <h3>Instant Borrowing</h3>
        <p>Borrow and return books with a single click. No queues, no paperwork.</p>
    </div>
    <div class="feature-card">
        <div class="feature-icon">📅</div>
        <h3>Due Date Tracking</h3>
        <p>Never miss a return date with automatic due date calculations and status updates.</p>
    </div>
    <div class="feature-card">
        <div class="feature-icon">📊</div>
        <h3>Personal Dashboard</h3>
        <p>Track your borrowing history, active loans, and library activity at a glance.</p>
    </div>
</div>

<!-- Recent Books -->
<h2 class="section-title">Recently Added</h2>
<p class="section-sub">Explore the latest additions to our collection</p>
<div class="books-grid">
    <?php while($book = $recent->fetch()): ?>
    <div class="book-card">
    <div class="book-cover" style="background:<?= sanitize($book['cover_color']) ?>20; border:1px solid rgba(0,0,0,.12);">
            <span>📖</span>
        </div>
        <div class="book-info">
            <div class="book-title"><?= sanitize($book['title']) ?></div>
            <div class="book-author">by <?= sanitize($book['author']) ?></div>
            <?php if($book['category']): ?>
                <span class="badge badge-info"><?= sanitize($book['category']) ?></span>
            <?php endif; ?>
            <div class="book-meta">
                <span class="book-avail" style="color:<?= $book['available_copies']>0?'var(--success)':'var(--danger)' ?>">
                    <?= $book['available_copies'] ?> / <?= $book['quantity'] ?> available
                </span>
            </div>
        </div>
        <div class="book-actions">
            <a href="books.php?id=<?= $book['id'] ?>" class="btn btn-ghost btn-sm" style="flex:1;justify-content:center">Details</a>
            <?php if($user && $book['available_copies'] > 0): ?>
                <a href="borrow.php?book_id=<?= $book['id'] ?>" class="btn btn-primary btn-sm" style="flex:1;justify-content:center">Borrow</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

