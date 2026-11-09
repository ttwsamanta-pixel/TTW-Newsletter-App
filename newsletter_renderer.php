<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function load_newsletter(int $id): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM newsletters WHERE id = ?');
    $stmt->execute([$id]);
    $newsletter = $stmt->fetch();

    if (!$newsletter) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM pick_news WHERE newsletter_id = ? ORDER BY sort_order, id');
    $stmt->execute([$id]);
    $newsletter['pick_news'] = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT * FROM more_travel_news WHERE newsletter_id = ? ORDER BY sort_order, id');
    $stmt->execute([$id]);
    $newsletter['more_news'] = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT * FROM newsletter_categories WHERE newsletter_id = ? ORDER BY sort_order, id');
    $stmt->execute([$id]);
    $categories = $stmt->fetchAll();

    $itemStmt = $pdo->prepare('SELECT * FROM category_news WHERE category_id = ? ORDER BY sort_order, id');
    foreach ($categories as &$category) {
        $itemStmt->execute([$category['id']]);
        $category['items'] = $itemStmt->fetchAll();
    }
    unset($category);
    $newsletter['categories'] = $categories;

    return $newsletter;
}

function render_newsletter(array $n): string
{
    $date = $n['newsletter_date'];
    $edition = strtoupper($n['edition']);
    $fileName = issue_file_name($date, $edition);
    $webUrl = rtrim(NEWSLETTER_BASE_URL, '/') . '/' . issue_month_path($date) . '/' . $fileName;
    $headerImage = public_upload_preview_url($n['header_image']) ?: rtrim(NEWSLETTER_BASE_URL, '/') . '/' . issue_month_path($date) . '/Header_' . issue_code($date, $edition) . '.jpg';

    ob_start();
    ?><!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="x-apple-disable-message-reformatting">
<link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
<meta name="supported-color-schemes" content="light">
<title><?= h($n['subject_title']) ?></title>
<style>
:root{color-scheme:light;supported-color-schemes:light}html,body{margin:0 auto!important;padding:0!important;height:100%!important;width:100%!important}*{-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%}table,td{mso-table-lspace:0pt!important;mso-table-rspace:0pt!important}table{border-spacing:0!important;border-collapse:collapse!important;table-layout:fixed!important;margin:0 auto!important}img{-ms-interpolation-mode:bicubic}a{text-decoration:none}.button-td,.button-a{transition:all 100ms ease-in}.button-td-primary:hover,.button-a-primary:hover{background:#555!important;border-color:#555!important;color:#fff!important}.email-text-align{padding:10px 0 0 10px}@media screen and (max-width:480px){.stack-column,.stack-column-center{display:block!important;width:100%!important;max-width:100%!important;direction:ltr!important}.stack-column-center{text-align:center!important;height:auto}.center-on-narrow{display:block!important;margin-left:auto!important;margin-right:auto!important;float:none!important;text-align:center!important}.email-container p{font-size:14px!important;font-family:'Roboto',sans-serif}.email-text-align{padding:10px 10px 0 10px}}
</style>
</head>
<body width="100%" style="margin:0;padding:0!important;mso-line-height-rule:exactly;background-color:#f3f2ef;">
<center role="article" aria-roledescription="email" lang="en" style="width:100%;background-color:#f3f2ef;">
<div style="max-height:0;overflow:hidden;mso-hide:all;" aria-hidden="true"><?= h($n['hidden_text']) ?></div>
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;font-family:'Roboto',sans-serif;">&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;</div>
<div style="width:100%;">
<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center"><tr><td align="center" height="20px" style="font-family:'Roboto',sans-serif;font-size:14px;color:#000">View as a Web Page <a href="<?= h($webUrl) ?>">here</a></td></tr></table>
</div>
<div style="max-width:650px;margin:0 auto;" class="email-container">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:auto;border:1px solid #e6e6e6;background:#fff;">
<tr><td><div style="margin:10px 20px 0 20px;border-bottom:1px solid #e6e6e6;"><a href="https://www.travelandtourworld.com"><img src="https://www.travelandtourworld.com/newsletter/ttw-header-new.jpg" alt="TTW Header" width="540" class="g-img" style="width:100%;max-width:540px;height:auto;background:#ddd;font-family:'Lucida Sans Unicode','Lucida Grande',sans-serif;font-size:15px;line-height:15px;color:#ccc;margin:auto;display:block;" border="0"></a></div></td></tr>
<tr><td height="4"></td></tr>
<tr><td><h2 style="margin:5px 0;font-family:'Roboto',sans-serif;font-size:14px;line-height:20px;color:#555;font-weight:600;text-align:center;">DAILY TRAVEL NEWS UPDATE</h2></td></tr>
<tr><td><div style="border-bottom:2px solid #b90005;margin:0 15% 10px 15%"></div></td></tr>
<?php if (trim((string)$n['header_title']) !== ''): ?>
<?= render_header_story($n, $headerImage) ?>
<?php endif; ?>
<?php foreach ($n['pick_news'] as $index => $item): ?>
<?= render_pick_news($item, $index, $date, $edition) ?>
<?php endforeach; ?>
<?= render_link_section('MORE TRAVEL NEWS', 'https://www.travelandtourworld.com/news/article/category/travel-news/', $n['more_news']) ?>
<?php foreach ($n['categories'] as $category): ?>
<?= render_link_section($category['category_name'], $category['category_url'], $category['items']) ?>
<?php endforeach; ?>
<?php if ((int) $n['promotion_enabled'] === 1): ?>
<?= render_promotion($n) ?>
<?php endif; ?>
<?= render_footer() ?>
</table>
<?= render_social_map() ?>
</div>
</center>
</body>
</html><?php
    return (string) ob_get_clean();
}

function render_header_story(array $n, string $image): string
{
    ob_start();
    ?><tr><td><div style="margin:10px 20px 0 20px"><table width="100%" border="0" cellspacing="0" cellpadding="0" style="border-bottom:1px solid #e6e6e6;"><tbody>
<tr><td><a style="text-decoration:none" href="<?= h($n['header_url']) ?>"><h2 style="margin:0 0 10px 0;font-family:Arial,Helvetica,sans-serif;font-size:17px;line-height:22px;color:#555;font-weight:600;"><?= h($n['header_title']) ?></h2></a></td></tr>
<tr><td><a href="<?= h($n['header_url']) ?>" style="text-decoration:none;"><img class="center-on-narrow" style="border:1px solid #ccc;width:100%;max-width:608px;height:auto;background:#ddd;font-size:15px;line-height:15px;color:#555;" src="<?= h($image) ?>" alt="TTW-News"></a></td></tr>
<tr><td style="font-family:'Roboto',sans-serif;font-size:15px;line-height:20px;color:#555;"><a href="<?= h($n['header_url']) ?>"><p style="margin:10px 0 20px 0;text-align:justify;color:#555"><?= h($n['header_description']) ?></p></a></td></tr>
<tr><td align="center" style="padding-bottom:5px;"><?= render_button($n['header_url']) ?></td></tr>
</tbody></table></div></td></tr><?php
    return (string) ob_get_clean();
}

function render_pick_news(array $item, int $index, string $date, string $edition): string
{
    $dir = $index % 2 === 0 ? 'ltr' : 'rtl';
    $padding = $dir === 'ltr' ? '10px 5px 5px 10px' : '10px 10px 5px 10px';
    $imagePadding = $dir === 'ltr' ? '0 10px 10px 10px' : '0 0 10px 10px';
    $image = public_upload_preview_url($item['image']) ?: rtrim(NEWSLETTER_BASE_URL, '/') . '/' . issue_month_path($date) . '/news' . ($index + 1) . '_' . issue_code($date, $edition) . '.jpg';

    ob_start();
    ?><tr><td dir="<?= $dir ?>" height="100%" valign="top" width="100%" style="font-size:0;padding:<?= $padding ?>;text-align:left;background-color:#fff;">
<table width="100%" border="0" cellspacing="0" cellpadding="0"><tr><td>
<div style="display:inline-block;margin:0 -1px;max-width:310px;min-width:160px;vertical-align:top;width:100%;" class="stack-column"><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"><tr><td dir="ltr" style="padding:<?= $imagePadding ?>;"><a href="<?= h($item['url']) ?>"><img src="<?= h($image) ?>" width="300" border="0" alt="TTW-News" class="center-on-narrow" style="width:100%;max-width:300px;height:auto;background:#ddd;font-family:'Roboto',sans-serif;font-size:15px;line-height:15px;color:#555;"></a></td></tr></table></div>
<div style="display:inline-block;margin:0 -1px;max-width:310px;vertical-align:top;" class="stack-column"><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"><tr><td dir="ltr" style="font-family:'Roboto',sans-serif;font-size:15px;line-height:20px;color:#555;text-align:left;" class="center-on-narrow email-text-align"><a href="<?= h($item['url']) ?>"><h2 style="margin:0 0 10px 0;font-family:'Roboto',sans-serif;font-size:17px;line-height:22px;color:#555;font-weight:600;"><?= h($item['title']) ?></h2></a><a href="<?= h($item['url']) ?>"><p style="margin:0 0 20px 0;text-align:justify;color:#555"><?= h($item['description']) ?></p></a><?= render_button($item['url']) ?></td></tr></table></div>
</td></tr></table></td></tr><?php
    return (string) ob_get_clean();
}

function render_button(?string $url): string
{
    ob_start();
    ?><table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="display:table;margin:0 auto;float:none;width:auto;"><tr><td align="center" class="button-td button-td-primary" style="border-radius:15px;background:#fff;"><a class="button-a button-a-primary" href="<?= h($url) ?>" style="background:#fff;border:1px solid #34421e;font-family:'Roboto',sans-serif;font-size:15px;line-height:15px;text-decoration:none;padding:6px 30px;color:#000;display:block;border-radius:15px;" target="_blank" rel="noopener noreferrer">Read More</a></td></tr></table><?php
    return (string) ob_get_clean();
}

function render_link_section(string $title, ?string $url, array $items): string
{
    if (!$items) {
        return '';
    }
    ob_start();
    ?><tr><td><div style="margin:5px 20px 10px 20px;border-bottom:1px solid #e6e6e6;"></div></td></tr>
<tr><td><a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer"><h2 style="margin:0 20px 0 20px;font-family:'Roboto',sans-serif;font-size:17px;line-height:22px;color:#b90005;font-weight:600;"><?= h(strtoupper($title)) ?></h2></a></td></tr>
<tr><td><div align="left"><ul style="list-style-type:square;font-size:16px;line-height:24px;padding-right:15px;">
<?php foreach ($items as $item): ?>
<li><font color="#000" style="text-align:left;font-family:'Roboto',sans-serif;font-size:17px;"><a style="color:#000;text-decoration:none;font-size:16px" href="<?= h($item['url']) ?>"><?= h($item['title']) ?></a></font></li>
<?php endforeach; ?>
</ul></div></td></tr><?php
    return (string) ob_get_clean();
}

function render_promotion(array $n): string
{
    $image = public_upload_preview_url($n['promotion_image']);
    ob_start();
    $width = max(1, (int) ($n['promotion_width'] ?? 200));
    $height = max(1, (int) ($n['promotion_height'] ?? 170));
    ?><tr><td><div style="margin:5px 20px 10px 20px;border-bottom:1px solid #e6e6e6;"></div></td></tr>
<tr><td dir="ltr" height="100%" valign="top" width="100%" style="font-size:0;padding:10px 10px 5px 10px;background-color:#fff;">
<div style="display:inline-block;margin:0 -1px;max-width:<?= $width + 20 ?>px;vertical-align:top;width:100%;" class="stack-column"><?php if ($image): ?><a href="<?= h($n['promotion_url']) ?>"><img src="<?= h($image) ?>" width="<?= $width ?>" height="<?= $height ?>" border="0" alt="Promotion" class="center-on-narrow" style="border:1px solid #ccc;width:100%;max-width:<?= $width ?>px;height:auto;background:#ddd;"></a><?php endif; ?></div>
<div style="display:inline-block;margin:0 -1px;max-width:390px;vertical-align:top;" class="stack-column"><p style="font-family:'Roboto',sans-serif;font-size:15px;line-height:22px;color:#555;margin:0 0 10px 10px;text-align:justify;"><?= h($n['promotion_text']) ?></p></div>
</td></tr><?php
    return (string) ob_get_clean();
}

function render_footer(): string
{
    $email = 'pr&#64;travelandtourworld.com';
    $mailto = '&#109;&#97;&#105;&#108;&#116;&#111;&#58;pr&#64;travelandtourworld.com';

    return '<tr><td><div style="margin:10px 20px 0 20px"><table width="100%" border="0" cellspacing="0" cellpadding="0"><tbody><tr><td><a href="https://www.travelandtourworld.com/ttwapp/" style="text-decoration:none;"><img class="center-on-narrow" style="border:1px solid #CCCCCC;width:100%;max-width:608px;height:auto;background:#dddddd;font-size:15px;line-height:15px;color:#555555;" src="https://www.travelandtourworld.com/newsletter/2025/Feb/ttw-app-banner.jpg" alt="TTW-News"></a></td></tr></tbody></table></div></td></tr>'
        . '<tr><td><div style="margin:5px 20px 0 20px"><table width="100%" border="0" cellspacing="0" cellpadding="0" style="border-bottom:1px solid #e6e6e6;"><tbody><tr><td height="5"></td></tr><tr><td style="font-family:Arial,Helvetica,sans-serif;line-height:18px;color:#fff;padding:5px 5px 0;text-align:center;" bgcolor="#023266"><h2 style="margin:10px 0 10px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:26px;color:#fff;font-weight:400;"><strong>MORE NEWS</strong> <br> <a style="text-decoration:none;color:#fff" href="https://www.travelandtourworld.com/news/article/category/travel-association-news/">ASSOCIATION</a>&nbsp;|&nbsp;<a style="text-decoration:none;color:#fff" href="https://www.travelandtourworld.com/news/article/category/tourism-news/">TOURISM</a>&nbsp;|&nbsp;<a style="text-decoration:none;color:#fff" href="https://www.travelandtourworld.com/news/article/category/meeting-and-event-industry-news/">MICE</a>&nbsp;|&nbsp;<a style="text-decoration:none;color:#fff" href="https://www.travelandtourworld.com/news/article/category/railway-news/">RAILWAY</a>&nbsp;|&nbsp;<a style="text-decoration:none;color:#fff" href="https://www.travelandtourworld.com/news/article/category/travel-technology-news/">TECHNOLOGY</a>&nbsp;|&nbsp;<a style="text-decoration:none;color:#fff" href="https://www.travelandtourworld.com/news/article/category/destination-news/">DESTINATION</a>&nbsp;|&nbsp;<a style="text-decoration:none;color:#fff" href="https://www.travelandtourworld.com/news/article/category/travel-event-news/">EVENTS</a></h2></td></tr><tr><td height="5"></td></tr></tbody></table></div></td></tr>'
        . '<tr><td height="8"></td></tr>'
        . '<tr><td dir="ltr" height="100%" valign="top" width="100%" style="font-size:0;padding:0;background-color:#b90005;"><div style="display:inline-block;margin:0;width:100%;vertical-align:top;text-align:center;" class="stack-column" align="center"><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"><tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:20px;color:#fff;padding:5px 0 0;text-align:center;" class="center-on-narrow stack-column"><h2 style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:22px;color:#fff;font-weight:400;">+1 914 619 2229 | <a href="' . $mailto . '" style="color:#FFF;text-decoration:none">' . $email . '</a></h2></td></tr></table></div><div style="display:inline-block;margin:0 -1px;vertical-align:top;width:100%;text-align:center;" class="stack-column" align="center"><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"><tr><td style="padding:0 0 5px 0;" align="center"><img src="https://www.travelandtourworld.com/newsletter/2024/Mar/ttw-social-icon.png" alt="Social" width="140" usemap="#Map" class="center-on-narrow" style="width:140px;max-width:140px;height:35px;" border="0"></td></tr></table></div></td></tr>';
}

function render_social_map(): string
{
    return '<map name="Map">'
        . '<area shape="rect" coords="43,7,66,36" href="http://www.facebook.com/ttwemagazine" target="_blank" rel="noopener noreferrer">'
        . '<area shape="rect" coords="11,8,33,31" href="http://www.linkedin.com/company/travel-and-tour-world" target="_blank" rel="noopener noreferrer">'
        . '<area shape="rect" coords="73,4,99,30" href="https://www.instagram.com/travelandtourworld" target="_blank" rel="noopener noreferrer">'
        . '<area shape="rect" coords="107,2,126,34" href="https://x.com/ttw_digital" target="_blank" rel="noopener noreferrer">'
        . '</map>';
}
