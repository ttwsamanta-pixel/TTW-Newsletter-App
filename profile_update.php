<?php
require_once __DIR__ . '/auth.php';

$user = require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('index.php');
}
verify_csrf();

$id = (int) $user['id'];
$username = trim((string) ($_POST['username'] ?? ''));
$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$newPassword = (string) ($_POST['new_password'] ?? '');
$currentPassword = (string) ($_POST['current_password'] ?? '');
$photo = save_profile_photo($_FILES['photo'] ?? null, $user['photo'] ?? null);
$returnTo = (string) ($_SERVER['HTTP_REFERER'] ?? 'index.php');
$returnPath = parse_url($returnTo, PHP_URL_PATH) ?: 'index.php';
$returnFile = basename($returnPath) ?: 'index.php';
$returnQuery = (string) (parse_url($returnTo, PHP_URL_QUERY) ?: '');
$allowedReturnFiles = ['index.php', 'newsletters.php', 'editor.php', 'report.php', 'employees.php'];
if (!in_array($returnFile, $allowedReturnFiles, true)) {
    $returnFile = 'index.php';
    $returnQuery = '';
}
$returnQuery = preg_replace('/(^|&)profile_(updated|error)=[^&]*/', '', $returnQuery);
$returnQuery = trim((string) $returnQuery, '&');
$returnTarget = $returnFile . ($returnQuery !== '' ? '?' . $returnQuery : '');
$returnSeparator = $returnQuery !== '' ? '&' : '?';

try {
    if ($currentPassword === '' || !password_verify($currentPassword, (string) $user['password_hash'])) {
        redirect_to($returnTarget . $returnSeparator . 'profile_error=old_password#profile-modal');
    }

    if ($newPassword !== '') {
        $stmt = db()->prepare('UPDATE users SET username=?, password_hash=?, first_name=?, last_name=?, photo=? WHERE id=?');
        $stmt->execute([$username, password_hash($newPassword, PASSWORD_DEFAULT), $firstName, $lastName, $photo, $id]);
    } else {
        $stmt = db()->prepare('UPDATE users SET username=?, first_name=?, last_name=?, photo=? WHERE id=?');
        $stmt->execute([$username, $firstName, $lastName, $photo, $id]);
    }
} catch (Throwable $e) {
    redirect_to($returnTarget . $returnSeparator . 'profile_error=username#profile-modal');
}

redirect_to($returnTarget . $returnSeparator . 'profile_updated=1');
