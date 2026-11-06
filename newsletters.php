<?php
require_once __DIR__ . '/auth.php';

$currentUser = require_permission('view');

$rows = [];
$error = null;
$page = max(1, (int) ($_GET['page'] ?? 1));
$allowedPerPage = [10, 25, 50, 100];
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 10;
}
$totalRows = 0;
$totalPages = 1;
$offset = ($page - 1) * $perPage;
try {
    $totalRows = (int) db()->query('SELECT COUNT(*) FROM newsletters')->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    if ($page > $totalPages) {
        redirect_to('newsletters.php?page=' . $totalPages . '&per_page=' . $perPage);
    }

    $rows = db()->query(
        'SELECT n.*, COALESCE(NULLIF(TRIM(CONCAT(u.first_name, " ", u.last_name)), ""), u.username, "Unknown") AS creator_name
         FROM newsletters n
         LEFT JOIN users u ON u.id = n.created_by
         ORDER BY n.newsletter_date DESC, n.edition DESC
         LIMIT ' . $perPage . ' OFFSET ' . $offset
    )->fetchAll();
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>All Newsletters</title>
<link rel="stylesheet" href="<?= h(asset_url('assets/app.css')) ?>">
<style>
.newsletter-list-tools{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:0 0 18px;padding:14px 16px;background:linear-gradient(135deg,#f8fbff,#fff);border:1px solid #dbe6f3;border-radius:16px;box-shadow:0 10px 26px rgba(15,23,42,.05)}
.newsletter-total{font-weight:900;color:#334155}
.newsletter-total span{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;margin-right:8px;border-radius:10px;background:#e8f2ff;color:#2563eb}
.newsletter-page-size{display:flex;align-items:center;gap:10px;font-weight:900;color:#475569}
.newsletter-page-size select{height:42px;border:1px solid #c9d7ea;border-radius:12px;background:#fff;color:#0f172a;font-weight:900;padding:0 38px 0 14px;outline:none;box-shadow:0 8px 18px rgba(15,23,42,.05)}
.newsletter-page-size select:focus{border-color:#38bdf8;box-shadow:0 0 0 4px rgba(56,189,248,.16)}
.newsletter-pager{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:0;padding:18px 0 0;border-top:1px solid #e4e9f2}
.newsletter-pager-count{font-weight:900;color:#64748b}
.newsletter-pager-buttons{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.pager-button{height:38px;border-radius:12px;border:1px solid #d6e2f1;background:#fff;color:#1e3a5f;text-decoration:none;font-weight:900;padding:0 14px;display:inline-flex;align-items:center;justify-content:center;gap:6px;box-shadow:0 8px 18px rgba(15,23,42,.06);transition:.18s ease}
.pager-button:hover{transform:translateY(-1px);border-color:#60a5fa;background:#eff6ff;color:#1d4ed8}
.pager-button.disabled{opacity:.45;pointer-events:none;transform:none;box-shadow:none}
.pager-button.primary{background:linear-gradient(135deg,#2563eb,#0891b2);border-color:transparent;color:#fff;box-shadow:0 12px 24px rgba(37,99,235,.22)}
.pager-status{height:38px;border-radius:999px;background:#edf3fb;color:#334155;border:1px solid #dbe6f3;font-weight:900;padding:0 16px;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap}
@media (max-width:860px){.newsletter-list-tools,.newsletter-pager{align-items:stretch;flex-direction:column}.newsletter-page-size{justify-content:space-between}.newsletter-page-size select{min-width:160px}.newsletter-pager-count{text-align:center}.newsletter-pager-buttons{justify-content:center}.pager-button{padding:0 11px}}
</style>
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand"><div class="logo"><img style="width: 40px;   /* adjust size */
  height: 40px;
  object-fit: contain; " src="TTW-Logo.png" alt="Logo"></div><h1>Newsletter Admin</h1></div>
    <?= sidebar_nav('newsletters', $currentUser) ?>
  </aside>
  <main>
    <?= app_shell_topbar('All Newsletters', $currentUser) ?>
    <div class="content">
      <div class="toolbar">
        <div>
          <h2 style="margin:0 0 6px">Newsletter Library</h2>
          <div class="muted">View, edit, delete and download generated HTML files.</div>
        </div>
        <div class="toolbar-actions">
          <?php if (can('delete', $currentUser)): ?>
            <button class="button danger" form="newsletterBulkForm" id="bulkDeleteButton" type="submit" disabled>Delete Selected</button>
          <?php endif; ?>
          <?php if (can('create', $currentUser)): ?><a class="button" href="editor.php">+ Add Newsletter</a><?php endif; ?>
        </div>
      </div>
      <div class="panel table-wrap">
        <?php if ($error): ?>
          <p>Please import <b>schema.sql</b> and update database credentials in <b>config.php</b>.</p>
          <p class="muted"><?= h($error) ?></p>
        <?php else: ?>
        <div class="newsletter-list-tools">
          <div class="newsletter-total"><span><?= h((string) $totalRows) ?></span>Total newsletters</div>
          <form class="newsletter-page-size" method="get" action="newsletters.php">
            <input type="hidden" name="page" value="1">
            <label for="perPageSelect">Show</label>
            <select id="perPageSelect" name="per_page" onchange="this.form.submit()">
              <?php foreach ($allowedPerPage as $option): ?>
                <option value="<?= $option ?>" <?= $option === $perPage ? 'selected' : '' ?>><?= $option ?> / page</option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>
        <form id="newsletterBulkForm" method="post" action="bulk-delete.php" onsubmit="return confirm(event.submitter?.name === 'id' ? 'Delete this newsletter?' : 'Delete selected newsletters?')">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <table class="table newsletter-table">
          <thead>
            <tr>
              <?php if (can('delete', $currentUser)): ?>
                <th class="select-col"><input type="checkbox" id="selectAllNewsletters" aria-label="Select all newsletters on this page"></th>
              <?php endif; ?>
              <th>File Name</th><th>Edition</th><th>Title</th><th>Date</th><th>Created By</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <?php if (can('delete', $currentUser)): ?>
                <td class="select-col"><input class="newsletter-select" type="checkbox" name="ids[]" value="<?= (int) $row['id'] ?>" aria-label="Select <?= h(issue_file_name($row['newsletter_date'], $row['edition'])) ?>"></td>
              <?php endif; ?>
              <td><?= h(issue_file_name($row['newsletter_date'], $row['edition'])) ?></td>
              <td><span class="pill <?= $row['edition'] === 'M' ? 'morning' : 'evening' ?>"><?= $row['edition'] === 'M' ? 'Morning' : 'Evening' ?></span></td>
              <td><?= h($row['subject_title']) ?><?php if (array_key_exists('approved_at', $row)): ?><br><small><?= $row['approved_at'] ? '✓ Approved' : 'Draft' ?></small><?php endif; ?></td>
              <td><?= h(date('d M Y', strtotime($row['newsletter_date']))) ?></td>
              <td><?= h($row['creator_name']) ?></td>
              <td class="actions">
                <a class="button ghost" href="view.php?id=<?= (int) $row['id'] ?>">View</a>
                <?php if (can('edit', $currentUser)): ?><a class="button secondary" href="editor.php?id=<?= (int) $row['id'] ?>">Edit</a><?php endif; ?>
                <?php if (can('download', $currentUser)): ?><a class="button" href="download.php?id=<?= (int) $row['id'] ?>">Download HTML</a><?php endif; ?>
                <?php if (can('download', $currentUser)): ?><button type="button" class="button ghost" data-copy-html="download.php?id=<?= (int) $row['id'] ?>">Copy HTML</button><?php endif; ?>
                <?php if (can('delete', $currentUser)): ?><button class="button danger" type="submit" formaction="delete.php" name="id" value="<?= (int) $row['id'] ?>">Delete</button><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="<?= can('delete', $currentUser) ? 7 : 6 ?>" class="muted">No newsletters yet. Create your first issue.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
        </form>
        <?php if ($totalPages > 1): ?>
          <div class="newsletter-pager">
            <span class="newsletter-pager-count">Showing <?= h((string) ($offset + 1)) ?>-<?= h((string) min($offset + $perPage, $totalRows)) ?> of <?= h((string) $totalRows) ?></span>
            <div class="newsletter-pager-buttons">
              <a class="pager-button <?= $page <= 1 ? 'disabled' : '' ?>" aria-label="First page" href="newsletters.php?page=1&per_page=<?= $perPage ?>">First</a>
              <a class="pager-button <?= $page <= 1 ? 'disabled' : '' ?>" aria-label="Previous page" href="newsletters.php?page=<?= max(1, $page - 1) ?>&per_page=<?= $perPage ?>">Prev</a>
              <span class="pager-status">Page <?= h((string) $page) ?> / <?= h((string) $totalPages) ?></span>
              <a class="pager-button primary <?= $page >= $totalPages ? 'disabled' : '' ?>" aria-label="Next page" href="newsletters.php?page=<?= min($totalPages, $page + 1) ?>&per_page=<?= $perPage ?>">Next</a>
              <a class="pager-button <?= $page >= $totalPages ? 'disabled' : '' ?>" aria-label="Last page" href="newsletters.php?page=<?= $totalPages ?>&per_page=<?= $perPage ?>">Last</a>
            </div>
          </div>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
<nav class="mobile-bottom-nav">
  <a class="active" href="newsletters.php"><span><?= nav_icon('news') ?></span>News</a>
  <a href="editor.php"><span><?= nav_icon('create') ?></span>Create</a>
  <a class="home" href="index.php"><span><?= nav_icon('home') ?></span>Home</a>
  <a href="newsletters.php"><span><?= nav_icon('html') ?></span>HTML</a>
  <a href="report.php"><span><?= nav_icon('report') ?></span>Report</a>
</nav>
<script src="<?= h(asset_url('assets/app.js')) ?>"></script>
</body>
</html>
