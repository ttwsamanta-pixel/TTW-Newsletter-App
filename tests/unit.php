<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/automation/fetcher.php';
require_once dirname(__DIR__).'/includes/security.php';
require_once dirname(__DIR__).'/newsletter_renderer.php';
$checks=0;
function check(bool $condition,string $message): void { global $checks; $checks++; if(!$condition) throw new RuntimeException($message); }
$master=[['id'=>1,'category_name'=>'TOURISM NEWS','category_slug'=>'tourism-news','aliases'=>'Tourism','active'=>1]];
$base='https://www.travelandtourworld.com/news/article/';
$parsed=parse_newsletter_input(" HEADER :\n{$base}header/\nPick News:\n{$base}pick/\nMore News:\n{$base}more/\nCategories:\nTourism  News:\n{$base}category/",$master);
check($parsed['total']===4 && !$parsed['errors'],'Sections and dynamic category');
check($parsed['articles'][3]['category_id']===1,'Category mapping');
foreach(['Tourism News','TOURISM NEWS','tourism news','Tourism  News','Tourism'] as $name)check(category_map($master)[normalize_category($name)]['id']===1,'Category normalization');
$p=parse_newsletter_input("header:\n{$base}header/?utm_source=email\nPick News:\n{$base}header/#fragment\n{$base}pick/",$master);
check($p['total']===2 && count($p['warnings'])===1,'Duplicate canonical input normalization');
$p=parse_newsletter_input("header:\nhttps://127.0.0.1/\nPick News:\nhttp://www.travelandtourworld.com/news/article/test/",$master);
check(count($p['errors'])===3,'Invalid URLs and missing header');
$p=parse_newsletter_input("header:\n{$base}header/\nCategories:\nUnknown news:\n{$base}other/",$master);
check(count($p['errors'])===2,'Unknown category blocks and URL has no category');
$p=parse_newsletter_input("header:\n{$base}one/\n{$base}two/",$master);check(count($p['errors'])===1,'Multiple headers blocked');
$p=parse_newsletter_input("header: {$base}one/\nPick News: {$base}two/\nCategories:\nTourism News： {$base}three/",$master);check($p['total']===3 && !$p['errors'],'Inline URLs and full-width colon');
$many="header:\n{$base}head/\nPick News:\n";for($i=0;$i<50;$i++)$many.=$base.$i."/\n";
check(count(parse_newsletter_input($many,$master)['errors'])===1,'Maximum 50 URLs enforced');
$html='<html><head><meta property="og:title" content="Original &amp; Exact Title"><meta name="description" content="Original excerpt."><meta property="og:image" content="/images/featured.jpg"><link rel="canonical" href="'.$base.'canonical/"></head><body><article><h1>Fallback</h1><p>Fallback text</p></article></body></html>';
$article=extract_article($html,$base.'test/');
check($article['title']==='Original & Exact Title','Original title extraction');
check($article['description']==='Original excerpt.','Original description extraction');
check($article['featured_image']==='https://www.travelandtourworld.com/images/featured.jpg','Relative image extraction');
check($article['canonical_url']===$base.'canonical/','Canonical extraction');
$a=extract_article('<article><h1>Original heading</h1><p>Original paragraph</p></article>',$base.'test/');
check($a['title']==='Original heading' && $a['description']==='Original paragraph','DOM fallback extraction');
foreach(['https://127.0.0.1/test','https://www.travelandtourworld.com.evil.test/test','http://www.travelandtourworld.com/test','https://user@www.travelandtourworld.com/test'] as $unsafe){try{safe_fetch($unsafe);check(false,'Unsafe URL permitted');}catch(RuntimeException $e){check(true,'Unsafe URL blocked');}}
try{process_image('not an image','2026-11-06',610,380);check(false,'Invalid image permitted');}catch(RuntimeException $e){check(true,'Invalid image rejected');}
check(valid_issue_date('2026-11-06') && !valid_issue_date('2026-02-30'),'Strict date validation');
$image=missing_image('2026-11-06',610,380);$size=getimagesize(dirname(__DIR__).'/'.$image);check($size[0]===610 && $size[1]===380,'Warning placeholder has real header dimensions');delete_local_upload($image);
$n=['newsletter_date'=>'2026-11-06','edition'=>'M','subject_title'=>'Fixture subject','hidden_text'=>'Fixture description','header_title'=>'Original Header','header_description'=>'Original description','header_url'=>$base.'test/','header_image'=>'uploads/fixture.jpg','pick_news'=>[],'more_news'=>[],'categories'=>[],'promotion_enabled'=>0];
$rendered=render_newsletter($n);check(str_contains($rendered,'Original Header') && str_contains($rendered,'DAILY TRAVEL NEWS UPDATE'),'Existing renderer smoke check');
echo "PASS: $checks checks\n";
