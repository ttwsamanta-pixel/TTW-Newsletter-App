<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || getenv('TTW_DB_NAME')!=='ttw_automation_test')exit('Use isolated test database only.');
require_once dirname(__DIR__).'/automation/jobs.php';
require_once dirname(__DIR__).'/newsletter_renderer.php';
$user=db()->query('SELECT * FROM users WHERE username="test_admin"')->fetch();
$id=create_job(['newsletter_date'=>'2097-11-06','edition'=>'M','subject_title'=>'Live extraction smoke','hidden_text'=>'Original data smoke','text'=>"header:\nhttps://www.travelandtourworld.com/news/article/tbfqplg7jbyu/"],$user);
if(!worker_tick())throw new RuntimeException('Worker failed to claim job.');
$job=read_job($id,$user);
if(!in_array($job['status'],['completed','completed_with_warnings'],true))throw new RuntimeException('Worker article failed: '.json_encode($job['articles']));
if(!$job['articles'][0]['result']['title'])throw new RuntimeException('Original title missing.');
$image=$job['articles'][0]['result']['image'];$size=getimagesize(dirname(__DIR__).'/'.$image);
if($size[0]!==610 || $size[1]!==380)throw new RuntimeException('Header dimensions incorrect.');
$nId=generate_job($id,$user,['accept_warnings'=>true,'replace_draft'=>true]);
if(!str_contains(render_newsletter(load_newsletter($nId)),'DAILY TRAVEL NEWS UPDATE'))throw new RuntimeException('Renderer failed.');
echo 'PASS: real article fetched, image processed, queued draft rendered; status='.$job['status']."\n";
