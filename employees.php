<?php
require_once __DIR__ . '/auth.php';

$currentUser = require_auth();
if (!is_admin($currentUser)) {
    http_response_code(403);
    echo '<h1>Permission denied</h1>';
    exit;
}

$editingId = (int) ($_GET['edit'] ?? 0);
$editing = null;
if ($editingId) {
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND role = "employee"');
    $stmt->execute([$editingId]);
    $editing = $stmt->fetch() ?: null;
}

$rows = db()->query('SELECT * FROM users ORDER BY role, first_name, username')->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Employees</title>
<link rel="stylesheet" href="<?= h(asset_url('assets/app.css')) ?>">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand"><div class="logo"><img style="width:40px;height:40px;object-fit:contain" src="TTW-Logo.png" alt="Logo"></div><h1>Newsletter Admin</h1></div>
    <?= sidebar_nav('employees', $currentUser) ?>
  </aside>
  <main>
    <?= app_shell_topbar('Employee Master', $currentUser) ?>
    <div class="content">
      <div class="panel employee-form-panel">
        <h2 style="margin-top:0"><?= $editing ? 'Edit Employee' : 'Add Employee' ?></h2>
        <form method="post" action="employee_save.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
          <div class="grid">
            <div class="field"><label>First Name</label><input name="first_name" value="<?= h($editing['first_name'] ?? '') ?>" required></div>
            <div class="field"><label>Last Name</label><input name="last_name" value="<?= h($editing['last_name'] ?? '') ?>"></div>
            <div class="field"><label>Contact No</label><input name="contact_no" value="<?= h($editing['contact_no'] ?? '') ?>"></div>
            <div class="field"><label>Username</label><input name="username" value="<?= h($editing['username'] ?? '') ?>" required></div>
            <div class="field"><label>Password <?= $editing ? '(leave blank to keep)' : '' ?></label><input type="password" name="password" <?= $editing ? '' : 'required' ?>></div>
            <div class="field"><label>Photo</label><input type="file" name="photo" accept="image/*"></div>
            <div class="field" style="grid-column:1/-1"><label>Address</label><textarea name="address"><?= h($editing['address'] ?? '') ?></textarea></div>
          </div>
          <div class="permission-grid">
            <?php $perms = $editing ? user_permissions($editing) : ['view']; ?>
            <?php foreach (PERMISSIONS as $permission): ?>
              <label><input type="checkbox" name="permissions[]" value="<?= h($permission) ?>" <?= in_array($permission, $perms, true) ? 'checked' : '' ?>> <?= h(ucfirst($permission)) ?> newsletters</label>
            <?php endforeach; ?>
          </div>
          <div class="toolbar" style="margin:18px 0 0"><button class="button" type="submit"><?= $editing ? 'Update Employee' : '+ Save Employee' ?></button><?php if ($editing): ?><a class="button secondary" href="employees.php">Cancel</a><?php endif; ?></div>
        </form>
      </div>

      <div class="panel table-wrap" style="margin-top:24px">
        <table class="table">
          <thead><tr><th>Photo</th><th>Name</th><th>Username</th><th>Role</th><th>Permissions</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= avatar_html($row, 'avatar table-avatar') ?></td>
              <td><?= h(current_user_name($row)) ?></td>
              <td><?= h($row['username']) ?></td>
              <td><?= h(ucfirst($row['role'])) ?></td>
              <td><?= is_admin($row) ? 'All' : h(implode(', ', user_permissions($row))) ?></td>
              <td class="actions">
                <?php if ($row['role'] === 'employee'): ?>
                  <a class="button secondary" href="employees.php?edit=<?= (int) $row['id'] ?>">Edit</a>
                  <form method="post" action="employee_save.php" onsubmit="return confirm('Delete employee?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><button class="button danger" name="delete" value="<?= (int) $row['id'] ?>">Delete</button></form>
                <?php else: ?>
                  <span class="muted">Main admin</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
</body>
</html>
