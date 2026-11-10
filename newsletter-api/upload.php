<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
require_once __DIR__.'/service.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
function upload_reply(array $body,int $status): never { http_response_code($status); echo json_encode($body); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') upload_reply(['success'=>false,'message'=>'POST required.'],405);
$local=in_array($_SERVER['REMOTE_ADDR'] ?? '',['127.0.0.1','::1'],true) && getenv('TTW_ALLOW_LOCAL_HTTP')==='1';
if ((empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') && !$local) upload_reply(['success'=>false,'message'=>'HTTPS required.'],426);
if (strlen(NEWSLETTER_UPLOAD_TOKEN)<32 || str_starts_with(NEWSLETTER_UPLOAD_TOKEN,'REPLACE_')) upload_reply(['success'=>false,'message'=>'Upload API is not configured.'],503);
if (!hash_equals(NEWSLETTER_UPLOAD_TOKEN,(string)($_SERVER['HTTP_X_NEWSLETTER_TOKEN'] ?? ''))) upload_reply(['success'=>false,'message'=>'Unauthorized.'],401);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>2200000) upload_reply(['success'=>false,'message'=>'File too large.'],413);
try { upload_reply(receive_newsletter_html($_POST,$_FILES['html'] ?? []),200); }
catch (DomainException $e) { upload_reply(['success'=>false,'code'=>'FILE_EXISTS','message'=>'Newsletter HTML already exists.'],409); }
catch (LengthException $e) { upload_reply(['success'=>false,'message'=>$e->getMessage()],413); }
catch (InvalidArgumentException $e) { upload_reply(['success'=>false,'message'=>$e->getMessage()],422); }
catch (Throwable $e) { upload_reply(['success'=>false,'message'=>'Unable to upload newsletter.'],500); }
