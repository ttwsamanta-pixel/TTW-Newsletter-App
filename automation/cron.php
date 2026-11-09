<?php
declare(strict_types=1);
// Select PHP script type in the hosting cron panel. This is not an HTTP endpoint.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('TTW_CRON_MODE', true);
require __DIR__ . '/worker.php';
