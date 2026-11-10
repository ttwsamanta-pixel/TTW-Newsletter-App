<?php
require_once __DIR__ . '/newsletter_renderer.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/publishing.php';

$currentUser = require_permission('view');

$id = (int) ($_GET['id'] ?? 0);
$newsletter = $id ? load_newsletter($id) : null;
if (!$newsletter) {
    http_response_code(404);
    echo 'Newsletter not found';
    exit;
}
$html = render_newsletter($newsletter);
$publication=null;
try { $publication=publication_data($id); } catch (PDOException $e) { /* Existing preview works before the additive migration. */ }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Preview <?= h(issue_file_name($newsletter['newsletter_date'], $newsletter['edition'])) ?></title>
<link rel="stylesheet" href="<?= h(asset_url('assets/app.css')) ?>">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand"><div class="logo"><img style="width:40px;height:40px;object-fit:contain" src="TTW-Logo.png" alt="Logo"></div><h1>Newsletter Admin</h1></div>
    <?= sidebar_nav('newsletters', $currentUser) ?>
  </aside>
  <main>
    <?= app_shell_topbar('Preview Newsletter', $currentUser) ?>
    <div class="content preview-content">
      <div class="toolbar">
        <div>
          <h2 style="margin:0 0 6px"><?= h(issue_file_name($newsletter['newsletter_date'], $newsletter['edition'])) ?></h2>
          <div class="muted">Newsletter preview with current saved images.</div>
        </div>
        <div class="actions">
          <a class="button secondary" href="newsletters.php">Newsletters</a>
          <?php if (can('edit', $currentUser)): ?><a class="button secondary" href="editor.php?id=<?= $id ?>">Edit</a><?php endif; ?>
          <?php if (can('download', $currentUser)): ?><a class="button" href="download.php?id=<?= $id ?>">Download HTML</a><?php endif; ?>
          <?php if (can('edit',$currentUser) && can('download',$currentUser)): ?>
          <?php if (!$newsletter['approved_at']): ?><form method="post" action="publish.php"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="approve"><button class="button secondary">Approve Newsletter</button></form><?php endif; ?>
          <form method="post" action="publish.php"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $id ?>"><button class="button" <?= !$newsletter['approved_at'] ? 'disabled':'' ?>>Publish HTML</button></form>
          <?php if (is_admin($currentUser)): ?><form method="post" action="publish.php" onsubmit="return confirm('Replace the existing published HTML with this approved version?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="replace" value="1"><button class="button secondary" <?= !$newsletter['approved_at'] ? 'disabled':'' ?>>Replace Published HTML</button></form><?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <?php if (!empty($_SESSION['publish_notice'])): ?><p role="status"><?= h($_SESSION['publish_notice']) ?></p><?php unset($_SESSION['publish_notice']); endif; ?>
      <?php if (!empty($publication['public_url'])): ?><div class="card"><strong>HTML <?= h($publication['status']) ?></strong><p><a target="_blank" rel="noopener" href="<?= h($publication['public_url']) ?>">View HTML</a></p><input aria-label="Published URL" readonly value="<?= h($publication['public_url']) ?>" onclick="this.select()"><button type="button" class="button secondary" data-url="<?= h($publication['public_url']) ?>" onclick="navigator.clipboard.writeText(this.dataset.url).then(()=>this.textContent='Copied')">Copy URL</button></div><?php endif; ?>
      <?php if (!$newsletter['approved_at']): ?><p>Add Email Title and Header news, then approve this newsletter before publishing.</p><?php endif; ?>
      <iframe class="preview-frame" srcdoc="<?= h($html) ?>"></iframe>
    </div>
  </main>
</div>
<nav class="mobile-bottom-nav">
  <a class="active" href="newsletters.php"><span><?= nav_icon('news') ?></span>News</a>
  <a href="editor.php"><span><?= nav_icon('create') ?></span>Create</a>
  <a class="home" href="index.php"><span><?= nav_icon('home') ?></span>Home</a>
  <a href="download.php?id=<?= $id ?>"><span><?= nav_icon('html') ?></span>HTML</a>
  <a href="report.php"><span><?= nav_icon('report') ?></span>Report</a>
</nav>
</body>
</html>
