<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
function failure_request(string $route,array $data,?string $token=null): array {
    $ch=curl_init('http://127.0.0.1:8093/New/api/index.php?route='.urlencode($route));$headers=['Content-Type: application/json'];if($token)$headers[]='Authorization: Bearer '.$token;
    curl_setopt_array($ch,[CURLOPT_POSTFIELDS=>json_encode($data),CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>35]);$r=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return [$code,json_decode((string)$r,true)];
}
$pdo=new PDO('mysql:host=127.0.0.1;port=33319;dbname=ttw_manager_test','root','manager_fixture_only');
$p=$pdo->query("SELECT p.* FROM newsletter_publications p JOIN newsletters n ON n.id=p.newsletter_id WHERE p.status='published' AND n.approved_at IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if(!$p)throw new RuntimeException('Run the web publication fixture first.');
[$code,$auth]=failure_request('/auth/login',['username'=>'manager_admin','password'=>'fixture_password_123']);if($code!==200)throw new RuntimeException('Fixture login failed.');
[$code,$r]=failure_request('/newsletters/'.$p['newsletter_id'].'/publish-html',[],$auth['token']);
if($code!==502 || !str_contains($r['error'],'Main website HTML upload failed'))throw new RuntimeException('Failed receiver authorization did not return a useful retry error.');
$s=$pdo->prepare('SELECT * FROM newsletter_publications WHERE newsletter_id=?');$s->execute([$p['newsletter_id']]);$after=$s->fetch(PDO::FETCH_ASSOC);
if($after['status']!=='published'||$after['public_url']!==$p['public_url']||$after['html_sha256']!==$p['html_sha256'])throw new RuntimeException('Prior successful publication was damaged by failed republish.');
if(str_contains(json_encode($r),'fixture-token')||str_contains(json_encode($r),'127.0.0.1'))throw new RuntimeException('Publication failure revealed configuration.');
echo "PASS: 3 publication failure checks (useful error, prior publication preserved, no secret disclosure)\n";
