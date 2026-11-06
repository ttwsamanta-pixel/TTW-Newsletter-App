<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');

// Hosting-only credentials can be supplied through environment or an ignored local file.
if (is_file(__DIR__ . '/config.local.php')) { require_once __DIR__ . '/config.local.php'; }
defined('DB_HOST') || define('DB_HOST', getenv('TTW_DB_HOST') ?: 'localhost');
defined('DB_NAME') || define('DB_NAME', getenv('TTW_DB_NAME') ?: '');
defined('DB_USER') || define('DB_USER', getenv('TTW_DB_USER') ?: '');
defined('DB_PASS') || define('DB_PASS', getenv('TTW_DB_PASSWORD') ?: '');

define('APP_BASE_URL', getenv('TTW_APP_BASE_URL') ?: 'https://newsletter.travelandtourworld.com');
define('NEWSLETTER_BASE_URL', getenv('TTW_NEWSLETTER_BASE_URL') ?: 'https://www.travelandtourworld.com/newsletter');
const ASSET_VERSION = '20260924a';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . (getenv('TTW_DB_HOST') ?: DB_HOST) . ';port=' . (getenv('TTW_DB_PORT') ?: '3306') . ';dbname=' . (getenv('TTW_DB_NAME') ?: DB_NAME) . ';charset=utf8mb4';
    $pdo = new PDO($dsn, getenv('TTW_DB_USER') ?: DB_USER, getenv('TTW_DB_PASSWORD') !== false ? getenv('TTW_DB_PASSWORD') : DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function ensure_promotion_columns(): void
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'newsletters'
         AND COLUMN_NAME IN ('promotion_width', 'promotion_height')"
    );
    $stmt->execute();
    $columns = array_column($stmt->fetchAll(), 'COLUMN_NAME');

    if (!in_array('promotion_width', $columns, true)) {
        $pdo->exec('ALTER TABLE newsletters ADD COLUMN promotion_width INT UNSIGNED NOT NULL DEFAULT 200 AFTER promotion_image');
    }

    if (!in_array('promotion_height', $columns, true)) {
        $pdo->exec('ALTER TABLE newsletters ADD COLUMN promotion_height INT UNSIGNED NOT NULL DEFAULT 170 AFTER promotion_width');
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function asset_url(string $path): string
{
    return $path . '?v=' . ASSET_VERSION;
}

function nav_icon(string $name): string
{
    $icons = [
        'news' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v13A2.5 2.5 0 0 1 17.5 21h-11A2.5 2.5 0 0 1 4 18.5v-13Z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>',
        'create' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/><path d="M5 4h14v16H5z"/></svg>',
        'home' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 11.5 12 5l8 6.5"/><path d="M6.5 10.5V20h11v-9.5"/><path d="M10 20v-6h4v6"/></svg>',
        'html' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M5 20h14"/></svg>',
        'report' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5z"/><path d="M8 14v3M12 10v7M16 7v10"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M21 3v18"/></svg>',
    ];

    return $icons[$name] ?? '';
}

function issue_code(string $date, string $edition): string
{
    return date('d', strtotime($date)) . strtoupper($edition);
}

function issue_file_name(string $date, string $edition): string
{
    return 'TTW_newsletter_' . date('dMY', strtotime($date)) . strtoupper($edition) . '.html';
}

function issue_month_path(string $date): string
{
    return date('Y/M', strtotime($date));
}

function public_upload_url(?string $path): string
{
    if (!$path) {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    $base = APP_BASE_URL ?: current_base_url();
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function public_upload_preview_url(?string $path): string
{
    $url = public_upload_url($path);
    if (!$url || preg_match('/^https?:\/\//i', (string) $path)) {
        return $url;
    }

    $fullPath = __DIR__ . '/' . ltrim((string) $path, '/');
    if (!is_file($fullPath)) {
        return $url;
    }

    return $url . (strpos($url, '?') !== false ? '&' : '?') . 'v=' . filemtime($fullPath);
}

function current_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . ($dir === '' ? '' : $dir);
}

function upload_month_path(string $date): string
{
    return 'uploads/' . date('Y/M', strtotime($date));
}

function save_cropped_image(?string $dataUrl, string $fileName, string $date, ?string $existing = null): ?string
{
    if (!$dataUrl || strpos($dataUrl, 'data:image/') !== 0) {
        return $existing;
    }

    if (!preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,(.+)$/', $dataUrl, $matches)) {
        return $existing;
    }

    $binary = base64_decode($matches[2], true);
    if ($binary === false) {
        return $existing;
    }
    $info = @getimagesizefromstring($binary);
    if (strlen($binary) > 8 * 1024 * 1024 || !$info || $info[0] * $info[1] > 20000000
        || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new InvalidArgumentException('Choose a valid image under 8 MB and 20 megapixels.');
    }

    $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
    $fileName = preg_replace('/\.[A-Za-z0-9]+$/', '.' . $extension, $fileName);
    $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $fileName);
    $directory = upload_month_path($date);
    if (!is_dir(__DIR__ . '/' . $directory)) {
        mkdir(__DIR__ . '/' . $directory, 0775, true);
    }

    $path = $directory . '/' . $safeName;
    delete_local_upload($existing);
    delete_local_upload($path);
    if (file_put_contents(__DIR__ . '/' . $path, $binary, LOCK_EX) === false) {
        throw new RuntimeException('Image could not be saved.');
    }
    clearstatcache(true, __DIR__ . '/' . $path);

    return $path;
}

function delete_local_upload(?string $path): void
{
    if (!$path || preg_match('/^https?:\/\//i', $path)) {
        return;
    }

    $fullPath = realpath(__DIR__ . '/' . ltrim($path, '/'));
    $uploadRoot = realpath(__DIR__ . '/uploads');
    if (!$fullPath || !$uploadRoot || strpos($fullPath, $uploadRoot . DIRECTORY_SEPARATOR) !== 0) {
        return;
    }

    if (is_file($fullPath)) {
        unlink($fullPath);
        clearstatcache(true, $fullPath);
    }
}

function redirect_to(string $path): never
{
    header('Location: ' . $path);
    exit;
}
