<?php
require_once __DIR__ . '/newsletter_renderer.php';
require_once __DIR__ . '/includes/security.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$currentUser = require_permission($id ? 'edit' : 'create');
$n = $id ? load_newsletter($id) : null;
$today = date('Y-m-d');

$defaults = [
    'id' => 0,
    'newsletter_date' => $today,
    'edition' => 'M',
    'subject_title' => '',
    'hidden_text' => '',
    'header_title' => '',
    'header_description' => '',
    'header_url' => '',
    'promotion_enabled' => 0,
    'promotion_text' => '',
    'promotion_url' => '',
    'promotion_width' => 200,
    'promotion_height' => 170,
    'pick_news' => [],
    'more_news' => [],
    'categories' => [],
];
$n = array_replace($defaults, $n ?: []);
$existingEditions = [];
try {
    $stmt = db()->query('SELECT newsletter_date, edition, id FROM newsletters');
    foreach ($stmt->fetchAll() as $issue) {
        $existingEditions[$issue['newsletter_date']][] = [
            'edition' => $issue['edition'],
            'id' => (int) $issue['id'],
        ];
    }
} catch (Throwable $e) {
    $existingEditions = [];
}
if (!$n['pick_news']) {
    $n['pick_news'] = array_fill(0, 2, ['title' => '', 'description' => '', 'url' => '', 'image' => '']);
}
if (!$n['more_news']) {
    $n['more_news'] = array_fill(0, 3, ['title' => '', 'url' => '']);
}
if (!$n['categories']) {
    $n['categories'] = [
        ['category_name' => '', 'category_url' => '', 'items' => array_fill(0, 3, ['title' => '', 'url' => ''])],
        ['category_name' => '', 'category_url' => '', 'items' => array_fill(0, 3, ['title' => '', 'url' => ''])],
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $id ? 'Edit' : 'Create' ?> Newsletter</title>
<link rel="stylesheet" href="<?= h(asset_url('assets/app.css')) ?>">
<link rel="stylesheet" href="<?= h(asset_url('assets/automation.css')) ?>">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand"><div class="logo"><img style="width: 40px;   /* adjust size */
  height: 40px;
  object-fit: contain; " src="TTW-Logo.png" alt="Logo"></div><h1>Newsletter Admin</h1></div>
    <?= sidebar_nav('create', $currentUser) ?>
  </aside>
  <main>
    <?= app_shell_topbar($id ? 'Edit Newsletter' : 'Single Newsletter Details', $currentUser) ?>
    <div class="content">
      <div class="steps">
        <?php foreach (['Basic','Header','Pick News','More News','Categories','Promotion'] as $i => $label): ?>
        <button type="button" class="step <?= $i === 0 ? 'active' : '' ?>" onclick="showStep(<?= $i ?>)"><span><?= $i + 1 ?></span><?= h($label) ?></button>
        <?php endforeach; ?>
      </div>
      <form class="panel" method="post" action="save.php" data-autosave-newsletter>
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <p data-save-status role="status" aria-live="polite" hidden></p>
        <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
        <input type="hidden" id="currentNewsletterId" value="<?= (int) $n['id'] ?>">

        <section class="section active">
          <div class="grid">
            <div class="field"><label>Date *</label><input type="date" id="newsletterDate" name="newsletter_date" value="<?= h($n['newsletter_date']) ?>" required></div>
            <div class="field"><label>Morning / Evening *</label><select id="editionSelect" name="edition" required><option value="M" <?= $n['edition'] === 'M' ? 'selected' : '' ?>>Morning</option><option value="E" <?= $n['edition'] === 'E' ? 'selected' : '' ?>>Evening</option></select><div class="field-note" id="editionNote"></div></div>
            <div class="field"><label>Email Title</label><input name="subject_title" value="<?= h($n['subject_title']) ?>" placeholder="Can be added later"></div>
            <div class="field"><label>Hidden Preview Text</label><textarea name="hidden_text"><?= h($n['hidden_text']) ?></textarea></div>
          </div>
          <div class="toolbar" style="margin:24px 0 0"><button class="button secondary" type="submit" name="after_save" value="edit">Save Draft</button><button class="button" type="button" onclick="showStep(1)">Next</button></div>
        </section>

        <section class="section">
          <?= section_import_box('header', $n) ?>
          <div class="grid">
            <div class="field"><label>Header News Title</label><input name="header_title" value="<?= h($n['header_title']) ?>"></div>
            <div class="field"><label>Header News URL</label><input name="header_url" value="<?= h($n['header_url']) ?>"></div>
            <div class="field" style="grid-column:1/-1"><label>Header Description</label><textarea name="header_description"><?= h($n['header_description']) ?></textarea></div>
          </div>
          <div class="field-label" style="margin-top:18px">Header Image 610 x 380</div>
          <?= cropper('header_image_data', '610 / 380', 610, 610 / 380, public_upload_preview_url($n['header_image'] ?? null)) ?>
          <div class="toolbar" style="margin:24px 0 0"><button class="button secondary" type="button" onclick="showStep(0)">Previous</button><button class="button secondary" type="submit" name="after_save" value="edit">Save Draft</button><button class="button" type="button" onclick="showStep(2)">Next</button></div>
        </section>

        <section class="section">
          <?= section_import_box('pick', $n) ?>
          <div id="pickNews" class="repeat-list">
            <?php foreach ($n['pick_news'] as $item): ?>
              <?= pick_form($item) ?>
            <?php endforeach; ?>
          </div>
          <div class="toolbar" style="margin:18px 0"><button type="button" class="button ghost" onclick="addItem('pickTemplate','pickNews')">+ Add Pick News</button></div>
          <div class="toolbar" style="margin:24px 0 0"><button class="button secondary" type="button" onclick="showStep(1)">Previous</button><button class="button secondary" type="submit" name="after_save" value="edit">Save Draft</button><button class="button" type="button" onclick="showStep(3)">Next</button></div>
        </section>

        <section class="section">
          <?= section_import_box('more', $n) ?>
          <div id="moreNews" class="repeat-list">
            <?php foreach ($n['more_news'] as $item): ?>
              <?= link_form('more', $item) ?>
            <?php endforeach; ?>
          </div>
          <div class="toolbar" style="margin:18px 0"><button type="button" class="button ghost" onclick="addItem('moreTemplate','moreNews')">+ Add MTN News</button></div>
          <div class="toolbar" style="margin:24px 0 0"><button class="button secondary" type="button" onclick="showStep(2)">Previous</button><button class="button secondary" type="submit" name="after_save" value="edit">Save Draft</button><button class="button" type="button" onclick="showStep(4)">Next</button></div>
        </section>

        <section class="section">
          <?= section_import_box('category', $n) ?>
          <div id="categories" class="repeat-list">
            <?php foreach ($n['categories'] as $category): ?>
              <?= category_form($category) ?>
            <?php endforeach; ?>
          </div>
          <div class="toolbar" style="margin:18px 0"><button type="button" class="button ghost" onclick="addItem('categoryTemplate','categories')">+ Add Category</button></div>
          <div class="toolbar" style="margin:24px 0 0"><button class="button secondary" type="button" onclick="showStep(3)">Previous</button><button class="button secondary" type="submit" name="after_save" value="edit">Save Draft</button><button class="button" type="button" onclick="showStep(5)">Next</button></div>
        </section>

        <section class="section">
          <div class="grid">
            <div class="field"><label>Show Client Promotion?</label><select name="promotion_enabled"><option value="0">No</option><option value="1" <?= (int) $n['promotion_enabled'] === 1 ? 'selected' : '' ?>>Yes</option></select></div>
            <div class="field"><label>Promotion URL</label><input name="promotion_url" value="<?= h($n['promotion_url']) ?>"></div>
            <div class="field"><label>Promotion Width</label><input type="number" min="1" name="promotion_width" value="<?= h((string) $n['promotion_width']) ?>"></div>
            <div class="field"><label>Promotion Height</label><input type="number" min="1" name="promotion_height" value="<?= h((string) $n['promotion_height']) ?>"></div>
            <div class="field" style="grid-column:1/-1"><label>Promotion Text</label><textarea name="promotion_text"><?= h($n['promotion_text']) ?></textarea></div>
          </div>
          <div class="field-label" style="margin-top:18px">Promotion Image Original Upload</div>
          <?= original_uploader('promotion_image_data', public_upload_preview_url($n['promotion_image'] ?? null,'promotion')) ?>
          <div class="toolbar" style="margin:24px 0 0"><button class="button secondary" type="button" onclick="showStep(4)">Previous</button><button class="button" type="submit" name="after_save" value="view">Save Newsletter</button></div>
        </section>
      </form>
    </div>
  </main>
</div>

<template id="pickTemplate"><?= pick_form(['title' => '', 'description' => '', 'url' => '', 'image' => '']) ?></template>
<template id="moreTemplate"><?= link_form('more', ['title' => '', 'url' => '']) ?></template>
<template id="categoryTemplate"><?= category_form(['category_name' => '', 'category_url' => '', 'items' => [['title' => '', 'url' => '']]], '__KEY__') ?></template>
<template id="categoryNewsTemplate"><?= category_news_form('__KEY__', ['title' => '', 'url' => '']) ?></template>
<script src="<?= h(asset_url('assets/app.js')) ?>"></script>
<script>
window.ttwCsrf = <?= json_encode(csrf_token()) ?>;
window.existingNewsletterEditions = <?= json_encode($existingEditions, JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= h(asset_url('assets/section-import.js')) ?>"></script>
<nav class="mobile-bottom-nav">
  <a href="newsletters.php"><span><?= nav_icon('news') ?></span>News</a>
  <a class="active" href="editor.php"><span><?= nav_icon('create') ?></span>Create</a>
  <a class="home" href="index.php"><span><?= nav_icon('home') ?></span>Home</a>
  <a href="newsletters.php"><span><?= nav_icon('html') ?></span>HTML</a>
  <a href="report.php"><span><?= nav_icon('report') ?></span>Report</a>
</nav>
</body>
</html>
<?php
function cropper(string $name, string $ratioStyle, int $width, float $ratio, string $existingSrc = '', bool $originalMode = false): string
{
    ob_start(); ?>
    <div class="cropper" data-cropper data-width="<?= $width ?>" data-ratio="<?= $ratio ?>" <?= $existingSrc ? 'data-existing-src="' . h($existingSrc) . '"' : '' ?> <?= $originalMode ? 'data-mode="original"' : '' ?> tabindex="0">
      <input type="file" accept="image/*">
      <input type="hidden" name="<?= h($name) ?>">
      <?php if ($existingSrc): ?><img class="crop-existing-preview" src="<?= h($existingSrc) ?>" alt="Saved image preview"><?php endif; ?>
      <canvas class="crop-canvas" style="--ratio:<?= h($ratioStyle) ?>"></canvas>
      <div class="crop-controls"><span class="muted"><?= $existingSrc ? 'Saved image loaded. Paste or choose new image to replace.' : ($originalMode ? 'Paste or choose image. Original file is saved.' : 'Paste image here, drag to position') ?></span><?php if (!$originalMode): ?><label>Zoom <input type="range" data-zoom min="1" max="3" step="0.01" value="1"></label><?php endif; ?></div>
    </div>
    <?php return (string) ob_get_clean();
}

function original_uploader(string $name, string $existingSrc = ''): string
{
    ob_start(); ?>
    <div class="original-uploader" data-original-upload <?= $existingSrc ? 'data-existing-src="' . h($existingSrc) . '"' : '' ?> tabindex="0">
      <input type="file" accept="image/*">
      <input type="hidden" name="<?= h($name) ?>">
      <div class="original-preview">
        <img alt="Promotion preview" <?= $existingSrc ? 'src="' . h($existingSrc) . '" style="display:block"' : '' ?>>
        <span><?= $existingSrc ? 'Saved promotion image loaded. Paste or choose new image to replace.' : 'Paste or choose promotion image. Original image will be saved.' ?></span>
      </div>
    </div>
    <?php return (string) ob_get_clean();
}

function pick_form(array $item): string
{
    ob_start(); ?>
    <div class="repeat-item">
      <div class="repeat-head"><label class="order-control">No. <input type="number" min="1" class="sort-order" value="1"></label><strong>Pick News</strong><button type="button" class="button danger" onclick="removeItem(this)">Delete</button></div>
      <div class="grid">
        <div class="field"><label>Title</label><input name="pick_title[]" value="<?= h($item['title'] ?? '') ?>"></div>
        <div class="field"><label>URL</label><input name="pick_url[]" value="<?= h($item['url'] ?? '') ?>"></div>
        <div class="field" style="grid-column:1/-1"><label>Description</label><textarea name="pick_description[]"><?= h($item['description'] ?? '') ?></textarea></div>
      </div>
      <div class="field-label" style="margin-top:14px">Pick Image 350 x 291</div>
      <input type="hidden" name="pick_existing_image[]" value="<?= h($item['image'] ?? '') ?>">
      <?= cropper('pick_image_data[]', '350 / 291', 350, 350 / 291, public_upload_preview_url($item['image'] ?? null,'pick')) ?>
    </div>
    <?php return (string) ob_get_clean();
}

function link_form(string $prefix, array $item): string
{
    ob_start(); ?>
    <div class="repeat-item" draggable="true">
      <div class="repeat-head"><label class="order-control">No. <input type="number" min="1" class="sort-order" value="1"></label><span class="drag-handle" title="Drag to change position">⇅</span><strong>News Link</strong><button type="button" class="button danger" onclick="removeItem(this)">Delete</button></div>
      <div class="grid">
        <div class="field"><label>Title</label><input name="<?= $prefix ?>_title[]" value="<?= h($item['title'] ?? '') ?>"></div>
        <div class="field"><label>URL</label><input name="<?= $prefix ?>_url[]" value="<?= h($item['url'] ?? '') ?>"></div>
      </div>
    </div>
    <?php return (string) ob_get_clean();
}

function category_form(array $category, ?string $forcedKey = null): string
{
    $key = $forcedKey ?: 'cat_' . bin2hex(random_bytes(4));
    ob_start(); ?>
    <div class="repeat-item" data-category="<?= h($key) ?>" draggable="true">
      <div class="repeat-head"><label class="order-control">No. <input type="number" min="1" class="sort-order" value="1"></label><span class="drag-handle" title="Drag to change position">⇅</span><strong>Category Section</strong><button type="button" class="button danger" onclick="removeItem(this)">Delete</button></div>
      <input type="hidden" name="category_key[]" value="<?= h($key) ?>" data-category-key>
      <div class="grid">
        <div class="field"><label>Category Name</label><input name="category_name[]" value="<?= h($category['category_name'] ?? '') ?>"></div>
        <div class="field"><label>Category URL</label><input name="category_url[]" value="<?= h($category['category_url'] ?? '') ?>"></div>
      </div>
      <div class="repeat-list category-items" style="margin-top:14px">
        <?php foreach (($category['items'] ?? []) as $item): ?>
          <?= category_news_form($key, $item) ?>
        <?php endforeach; ?>
      </div>
      <div class="toolbar" style="margin:14px 0 0"><button type="button" class="button ghost" onclick="addCategoryNews(this)">+ Add News</button></div>
    </div>
    <?php return (string) ob_get_clean();
}

function category_news_form(string $key, array $item): string
{
    ob_start(); ?>
    <div class="repeat-item" draggable="true">
      <div class="repeat-head"><label class="order-control">No. <input type="number" min="1" class="sort-order" value="1"></label><span class="drag-handle" title="Drag to change position">⇅</span><strong>Category News</strong><button type="button" class="button danger" onclick="removeItem(this)">Delete</button></div>
      <div class="grid">
        <div class="field"><label>Title</label><input name="category_news_title[<?= h($key) ?>][]" value="<?= h($item['title'] ?? '') ?>"></div>
        <div class="field"><label>URL</label><input name="category_news_url[<?= h($key) ?>][]" value="<?= h($item['url'] ?? '') ?>"></div>
      </div>
    </div>
    <?php return (string) ob_get_clean();
}


function section_import_box(string $section, array $n): string
{
    if (!can('create')) return '';
    $labels = ['header'=>'Header', 'pick'=>'Pick News', 'more'=>'MTN / More News', 'category'=>'Categories'];
    $links = [];
    if ($section === 'header' && !empty($n['header_url'])) $links[] = $n['header_url'];
    if ($section === 'pick' || $section === 'more') foreach ($n[$section === 'pick' ? 'pick_news' : 'more_news'] as $item) if (!empty($item['url'])) $links[] = $item['url'];
    if ($section === 'category') foreach ($n['categories'] as $category) {
        if (!trim($category['category_name'])) continue;
        $links[] = $category['category_name'] . ':';
        foreach ($category['items'] as $item) if (!empty($item['url'])) $links[] = $item['url'];
        $links[] = '';
    }
    ob_start(); ?>
    <details class="section-import" data-import-section="<?= h($section) ?>">
      <summary>Import / refresh <?= h($labels[$section]) ?> from article links</summary>
      <p>Paste this section's links only. The rest of your draft is kept. You can start with any section and return later.</p>
      <?php if ($section === 'category'): ?><p>Include category headings, for example <strong>TOURISM NEWS:</strong>, followed by its links. Only named category groups are replaced.</p><?php endif; ?>
      <textarea data-import-links rows="5" placeholder="<?= $section === 'category' ? 'TOURISM NEWS:' . "
" : '' ?>https://www.travelandtourworld.com/news/article/..."><?= h(implode("
",$links)) ?></textarea>
      <button type="button" class="button" data-import-start>Import <?= h($labels[$section]) ?> links</button>
      <button type="button" class="button secondary" data-import-stop>Return to editing</button>
      <div data-import-status role="status" aria-live="polite"></div>
      <label data-import-warning hidden><input type="checkbox" data-import-accept> I reviewed the warnings and accept missing descriptions or placeholder images.</label>
      <button type="button" class="button secondary" data-import-retry hidden>Retry articles with problems</button>
      <button type="button" class="button" data-import-apply hidden>Save imported section</button>
    </details>
    <?php return (string)ob_get_clean();
}
