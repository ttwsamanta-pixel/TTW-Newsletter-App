<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth.php';

function valid_issue_date(string $date): bool {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}
