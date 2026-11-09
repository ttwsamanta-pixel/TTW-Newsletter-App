<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('TTW_DB_PORT') !== '33318' || getenv('TTW_DB_NAME') !== 'ttw_automation_test') {
    exit('Use the isolated fixture server on port 33318 only.');
}
require_once dirname(__DIR__) . '/includes/api-diagnostics.php';
$server = new PDO('mysql:host=127.0.0.1;port=33318;charset=utf8mb4', getenv('TTW_DB_USER'), getenv('TTW_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
function repair_sql(PDO $pdo, string $file): array {
    $sql = preg_replace('/^[\t ]*--[^\r\n]*(?:\r?\n|$)/m', '', file_get_contents($file));
    $results = [];
    foreach (explode(';', $sql) as $statement) {
        if (trim($statement) === '') continue;
        $query = $pdo->query($statement);
        if ($query->columnCount()) $results = array_merge($results, $query->fetchAll(PDO::FETCH_ASSOC));
        $query->closeCursor();
    }
    return $results;
}
function repair_assert(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
}
$base = 'ttw_schema_repair_' . bin2hex(random_bytes(4));
$created = [];
try {
    foreach (['missing', 'indexed', 'duplicates'] as $mode) {
        $database = $base . '_' . $mode;
        $server->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4');
        $created[] = $database;
        $server->exec('USE `' . $database . '`');
        repair_sql($server, __DIR__ . '/fixture-schema.sql');
        $server->exec("INSERT INTO newsletters(newsletter_date,edition,subject_title) VALUES('2026-11-09','M','Preserve original issue')");
        if ($mode === 'indexed') $server->exec('CREATE UNIQUE INDEX legacy_issue_unique ON newsletters(edition,newsletter_date)');
        if ($mode === 'duplicates') $server->exec("INSERT INTO newsletters(newsletter_date,edition,subject_title) VALUES('2026-11-09','M','Preserve duplicate too')");
        $before = automation_setup_check($server);
        repair_assert(count($before['missing_tables']) === 5 && count($before['missing_columns']) === 2, 'Fixture does not match the reported production setup.');
        $result = repair_sql($server, dirname(__DIR__) . '/migrations/002_complete_automation_setup.sql');
        repair_assert(automation_setup_check($server)['ready'], 'Repair did not complete the required schema.');
        repair_assert((int)$server->query('SELECT COUNT(*) FROM newsletters')->fetchColumn() === ($mode === 'duplicates' ? 2 : 1), 'Existing newsletter rows changed.');
        $server->exec("UPDATE newsletter_categories_master SET category_name='Custom Tourism',aliases='My alias',active=0 WHERE category_slug='tourism-news'");
        $server->exec("UPDATE newsletters SET approved_at='2026-11-09 12:00:00',approved_by=42");
        repair_sql($server, dirname(__DIR__) . '/migrations/002_complete_automation_setup.sql');
        repair_assert((int)$server->query('SELECT COUNT(*) FROM newsletter_categories_master')->fetchColumn() === 6, 'Rerun duplicated master categories.');
        $category = $server->query("SELECT * FROM newsletter_categories_master WHERE category_slug='tourism-news'")->fetch(PDO::FETCH_ASSOC);
        repair_assert($category['category_name'] === 'Custom Tourism' && $category['aliases'] === 'My alias' && (int)$category['active'] === 0, 'Rerun overwrote category settings.');
        repair_assert((int)$server->query('SELECT MIN(approved_by) FROM newsletters')->fetchColumn() === 42, 'Rerun reset approval data.');
        $indexes = $server->query("SELECT DISTINCT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletters' AND NON_UNIQUE=0 AND INDEX_NAME<>'PRIMARY'")->fetchAll(PDO::FETCH_COLUMN);
        repair_assert(count($indexes) === ($mode === 'duplicates' ? 0 : 1), 'Unique index was duplicated or applied over duplicate records.');
        if ($mode === 'indexed') repair_assert($indexes[0] === 'legacy_issue_unique', 'Existing index was not retained.');
        if ($mode === 'duplicates') repair_assert(str_contains(json_encode($result), 'ACTION REQUIRED'), 'Duplicate records did not produce a visible migration warning.');
        echo 'PASS: ' . $mode . " schema, repeated import, and existing data preserved.\n";
    }
} finally {
    foreach ($created as $database) {
        if (!str_starts_with($database, $base . '_')) throw new RuntimeException('Unexpected cleanup target.');
        $server->exec('DROP DATABASE `' . $database . '`');
    }
}
