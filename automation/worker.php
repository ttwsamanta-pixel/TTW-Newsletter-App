<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/jobs.php';
$options = getopt('', ['once', 'drain', 'max-seconds:', 'max-articles:']);
$once = array_key_exists('once', $options);
$drain = (defined('TTW_CRON_MODE') && TTW_CRON_MODE) || array_key_exists('drain', $options);
$maxSeconds = max(1, min(300, (int)($options['max-seconds'] ?? 50)));
$maxArticles = max(1, min(500, (int)($options['max-articles'] ?? 50)));

// Cron may fire again while a slow article is still finishing. Never overlap local workers.
$storage = dirname(__DIR__) . '/storage';
if (!is_dir($storage) && !@mkdir($storage, 0775, true)) {
    fwrite(STDERR, "Worker cannot create storage. Check directory permissions.\n"); exit(1);
}
$lock = @fopen($storage . '/automation-worker.lock', 'c');
if ($lock === false) {
    fwrite(STDERR, "Worker cannot open its lock file. Check storage permissions.\n"); exit(1);
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    fclose($lock);
    fwrite(STDOUT, "Worker is already running; this invocation was skipped.\n"); exit(0);
}
$started = microtime(true);
$processed = 0;
$exitCode = 0;
try {
    do {
        if ($drain && microtime(true) - $started >= $maxSeconds) break;
        try {
            $worked = worker_tick();
            @file_put_contents($storage.'/worker-heartbeat.json',json_encode(['time'=>time(),'checked_at'=>date(DATE_ATOM)]),LOCK_EX);
        }
        catch (Throwable $e) {
            fwrite(STDERR, "Worker error; verify database configuration, migration and PHP extensions.\n");
            $exitCode = 1;
            break;
        }
        if ($worked) $processed++;
        if ($once || ($drain && (!$worked || $processed >= $maxArticles))) break;
        if (!$worked) sleep(2);
    } while (true);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
fwrite(STDOUT, 'Worker finished: ' . $processed . " article(s) processed.\n");
exit($exitCode);
