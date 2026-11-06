<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

const PERMISSIONS = ['view', 'create', 'edit', 'delete', 'download'];
const AUTH_REMEMBER_COOKIE = 'ttw_newsletter_remember';
const AUTH_REMEMBER_LIFETIME = 60 * 60 * 24 * 365 * 5;
const AUTH_SESSION_LIFETIME = 60 * 60 * 24 * 365 * 5;

function csrf_token(): string
{
    start_auth_session();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function verify_csrf(): void
{
    $value = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '');
    if (!hash_equals(csrf_token(), $value)) {
        http_response_code(403);
        exit('Invalid CSRF token. Reload the page and try again.');
    }
}

function start_auth_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('ttw_newsletter_auth');
    $lifetime = AUTH_SESSION_LIFETIME;
    $sessionPath = __DIR__ . '/storage/sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0775, true);
    }
    session_save_path($sessionPath);
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '1000');
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    if (session_id() !== '') {
        setcookie(session_name(), session_id(), [
            'expires' => time() + $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function auth_cookie_options(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function auth_remember_signature(int $userId, int $expires, string $passwordHash): string
{
    return hash_hmac('sha256', $userId . '|' . $expires . '|' . $passwordHash, DB_PASS);
}

function set_auth_remember_cookie(array $user): void
{
    $userId = (int) ($user['id'] ?? 0);
    $passwordHash = (string) ($user['password_hash'] ?? '');
    if ($userId <= 0 || $passwordHash === '') {
        return;
    }

    $expires = time() + AUTH_REMEMBER_LIFETIME;
    $token = $userId . '|' . $expires . '|' . auth_remember_signature($userId, $expires, $passwordHash);
    setcookie(AUTH_REMEMBER_COOKIE, $token, auth_cookie_options($expires));
}

function clear_auth_remember_cookie(): void
{
    setcookie(AUTH_REMEMBER_COOKIE, '', auth_cookie_options(time() - 42000));
}

function restore_auth_from_remember_cookie(): ?array
{
    $token = (string) ($_COOKIE[AUTH_REMEMBER_COOKIE] ?? '');
    if ($token === '') {
        return null;
    }

    $parts = explode('|', $token);
    if (count($parts) !== 3) {
        clear_auth_remember_cookie();
        return null;
    }

    [$userIdRaw, $expiresRaw, $signature] = $parts;
    $userId = (int) $userIdRaw;
    $expires = (int) $expiresRaw;
    if ($userId <= 0 || $expires < time() || $signature === '') {
        clear_auth_remember_cookie();
        return null;
    }

    try {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND status = "active"');
        $stmt->execute([$userId]);
        $user = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        $user = null;
    }

    if (!$user) {
        clear_auth_remember_cookie();
        return null;
    }

    $expected = auth_remember_signature($userId, $expires, (string) $user['password_hash']);
    if (!hash_equals($expected, $signature)) {
        clear_auth_remember_cookie();
        return null;
    }

    $_SESSION['user_id'] = $userId;
    set_auth_remember_cookie($user);
    return $user;
}

function auth_user(): ?array
{
    start_auth_session();
    if (empty($_SESSION['user_id'])) {
        return restore_auth_from_remember_cookie();
    }

    static $user = null;
    if ($user !== null) {
        return $user;
    }

    try {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND status = "active"');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        $user = null;
    }
    return $user;
}

function is_admin(?array $user = null): bool
{
    if ($user === null) {
        $user = auth_user();
    }
    return ($user['role'] ?? '') === 'admin';
}

function require_auth(): array
{
    $user = auth_user();
    if (!$user) {
        redirect_to('login.php');
    }
    return $user;
}

function user_permissions(?array $user = null): array
{
    if ($user === null) {
        $user = auth_user();
    }
    if (!$user) {
        return [];
    }
    if (is_admin($user)) {
        return PERMISSIONS;
    }
    $decoded = json_decode((string) ($user['permissions'] ?? '[]'), true);
    return is_array($decoded) ? array_values(array_intersect(PERMISSIONS, $decoded)) : [];
}

function can(string $permission, ?array $user = null): bool
{
    return in_array($permission, user_permissions($user), true);
}

function require_permission(string $permission): array
{
    $user = require_auth();
    if (!can($permission, $user)) {
        http_response_code(403);
        echo '<h1>Permission denied</h1>';
        exit;
    }
    return $user;
}

function current_user_name(?array $user = null): string
{
    if ($user === null) {
        $user = auth_user();
    }
    if (!$user) {
        return '';
    }
    return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: (string) $user['username'];
}

function avatar_html(?array $user = null, string $class = 'avatar'): string
{
    if ($user === null) {
        $user = auth_user();
    }
    $photo = public_upload_url($user['photo'] ?? null);
    if ($photo) {
        return '<div class="' . h($class) . '"><img src="' . h($photo) . '" alt="Profile"></div>';
    }
    return '<div class="' . h($class) . '" aria-label="Profile"></div>';
}

function save_profile_photo(?array $file, ?string $existing = null): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $existing;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return $existing;
    }

    $tmp = (string) $file['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info || (int) ($file['size'] ?? 0) > 8 * 1024 * 1024 || $info[0] * $info[1] > 20000000) {
        return $existing;
    }
    $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    $extension = $extensions[$info[2]] ?? null;
    if (!$extension) {
        return $existing;
    }

    $directory = 'uploads/profiles';
    if (!is_dir(__DIR__ . '/' . $directory)) {
        mkdir(__DIR__ . '/' . $directory, 0775, true);
    }
    $path = $directory . '/' . bin2hex(random_bytes(12)) . '.' . $extension;
    move_uploaded_file($tmp, __DIR__ . '/' . $path);
    return $path;
}

function app_shell_topbar(string $title, ?array $user = null): string
{
    if ($user === null) {
        $user = auth_user();
    }
    ob_start(); ?>
    <div class="topbar">
      <h2><?= h($title) ?></h2>
      <details class="user-menu">
        <summary class="user-chip">
          <?= avatar_html($user) ?>
          <span><?= h(current_user_name($user)) ?></span>
        </summary>
        <div class="profile-card">
          <?= avatar_html($user, 'avatar profile-card-avatar') ?>
          <strong><?= h(current_user_name($user)) ?></strong>
          <small><?= is_admin($user) ? 'Main Admin' : 'Employee' ?></small>
          <div class="profile-card-actions">
            <a class="button secondary compact" href="#profile-modal">Update</a>
            <form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><button class="button ghost compact" type="submit">Sign out</button></form>
          </div>
        </div>
      </details>
    </div>
    <?= profile_modal($user) ?>
    <?php return (string) ob_get_clean();
}

function sidebar_nav(string $active, ?array $user = null): string
{
    if ($user === null) {
        $user = auth_user();
    }
    ob_start(); ?>
    <nav class="nav">
      <?php if (can('create', $user)): ?><a class="<?= $active === 'automation' ? 'active' : '' ?>" href="automation.php"><span class="nav-icon"><?= nav_icon('create') ?></span>Automation</a><?php endif; ?>
      <?php if (is_admin($user)): ?><a class="<?= $active === 'categories' ? 'active' : '' ?>" href="categories.php"><span class="nav-icon"><?= nav_icon('news') ?></span>Categories</a><?php endif; ?>
      <a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="index.php"><span class="nav-icon"><?= nav_icon('report') ?></span>Dashboard</a>
      <?php if (can('view', $user)): ?><a class="<?= $active === 'newsletters' ? 'active' : '' ?>" href="newsletters.php"><span class="nav-icon"><?= nav_icon('news') ?></span>Newsletters</a><?php endif; ?>
      <?php if (can('create', $user)): ?><a class="<?= $active === 'create' ? 'active' : '' ?>" href="editor.php"><span class="nav-icon"><?= nav_icon('create') ?></span>Create</a><?php endif; ?>
      <?php if (can('view', $user)): ?><a class="<?= $active === 'report' ? 'active' : '' ?>" href="report.php"><span class="nav-icon"><?= nav_icon('report') ?></span>Report</a><?php endif; ?>
      <?php if (is_admin($user)): ?><a class="<?= $active === 'employees' ? 'active' : '' ?>" href="employees.php"><span class="nav-icon"><?= nav_icon('users') ?></span>Employees</a><?php endif; ?>
    </nav>
    <?php return (string) ob_get_clean();
}

function profile_modal(array $user): string
{
    ob_start(); ?>
    <div id="profile-modal" class="modal-shell">
      <form class="profile-modal" method="post" action="profile_update.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <div class="modal-head">
          <h2>Profile</h2>
          <a href="#" aria-label="Close">x</a>
        </div>
        <?php if (($_GET['profile_error'] ?? '') === 'old_password'): ?>
          <div class="login-error profile-message">Current password is required and must be correct.</div>
        <?php endif; ?>
        <?php if (($_GET['profile_error'] ?? '') === 'username'): ?>
          <div class="login-error profile-message">Username already exists. Please choose another username.</div>
        <?php endif; ?>
        <div class="profile-form-grid">
          <label>Username</label>
          <input name="username" value="<?= h($user['username'] ?? '') ?>" required>

          <label>Password</label>
          <input type="password" name="new_password" autocomplete="new-password" placeholder="Enter new password only if changing">

          <label>Firstname</label>
          <input name="first_name" value="<?= h($user['first_name'] ?? '') ?>" required>

          <label>Lastname</label>
          <input name="last_name" value="<?= h($user['last_name'] ?? '') ?>">

          <label>Photo:</label>
          <input type="file" name="photo" accept="image/*">

          <div class="profile-divider"></div>

          <label>Current Password:</label>
          <input type="password" name="current_password" autocomplete="current-password" placeholder="input current password to save changes" required>
        </div>
        <div class="modal-actions">
          <a class="button secondary compact" href="#">Close</a>
          <button class="button compact" type="submit">Save Profile</button>
        </div>
      </form>
    </div>
    <?php return (string) ob_get_clean();
}
