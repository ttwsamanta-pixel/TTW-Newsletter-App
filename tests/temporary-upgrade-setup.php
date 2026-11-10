<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$pdo=new PDO('mysql:host=127.0.0.1;port=33319;charset=utf8mb4','root','manager_fixture_only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE IF NOT EXISTS ttw_upgrade_page_test CHARACTER SET utf8mb4');$pdo->exec('USE ttw_upgrade_page_test');
function upgrade_fixture_sql(PDO $pdo,string $file):void {foreach(explode(';',preg_replace('/^\s*--.*$/m','',file_get_contents($file))) as $sql)if(trim($sql)!==''){$s=$pdo->query($sql);$s->closeCursor();}}
if(!(int)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE()")->fetchColumn()) {
    upgrade_fixture_sql($pdo,__DIR__.'/fixture-schema.sql');
    upgrade_fixture_sql($pdo,dirname(__DIR__).'/migrations/001_automation.sql');
    foreach(['admin','employee'] as $role)$pdo->prepare('INSERT INTO users(username,password_hash,role,first_name,permissions) VALUES(?,?,?,?,?)')->execute(['upgrade_'.$role,password_hash('upgrade_fixture_password',PASSWORD_DEFAULT),$role,ucfirst($role),'["view"]']);
    $pdo->exec("INSERT INTO newsletters(newsletter_date,edition,subject_title,header_title) VALUES('2092-02-03','M','Preserved issue','Original Header')");
    $pdo->exec('CREATE TABLE backup_payloads(id INT PRIMARY KEY,label TEXT,raw_bytes LONGBLOB,nullable_value TEXT NULL,next_id INT AS (id+1) STORED)');
    $pdo->prepare('INSERT INTO backup_payloads(id,label,raw_bytes,nullable_value) VALUES(?,?,?,NULL)')->execute([69,"Quoted ' slash \\ newline\nUnicode \u{09AC}\u{09BE}\u{0982}\u{09B2}\u{09BE}","\0\xff\x01binary"]);
}
echo "Temporary upgrader fixture ready (no manager migration applied).\n";
