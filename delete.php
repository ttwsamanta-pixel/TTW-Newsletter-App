<?php
require_once __DIR__ . '/auth.php';

require_permission('delete');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('newsletters.php'); }
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id) {
    $stmt = db()->prepare('DELETE FROM newsletters WHERE id = ?');
    $stmt->execute([$id]);
}

redirect_to('newsletters.php');
