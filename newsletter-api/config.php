<?php
declare(strict_types=1);
// Main website only. Production secrets belong in config.local.php or environment.
if (is_file(__DIR__.'/config.local.php')) require_once __DIR__.'/config.local.php';
defined('NEWSLETTER_UPLOAD_TOKEN') || define('NEWSLETTER_UPLOAD_TOKEN',getenv('NEWSLETTER_UPLOAD_TOKEN') ?: '');
defined('NEWSLETTER_HTML_ROOT') || define('NEWSLETTER_HTML_ROOT',getenv('NEWSLETTER_HTML_ROOT') ?: dirname(__DIR__).'/newsletter');
defined('NEWSLETTER_PUBLIC_BASE') || define('NEWSLETTER_PUBLIC_BASE',getenv('NEWSLETTER_PUBLIC_BASE') ?: 'https://www.travelandtourworld.com/newsletter');
