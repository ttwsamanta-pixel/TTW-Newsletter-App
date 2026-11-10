<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
$checks=0;
function receiver_check(bool $ok,string $message): void { global $checks; $checks++; if (!$ok) throw new RuntimeException($message); }
$temp=tempnam(sys_get_temp_dir(),'ttw-receiver-');
$token=str_repeat('fixture-token-',4);
function receiver_request(array $fields=[],?string $file=null,string $token='',string $name='TTW_newsletter_03Oct2094M.html',string $method='POST'): array {
    $ch=curl_init('http://127.0.0.1:8091/newsletter-api/upload.php');
    if ($file) $fields['html']=new CURLFile($file,'text/html',$name);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['X-Newsletter-Token: '.$token],CURLOPT_TIMEOUT=>10]);
    if ($method==='POST') curl_setopt($ch,CURLOPT_POSTFIELDS,$fields);
    $raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return [$code,json_decode((string)$raw,true)];
}
try {
    $year=2094; while(is_file(__DIR__.'/.runtime/manager-main/newsletter/'.$year.'/Oct/TTW_newsletter_03Oct'.$year.'M.html'))$year++;
    $name='TTW_newsletter_03Oct'.$year.'M.html';
    $fields=['filename'=>$name,'year'=>(string)$year,'month'=>'Oct','approved'=>'1'];
    file_put_contents($temp,'<!doctype html><html><body><h1>Newsletter receiver fixture</h1></body></html>');
    [$code]=receiver_request($fields,$temp,'wrong');receiver_check($code===401,'Invalid token rejected');
    [$code]=receiver_request([],null,$token,'x','GET');receiver_check($code===405,'GET rejected');
    [$code,$r]=receiver_request($fields,$temp,$token);receiver_check($code===200 && $r['success']===true,'Valid HTML accepted');
    receiver_check($r['url']==='https://www.travelandtourworld.com/newsletter/'.$year.'/Oct/'.$name,'Canonical public URL returned');
    receiver_check($r['sha256']===hash_file('sha256',$temp),'Stored content hash verified');
    [$code,$r]=receiver_request($fields,$temp,$token);receiver_check($code===200 && $r['unchanged']===true,'Identical retry is idempotent');
    file_put_contents($temp,'<!doctype html><html><body>Changed approved newsletter</body></html>');
    [$code,$r]=receiver_request($fields,$temp,$token);receiver_check($code===409 && $r['code']==='FILE_EXISTS','Different duplicate protected');
    [$code]=receiver_request($fields+['replace'=>'1'],$temp,$token);receiver_check($code===200,'Explicit replacement accepted from trusted backend');
    foreach (['../../shell.php','shell.php','index.html','TTW_newsletter_31Feb2094M.html','TTW_newsletter_03Oct2094M.html.php'] as $name) {
        [$code]=receiver_request(array_replace($fields,['filename'=>$name]),$temp,$token,$name);receiver_check($code===422,'Unsafe filename rejected');
    }
    foreach ([['year'=>'2093'],['month'=>'../Oct'],['approved'=>'0']] as $change) { [$code]=receiver_request(array_replace($fields,$change),$temp,$token);receiver_check($code===422,'Invalid folder or approval rejected'); }
    [$code]=receiver_request($fields,null,$token);receiver_check($code===422,'Missing upload rejected');
    [$code]=receiver_request($fields,$temp,$token,'shell.php');receiver_check($code===422,'Invalid uploaded extension rejected');
    foreach (['not HTML',"<?php echo 'x'; ?><html><body>x</body></html>",'<html><body><script>alert(1)</script></body></html>','<html><body><img src="x" onerror="x"></body></html>'] as $bad) { file_put_contents($temp,$bad);[$code]=receiver_request($fields,$temp,$token);receiver_check($code===422,'Invalid/active content rejected'); }
    file_put_contents($temp,'<html><body>'.str_repeat('x',2100000).'</body></html>');[$code]=receiver_request($fields,$temp,$token);receiver_check($code===413,'Oversize HTML rejected');
    echo "PASS: $checks independent upload receiver checks\n";
} finally { unlink($temp); }
