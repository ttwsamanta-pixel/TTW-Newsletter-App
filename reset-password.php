<?php
require_once __DIR__.'/includes/password-reset.php';
header('Referrer-Policy: no-referrer'); header('Cache-Control: no-store');
$message=''; $done=false;
if (($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    verify_csrf();
    try { complete_password_reset((string)($_POST['token'] ?? ''),(string)($_POST['password'] ?? '')); $done=true; $message='Password updated. Sign in again in the website or mobile app.'; }
    catch (InvalidArgumentException $e) { $message=$e->getMessage(); }
    catch (Throwable $e) { $message='Reset could not be completed. Contact your administrator.'; }
}
?>
<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TTW password reset</title>
<style>body{font:16px system-ui;background:#f2f6ff;color:#101f50;padding:30px}main{max-width:420px;margin:8vh auto;background:white;padding:30px;border-radius:20px}input,button{box-sizing:border-box;width:100%;padding:14px;margin-top:15px;border:1px solid #ccd6ee;border-radius:10px}button{background:#085ed7;color:white}</style>
<main><h1>Reset password</h1><p><?= h($message) ?></p><?php if (!$done): ?><form method="post"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" id="token" name="token" value="<?= h($_POST['token'] ?? '') ?>"><label>New password<input name="password" type="password" minlength="10" maxlength="128" required autocomplete="new-password"></label><button>Save new password</button></form><?php endif; ?><p><a href="login.php">Back to login</a></p></main>
<script>if(location.hash){document.getElementById('token')?.setAttribute('value',location.hash.slice(1));history.replaceState(null,'',location.pathname)}</script></html>
