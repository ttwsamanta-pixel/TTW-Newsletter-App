<?php
declare(strict_types=1);
// Guard this isolated fixture against accidental use on an existing database.
if(PHP_SAPI!=='cli' || getenv('TTW_DB_NAME')!=='ttw_automation_test')exit('Use the isolated ttw_automation_test database.');
require_once dirname(__DIR__).'/automation/jobs.php';
$server=new PDO('mysql:host=127.0.0.1;port='.(getenv('TTW_DB_PORT') ?: '33318'),getenv('TTW_DB_USER'),getenv('TTW_DB_PASSWORD'));
$server->exec('CREATE DATABASE IF NOT EXISTS ttw_automation_test CHARACTER SET utf8mb4');
if(!db()->query('SHOW TABLES')->fetch()) {
    foreach([__DIR__.'/fixture-schema.sql',dirname(__DIR__).'/migrations/001_automation.sql'] as $file) {
        foreach(explode(';',file_get_contents($file)) as $sql) {
            if(trim($sql)!=='') { $stmt=db()->query($sql); $stmt->closeCursor(); }
        }
    }
}
if((int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn()>0)exit("Fixture already seeded.\n");
$stmt=db()->prepare('INSERT INTO users(username,password_hash,role,first_name,permissions) VALUES(?,?,?,?,?)');
$stmt->execute(['test_admin',password_hash('ttw_test_password',PASSWORD_DEFAULT),'admin','Test Admin','[]']);
$stmt->execute(['test_viewer',password_hash('ttw_test_password',PASSWORD_DEFAULT),'employee','Viewer','["view"]']);
echo "Fixture and migration installed.\n";
