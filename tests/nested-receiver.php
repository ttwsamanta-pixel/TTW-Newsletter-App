<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$name='TTW_newsletter_13Nov2090E.html';$temp=tempnam(sys_get_temp_dir(),'ttw-nested-');
file_put_contents($temp,'<!doctype html><html><body>Nested receiver fixture</body></html>');
try {
    $ch=curl_init('http://127.0.0.1:8095/newsletter/newsletter-api/upload.php');
    curl_setopt_array($ch,[CURLOPT_POSTFIELDS=>['filename'=>$name,'year'=>'2090','month'=>'Nov','approved'=>'1','html'=>new CURLFile($temp,'text/html',$name)],CURLOPT_HTTPHEADER=>['X-Newsletter-Token: '.str_repeat('nested-fixture-token-',3)],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
    $r=json_decode(curl_exec($ch),true);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($code!==200 || empty($r['success']))throw new RuntimeException('Nested upload failed.');
    $expected='https://www.travelandtourworld.com/newsletter/2090/Nov/'.$name;
    if($r['url']!==$expected)throw new RuntimeException('Public URL changed unexpectedly.');
    $root=__DIR__.'/.runtime/nested-main/newsletter';
    if(!is_file($root.'/2090/Nov/'.$name)||is_dir($root.'/newsletter'))throw new RuntimeException('Wrong nested destination.');
    if(hash_file('sha256',$root.'/2090/Nov/'.$name)!==hash_file('sha256',$temp))throw new RuntimeException('Stored HTML changed.');
    echo "PASS: 4 actual nested receiver upload checks\n";
} finally {unlink($temp);}
