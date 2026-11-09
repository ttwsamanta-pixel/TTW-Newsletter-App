<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method,['GET','HEAD'],true)) { http_response_code(405); header('Allow: GET, HEAD'); exit; }
$kind=$_GET['kind'] ?? 'header';
if (!is_string($kind)) { http_response_code(400); exit('Invalid image kind.'); }
if (!in_array($kind,['header','pick','promotion','profile'],true)) { http_response_code(400); exit('Invalid image kind.'); }
$raw=$_GET['path'] ?? '';
if (!is_string($raw) || strlen($raw)>4096) { http_response_code(400); exit('Invalid image path.'); }
$path=$raw==='' ? null : uploaded_image_path($raw);
if ($raw!=='' && $path===null) { http_response_code(400); exit('Invalid image path.'); }
$image=$path ? resolve_uploaded_image($path,$kind) : image_placeholder($kind);
$etag='"' . hash('sha256',$image['source'] . $image['bytes']) . '"';
header('Content-Type: ' . $image['mime']);
header('X-Content-Type-Options: nosniff');
header('X-TTW-Image-Source: ' . $image['source']);
// Revalidate on reload so a newly uploaded New image replaces a cached legacy/placeholder image.
header('Cache-Control: public, no-cache');
header('ETag: ' . $etag);
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''))===$etag) { http_response_code(304); exit; }
header('Content-Length: ' . strlen($image['bytes']));
if ($method!=='HEAD') echo $image['bytes'];
