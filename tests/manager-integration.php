<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
$checks=0;
function manager_check(bool $ok,string $label): void {global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function manager_request(string $route,?array $data=null,?string $token=null,array $query=[]): array {
    $ch=curl_init('http://127.0.0.1:8090/New/api/index.php?'.http_build_query(['route'=>$route]+$query)); $headers=['Accept: application/json'];
    if ($token) $headers[]='Authorization: Bearer '.$token;
    if ($data!==null) { $headers[]='Content-Type: application/json'; curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data)); }
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>40]); $raw=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return [$status,json_decode((string)$raw,true)??$raw];
}
$pdo=new PDO('mysql:host=127.0.0.1;port=33319;dbname=ttw_manager_test','root','manager_fixture_only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$tokens=[];
foreach (['admin','editor','viewer'] as $role) {[$status,$r]=manager_request('/auth/login',['username'=>'manager_'.$role,'password'=>'fixture_password_123']);manager_check($status===200,'Existing '.$role.' account signs in');$tokens[$role]=$r['token'];}
$admin=$tokens['admin'];$editor=$tokens['editor'];$viewer=$tokens['viewer'];
[$status]=manager_request('/dashboard');manager_check($status===401,'Dashboard requires auth');
[$status,$dashboard]=manager_request('/dashboard',null,$admin);manager_check($status===200 && isset($dashboard['stats']['total']),'Real dashboard counts');
[$status]=manager_request('/employees',null,$viewer);manager_check($status===403,'Viewer cannot enumerate employees');
[$status,$employees]=manager_request('/employees',null,$admin);manager_check($status===200 && !str_contains(json_encode($employees),'password_hash'),'Employee response contains no password hashes');
$suffix=bin2hex(random_bytes(3));
[$status,$employee]=manager_request('/employees',['username'=>'fixture_'.$suffix,'first_name'=>'Fixture','email'=>'fixture@example.invalid','password'=>'fixture_password_123','permissions'=>['view'],'status'=>'active'],$admin);manager_check($status===200,'Admin creates employee');$employeeId=$employee['id'];
[$status]=manager_request('/employees',['id'=>$employeeId,'permissions'=>['view','create']],$viewer);manager_check($status===403,'Permissions cannot be escalated by employee');
[$status]=manager_request('/employees',['id'=>$employeeId,'permissions'=>['view','create']],$admin);manager_check($status===200,'Admin updates existing permissions');
[$status]=manager_request('/employees',['id'=>$employeeId,'permissions'=>['admin']],$admin);manager_check($status===422,'Unknown permission rejected');
[$status]=manager_request('/employees',['id'=>1,'first_name'=>'Overwrite admin'],$admin);manager_check($status===422,'Admin account protected from employee editor');
[$status]=manager_request('/employees',['id'=>$employeeId,'status'=>'inactive'],$admin);manager_check($status===200,'Employee deactivation supported');
[$status]=manager_request('/auth/login',['username'=>'fixture_'.$suffix,'password'=>'fixture_password_123']);manager_check($status===401,'Inactive employee cannot log in');
$date='2093-11-10';
while (true) {$s=$pdo->prepare('SELECT COUNT(*) FROM newsletters WHERE newsletter_date=?');$s->execute([$date]);if(!$s->fetchColumn() && !is_file(__DIR__.'/.runtime/manager-main/newsletter/'.date('Y/M',strtotime($date)).'/TTW_newsletter_'.date('dMY',strtotime($date)).'M.html'))break;$date=(new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');}
[$status]=manager_request('/newsletters/draft',['newsletter_date'=>$date,'edition'=>'M'],$viewer);manager_check($status===403,'Viewer cannot create newsletter');
[$status,$draft]=manager_request('/newsletters/draft',['newsletter_date'=>$date,'edition'=>'M','hidden_text'=>'Manual hidden preview'],$admin);manager_check($status===200,'Draft without Email Title and Header is allowed');$id=$draft['id'];
[$status]=manager_request('/newsletters/'.$id.'/publish-html',[],$admin);manager_check($status===422,'Unapproved publishing rejected');
[$status]=manager_request('/newsletters/'.$id.'/approve',[],$editor);manager_check($status===422,'Approval requires title and Header');
[$status,$n]=manager_request('/newsletters/'.$id,null,$admin);manager_check($status===200 && $n['newsletter']['hidden_text']==='Manual hidden preview','Hidden text saved and resume works');
[$status]=manager_request('/newsletters/'.$id,['subject_title'=>'Manager Integration '.$suffix,'header_title'=>'Original Header Title','header_description'=>'Original body words...'],$editor);manager_check($status===200,'Metadata edits preserve draft');
$pdo->prepare('INSERT INTO more_travel_news(newsletter_id,sort_order,title,url) VALUES(?,1,?,?)')->execute([$id,'Retained More','https://www.travelandtourworld.com/news/article/retained/']);
$pdo->prepare('INSERT INTO pick_news(newsletter_id,sort_order,title,description,url) VALUES(?,1,?,?,?)')->execute([$id,'Pick Original','Original body snippet','https://www.travelandtourworld.com/news/article/pick/']);
[$status,$n]=manager_request('/newsletters/'.$id,null,$editor);$pickId=$n['newsletter']['pick_news'][0]['id'];
[$status]=manager_request('/newsletters/'.$id.'/section',['section'=>'pick','items'=>[['id'=>$pickId,'title'=>'Edited Pick','description'=>'Edited body...']]],$editor);manager_check($status===200,'Pick edits use shared persistence');
[$status,$n]=manager_request('/newsletters/'.$id,null,$admin);manager_check(count($n['newsletter']['more_news'])===1 && $n['newsletter']['more_news'][0]['title']==='Retained More','Editing Pick preserves unrelated More section');
[$status]=manager_request('/newsletters/'.$id.'/section',['section'=>'pick','items'=>[['id'=>999999,'title'=>'Injected','description'=>'x']]],$editor);manager_check($status===422,'Foreign article ID rejected');
[$status]=manager_request('/newsletters/'.$id.'/approve',[],$editor);manager_check($status===200,'Valid draft approved');
[$status]=manager_request('/newsletters/'.$id.'/section',['section'=>'header','header_title'=>'Unapproved change'],$editor);manager_check($status===422,'Approved section requires explicit reopen');
[$status]=manager_request('/newsletters/'.$id.'/download-html',null,$viewer);manager_check($status===403,'Download permission enforced');
[$status,$html]=manager_request('/newsletters/'.$id.'/download-html',null,$editor);manager_check($status===200 && str_contains($html,'DAILY TRAVEL NEWS UPDATE') && str_contains($html,'Edited Pick'),'Download uses existing renderer');
[$status]=manager_request('/newsletters/'.$id.'/publish-html',[],$viewer);manager_check($status===403,'Viewer cannot publish');
[$status,$published]=manager_request('/newsletters/'.$id.'/publish-html',[],$editor);manager_check($status===200 && $published['publication']['status']==='published','Approved backend-to-receiver publication succeeds');
manager_check(str_starts_with($published['publication']['public_url'],'https://www.travelandtourworld.com/newsletter/'),'Main-domain public URL saved');
$path=__DIR__.'/.runtime/manager-main'.parse_url($published['publication']['public_url'],PHP_URL_PATH);
manager_check(is_file($path) && hash_file('sha256',$path)===$published['publication']['html_sha256'],'Uploaded HTML bytes match stored publication hash');
[$status]=manager_request('/newsletters/'.$id.'/publish-html',[],$editor);manager_check($status===200,'Identical publication retry succeeds safely');
[$status]=manager_request('/newsletters/'.$id.'/publish-html',['replace'=>true],$editor);manager_check($status===403,'Only admin may explicitly replace publication');
[$status]=manager_request('/newsletters/'.$id.'/reopen',[],$editor);manager_check($status===200,'Explicit draft reopen works');
[$status,$n]=manager_request('/newsletters/'.$id,null,$editor);manager_check($n['newsletter']['publication']['status']==='stale','Published metadata remains but is marked stale after editing');
[$status]=manager_request('/newsletters/'.$id,['subject_title'=>'Revised Title '.$suffix],$editor);manager_check($status===200,'Revised title saved');
manager_request('/newsletters/'.$id.'/approve',[],$editor);
[$status,$conflict]=manager_request('/newsletters/'.$id.'/publish-html',[],$editor);manager_check($status===409 && $conflict['code']==='FILE_EXISTS','Changed existing HTML is not silently overwritten');
[$status]=manager_request('/newsletters/'.$id.'/publish-html',['replace'=>true],$admin);manager_check($status===200,'Admin replacement succeeds after explicit request');
[$status,$list]=manager_request('/newsletters',null,$admin,['q'=>$suffix,'from'=>$date,'to'=>$date,'edition'=>'M','status'=>'published']);manager_check($status===200 && count($list['newsletters'])===1,'Combined search/date/edition/publication filters work');
[$status]=manager_request('/newsletters',null,$admin,['from'=>'../invalid']);manager_check($status===422,'Invalid filter dates rejected');
[$status,$reports]=manager_request('/reports',null,$viewer,['month'=>substr($date,0,7)]);manager_check($status===200 && $reports['totals']['total']>=1 && count($reports['days'])>=1,'Monthly calendar and employee reports share existing data');
[$status]=manager_request('/reports',null,$admin,['month'=>'2093-99']);manager_check($status===422,'Invalid report month rejected');
[$status,$notes]=manager_request('/notifications',null,$admin);manager_check($status===200 && count($notes['notifications'])>0,'Notifications derived from actual saved events');
[$status]=manager_request('/notifications/read',[],$admin);manager_check($status===200,'Notifications can be marked read');
[$status,$notes]=manager_request('/notifications',null,$admin);manager_check($notes['unread']===0,'Read state persisted for this user');
[$status]=manager_request('/automation/run',[],$editor);manager_check($status===403,'Worker manual run restricted to admin');
[$status,$run]=manager_request('/automation/run',[],$admin);manager_check($status===200 && $run['processed']===0,'Bounded manual worker run reuses empty queue safely');
[$status,$monitor]=manager_request('/automation/status',null,$admin);manager_check($status===200 && $monitor['worker_recent']===(!empty($monitor['heartbeat']) && time()-(int)$monitor['heartbeat']['time']<180),'Worker status derives from actual heartbeat');
[$status,$profile]=manager_request('/profile',null,$editor);manager_check($status===200 && $profile['user']['username']==='manager_editor','Profile uses existing account');
[$status]=manager_request('/profile',['first_name'=>'Editor','last_name'=>'Fixture','email'=>'bad-email'],$editor);manager_check($status===422,'Invalid profile email rejected');
[$status]=manager_request('/profile',['first_name'=>'Editor','last_name'=>'Fixture','email'=>'editor@example.invalid','contact_no'=>'123'],$editor);manager_check($status===200,'Registered email/profile saved');
[$status,$reset]=manager_request('/auth/forgot-password',['identifier'=>'manager_editor']);manager_check($status===422 && !str_contains(json_encode($reset),'token'),'Unconfigured reset email is honest and reveals no reset token');
[$status]=manager_request('/newsletters/'.$id.'/delete',[],$editor);manager_check($status===403,'Delete permission enforced');
[$status]=manager_request('/newsletters/'.$id.'/delete',[],$admin);manager_check($status===200 && is_file($path),'Delete preserves published HTML');
[$status]=manager_request('/newsletters/'.$id,null,$admin);manager_check($status===404,'Deleted newsletter no longer readable');
$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$employeeId]);
echo "PASS: $checks full manager integration checks\n";
