<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/api-diagnostics.php';
$cases = [1146 => 503, 1054 => 503, 1045 => 503, 2002 => 503, 1062 => 500];
foreach ($cases as $number => $expected) {
    $e = new PDOException('sensitive SQL and password must never be returned');
    $e->errorInfo = ['HY000', $number, 'private driver details'];
    [$status, $message] = api_database_failure($e);
    if ($status !== $expected || str_contains($message, 'sensitive') || str_contains($message, 'private')) {
        throw new RuntimeException('Database diagnostic classification failed.');
    }
}
echo "PASS: 5 database failure classifications; private exception details excluded.\n";
