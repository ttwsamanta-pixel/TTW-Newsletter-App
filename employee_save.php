<?php
require_once __DIR__ . '/auth.php';

$currentUser = require_auth();
if (!is_admin($currentUser)) {
    http_response_code(403);
    echo '<h1>Permission denied</h1>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('employees.php'); }
verify_csrf();
if (isset($_POST['delete'])) {
    $id = (int) $_POST['delete'];
    $stmt = db()->prepare('DELETE FROM users WHERE id = ? AND role = "employee"');
    $stmt->execute([$id]);
    redirect_to('employees.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('employees.php');
}

$id = (int) ($_POST['id'] ?? 0);
$permissions = array_values(array_intersect(PERMISSIONS, $_POST['permissions'] ?? []));

if ($id) {
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND role = "employee"');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        redirect_to('employees.php');
    }
    $photo = save_profile_photo($_FILES['photo'] ?? null, $existing['photo'] ?? null);
    $password = (string) ($_POST['password'] ?? '');
    if ($password !== '') {
        $stmt = db()->prepare('UPDATE users SET username=?, password_hash=?, first_name=?, last_name=?, contact_no=?, address=?, photo=?, permissions=? WHERE id=? AND role="employee"');
        $stmt->execute([trim($_POST['username']), password_hash($password, PASSWORD_DEFAULT), trim($_POST['first_name']), trim($_POST['last_name']), trim($_POST['contact_no']), trim($_POST['address']), $photo, json_encode($permissions), $id]);
    } else {
        $stmt = db()->prepare('UPDATE users SET username=?, first_name=?, last_name=?, contact_no=?, address=?, photo=?, permissions=? WHERE id=? AND role="employee"');
        $stmt->execute([trim($_POST['username']), trim($_POST['first_name']), trim($_POST['last_name']), trim($_POST['contact_no']), trim($_POST['address']), $photo, json_encode($permissions), $id]);
    }
} else {
    $photo = save_profile_photo($_FILES['photo'] ?? null);
    $stmt = db()->prepare('INSERT INTO users (username, password_hash, role, first_name, last_name, contact_no, address, photo, permissions) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([trim($_POST['username']), password_hash((string) $_POST['password'], PASSWORD_DEFAULT), 'employee', trim($_POST['first_name']), trim($_POST['last_name']), trim($_POST['contact_no']), trim($_POST['address']), $photo, json_encode($permissions)]);
}

redirect_to('employees.php');
