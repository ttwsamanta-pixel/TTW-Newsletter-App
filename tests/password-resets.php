<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
putenv('TTW_DB_HOST=127.0.0.1'); putenv('TTW_DB_PORT=33319'); putenv('TTW_DB_NAME=ttw_manager_test'); putenv('TTW_DB_USER=root'); putenv('TTW_DB_PASSWORD=manager_fixture_only');
require_once __DIR__.'/.runtime/manager-server/New/includes/password-reset.php';
$checks=0;
function reset_check(bool $ok,string $message): void {global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
$s=db()->prepare('SELECT * FROM users WHERE username=?');$s->execute(['manager_viewer']);$u=$s->fetch();$old=$u['password_hash'];
$token=bin2hex(random_bytes(32));$expired=bin2hex(random_bytes(32));
try {
    $s=db()->prepare('INSERT INTO newsletter_password_resets(token_hash,user_id,expires_at) VALUES(?,?,?)');
    $s->execute([hash('sha256',$token),$u['id'],date('Y-m-d H:i:s',time()+1800)]);
    $s->execute([hash('sha256',$expired),$u['id'],date('Y-m-d H:i:s',time()-60)]);
    try { complete_password_reset($expired,'new_fixture_password');reset_check(false,'Expired reset denied'); } catch (InvalidArgumentException $e) {reset_check(true,'Expired reset denied');}
    try { complete_password_reset($token,'short');reset_check(false,'Short password denied'); } catch (InvalidArgumentException $e) {reset_check(true,'Short password denied');}
    complete_password_reset($token,'new_fixture_password');
    $s=db()->prepare('SELECT password_hash FROM users WHERE id=?');$s->execute([$u['id']]);reset_check(password_verify('new_fixture_password',$s->fetchColumn()),'Password hash updated');
    $s=db()->prepare('SELECT COUNT(*) FROM api_tokens WHERE user_id=? AND revoked_at IS NULL');$s->execute([$u['id']]);reset_check((int)$s->fetchColumn()===0,'All mobile tokens revoked');
    try { complete_password_reset($token,'another_fixture_password');reset_check(false,'Replay denied'); } catch (InvalidArgumentException $e) {reset_check(true,'Replay denied');}
    $s=db()->prepare('SELECT COUNT(*) FROM newsletter_password_resets WHERE token_hash=?');$s->execute([$token]);reset_check((int)$s->fetchColumn()===0,'No plaintext reset token stored');
    echo "PASS: $checks password reset checks\n";
} finally {db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$old,$u['id']]);db()->prepare('DELETE FROM newsletter_password_resets WHERE user_id=?')->execute([$u['id']]);}
