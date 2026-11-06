<?php
require_once __DIR__ . '/newsletter_renderer.php';
require_once __DIR__ . '/auth.php';

require_permission('download');

$id = (int) ($_GET['id'] ?? 0);
$newsletter = $id ? load_newsletter($id) : null;
if (!$newsletter) {
    http_response_code(404);
    echo 'Newsletter not found';
    exit;
}

$fileName = issue_file_name($newsletter['newsletter_date'], $newsletter['edition']);
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
echo render_newsletter($newsletter);
