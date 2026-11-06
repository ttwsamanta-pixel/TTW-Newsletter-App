<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/jobs.php';
$once=in_array('--once',$argv,true);
do {
    try { $worked=worker_tick(); }
    catch(Throwable $e) { fwrite(STDERR,"Worker error; verify database migration and extensions.\n"); $worked=false; }
    if (!$worked && !$once) sleep(2);
} while (!$once);
