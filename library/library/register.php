<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (isLoggedIn()) { header('Location: dashboard.php'); exit(); }

$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing) {
            $error = 'An account with this email already exists.';
        } else {
            // Store raw password (no hashing)
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,'student')");
            if ($stmt->execute([$name, $email, $password])) {

                $newId = $conn->lastInsertId();
                logActivity($newId, 'user_registered', "New student account: $email");
                $success = 'Account created! You can now sign in.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Register — LibraryOS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root{--ink:#000;--ink-soft:#111827;--ink-muted:#374151;--surface:#fff;--border:#E5E5E5;--accent:#FFFF00;--accent-hover:#CCCC00;--accent-light:#FEFCE8;--danger:#FF0000;--danger-light:#FEE2E2;--success:#FF0000;--success-light:#FEE2E2;--warning:#FFFF00;--warning-light:#FEF3C7;--radius:10px;--font-serif:'DM Serif Display',serif;--font-sans:'DM Sans',sans-serif;}

        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:var(--font-sans);background:linear-gradient(135deg,#FFFFFF 0%,#FEFCE8 55%,#FEE2E2 100%);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;}
        .auth-brand{display:flex;align-items:center;gap:10px;margin-bottom:32px;text-decoration:none;color:var(--accent);font-family:var(--font-serif);font-size:1.5rem;}
        .brand-icon{width:42px;height:42px;background:var(--accent);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;}
        .auth-card{background:var(--surface);border-radius:18px;box-shadow:0 8px 32px rgba(0,0,0,.1);padding:40px;width:100%;max-width:440px;}
        .auth-title{font-family:var(--font-serif);font-size:1.9rem;color:var(--ink);margin-bottom:6px;}
        .auth-sub{color:var(--ink-muted);font-size:.9rem;margin-bottom:28px;}
        .form-group{margin-bottom:16px;}
        .form-label{display:block;font-size:.85rem;font-weight:600;color:var(--ink);margin-bottom:6px;}
        .form-control{width:100%;padding:11px 14px;border:1.5px solid #CBD5E1;border-radius:var(--radius);font-family:var(--font-sans);font-size:.9rem;color:var(--ink);transition:border-color .2s,box-shadow .2s;}
        .form-control:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(234,179,8,.18);}

        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px;}
        .btn-full{width:100%;padding:12px;border-radius:var(--radius);border:none;background:var(--accent);color:#000;font-family:var(--font-sans);font-size:.95rem;font-weight:700;cursor:pointer;transition:background .15s;margin-top:8px;}


        .btn-full:hover{background:var(--accent-hover);}
        .error-box{background:var(--danger-light);color:#991B1B;border:1px solid #FECACA;border-radius:var(--radius);padding:11px 14px;font-size:.875rem;margin-bottom:20px;}
        .success-box{background:var(--success-light);color:#991B1B;border:1px solid #FECACA;border-radius:var(--radius);padding:11px 14px;font-size:.875rem;margin-bottom:20px;}

        .auth-footer{text-align:center;margin-top:20px;font-size:.875rem;color:var(--ink-muted);}
        .auth-footer a{color:var(--accent);text-decoration:none;font-weight:500;}
        @media(max-width:480px){.form-grid{grid-template-columns:1fr;}}
    </style>
</head>
<body>
<a href="index.php" class="auth-brand"><div class="brand-icon">📚</div>LibraryOS</a>
<div class="auth-card">
    <h1 class="auth-title">Create account</h1>
    <p class="auth-sub">Join LibraryOS and start exploring our collection</p>

    <?php if($error): ?><div class="error-box">❌ <?= sanitize($error) ?></div><?php endif; ?>
    <?php if($success): ?><div class="success-box">✅ <?= sanitize($success) ?> <a href="login.php" style="color:#065F46;font-weight:600">Sign in now →</a></div><?php endif; ?>

    <?php if(!$success): ?>
    <form method="POST">
        <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" placeholder="John Doe" value="<?= sanitize($_POST['name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="you@example.com" value="<?= sanitize($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Min. 6 chars" required>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Repeat" required>
            </div>
        </div>
        <button type="submit" class="btn-full">Create Account →</button>
    </form>
    <?php endif; ?>

    <div class="auth-footer">Already have an account? <a href="login.php">Sign in</a></div>
</div>
</body>
</html>
