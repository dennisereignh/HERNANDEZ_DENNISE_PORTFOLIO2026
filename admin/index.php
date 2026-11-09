<?php
require __DIR__ . '/../api/auth.php';

start_admin_session();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

if (!admin_logged_in()) {
    header('Location: login.php');
    exit;
}
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf" content="<?= e($csrf) ?>">
<title>Admin — Dennise Hernandez</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css?v=3">
<script>try{var t=localStorage.getItem('theme');if(!t&&matchMedia('(prefers-color-scheme: dark)').matches)t='dark';if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
</head>
<body>
<div id="app"><p class="loading">Loading…</p></div>
<div id="toast" class="toast" role="status" aria-live="polite"></div>
<noscript><p style="padding:20px">Please enable JavaScript to use the admin dashboard.</p></noscript>
<script src="../icons.js?v=1"></script>
<script src="admin.js?v=1"></script>
</body>
</html>
