<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('TTW_DB_NAME') !== 'ttw_automation_test' || getenv('TTW_DB_PORT') !== '33318') {
    exit('Use the isolated fixture database on port 33318 only.');
}
require_once dirname(__DIR__) . '/automation/jobs.php';
$root = dirname(__DIR__);
function cli_assert(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException($label);
}
function cli_worker(array $arguments): array {
    $process = proc_open(array_merge([PHP_BINARY], $arguments), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) throw new RuntimeException('Could not launch worker fixture.');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    return [proc_close($process), $output, $error];
}
cli_assert((int)db()->query("SELECT COUNT(*) FROM automation_jobs WHERE status IN ('pending','processing')")->fetchColumn() === 0, 'Fixture must have no unfinished jobs before this test.');
[$status, $output] = cli_worker(['automation/cron.php']);
cli_assert($status === 0 && str_contains($output, '0 article(s)'), 'Empty cron did not exit cleanly.');
$lock = fopen($root . '/storage/automation-worker.lock', 'c');
flock($lock, LOCK_EX);
try {
    [$status, $output] = cli_worker(['automation/cron.php']);
    cli_assert($status === 0 && str_contains($output, 'already running'), 'Overlapping cron did not skip.');
} finally { flock($lock, LOCK_UN); fclose($lock); }
$user = db()->query('SELECT * FROM users WHERE username="test_admin"')->fetch();
$id = create_job([
    'newsletter_date' => '2092-11-09', 'edition' => 'M', 'subject_title' => 'CLI worker fixture', 'hidden_text' => 'Original article test',
    'text' => "header:\nhttps://www.travelandtourworld.com/news/article/tbfqplg7jbyu/\nPick News:\nhttps://www.travelandtourworld.com/news/article/sy1fa7hprgfw/",
], $user);
[$status, $output, $error] = cli_worker(['automation/worker.php', '--drain', '--max-articles=1']);
$job = read_job($id, $user);
cli_assert($status === 0 && (int)$job['completed'] === 1 && $job['status'] === 'pending', 'Bounded worker did not stop after one article: ' . $error);
[$status, $output, $error] = cli_worker(['automation/cron.php']);
$job = read_job($id, $user);
cli_assert($status === 0 && (int)$job['completed'] === 2 && in_array($job['status'], ['completed', 'completed_with_warnings'], true), 'Cron did not finish the remaining original article: ' . $error);
echo "PASS: empty exit, overlap lock, one-article bound and real queued article drain.\n";
