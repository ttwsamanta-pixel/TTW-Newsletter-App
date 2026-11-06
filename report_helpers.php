<?php
declare(strict_types=1);

function report_selected_month(): string
{
    $month = (string) ($_GET['month'] ?? date('Y-m'));
    return preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');
}

function report_month_label(string $month): string
{
    $time = strtotime($month . '-01');
    return $time ? date('F Y', $time) : date('F Y');
}

function report_month_options(): array
{
    $rows = db()->query(
        "SELECT DISTINCT DATE_FORMAT(newsletter_date, '%Y-%m') AS month_key
         FROM newsletters
         ORDER BY month_key DESC"
    )->fetchAll();

    if (!$rows) {
        return [[
            'month_key' => date('Y-m'),
            'month_label' => date('F Y'),
        ]];
    }

    return array_map(static function (array $row): array {
        $key = (string) $row['month_key'];
        return [
            'month_key' => $key,
            'month_label' => report_month_label($key),
        ];
    }, $rows);
}

function employee_month_report(string $month): array
{
    $stmt = db()->prepare(
        "SELECT
            COALESCE(u.id, 0) AS user_id,
            COALESCE(NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), u.username, 'Unknown') AS employee_name,
            SUM(CASE WHEN n.edition = 'M' THEN 1 ELSE 0 END) AS morning_count,
            SUM(CASE WHEN n.edition = 'E' THEN 1 ELSE 0 END) AS evening_count,
            COUNT(*) AS total_count
         FROM newsletters n
         LEFT JOIN users u ON u.id = n.created_by
         WHERE DATE_FORMAT(n.newsletter_date, '%Y-%m') = ?
         GROUP BY user_id, employee_name
         ORDER BY total_count DESC, employee_name ASC"
    );
    $stmt->execute([$month]);
    return $stmt->fetchAll();
}

function employee_month_dates(string $month, int $employeeId): array
{
    if ($employeeId > 0) {
        $stmt = db()->prepare(
            "SELECT id, newsletter_date, edition, subject_title
             FROM newsletters
             WHERE DATE_FORMAT(newsletter_date, '%Y-%m') = ?
             AND created_by = ?
             ORDER BY newsletter_date ASC, edition DESC"
        );
        $stmt->execute([$month, $employeeId]);
    } else {
        $stmt = db()->prepare(
            "SELECT n.id, n.newsletter_date, n.edition, n.subject_title
             FROM newsletters n
             LEFT JOIN users u ON u.id = n.created_by
             WHERE DATE_FORMAT(n.newsletter_date, '%Y-%m') = ?
             AND u.id IS NULL
             ORDER BY n.newsletter_date ASC, n.edition DESC"
        );
        $stmt->execute([$month]);
    }

    return $stmt->fetchAll();
}

function report_totals(array $rows): array
{
    $morning = 0;
    $evening = 0;
    foreach ($rows as $row) {
        $morning += (int) $row['morning_count'];
        $evening += (int) $row['evening_count'];
    }

    return [
        'morning' => $morning,
        'evening' => $evening,
        'total' => $morning + $evening,
    ];
}

function pie_gradient(array $rows, string $field, array $colors): string
{
    $total = 0;
    foreach ($rows as $row) {
        $total += (int) $row[$field];
    }
    if ($total <= 0) {
        return '#e5edf7';
    }

    $segments = [];
    $start = 0.0;
    $colorCount = count($colors);
    foreach (array_values($rows) as $index => $row) {
        $value = (int) $row[$field];
        if ($value <= 0) {
            continue;
        }
        $end = $start + ($value / $total * 360);
        $color = $colors[$index % $colorCount];
        $segments[] = $color . ' ' . round($start, 2) . 'deg ' . round($end, 2) . 'deg';
        $start = $end;
    }

    return 'conic-gradient(' . implode(', ', $segments) . ')';
}

function render_employee_pie_chart(string $title, string $label, array $rows, string $field, string $variant): string
{
    $colors = $variant === 'evening'
        ? ['#f97316', '#ef4444', '#db2777', '#7c3aed', '#f59e0b', '#fb7185']
        : ['#22c55e', '#06b6d4', '#2563eb', '#84cc16', '#14b8a6', '#0ea5e9'];

    $total = 0;
    foreach ($rows as $row) {
        $total += (int) $row[$field];
    }

    ob_start(); ?>
    <div class="employee-chart-card <?= h($variant) ?>">
      <div class="employee-chart-head">
        <div>
          <span><?= h($label) ?></span>
          <h3><?= h($title) ?></h3>
        </div>
        <strong><?= h((string) $total) ?></strong>
      </div>
      <div class="employee-chart-body">
        <div class="pie-chart" style="background:<?= h(pie_gradient($rows, $field, $colors)) ?>">
          <span><?= h((string) $total) ?></span>
        </div>
        <div class="chart-legend">
          <?php foreach (array_values($rows) as $index => $row):
              $value = (int) $row[$field];
              if ($value <= 0) {
                  continue;
              }
              $color = $colors[$index % count($colors)];
          ?>
            <div class="legend-row">
              <i style="background:<?= h($color) ?>"></i>
              <span><?= h($row['employee_name']) ?></span>
              <b><?= h((string) $value) ?></b>
            </div>
          <?php endforeach; ?>
          <?php if ($total <= 0): ?>
            <div class="legend-empty">No <?= h(strtolower($label)) ?> newsletters this month.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php return (string) ob_get_clean();
}
