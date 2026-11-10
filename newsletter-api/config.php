<?php
declare(strict_types=1);
// Main website only. Production secrets belong in config.local.php or environment.
if (is_file(__DIR__.'/config.local.php')) require_once __DIR__.'/config.local.php';
defined('NEWSLETTER_UPLOAD_TOKEN') || define('NEWSLETTER_UPLOAD_TOKEN',getenv('NEWSLETTER_UPLOAD_TOKEN') ?: '');
// Both public_html/newsletter-api and public_html/newsletter/newsletter-api are supported.
function newsletter_default_html_root(string $apiDirectory): string {
    $parent=dirname(rtrim($apiDirectory,'/\\'));
    return basename($parent)==='newsletter' ? $parent : $parent.'/newsletter';
}
defined('NEWSLETTER_HTML_ROOT') || define('NEWSLETTER_HTML_ROOT',getenv('NEWSLETTER_HTML_ROOT') ?: newsletter_default_html_root(__DIR__));
defined('NEWSLETTER_PUBLIC_BASE') || define('NEWSLETTER_PUBLIC_BASE',getenv('NEWSLETTER_PUBLIC_BASE') ?: 'https://www.travelandtourworld.com/newsletter');
