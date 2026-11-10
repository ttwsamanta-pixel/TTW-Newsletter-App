<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/publishing.php';
require_once dirname(__DIR__).'/newsletter-api/config.php';
$checks=0;
function nested_check(bool $ok,string $label): void {global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
foreach(['/newsletter-api/upload.php','/newsletter/newsletter-api/upload.php'] as $path) nested_check(publishing_endpoint_allowed('https://www.travelandtourworld.com'.$path),'Supported HTTPS receiver accepted');
foreach(['http://www.travelandtourworld.com/newsletter/newsletter-api/upload.php','https://evil.example/newsletter/newsletter-api/upload.php','https://www.travelandtourworld.com.evil.example/newsletter-api/upload.php','https://user:secret@www.travelandtourworld.com/newsletter-api/upload.php','https://www.travelandtourworld.com:444/newsletter-api/upload.php','https://www.travelandtourworld.com/newsletter-api/upload.php?redirect=evil','https://www.travelandtourworld.com/newsletter-api/upload.php#fragment','https://www.travelandtourworld.com/newsletter/../newsletter-api/upload.php','https://www.travelandtourworld.com/other/upload.php'] as $url) nested_check(!publishing_endpoint_allowed($url),'Unapproved receiver rejected');
nested_check(!publishing_endpoint_allowed('http://127.0.0.1:8091/newsletter-api/upload.php'),'Production rejects loopback HTTP');
nested_check(publishing_endpoint_allowed('http://127.0.0.1:8091/newsletter/newsletter-api/upload.php',true),'Explicit local test receiver supported');
nested_check(newsletter_default_html_root('/public_html/newsletter-api')==='/public_html/newsletter','Original location uses original HTML folder');
nested_check(newsletter_default_html_root('/public_html/newsletter/newsletter-api')==='/public_html/newsletter','Nested location avoids newsletter/newsletter destination');
echo "PASS: $checks nested publishing URL/path checks\n";
