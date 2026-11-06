<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || getenv('TTW_DB_NAME')!=='ttw_automation_test')exit('Use isolated test database only.');
require_once dirname(__DIR__).'/automation/jobs.php';
$checks=0;
function verify(bool $ok,string $label): void { global $checks; $checks++; if(!$ok)throw new RuntimeException($label); }
function request(string $route,?array $data=null,?string $token=null): array {
    $ch=curl_init('http://127.0.0.1:8088/api/index.php?route='.urlencode($route));
    $headers=['Accept: application/json']; if($token)$headers[]='Authorization: Bearer '.$token;
    if($data!==null){$headers[]='Content-Type: application/json';curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));}
    curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]);
    $body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    return [$status,json_decode((string)$body,true) ?? $body];
}
[$status]=request('/newsletters');verify($status===401,'Unauthenticated request denied');
[$status]=request('/auth/login',['username'=>'test_admin','password'=>'wrong']);verify($status===401,'Wrong password denied');
[$status,$auth]=request('/auth/login',['username'=>'test_admin','password'=>'ttw_test_password']);verify($status===200 && strlen($auth['token'])===64,'Login returns strong token');$token=$auth['token'];
$stmt=db()->prepare('SELECT token_hash FROM api_tokens WHERE token_hash=?');$stmt->execute([hash('sha256',$token)]);verify($stmt->fetchColumn()!==$token,'Only token hash stored');
[$status,$viewer]=request('/auth/login',['username'=>'test_viewer','password'=>'ttw_test_password']);verify($status===200,'Viewer login');
[$status]=request('/automation/parse',['text'=>'header:'],$viewer['token']);verify($status===403,'Viewer cannot create');
[$status,$categories]=request('/categories',null,$token);verify($status===200 && count($categories['categories'])===6,'Category master seeded');
[$status]=request('/categories',['category_name'=>'Collision','category_slug'=>'collision','aliases'=>'tourism news','active'=>1],$token);verify($status===422,'Ambiguous aliases rejected');
$base='https://www.travelandtourworld.com/news/article/';
$text="header:\n{$base}fixture-header/\nPick News:\n{$base}fixture-pick/\nMore News:\n{$base}fixture-more/\nCategories:\nTOURISM NEWS:\n{$base}fixture-category/";
[$status,$parsed]=request('/automation/parse',['text'=>$text],$token);verify($status===200 && $parsed['total']===4 && !$parsed['errors'],'API parser works');
$date='2098-'.date('m-d');
while(true) {
    $query=db()->prepare('SELECT COUNT(*) FROM newsletters WHERE newsletter_date=?');$query->execute([$date]);
    if(!(int)$query->fetchColumn())break;
    $date=(new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
}
$payload=['newsletter_date'=>$date,'edition'=>'M','subject_title'=>'Integration subject','hidden_text'=>'Manual email description','text'=>$text];
[$status,$created]=request('/automation/jobs',$payload,$token);verify($status===202,'Asynchronous job accepted');$jobId=$created['id'];
[$status,$job]=request('/automation/jobs/'.$jobId,null,$token);verify($status===200 && $job['status']==='pending' && $job['progress']===0,'Pending progress exposed');
[$status]=request('/automation/jobs/'.$jobId.'/generate',[],$token);verify($status===422,'Premature generation blocked');
// Deterministic extraction/image fixtures populate persisted article results.
// This tests queue-to-draft mapping without changing network or SSRF protections.
foreach($job['articles'] as $a){
    $result=extract_article('<meta property="og:title" content="Original '.$a['section'].' title"><meta property="og:description" content="Original excerpt">',$a['url']);
    if(in_array($a['section'],['header','pick'],true)){$size=$a['section']==='header'?[610,380]:[350,291];$result['image']=missing_image($date,...$size);}
    $result['warnings']=$a['section']==='header'?['Featured image could not be retrieved']:[];
    db()->prepare('UPDATE automation_articles SET status=?,result=?,error_message=?,started_at=NOW(),completed_at=NOW() WHERE id=?')->execute([$a['section']==='header'?'warning':'completed',json_encode($result),$a['section']==='header'?'Featured image could not be retrieved':null,$a['id']]);
}
db()->prepare("UPDATE automation_jobs SET status='completed_with_warnings',completed=total WHERE id=?")->execute([$jobId]);
[$status]=request('/automation/jobs/'.$jobId.'/generate',[],$token);verify($status===422,'Warnings require explicit consent');
[$status,$generated]=request('/automation/jobs/'.$jobId.'/generate',['accept_warnings'=>true,'replace_draft'=>true],$token);verify($status===200,'Draft generated with acknowledged warning');$id=$generated['newsletter_id'];
[$status,$n]=request('/newsletters/'.$id,null,$token);$n=$n['newsletter'];verify($status===200 && $n['header_title']==='Original header title' && count($n['pick_news'])===1 && count($n['more_news'])===1 && count($n['categories'][0]['items'])===1,'All existing collections populated');
[$status,$html]=request('/newsletters/'.$id.'/preview',null,$token);verify($status===200 && str_contains($html,'DAILY TRAVEL NEWS UPDATE') && str_contains($html,'Original header title'),'Secure existing renderer preview');
[$status,$again]=request('/automation/jobs/'.$jobId.'/generate',['accept_warnings'=>true],$token);verify($status===200 && $again['newsletter_id']===$id,'Generation idempotent');
[$status]=request('/newsletters/'.$id.'/approve',[],$token);verify($status===200,'Explicit approval works');
[$status,$n]=request('/newsletters/'.$id,null,$token);verify($n['newsletter']['approved_at']!==null,'Approval persisted');
[$status]=request('/newsletters/'.$id,['subject_title'=>'Edited subject','pick_news'=>[['id'=>$n['newsletter']['pick_news'][0]['id'],'title'=>'Corrected original title','description'=>'Corrected excerpt']]],$token);verify($status===200,'Mobile edit uses shared service');
[$status,$n]=request('/newsletters/'.$id,null,$token);verify($n['newsletter']['approved_at']===null && $n['newsletter']['pick_news'][0]['title']==='Corrected original title' && count($n['newsletter']['categories'])===1,'Edit resets approval and preserves collections');
// A distinct completed job for the same date must return conflict, then allow an explicit draft replacement.
$pdo=db();$user=$pdo->query('SELECT * FROM users WHERE username="test_admin"')->fetch();$otherId=create_job($payload,$user);
$rows=$pdo->prepare('SELECT * FROM automation_articles WHERE job_id=? ORDER BY position');$rows->execute([$jobId]);$source=$rows->fetchAll();
foreach($source as $a)$pdo->prepare('UPDATE automation_articles SET status="completed",result=? WHERE job_id=? AND position=?')->execute([$a['result'],$otherId,$a['position']]);
$pdo->prepare('UPDATE automation_jobs SET status="completed",completed=total WHERE id=?')->execute([$otherId]);
[$status,$conflict]=request('/automation/jobs/'.$otherId.'/generate',[],$token);verify($status===409 && $conflict['existing_id']===$id,'Duplicate issue protected');
[$status,$replaced]=request('/automation/jobs/'.$otherId.'/generate',['replace_draft'=>true],$token);verify($status===200 && $replaced['newsletter_id']===$id,'Explicit draft replacement reuses issue');
[$status]=request('/automation/jobs/'.$jobId.'/retry',[],$token);verify($status===422,'Generated jobs cannot be retried');

// Existing editor login, CSRF and six-step autosave regression.
$cookie=tempnam(sys_get_temp_dir(),'ttw_cookie');
$ch=curl_init('http://127.0.0.1:8088/login.php');curl_setopt_array($ch,[CURLOPT_COOKIEJAR=>$cookie,CURLOPT_RETURNTRANSFER=>true]);$login=curl_exec($ch);curl_close($ch);preg_match('/name="csrf" value="([^"]+)"/',$login,$loginToken);
$ch=curl_init('http://127.0.0.1:8088/login.php');curl_setopt_array($ch,[CURLOPT_POSTFIELDS=>http_build_query(['username'=>'test_admin','password'=>'ttw_test_password','csrf'=>$loginToken[1]]),CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_RETURNTRANSFER=>true]);curl_exec($ch);curl_close($ch);
$ch=curl_init('http://127.0.0.1:8088/editor.php');curl_setopt_array($ch,[CURLOPT_COOKIEFILE=>$cookie,CURLOPT_RETURNTRANSFER=>true]);$editor=curl_exec($ch);curl_close($ch);
verify(str_contains($editor,'Pick News') && str_contains($editor,'Promotion'),'Existing editor still renders six steps');preg_match('/name="csrf" value="([^"]+)"/',$editor,$m);
$data=['newsletter_date'=>$date,'edition'=>'E','subject_title'=>'Manual fixture','autosave'=>'1'];
$ch=curl_init('http://127.0.0.1:8088/save.php');curl_setopt_array($ch,[CURLOPT_COOKIEFILE=>$cookie,CURLOPT_POSTFIELDS=>http_build_query($data),CURLOPT_RETURNTRANSFER=>true]);curl_exec($ch);verify(curl_getinfo($ch,CURLINFO_HTTP_CODE)===403,'Web save rejects missing CSRF');curl_close($ch);
$data['csrf']=$m[1];$ch=curl_init('http://127.0.0.1:8088/save.php');curl_setopt_array($ch,[CURLOPT_COOKIEFILE=>$cookie,CURLOPT_POSTFIELDS=>http_build_query($data),CURLOPT_RETURNTRANSFER=>true]);$manual=json_decode(curl_exec($ch),true);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);verify($status===200 && isset($manual['id']),'Existing manual autosave works through shared service');
$ch=curl_init('http://127.0.0.1:8088/delete.php?id='.$manual['id']);curl_setopt_array($ch,[CURLOPT_COOKIEFILE=>$cookie,CURLOPT_RETURNTRANSFER=>true]);curl_exec($ch);curl_close($ch);
$stmt=db()->prepare('SELECT COUNT(*) FROM newsletters WHERE id=?');$stmt->execute([$manual['id']]);verify((int)$stmt->fetchColumn()===1,'GET cannot delete newsletters');
$ch=curl_init('http://127.0.0.1:8088/delete.php');curl_setopt_array($ch,[CURLOPT_COOKIEFILE=>$cookie,CURLOPT_POSTFIELDS=>http_build_query(['id'=>$manual['id']]),CURLOPT_RETURNTRANSFER=>true]);curl_exec($ch);verify(curl_getinfo($ch,CURLINFO_HTTP_CODE)===403,'Delete requires CSRF');curl_close($ch);
$ch=curl_init('http://127.0.0.1:8088/delete.php');curl_setopt_array($ch,[CURLOPT_COOKIEFILE=>$cookie,CURLOPT_POSTFIELDS=>http_build_query(['id'=>$manual['id'],'csrf'=>$m[1]]),CURLOPT_RETURNTRANSFER=>true]);curl_exec($ch);curl_close($ch);unlink($cookie);
$stmt->execute([$manual['id']]);verify((int)$stmt->fetchColumn()===0,'Authorized POST deletion works');
[$status]=request('/auth/logout',[],$token);verify($status===200,'Logout revokes token');
[$status]=request('/newsletters',null,$token);verify($status===401,'Revoked token denied');
echo "PASS: $checks integration checks\n";
