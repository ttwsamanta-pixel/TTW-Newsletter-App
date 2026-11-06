<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/report_helpers.php';

$currentUser = require_auth();

$rows = [];
$error = null;
$total = $morning = $evening = $monthTotal = 0;
$calendar = [];
$employeeReportRows = [];
$reportMonth = date('Y-m');

try {
    $rows = db()->query('SELECT * FROM newsletters ORDER BY newsletter_date DESC, edition DESC')->fetchAll();
    $total = count($rows);
    foreach ($rows as $row) {
        $morning += $row['edition'] === 'M' ? 1 : 0;
        $evening += $row['edition'] === 'E' ? 1 : 0;
        if (date('Y-m') === date('Y-m', strtotime($row['newsletter_date']))) {
            $monthTotal++;
        }
        $calendar[$row['newsletter_date']][] = $row['edition'];
    }
    $employeeReportRows = employee_month_report($reportMonth);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$monthStart = strtotime(date('Y-m-01'));
$daysInMonth = (int) date('t', $monthStart);
$firstWeekday = (int) date('N', $monthStart);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TTW Newsletter Dashboard</title>
<link rel="stylesheet" href="<?= h(asset_url('assets/app.css')) ?>">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand"><div class="logo"><img style="width: 40px;   /* adjust size */
  height: 40px;
  object-fit: contain; " src="TTW-Logo.png" alt="Logo"></div><h1>Newsletter Admin</h1></div>
    <?= sidebar_nav('dashboard', $currentUser) ?>
  </aside>
  <main>
    <?= app_shell_topbar('Dashboard', $currentUser) ?>
    <div class="content">
      <?php if ($error): ?>
        <div class="panel"><p>Please import <b>schema.sql</b> and update database credentials in <b>config.php</b>.</p><p class="muted"><?= h($error) ?></p></div>
      <?php else: ?>
      <div class="report-grid">
        <div class="report-card blue"><span>Total</span><strong><?= $total ?></strong><small>All newsletters</small></div>
        <div class="report-card green"><span>Morning</span><strong><?= $morning ?></strong><small>Morning issues</small></div>
        <div class="report-card orange"><span>Evening</span><strong><?= $evening ?></strong><small>Evening issues</small></div>
        <div class="report-card pink"><span>This Month</span><strong><?= $monthTotal ?></strong><small><?= h(date('F Y')) ?></small></div>
      </div>

      <div class="employee-chart-grid dashboard-chart-grid">
        <?= render_employee_pie_chart('Morning Created', date('F Y'), $employeeReportRows, 'morning_count', 'morning') ?>
        <?= render_employee_pie_chart('Evening Created', date('F Y'), $employeeReportRows, 'evening_count', 'evening') ?>
      </div>

      <div class="panel calendar-panel">
        <div class="toolbar">
          <div>
            <h2 style="margin:0 0 6px">Calendar Report</h2>
            <div class="muted"><?= h(date('F Y')) ?> newsletter schedule</div>
          </div>
          <?php if (can('create', $currentUser)): ?><a class="button" href="editor.php">+ Add Newsletter</a><?php endif; ?>
        </div>
        <div class="calendar">
          <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?>
            <div class="calendar-head"><?= $day ?></div>
          <?php endforeach; ?>
          <?php for ($blank = 1; $blank < $firstWeekday; $blank++): ?>
            <div class="calendar-day empty"></div>
          <?php endfor; ?>
          <?php for ($day = 1; $day <= $daysInMonth; $day++):
              $date = date('Y-m-', $monthStart) . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
              $editions = $calendar[$date] ?? [];
              $dayClasses = trim('calendar-day ' . ($editions ? 'has-news ' : '') . ($date === date('Y-m-d') ? 'today' : ''));
          ?>
            <div class="<?= h($dayClasses) ?>">
              <b><?= $day ?></b>
              <?php foreach ($editions as $edition): ?>
                <span class="pill <?= $edition === 'M' ? 'morning' : 'evening' ?>"><?= $edition === 'M' ? 'Morning' : 'Evening' ?></span>
              <?php endforeach; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
<?= mobile_menu('dashboard') ?>
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
