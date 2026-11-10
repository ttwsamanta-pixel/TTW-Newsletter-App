<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
$pdo=new PDO('mysql:host=127.0.0.1;port=33319;charset=utf8mb4','root','manager_fixture_only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE IF NOT EXISTS ttw_manager_test CHARACTER SET utf8mb4');
$pdo->exec('USE ttw_manager_test');
function fixture_sql(PDO $pdo,string $path): void { foreach(explode(';',file_get_contents($path)) as $sql) { if(trim($sql)!=='') { $q=$pdo->query($sql); $q->closeCursor(); } } }
// Only create into a new isolated database. No production database is selected.
if (!(int)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='ttw_manager_test'")->fetchColumn()) {
    fixture_sql($pdo,__DIR__.'/fixture-schema.sql');
}
if (!(int)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='ttw_manager_test' AND TABLE_NAME='automation_jobs'")->fetchColumn()) fixture_sql($pdo,dirname(__DIR__).'/migrations/001_automation.sql');
fixture_sql($pdo,dirname(__DIR__).'/migrations/003_full_manager.sql');
fixture_sql($pdo,dirname(__DIR__).'/migrations/003_full_manager.sql');
foreach (['admin'=>['view','create','edit','delete','download'],'editor'=>['view','create','edit','download'],'viewer'=>['view']] as $key=>$permissions) {
    $pdo->prepare('INSERT INTO users(username,password_hash,role,first_name,last_name,permissions,status,email) VALUES(?,?,?,?,?,?,"active",?) ON DUPLICATE KEY UPDATE permissions=VALUES(permissions),status="active",password_hash=VALUES(password_hash)')->execute(['manager_'.$key,password_hash('fixture_password_123',PASSWORD_DEFAULT),$key==='admin'?'admin':'employee',ucfirst($key),'Fixture',json_encode($permissions),$key.'@example.invalid']);
}
$pdo->exec('DELETE FROM api_rate_limits');
echo "Isolated manager schema and users ready; additive migration rerun passed.\n";
