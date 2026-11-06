<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/report_helpers.php';

$currentUser = require_permission('view');
$month = report_selected_month();
$months = [];
$rows = [];
$details = [];
$selectedEmployeeId = isset($_GET['employee']) ? (int) $_GET['employee'] : 0;
$selectedEmployeeName = '';
$error = null;

try {
    $months = report_month_options();
    $rows = employee_month_report($month);
    foreach ($rows as $row) {
        if ((int) $row['user_id'] === $selectedEmployeeId) {
            $selectedEmployeeName = (string) $row['employee_name'];
            break;
        }
    }
    if (isset($_GET['employee'])) {
        $details = employee_month_dates($month, $selectedEmployeeId);
        $selectedEmployeeName = $selectedEmployeeName ?: 'Unknown';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$totals = report_totals($rows);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Employee Newsletter Report</title>
<link rel="stylesheet" href="<?= h(asset_url('assets/app.css')) ?>">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand"><div class="logo"><img style="width:40px;height:40px;object-fit:contain" src="TTW-Logo.png" alt="Logo"></div><h1>Newsletter Admin</h1></div>
    <?= sidebar_nav('report', $currentUser) ?>
  </aside>
  <main>
    <?= app_shell_topbar('Employee Report', $currentUser) ?>
    <div class="content report-page">
      <?php if ($error): ?>
        <div class="panel"><p class="muted"><?= h($error) ?></p></div>
      <?php else: ?>
      <div class="toolbar report-toolbar">
        <div>
          <h2 style="margin:0 0 6px">Month Wise Newsletter Report</h2>
          <div class="muted">Employee wise morning and evening newsletter creation.</div>
        </div>
        <form class="month-filter" method="get" action="report.php">
          <label for="reportMonth">Month</label>
          <select id="reportMonth" name="month" onchange="this.form.submit()">
            <?php foreach ($months as $option): ?>
              <option value="<?= h($option['month_key']) ?>" <?= $option['month_key'] === $month ? 'selected' : '' ?>><?= h($option['month_label']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>

      <div class="report-count-strip">
        <div class="count-chip morning"><span>Morning</span><strong><?= h((string) $totals['morning']) ?></strong></div>
        <div class="count-chip evening"><span>Evening</span><strong><?= h((string) $totals['evening']) ?></strong></div>
        <div class="count-chip total"><span>Total</span><strong><?= h((string) $totals['total']) ?></strong></div>
      </div>

      <div class="employee-chart-grid">
        <?= render_employee_pie_chart('Morning Created', report_month_label($month), $rows, 'morning_count', 'morning') ?>
        <?= render_employee_pie_chart('Evening Created', report_month_label($month), $rows, 'evening_count', 'evening') ?>
      </div>

      <div class="panel">
        <div class="panel-title-row">
          <div>
            <h2 style="margin:0 0 6px"><?= h(report_month_label($month)) ?> Creating Report</h2>
            <div class="muted">Morning and evening counts by employee.</div>
          </div>
        </div>
        <div class="table-wrap">
          <table class="table employee-report-table">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Morning</th>
                <th>Evening</th>
                <th>Total</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
              <tr>
                <td><strong><?= h($row['employee_name']) ?></strong></td>
                <td><span class="mini-count morning"><?= h((string) (int) $row['morning_count']) ?></span></td>
                <td><span class="mini-count evening"><?= h((string) (int) $row['evening_count']) ?></span></td>
                <td><b><?= h((string) (int) $row['total_count']) ?></b></td>
                <td><a class="button compact secondary" href="report.php?month=<?= h($month) ?>&employee=<?= (int) $row['user_id'] ?>#details">View</a></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr><td colspan="5" class="muted">No newsletters created in this month.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if (isset($_GET['employee'])): ?>
      <div id="details" class="panel employee-date-panel">
        <div class="panel-title-row">
          <div>
            <h2 style="margin:0 0 6px"><?= h($selectedEmployeeName) ?></h2>
            <div class="muted">Created dates for <?= h(report_month_label($month)) ?>.</div>
          </div>
          <a class="button compact ghost" href="report.php?month=<?= h($month) ?>">Close</a>
        </div>
        <div class="date-chip-grid">
          <?php foreach ($details as $item): ?>
            <a class="date-chip <?= $item['edition'] === 'M' ? 'morning' : 'evening' ?>" href="view.php?id=<?= (int) $item['id'] ?>">
              <span><?= h(date('d M Y', strtotime($item['newsletter_date']))) ?></span>
              <b><?= $item['edition'] === 'M' ? 'Morning' : 'Evening' ?></b>
              <small><?= h($item['subject_title'] ?: issue_file_name($item['newsletter_date'], $item['edition'])) ?></small>
            </a>
          <?php endforeach; ?>
          <?php if (!$details): ?>
            <p class="muted">No records found for this employee.</p>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
</div>
<?= mobile_menu('report') ?>
</body>
</html>
<?php
function mobile_menu(string $active): string
{
    ob_start(); ?>
    <nav class="mobile-bottom-nav">
      <a class="<?= $active === 'newsletters' ? 'active' : '' ?>" href="newsletters.php"><span><?= nav_icon('news') ?></span>News</a>
      <a class="<?= $active === 'create' ? 'active' : '' ?>" href="editor.php"><span><?= nav_icon('create') ?></span>Create</a>
      <a class="home <?= $active === 'dashboard' ? 'active' : '' ?>" href="index.php"><span><?= nav_icon('home') ?></span>Home</a>
      <a href="newsletters.php"><span><?= nav_icon('html') ?></span>HTML</a>
      <a class="<?= $active === 'report' ? 'active' : '' ?>" href="report.php"><span><?= nav_icon('report') ?></span>Report</a>
    </nav>
    <?php return (string) ob_get_clean();
}
