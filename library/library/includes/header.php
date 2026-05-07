
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$user = isset($_SESSION['user_id']) ? ['name'=>$_SESSION['user_name'],'role'=>$_SESSION['role']] : null;
$flash = null;
if (isset($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' — LibraryOS' : 'LibraryOS' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
:root {
            /* Palette: black / yellow / red / white */
            --ink: #000000;
            --ink-soft: #111827;
            --ink-muted: #374151;
            --surface: #FFFFFF;
            --surface-2: #FFFFFF;
            --surface-3: #FFFFFF;
            --border: #E5E5E5;
            --border-strong: #CFCFCF;
            --accent: #FFFF00;          /* yellow */
            --accent-light: #FEFCE8;   /* near-white/yellow */
            --accent-hover: #CCCC00;   /* darker yellow */
            --success: #FF0000;       /* red */
            --success-light: #FEE2E2; /* red-tint */
            --danger: #FF0000;
            --danger-light: #FEE2E2;
            --warning: #FFFF00;       /* yellow */
            --warning-light: #FEF3C7;
            --gold: #FFFF00;
            --nav-h: 64px;
            --radius: 10px;
            --radius-lg: 16px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.04);
            --shadow: 0 4px 12px rgba(0,0,0,.08), 0 2px 4px rgba(0,0,0,.04);
            --shadow-lg: 0 12px 32px rgba(0,0,0,.12), 0 4px 8px rgba(0,0,0,.06);
            --font-serif: 'DM Serif Display', Georgia, serif;
            --font-sans: 'DM Sans', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: var(--font-sans);
            background: var(--surface-2);
            color: var(--ink);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* ── NAV ── */
.nav {
            position: sticky; top:0; z-index:2000;
            min-height: var(--nav-h);
            height: auto;
            align-items: center;



            background: var(--surface);

            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            display: flex; align-items: center;
        }
        .nav-inner {
            max-width: 1280px; margin: 0 auto; padding: 0 24px;
            width: 100%; display: flex; align-items:center; gap: 8px;
        }

        .nav-brand {
            display:flex; align-items:center; gap:10px;
            font-family: var(--font-serif);
            font-size: 1.25rem; color: var(--accent);
            text-decoration:none; margin-right: 16px;
            flex-shrink:0;
        }
        .nav-brand .brand-icon {
            width:34px; height:34px;
            background: var(--accent); border-radius:8px;
            display:flex; align-items:center; justify-content:center;
            color:#fff; font-size:16px;
        }
        .nav-links { display:flex; align-items:center; gap:2px; flex:1; justify-content:flex-end; }


        /* Dropdown menu (single-line nav) */
        .nav-item { position:relative; display:flex; align-items:center; }
        .nav-link {
            padding: 7px 14px; border-radius:7px;
            font-size:.875rem; font-weight:500;
            color: var(--ink-soft); text-decoration:none;
            transition: all .15s; white-space:nowrap;
            display:inline-flex; align-items:center; gap:8px;
        }
        .nav-link:hover { background: var(--surface-3); color: var(--ink); }
        .nav-link.active { background: var(--accent-light); color: var(--accent); }
        .nav-link.admin-only { color: var(--gold); }
        .nav-link.admin-only:hover { background: var(--warning-light); }
        .nav-link.admin-only.active { background: var(--warning-light); color: var(--gold); }

        .nav-dropdown-toggle {
            cursor:pointer; border:none; background:transparent;
        }

        .nav-dropdown {
            position:absolute; top: calc(100% + 10px); left:0;
            min-width: 220px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 6px;
            display:none;
            z-index: 3000;
        }
        .nav-item:hover .nav-dropdown,
        .nav-dropdown:focus-within,
        .nav-dropdown.open {
            display:block;
        }

        .nav-dropdown a {
            display:flex; align-items:center; gap:10px;
            padding: 9px 12px; border-radius: 8px;
            text-decoration:none;
            color: var(--ink-soft);
            font-size: .875rem;
            transition: all .15s;
            white-space:nowrap;
        }
        .nav-dropdown a:hover { background: var(--surface-3); color: var(--ink); }
        .nav-dropdown a.active { background: var(--accent-light); color: var(--accent); }

        .nav-right { display:flex; align-items:center; gap:10px; margin-left:12px; }

        .nav-user {
            display:flex; align-items:center; gap:8px;
            font-size:.875rem; color: var(--ink-soft);
        }

        .nav-avatar {
            width:32px; height:32px; border-radius:50%;
            background: var(--accent); color:#fff;
            display:flex; align-items:center; justify-content:center;
            font-size:.75rem; font-weight:600; text-transform:uppercase;
        }
        .nav-badge {
            font-size:.65rem; padding:2px 7px; border-radius:99px;
            font-weight:600; text-transform:uppercase; letter-spacing:.04em;
        }
        .badge-admin { background:var(--warning-light); color:var(--gold); }

        .badge-student { background:var(--accent-light); color:var(--accent); }
        .btn-nav-logout {
            padding:6px 14px; border-radius:7px; border:1px solid var(--border);
            background: transparent; color:var(--danger); font-size:.875rem;
            font-family:var(--font-sans); cursor:pointer; font-weight:500;
            transition: all .15s;
        }
        .btn-nav-logout:hover { background:var(--danger-light); border-color:var(--danger); }
.btn-nav-login {
            padding:7px 16px; border-radius:7px; border:none;
            background: var(--accent); color:#000; font-size:.875rem;

            font-family:var(--font-sans); cursor:pointer; font-weight:500;
            text-decoration:none; transition: background .15s;
        }
        .btn-nav-login:hover { background: var(--accent-hover); }

        /* Mobile Nav */
        .nav-toggle { display:none; background:none; border:none; cursor:pointer; padding:6px; }
        .nav-toggle span { display:block; width:22px; height:2px; background:var(--ink); margin:5px 0; border-radius:2px; transition:.3s; }

        /* ── MAIN ── */
        main { max-width:1280px; margin:0 auto; padding:32px 16px; }




        /* ── FLASH ── */
        .flash {
            display:flex; align-items:center; gap:10px;
            padding:12px 16px; border-radius:var(--radius);
            margin-bottom:24px; font-size:.9rem; font-weight:500;
            animation: slideDown .3s ease;
        }
        .flash-success { background:var(--success-light); color: #991B1B; border:1px solid #FECACA; }
        .flash-error   { background:var(--danger-light); color: #991B1B; border:1px solid #FECACA; }
        .flash-warning { background:var(--warning-light); color: #000; border:1px solid #FDE68A; }
        .flash-icon { font-size:1.1rem; }
        @keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }

        /* ── CARDS ── */
        .card {
            background: var(--surface); border-radius:var(--radius-lg);
            border:1px solid var(--border); box-shadow: var(--shadow-sm);
            overflow:hidden;
        }
        .card-header {
            padding:20px 24px; border-bottom:1px solid var(--border);
            display:flex; align-items:center; justify-content:space-between; gap:12px;
        }
        .card-title { font-family:var(--font-serif); font-size:1.2rem; color:var(--ink); }
        .card-body { padding:24px; }

        /* ── STATS GRID ── */
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:28px; }
        .stat-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius-lg); padding:20px 22px;
            box-shadow:var(--shadow-sm); position:relative; overflow:hidden;
        }
        .stat-card::before {
            content:''; position:absolute; top:0; left:0; right:0; height:3px;
        }
        .stat-card.blue::before  { background:var(--accent); }
        .stat-card.green::before { background:var(--success); }
        .stat-card.red::before   { background:var(--danger); }
        .stat-card.gold::before  { background:var(--warning); }
        .stat-label { font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-muted); margin-bottom:8px; }
        .stat-value { font-family:var(--font-serif); font-size:2.2rem; color:var(--ink); line-height:1; }
        .stat-icon { position:absolute; right:16px; top:50%; transform:translateY(-50%); font-size:2rem; opacity:.1; }

        /* ── TABLES ── */
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:.9rem; }
        thead th {
            background:var(--surface-3); padding:11px 14px;
            text-align:left; font-size:.75rem; font-weight:600;
            text-transform:uppercase; letter-spacing:.06em; color:var(--ink-muted);
            border-bottom:1px solid var(--border); white-space:nowrap;
        }
        tbody tr { border-bottom:1px solid var(--border); transition:background .1s; }
        tbody tr:hover { background:var(--surface-2); }
        tbody tr:last-child { border-bottom:none; }
        td { padding:12px 14px; color:var(--ink-soft); vertical-align:middle; }
        td strong { color:var(--ink); }

        /* ── BADGES ── */
        .badge {
            display:inline-block; padding:3px 9px; border-radius:99px;
            font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em;
        }
        .badge-success  { background:var(--success-light); color:#991B1B; }
        .badge-danger   { background:var(--danger-light); color:#991B1B; }
        .badge-warning  { background:var(--warning-light); color:#000; }
        .badge-info     { background:var(--accent-light); color:var(--accent); }
        .badge-gray     { background:var(--surface-3); color:var(--ink-muted); }

        /* ── BUTTONS ── */
        .btn {
            display:inline-flex; align-items:center; gap:6px;
            padding:9px 18px; border-radius:var(--radius); border:none;
            font-family:var(--font-sans); font-size:.875rem; font-weight:500;
            cursor:pointer; text-decoration:none; transition:all .15s; white-space:nowrap;
        }
        .btn-primary { background:var(--accent); color:#fff; }
        .btn-primary:hover { background:var(--accent-hover); }
        .btn-success { background:var(--success); color:#fff; }
        .btn-success:hover { background:#CC0000; }
        .btn-danger  { background:var(--danger); color:#fff; }
        .btn-danger:hover  { background:#CC0000; }
        .btn-warning { background:var(--warning); color:#000; }
        .btn-warning:hover { background:#CCCC00; }

        .btn-ghost   { background:transparent; color:var(--ink-soft); border:1px solid var(--border); }
        .btn-ghost:hover   { background:var(--surface-3); }
        .btn-sm { padding:6px 12px; font-size:.8rem; }
        .btn:disabled { opacity:.5; cursor:not-allowed; }

        /* ── FORMS ── */
        .form-group { margin-bottom:18px; }
        .form-label { display:block; font-size:.85rem; font-weight:600; color:var(--ink); margin-bottom:6px; }
        .form-control {
            width:100%; padding:10px 14px; border:1px solid var(--border-strong);
            border-radius:var(--radius); font-family:var(--font-sans);
            font-size:.9rem; color:var(--ink); background:var(--surface);
            transition:border-color .2s, box-shadow .2s;
        }
        .form-control:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(234,179,8,.18); }
        .form-control::placeholder { color:var(--ink-muted); }
        select.form-control { cursor:pointer; }
        textarea.form-control { resize:vertical; min-height:90px; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:0 20px; }
        .form-actions { display:flex; gap:10px; justify-content:center; margin-top:8px; flex-wrap:wrap; }

        .form-hint { font-size:.78rem; color:var(--ink-muted); margin-top:4px; }


        /* ── PAGE HEADER ── */
        .page-header {
            display:flex; align-items:center; justify-content:space-between;
            margin-bottom:28px; gap:16px; flex-wrap:wrap;
        }
        .page-header-left h1 { font-family:var(--font-serif); font-size:1.8rem; color:var(--ink); }
        .page-header-left p  { color:var(--ink-muted); font-size:.9rem; margin-top:2px; }
        .breadcrumb { display:flex; align-items:center; gap:6px; font-size:.8rem; color:var(--ink-muted); margin-bottom:6px; }
        .breadcrumb a { color:var(--accent); text-decoration:none; }
        .breadcrumb a:hover { text-decoration:underline; }

        /* ── EMPTY STATE ── */
        .empty-state { padding:48px; text-align:center; color:var(--ink-muted); }
        .empty-state .empty-icon { font-size:3rem; margin-bottom:12px; opacity:.4; }
        .empty-state p { font-size:.95rem; }

        /* ── SEARCH BAR ── */
        .search-bar {
            display:flex; gap:10px; align-items:center;
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:8px 14px;
            box-shadow:var(--shadow-sm);
        }
        .search-bar input {
            border:none; outline:none; flex:1; font-family:var(--font-sans);
            font-size:.9rem; color:var(--ink); background:transparent;
        }
        .search-bar input::placeholder { color:var(--ink-muted); }

        /* ── BOOK CARDS ── */
        .books-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:18px; }
        .book-card {
            background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg);
            overflow:hidden; box-shadow:var(--shadow-sm); transition:transform .2s, box-shadow .2s;
            display:flex; flex-direction:column;
        }
        .book-card:hover { transform:translateY(-2px); box-shadow:var(--shadow); }
        .book-cover {
            height:100px; display:flex; align-items:center; justify-content:center;
            font-size:2.5rem; position:relative; overflow:hidden;
        }
        .book-cover::after {
            content:''; position:absolute; bottom:0; left:0; right:0; height:30px;
            background:linear-gradient(transparent, rgba(0,0,0,.15));
        }
        .book-info { padding:16px; flex:1; display:flex; flex-direction:column; gap:6px; }
        .book-title { font-weight:600; color:var(--ink); font-size:.95rem; line-height:1.4; }
        .book-author { font-size:.82rem; color:var(--ink-muted); }
        .book-meta { display:flex; align-items:center; justify-content:space-between; margin-top:auto; padding-top:10px; }
        .book-avail { font-size:.8rem; font-family:var(--font-mono); }
        .book-actions { display:flex; gap:6px; padding:12px 16px; border-top:1px solid var(--border); background:var(--surface-2); }

        /* ── MODAL ── */
        .modal-overlay {
            position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000;
            display:flex; align-items:center; justify-content:center; padding:20px;
            backdrop-filter:blur(4px);
        }
        .modal {
            background:var(--surface); border-radius:var(--radius-lg);
            box-shadow:var(--shadow-lg); width:100%; max-width:520px;
            max-height:90vh; overflow-y:auto;
        }
        .modal-header {
            padding:20px 24px; border-bottom:1px solid var(--border);
            display:flex; align-items:center; justify-content:space-between;
        }
        .modal-title { font-family:var(--font-serif); font-size:1.2rem; }
        .modal-close { background:none; border:none; font-size:1.4rem; cursor:pointer; color:var(--ink-muted); padding:4px; line-height:1; }
        .modal-close:hover { color:var(--ink); }
        .modal-body { padding:24px; }
        .modal-footer { padding:16px 24px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; }

        /* ── FOOTER ── */
        footer {
            margin-top:60px; padding:24px; text-align:center;
            font-size:.8rem; color:var(--ink-muted);
            border-top:1px solid var(--border); background:var(--surface);
        }

        /* ── RESPONSIVE ── */
        @media(max-width:768px){
            .nav-links { display:none; flex-direction:column; position:absolute; top:var(--nav-h); left:0; right:0; background:var(--surface); border-bottom:1px solid var(--border); padding:12px; gap:4px; box-shadow:var(--shadow); }
            .nav-links.open { display:flex; }
            .nav-toggle { display:block; }

            /* Dropdowns become stacked */
            .nav-item { width:100%; }
            .nav-dropdown { position:static; min-width:unset; box-shadow:none; border:none; padding:0 0 6px 12px; display:none; }
            .nav-item:hover .nav-dropdown,
            .nav-dropdown:focus-within,
            .nav-dropdown.open { display:block; }

            main { padding:20px 16px; }
            .form-grid { grid-template-columns:1fr; }
            .stats-grid { grid-template-columns:1fr 1fr; }
            .page-header { flex-direction:column; align-items:flex-start; }
        }

        @media(max-width:480px){
            .stats-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<nav class="nav">
    <div class="nav-inner">
        <a href="index.php" class="nav-brand">
            <div class="brand-icon">📚</div>
            LibraryOS
        </a>

        <div class="nav-links" id="navLinks">
            <a href="index.php" class="nav-link <?= $currentPage==='index'?'active':'' ?>">🏠 Admin Home</a>

            <div class="nav-item">
                <button type="button" class="nav-link nav-dropdown-toggle" aria-haspopup="true" aria-expanded="false">
                    📖 Books ▾
                </button>
                <div class="nav-dropdown" role="menu">
                    <a href="books.php" class="<?= $currentPage==='books' ? 'active' : '' ?>">👁️ View Books</a>
                    <?php if($user && isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?>
                        <a href="add_book.php" class="<?= $currentPage==='add_book' ? 'active' : '' ?>">➕ Add Books</a>
                        <a href="books.php">🏷️ Categories</a>

                    <?php else: ?>
                        <a href="books.php" class="<?= $currentPage==='books' ? 'active' : '' ?>">📚 Categories</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="nav-item">
                <button type="button" class="nav-link nav-dropdown-toggle" aria-haspopup="true" aria-expanded="false">
                    🔁 Transactions ▾
                </button>
                <div class="nav-dropdown" role="menu">
                    <a href="borrow.php" class="<?= $currentPage==='borrow' ? 'active' : '' ?>">⬇️ Borrow</a>
                    <a href="return.php" class="<?= $currentPage==='return' ? 'active' : '' ?>">⬆️ Return</a>
                    <a href="reservations.php" class="<?= $currentPage==='reservations' ? 'active' : '' ?>">🔖 Reserve</a>
                    <a href="fine_settings.php" class="<?= $currentPage==='fine_settings' ? 'active' : '' ?>">💰 Fines</a>
                </div>
            </div>

            <div class="nav-item">
                <button type="button" class="nav-link nav-dropdown-toggle" aria-haspopup="true" aria-expanded="false">
                    📈 Reports ▾
                </button>
                <div class="nav-dropdown" role="menu">
                    <a href="reports.php" class="<?= $currentPage==='reports' ? 'active' : '' ?>">📄 Reports</a>
                    <a href="activity_log.php" class="<?= $currentPage==='activity_log' ? 'active' : '' ?>">🧾 Logs</a>
                </div>
            </div>

            <a href="dashboard.php" class="nav-link <?= $currentPage==='dashboard'?'active':'' ?>">📊 Dashboard</a>

            <?php if(isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?>
                <a href="users.php" class="nav-link admin-only <?= $currentPage==='users'?'active':'' ?>">👥 Users</a>
            <?php endif; ?>

            <?php if($user): ?>
                <div class="nav-item">
                    <button type="button" class="nav-link nav-dropdown-toggle" aria-haspopup="true" aria-expanded="false">
                        👤 Profile ▾
                    </button>
                    <div class="nav-dropdown" role="menu">
                        <a href="profile.php" class="<?= $currentPage==='profile' ? 'active' : '' ?>">✏️ Profile</a>
                        <a href="logout.php" onclick="event.preventDefault();document.getElementById('logoutForm').submit();" class="">🚪 Logout</a>
                    </div>
                </div>
                <form id="logoutForm" action="logout.php" method="POST" style="display:none"></form>
            <?php else: ?>
                <a href="login.php" class="nav-link <?= $currentPage==='login'?'active':'' ?>">🔐 Login</a>
            <?php endif; ?>
        </div>


        <div class="nav-right">
            <?php if($user): ?>
                <div class="nav-user">
                    <div class="nav-avatar"><?= strtoupper(substr($user['name'],0,2)) ?></div>
                    <span style="display:none" class="name-label"><?= sanitize($user['name']) ?></span>
                    <span class="nav-badge <?= $user['role']==='admin'?'badge-admin':'badge-student' ?>"><?= $user['role'] ?></span>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn-nav-login">Sign In</a>
            <?php endif; ?>
                <button class="nav-toggle" id="navToggle" type="button" onclick="document.getElementById('navLinks').classList.toggle('open')">

                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</nav>

<main>
<?php if($flash): ?>
    <div class="flash flash-<?= $flash['type'] ?>">
        <span class="flash-icon"><?= $flash['type']==='success'?'✅':($flash['type']==='error'?'❌':'⚠️') ?></span>
        <?= sanitize($flash['message']) ?>
    </div>
<?php endif; ?>
