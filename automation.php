<?php
require_once __DIR__.'/includes/security.php';
$user=require_permission('create');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TTW Newsletter Automation</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/automation.css"></head>
<body class="automation-page"><div class="app"><aside class="sidebar"><div class="brand"><img width="76" src="TTW-Logo.png" alt="TTW"><h1>Newsletter Manager</h1></div><?= sidebar_nav('automation',$user) ?></aside><main>
<?= app_shell_topbar('Newsletter Automation',$user) ?>
<div class="content auto-content"><div class="auto-hero"><div><span class="eyebrow">TRAVEL AND TOUR WORLD</span><h1>Good morning,<br><?= h(current_user_name($user)) ?>.</h1><p>Your daily news. A simpler workflow.<br>Create, review and manage TTW newsletters.</p></div><img src="TTW-Logo.png" alt="Travel and Tour World"></div>
<div id="today" class="edition-cards" aria-live="polite"></div>
<div class="auto-layout"><section class="auto-card"><div class="auto-heading"><h2>Create Newsletter</h2><a href="editor.php">Manual editor ↗</a></div>
<ol class="auto-steps"><li class="active">1 <span>Input</span></li><li>2 <span>Review</span></li><li>3 <span>Details</span></li><li>4 <span>Generate</span></li></ol>
<p id="message" class="auto-message" role="status" hidden></p>
<form id="automationForm">
<section data-stage="0"><div class="grid"><label>Newsletter date<input type="date" name="newsletter_date" value="<?= h(date('Y-m-d')) ?>" required></label><label>Edition<select name="edition"><option value="M">☀ Morning</option><option value="E">☾ Evening</option></select></label></div><label>Paste Newsletter Links<textarea name="text" rows="13" required placeholder="header:
https://www.travelandtourworld.com/news/article/...

Pick News:
https://www.travelandtourworld.com/news/article/...

More News:

Categories:
TOURISM NEWS:
https://www.travelandtourworld.com/news/article/..."></textarea></label><div class="auto-actions"><button type="button" class="button secondary" id="template">Use template</button><button class="button" id="parse">Parse Links →</button></div></section>
<section data-stage="1" hidden><div class="auto-success" id="parseSummary"></div><p class="muted">Review links and their order. Original titles and images are fetched in the next step.</p><div id="parsedArticles"></div><div class="auto-actions"><button type="button" class="button secondary" data-back="0">Back</button><button type="button" class="button" id="toDetails">Continue →</button></div></section>
<section data-stage="2" hidden><h3>Promotion &amp; Details</h3><label>Promotional banner (optional)<input type="file" id="banner" accept="image/jpeg,image/png,image/webp"></label><img id="bannerPreview" alt="Promotion preview" hidden><button type="button" class="button secondary" id="removeBanner">Remove banner</button><label>Promotion URL<input name="promotion_url" type="url" placeholder="https://..."></label><label>Promotion title (optional)<input name="promotion_text"></label><label>Email Subject *<input name="subject_title" maxlength="250"></label><label>Email Description *<textarea name="hidden_text" rows="4" maxlength="5000"></textarea></label><div class="auto-actions"><button type="button" class="button secondary" data-back="1">Back</button><button type="button" class="button" id="startJob">Process Articles →</button></div></section>
<section data-stage="3" hidden><div class="processing-head"><img width="70" src="TTW-Logo.png" alt="TTW"><h2 id="jobTitle">Creating Newsletter…</h2><p id="jobStatus" aria-live="polite"></p><progress id="jobProgress" max="100" value="0"></progress></div><div id="articleResults"></div><label id="warningConsent" hidden><input type="checkbox" id="acceptWarnings"> I reviewed the warnings and accept missing descriptions or placeholder images.</label><div class="auto-actions"><button type="button" class="button secondary" id="retry" hidden>Retry failed articles</button><button type="button" class="button" id="generate" hidden>Generate Draft →</button></div><div id="preview" hidden><div class="auto-actions"><a class="button secondary" id="editDraft">Edit Draft</a><button type="button" class="button" id="approve">✓ Approve</button></div><iframe id="newsletterPreview" title="Newsletter preview" sandbox="allow-same-origin allow-popups"></iframe></div></section>
</form></section><aside class="auto-card auto-history"><h2>Recent newsletters</h2><div id="recentNews"></div><a href="newsletters.php">View newsletter library →</a><h2>Automation jobs</h2><div id="recentJobs"></div><a href="categories.php">Manage categories →</a></aside></div></div></main></div>
<script>window.ttwCsrf=<?= json_encode(csrf_token()) ?>;</script><script src="assets/automation.js"></script></body></html>
