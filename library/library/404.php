<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
http_response_code(404);
$pageTitle = 'Page Not Found';
require_once 'includes/header.php';
?>

<style>
.not-found-wrap {
    min-height: 60vh;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    text-align: center; padding: 60px 20px;
}
.nf-code {
    font-family: var(--font-serif);
    font-size: 8rem; line-height: 1;
    color: var(--accent); opacity: .15;
    margin-bottom: -20px;
}
.nf-icon { font-size: 4rem; margin-bottom: 16px; }
.nf-title { font-family: var(--font-serif); font-size: 2rem; margin-bottom: 10px; }
.nf-sub   { color: var(--ink-muted); font-size: 1rem; max-width: 400px; margin-bottom: 32px; line-height: 1.7; }
.nf-links { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; }
</style>

<div class="not-found-wrap">
    <div class="nf-code">404</div>
    <div class="nf-icon">📚</div>
    <h1 class="nf-title">Page Not Found</h1>
    <p class="nf-sub">
        Looks like this page checked out and never came back.
        The page you're looking for doesn't exist or may have moved.
    </p>
    <div class="nf-links">
        <a href="index.php"     class="btn btn-primary">🏠 Go Home</a>
        <a href="books.php"     class="btn btn-ghost">📖 Browse Books</a>
        <?php if(isLoggedIn()): ?>
            <a href="dashboard.php" class="btn btn-ghost">📊 Dashboard</a>
        <?php else: ?>
            <a href="login.php"     class="btn btn-ghost">🔐 Sign In</a>
        <?php endif; ?>
    </div>

    <div style="margin-top: 48px; font-size: .82rem; color: var(--ink-muted);">
        If you believe this is a mistake, please contact the library administrator.
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
