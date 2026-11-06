<?php
require_once __DIR__ . '/auth.php';

start_auth_session();
if (auth_user()) {
    redirect_to('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? AND status = "active" LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        set_auth_remember_cookie($user);
        redirect_to('index.php');
    }
    $error = 'Invalid username or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Newsletter Admin Login</title>
<link rel="stylesheet" href="<?= h(asset_url('assets/app.css')) ?>">
</head>
<body class="login-page">
  <form class="login-card" method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <div class="login-logo"><img src="TTW-Logo.svg" onerror="this.onerror=null;this.src='TTW-Logo.png';" alt="TTW"></div>
    <h1>Admin Login</h1>
    <?php if ($error): ?><div class="login-error"><?= h($error) ?></div><?php endif; ?>
    <label><span>Username</span><input name="username" autocomplete="username" placeholder="Username" required autofocus></label>
    <label><span>Password</span><input type="password" name="password" autocomplete="current-password" placeholder="Password" required></label>
    <button class="login-button" type="submit">Login</button>
  </form>
</body>
</html>
