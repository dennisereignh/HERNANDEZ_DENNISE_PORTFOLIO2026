<?php
require __DIR__ . '/../api/db.php';
require __DIR__ . '/../api/auth.php';

start_admin_session();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

$error = '';
$username = '';
$dbError = '';
$setup = false;
$pdo = null;

try {
    $pdo = db();
    ensure_schema($pdo);
    $setup = ((int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() === 0);
} catch (Throwable $ex) {
    error_log('admin/login.php: ' . $ex->getMessage());
    $dbError = 'Cannot connect to the database. Make sure Apache and MySQL are running in XAMPP.'
             . (DEBUG ? ' (' . $ex->getMessage() . ')' : '');
}

// Opening the login page (e.g. from the website footer) always starts a fresh login:
// any old admin session is ended, so the password is asked every time.
if (!$dbError && $_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['admin_id'])) {
    $_SESSION = [];
    session_regenerate_id(true);
}

$csrf = csrf_token();

if (!$dbError && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($setup) {
        // First run: create the one admin account
        $confirm = (string)($_POST['confirm'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username)) {
            $error = 'Username must be 3-40 characters (letters, numbers, . _ -).';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $ins = $pdo->prepare('INSERT INTO admin_users (username, password_hash) SELECT ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM admin_users)');
            $ins->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            if ($ins->rowCount() === 1) {
                $id = (int)$pdo->lastInsertId();
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $id;
                $_SESSION['admin_user'] = $username;
                $_SESSION['last'] = time();
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                header('Location: index.php');
                exit;
            }
            $error = 'An admin account already exists. Please log in.';
            $setup = false;
        }
    } else {
        // Normal login with a simple brute-force limit: 5 failures / 15 min / IP
        $ip = client_ip();
        $pdo->exec('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)');
        $q = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)');
        $q->execute([$ip]);
        if ((int)$q->fetchColumn() >= 5) {
            $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
        } else {
            $st = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ?');
            $st->execute([$username]);
            $user = $st->fetch();
            // Always run password_verify so response time does not reveal valid usernames
            $hash = $user['password_hash'] ?? password_hash('not-a-real-password', PASSWORD_DEFAULT);
            if (password_verify($password, $hash) && $user) {
                $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$user['id'];
                $_SESSION['admin_user'] = $user['username'];
                $_SESSION['last'] = time();
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                header('Location: index.php');
                exit;
            }
            $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
            $error = 'Wrong username or password.';
        }
    }
    $csrf = csrf_token();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= $setup ? 'Create admin account' : 'Admin login' ?> — Dennise Hernandez</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css?v=3">
<script>try{var t=localStorage.getItem('theme');if(!t&&matchMedia('(prefers-color-scheme: dark)').matches)t='dark';if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
</head>
<body class="login-body">
<main class="login-card">
  <div class="logo"><span class="mono"><img src="../imgs/logo1.png" alt="" onerror="this.replaceWith(document.createTextNode('DH'))"></span><span>Dennise H.</span></div>
  <h1><?= $setup ? 'Create admin account' : 'Admin Login' ?></h1>
  <p class="sub"><?= $setup ? 'First time here. Choose the username and password you will use to manage your website.' : 'Sign in to manage your portfolio and read your messages.' ?></p>

  <?php if ($dbError): ?>
    <div class="alert bad"><?= e($dbError) ?></div>
  <?php else: ?>
    <?php if ($error): ?><div class="alert bad" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <label for="username">Username</label>
      <input id="username" name="username" type="text" required maxlength="40" autofocus value="<?= e($username) ?>" autocomplete="username">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" required autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>">
      <?php if ($setup): ?>
        <label for="confirm">Confirm password</label>
        <input id="confirm" name="confirm" type="password" required autocomplete="new-password">
        <p class="hint">At least 8 characters.</p>
      <?php endif; ?>
      <button class="btn-primary" type="submit"><?= $setup ? 'Create account' : 'Log in' ?></button>
    </form>
  <?php endif; ?>
  <a class="back" href="../index.html">← Back to website</a>
</main>
</body>
</html>
