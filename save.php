<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/newsletter-service.php';
require_once __DIR__ . '/includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('index.php');
}

$currentUser = require_auth();
verify_csrf();
$autosave = ($_POST['autosave'] ?? '') === '1';

ensure_promotion_columns();
$pdo = db();
$pdo->beginTransaction();

try {
    $requestedId = (int) ($_POST['id'] ?? 0);
    require_permission($requestedId ? 'edit' : 'create');
    $id = persist_newsletter($pdo, $_POST, $currentUser, $autosave);
    $pdo->commit();
    if ($autosave) {
        header('Content-Type: application/json');
        echo json_encode(['id' => $id]);
        exit;
    }
    $afterSave = $_POST['after_save'] ?? 'view';
    redirect_to(($afterSave === 'edit' ? 'editor.php?id=' : 'view.php?id=') . $id);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    if ($autosave) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Draft could not be saved. Please check the date and edition and try again.']);
        exit;
    }
    echo '<h1>Save failed</h1><p>' . h($e->getMessage()) . '</p>';
}
