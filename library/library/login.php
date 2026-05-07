<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (isLoggedIn()) { header('Location: dashboard.php'); exit(); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user   = $stmt->fetch();

        if ($user && !empty($user['password']) && hash_equals((string)$user['password'], $password)) {

            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role']       = $user['role'];
            logActivity($user['id'], 'user_login', 'Logged in from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            setFlash('success', 'Welcome back, ' . $user['name'] . '!');
            header('Location: dashboard.php'); exit();
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$pageTitle = 'Sign In';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sign In — LibraryOS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
:root{--ink:#000;--ink-soft:#111827;--ink-muted:#374151;--surface:#fff;--surface-2:#fff;--border:#E5E5E5;--accent:#FFFF00;--accent-hover:#CCCC00;--accent-light:#FEFCE8;--danger:#FF0000;--danger-light:#FEE2E2;--success:#FF0000;--success-light:#FEE2E2;--warning:#FFFF00;--warning-light:#FEF3C7;--radius:10px;--font-serif:'DM Serif Display',serif;--font-sans:'DM Sans',sans-serif;}
        

        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:var(--font-sans);background:linear-gradient(135deg,#FFFFFF 0%,#FEFCE8 55%,#FEE2E2 100%);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;}
        .auth-brand{display:flex;align-items:center;gap:10px;margin-bottom:32px;text-decoration:none;color:var(--accent);font-family:var(--font-serif);font-size:1.5rem;}
        .brand-icon{width:42px;height:42px;background:var(--accent);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;}
        .auth-card{background:var(--surface);border-radius:18px;box-shadow:0 8px 32px rgba(0,0,0,.1),0 2px 8px rgba(0,0,0,.06);padding:40px;width:100%;max-width:420px;}
        .auth-title{font-family:var(--font-serif);font-size:1.9rem;color:var(--ink);margin-bottom:6px;}
        .auth-sub{color:var(--ink-muted);font-size:.9rem;margin-bottom:28px;}
        .form-group{margin-bottom:18px;}
        .form-label{display:block;font-size:.85rem;font-weight:600;color:var(--ink);margin-bottom:6px;}
        .form-control{width:100%;padding:11px 14px;border:1.5px solid #CBD5E1;border-radius:var(--radius);font-family:var(--font-sans);font-size:.9rem;color:var(--ink);transition:border-color .2s,box-shadow .2s;}
        .form-control:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(234,179,8,.18);}
        .btn-full{width:100%;padding:12px;border-radius:var(--radius);border:none;background:var(--accent);color:#000;font-family:var(--font-sans);font-size:.95rem;font-weight:700;cursor:pointer;transition:background .15s;margin-top:8px;}

        .btn-full:hover{background:var(--accent-hover);}
        .error-box{background:var(--danger-light);color:#991B1B;border:1px solid #FECACA;border-radius:var(--radius);padding:11px 14px;font-size:.875rem;margin-bottom:20px;display:flex;gap:8px;align-items:center;}

        .auth-footer{text-align:center;margin-top:20px;font-size:.875rem;color:var(--ink-muted);}
        .auth-footer a{color:var(--accent);text-decoration:none;font-weight:500;}
        .auth-footer a:hover{text-decoration:underline;}
        .demo-box{background:var(--accent-light);border-radius:var(--radius);padding:12px 14px;margin-bottom:20px;font-size:.82rem;color:var(--accent);}
        .demo-box strong{display:block;margin-bottom:4px;}
        .divider{display:flex;align-items:center;gap:10px;margin:16px 0;color:var(--ink-muted);font-size:.8rem;}
        .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border);}
    </style>
</head>
<body>
<a href="index.php" class="auth-brand"><div class="brand-icon">📚</div>LibraryOS</a>
<div class="auth-card">
    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-sub">Sign in to access your library account</p>

    <div class="demo-box">
        <strong>🔑 Demo Credentials</strong>
        Admin: admin@library.com / admin123<br>
        Student: Register a new account
    </div>

    <?php if($error): ?><div class="error-box">❌ <?= sanitize($error) ?></div><?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="you@example.com" value="<?= sanitize($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-full">Sign In →</button>
    </form>

    <div class="auth-footer">
        Don't have an account? <a href="register.php">Create one</a>
    </div>
</div>
</body>
</html>
