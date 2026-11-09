<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('TTW_DB_NAME') !== 'ttw_automation_test' || getenv('TTW_DB_PORT') !== '33318') {
    exit('Use the isolated test database on port 33318 only.');
}
require_once dirname(__DIR__) . '/config.php';
function setup_request(string $route, ?array $data = null, ?string $token = null): array {
    $ch = curl_init('http://127.0.0.1:8088/api/index.php?route=' . urlencode($route));
    $headers = ['Accept: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    if ($data !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 15]);
    $body = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [$status, json_decode((string)$body, true)];
}
function setup_assert(bool $value, string $label): void {
    if (!$value) throw new RuntimeException($label);
}
setup_assert(db()->query('SELECT DATABASE()')->fetchColumn() === 'ttw_automation_test', 'Wrong fixture database.');
[$status, $admin] = setup_request('/auth/login', ['username' => 'test_admin', 'password' => 'ttw_test_password']);
setup_assert($status === 200, 'Admin login failed.');
[$status, $viewer] = setup_request('/auth/login', ['username' => 'test_viewer', 'password' => 'ttw_test_password']);
setup_assert($status === 200, 'Viewer login failed.');
[$status, $report] = setup_request('/system/check', null, $admin['token']);
setup_assert($status === 200 && $report['ready'] === true, 'Complete schema was not recognized.');
[$status] = setup_request('/system/check', null, $viewer['token']);
setup_assert($status === 403, 'Non-admin received deployment details.');
db()->exec('RENAME TABLE api_rate_limits TO diagnostics_saved_limits');
try {
    [$status, $report] = setup_request('/system/check', null, $admin['token']);
    setup_assert($status === 200 && !$report['ready'] && in_array('api_rate_limits', $report['missing_tables'], true), 'Diagnostic did not work with missing rate-limit table.');
    [$status, $failure] = setup_request('/categories', null, $admin['token']);
    setup_assert($status === 503 && str_contains($failure['error'], 'setup is incomplete'), 'Missing schema still returns an unhelpful generic error.');
} finally {
    db()->exec('RENAME TABLE diagnostics_saved_limits TO api_rate_limits');
}
[$status, $report] = setup_request('/system/check', null, $admin['token']);
setup_assert($status === 200 && $report['ready'] === true, 'Fixture schema was not restored.');
echo "PASS: ready/missing-schema diagnostics, admin-only access, actionable API errors and schema restoration.\n";
