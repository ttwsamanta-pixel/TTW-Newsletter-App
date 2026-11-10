<?php
declare(strict_types=1);
require_once __DIR__.'/security.php';
function request_password_reset(string $identifier): void {
    $from=defined('TTW_RESET_FROM') ? TTW_RESET_FROM : (getenv('TTW_RESET_FROM') ?: '');
    if (!filter_var($from,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Password reset email is not configured. Ask your administrator to reset your account.');
    $s=db()->prepare('SELECT id,username,email FROM users WHERE (username=? OR email=?) AND status="active" LIMIT 1'); $s->execute([$identifier,$identifier]); $u=$s->fetch();
    if (!$u) return;
    $email=$u['email'] ?: (filter_var($u['username'],FILTER_VALIDATE_EMAIL) ? $u['username']:''); if (!$email || !filter_var($email,FILTER_VALIDATE_EMAIL)) return;
    $token=bin2hex(random_bytes(32));
    db()->prepare('UPDATE newsletter_password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$u['id']]);
    db()->prepare('INSERT INTO newsletter_password_resets(token_hash,user_id,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))')->execute([hash('sha256',$token),$u['id']]);
    $link=rtrim(APP_BASE_URL,'/').'/reset-password.php#'.$token;
    // Never return or log the raw token. Mail delivery uses the hosting mail transport.
    if (!@mail($email,'TTW Newsletter password reset',"Reset your TTW Newsletter password within 30 minutes:\n".$link."\n\nIf you did not request this, ignore this email.",['From'=>$from,'Content-Type'=>'text/plain; charset=UTF-8'])) {
        db()->prepare('UPDATE newsletter_password_resets SET used_at=NOW() WHERE token_hash=?')->execute([hash('sha256',$token)]);
    }
}
function complete_password_reset(string $token,string $password): void {
    if (!preg_match('/^[a-f0-9]{64}$/D',$token) || strlen($password)<10 || strlen($password)>128) throw new InvalidArgumentException('Use a valid reset link and a password of 10–128 characters.');
    $pdo=db(); $pdo->beginTransaction();
    try {
        $s=$pdo->prepare('SELECT user_id FROM newsletter_password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() FOR UPDATE'); $s->execute([hash('sha256',$token)]); $id=$s->fetchColumn();
        if (!$id) throw new InvalidArgumentException('Reset link has expired or was already used.');
        $pdo->prepare('UPDATE users SET password_hash=? WHERE id=? AND status="active"')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
        $pdo->prepare('UPDATE newsletter_password_resets SET used_at=NOW() WHERE user_id=?')->execute([$id]);
        $pdo->prepare('UPDATE api_tokens SET revoked_at=NOW() WHERE user_id=?')->execute([$id]); $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
