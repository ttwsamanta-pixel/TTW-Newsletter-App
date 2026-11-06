<?php
require_once __DIR__ . '/auth.php';

require_permission('delete');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('newsletters.php'); }
verify_csrf();

$ids = array_values(array_unique(array_filter(array_map('intval', $_POST['ids'] ?? []))));

if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("DELETE FROM newsletters WHERE id IN ($placeholders)");
    $stmt->execute($ids);
}

redirect_to('newsletters.php');
