<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$root=__DIR__.'/.runtime/manager-server/New';
$env=getenv();
foreach(['TTW_DB_HOST'=>'127.0.0.1','TTW_DB_PORT'=>'33319','TTW_DB_NAME'=>'ttw_manager_test','TTW_DB_USER'=>'root','TTW_DB_PASSWORD'=>'manager_fixture_only'] as $k=>$v)$env[$k]=$v;
function run_manager_worker(string $root,array $env): array {
    $p=proc_open([PHP_BINARY,$root.'/automation/worker.php','--once'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($p),$out,$err];
}
[$code,$out]=run_manager_worker($root,$env);
if($code!==0||!str_contains($out,'0 article(s) processed'))throw new RuntimeException('Existing empty queue worker regressed.');
$heartbeat=json_decode(file_get_contents($root.'/storage/worker-heartbeat.json'),true);
if(!$heartbeat||abs(time()-$heartbeat['time'])>10)throw new RuntimeException('Worker heartbeat not recorded.');
$lock=fopen($root.'/storage/automation-worker.lock','c');flock($lock,LOCK_EX);
try {[$code,$out]=run_manager_worker($root,$env);if($code!==0||!str_contains($out,'already running'))throw new RuntimeException('Overlap lock regressed.');}
finally {flock($lock,LOCK_UN);fclose($lock);}
echo "PASS: 3 worker checks (existing queue, heartbeat, overlap lock)\n";
