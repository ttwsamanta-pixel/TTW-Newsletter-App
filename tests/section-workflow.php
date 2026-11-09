<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli' || getenv('TTW_DB_NAME')!=='ttw_automation_test' || getenv('TTW_DB_PORT')!=='33318') exit('Use the isolated fixture database only.');
require_once dirname(__DIR__).'/automation/jobs.php';
$checks=0;
function section_check(bool $ok,string $message): void {global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function section_request(string $route,?array $data,?string $token): array {
    $c=curl_init('http://127.0.0.1:8088/api/index.php?route='.urlencode($route));$headers=['Accept: application/json'];
    if($token)$headers[]='Authorization: Bearer '.$token;
    if($data!==null){$headers[]='Content-Type: application/json';curl_setopt($c,CURLOPT_POSTFIELDS,json_encode($data));}
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>20]);$body=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    return [$status,json_decode((string)$body,true) ?? $body];
}
function section_fixture(int $id): void {
    $job=db()->prepare('SELECT * FROM automation_articles WHERE job_id=? ORDER BY position');$job->execute([$id]);
    $text=implode(' ',array_map(fn($i)=>'bodyword'.$i,range(1,70)));
    foreach($job->fetchAll() as $a){
        $r=extract_article('<meta property="og:title" content="Wrong SEO title - Travel And Tour World"><meta name="description" content="Wrong metadata excerpt"><article><h1>Original '.$a['section'].' '.$a['position'].' - Travel And Tour World</h1><div class="single-news-content"><p>'.$text.'</p></div></article>',$a['url'],$a['section']);
        if(in_array($a['section'],['header','pick'])){$r['image']=missing_image('2091-11-09',...($a['section']==='header'?[610,380]:[350,291]));}
        $r['warnings']=[];
        db()->prepare('UPDATE automation_articles SET status="completed",result=? WHERE id=?')->execute([json_encode($r),$a['id']]);
    }
    db()->prepare('UPDATE automation_jobs SET status="completed",completed=total WHERE id=?')->execute([$id]);
}
function section_import(array $payload,array $user): int { $id=create_job($payload,$user);section_fixture($id);return $id; }
[$code,$login]=section_request('/auth/login',['username'=>'test_admin','password'=>'ttw_test_password'],null);section_check($code===200,'Admin API login');$token=$login['token'];
$user=db()->query('SELECT * FROM users WHERE username="test_admin"')->fetch();
$date='2091-11-09';do{$q=db()->prepare('SELECT COUNT(*) FROM newsletters WHERE newsletter_date=?');$q->execute([$date]);$used=(int)$q->fetchColumn();if($used)$date=(new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');}while($used);
[$code,$saved]=section_request('/newsletters/draft',['newsletter_date'=>$date,'edition'=>'M','subject_title'=>''],$token);
section_check($code===200 && $saved['newsletter']['subject_title']==='','Partial draft saves with no Email Title or Header');$id=(int)$saved['id'];
$base=['newsletter_date'=>$date,'edition'=>'M','input_mode'=>'section','target_id'=>$id];$url='https://www.travelandtourworld.com/news/article/';
$job=section_import($base+['section'=>'more','text'=>$url.'more1/' . "\n" . $url.'more2/'],$user);
[$code,$generated]=section_request('/automation/jobs/'.$job.'/generate',[],$token);section_check($code===200 && $generated['newsletter_id']===$id,'More News first applies to the same partial draft');
$n=load_newsletter($id);section_check(count($n['more_news'])===2 && $n['header_title']==='' && $n['subject_title']==='','More News import does not require Header/Email Title');
section_check(!str_contains(render_newsletter($n),'Header_'),'Partial preview does not render a broken missing header');
$mice=(int)db()->query("SELECT id FROM newsletter_categories_master WHERE category_slug='meeting-and-event-industry-news'")->fetchColumn();
$tourism=(int)db()->query("SELECT id FROM newsletter_categories_master WHERE category_slug='tourism-news'")->fetchColumn();
foreach([$mice,$tourism] as $category){$job=section_import($base+['section'=>'category','category_id'=>$category,'text'=>$url.'category'.$category.'/'],$user);[$code]=section_request('/automation/jobs/'.$job.'/generate',[],$token);section_check($code===200,'Category can arrive before Header');}
$n=load_newsletter($id);section_check(count($n['categories'])===2 && count($n['more_news'])===2,'Separate category imports retain other categories and More News');
$job=section_import($base+['section'=>'header','text'=>$url.'header/'],$user);[$code]=section_request('/automation/jobs/'.$job.'/generate',[],$token);$n=load_newsletter($id);
section_check($code===200 && $n['header_title']==='Original header 0','Header title uses original heading without site suffix');
section_check(count(explode(' ',$n['header_description']))===35 && str_ends_with($n['header_description'],'...'),'Header description is 35 content words plus dots');
section_check(count(explode(' ',$n['hidden_text']))===16 && str_starts_with($n['hidden_text'],'bodyword1 bodyword2'),'Hidden Preview Text is the first 16 header content words');
section_check(count($n['categories'])===2 && count($n['more_news'])===2,'Header import preserves earlier sections');
$job=section_import($base+['section'=>'pick','text'=>$url.'pick1/' . "\n" . $url.'pick2/'],$user);[$code]=section_request('/automation/jobs/'.$job.'/generate',[],$token);$n=load_newsletter($id);
section_check($code===200 && count($n['pick_news'])===2,'Pick News imports later into the existing draft');
section_check(count(explode(' ',$n['pick_news'][0]['description']))===16 && !str_contains($n['pick_news'][0]['title'],'Travel And Tour World'),'Pick description uses 16 body words and clean title');
section_check(count($n['more_news'])===2 && count($n['categories'])===2 && $n['hidden_text']!=='','Pick News retains Header, preview text, More News and Categories');
// Create two imports before applying either: the second must merge with fresh database state.
$moreJob=section_import($base+['section'=>'more','text'=>$url.'replacement-more/'],$user);
$categoryJob=section_import($base+['section'=>'category','category_id'=>$tourism,'text'=>$url.'replacement-category/'],$user);
section_request('/automation/jobs/'.$categoryJob.'/generate',[],$token);section_request('/automation/jobs/'.$moreJob.'/generate',[],$token);$n=load_newsletter($id);
section_check(count($n['more_news'])===1 && count($n['categories'])===2 && count($n['pick_news'])===2,'Overlapping prepared section jobs preserve unrelated latest data');
$tourismRows=array_values(array_filter($n['categories'],fn($c)=>$c['category_name']==='TOURISM NEWS'));
section_check(str_contains($tourismRows[0]['items'][0]['url'],'replacement-category'),'Only selected category group is refreshed');
[$code]=section_request('/newsletters/'.$id.'/approve',[],$token);section_check($code===422,'Approval requires manually entered Email Title');
[$code]=section_request('/newsletters/'.$id,['subject_title'=>'Manually added after all sections'],$token);$n=load_newsletter($id);
section_check($code===200 && $n['subject_title']==='Manually added after all sections' && count($n['pick_news'])===2 && count($n['categories'])===2,'Email Title can be entered after content is complete');
[$code]=section_request('/newsletters/'.$id.'/approve',[],$token);section_check($code===200,'Completed issue can be approved');
try{create_job($base+['section'=>'more','text'=>$url.'cannot-overwrite/'],$user);section_check(false,'Approved issue import allowed');}catch(InvalidArgumentException $e){section_check(true,'Approved issue protected from section imports');}
[$code,$viewer]=section_request('/auth/login',['username'=>'test_viewer','password'=>'ttw_test_password'],null);
[$code]=section_request('/newsletters/draft',['id'=>$id,'newsletter_date'=>$date,'edition'=>'M'],$viewer['token']);section_check($code===403,'Viewer cannot edit section drafts');
$all=['newsletter_date'=>$date,'edition'=>'E','text'=>"header:\n{$url}all-header/\nPick News:\n{$url}all-pick/\nMore News:\n{$url}all-more/"];
$allJob=section_import($all,$user);[$code,$result]=section_request('/automation/jobs/'.$allJob.'/generate',[],$token);$allN=load_newsletter((int)$result['newsletter_id']);
section_check($code===200 && $allN['subject_title']==='' && count($allN['pick_news'])===1 && count($allN['more_news'])===1,'All-links mode still works with optional title');
section_check(count(explode(' ',$allN['header_description']))===35 && count(explode(' ',$allN['hidden_text']))===16,'All-links mode shares body-based text rules');
echo "PASS: $checks section workflow checks\n";
