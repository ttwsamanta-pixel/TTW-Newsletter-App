<?php
declare(strict_types=1);
// TEMPORARY: upload beside the EXISTING /New/auth.php; delete this page after use.
if (!is_file(__DIR__.'/auth.php') || !is_file(__DIR__.'/config.php')) {
    http_response_code(503); header('Content-Type: text/plain; charset=utf-8');
    exit('Upload this temporary file directly into /New/, beside the existing auth.php and config.php.');
}
require_once __DIR__.'/auth.php';
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
$local=getenv('TTW_ALLOW_LOCAL_HTTP')==='1' && in_array($_SERVER['REMOTE_ADDR'] ?? '',['127.0.0.1','::1'],true);
if ((empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') && !$local) { http_response_code(426); exit('Open this page over HTTPS.'); }
$user=require_auth();
if (!is_admin($user)) { http_response_code(403); exit('Administrator permission required.'); }
$method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method,['GET','POST'],true)) { http_response_code(405); header('Allow: GET, POST'); exit; }

function upgrade003_definitions(): array {
    return [
        'newsletter_publications'=>[
            'columns'=>['newsletter_id','status','file_name','public_url','html_sha256','published_at','published_by','error_message','updated_at'],
            'primary'=>['newsletter_id'],
            'sql'=>"CREATE TABLE IF NOT EXISTS newsletter_publications (
                newsletter_id BIGINT UNSIGNED PRIMARY KEY,
                status VARCHAR(24) NOT NULL DEFAULT 'unpublished', file_name VARCHAR(100) NULL,
                public_url VARCHAR(2048) NULL, html_sha256 CHAR(64) NULL, published_at DATETIME NULL,
                published_by BIGINT UNSIGNED NULL, error_message VARCHAR(250) NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ],
        'newsletter_notification_reads'=>[
            'columns'=>['user_id','event_key','read_at'], 'primary'=>['user_id','event_key'],
            'sql'=>"CREATE TABLE IF NOT EXISTS newsletter_notification_reads (
                user_id BIGINT UNSIGNED NOT NULL, event_key VARCHAR(100) NOT NULL,
                read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(user_id,event_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ],
        'newsletter_password_resets'=>[
            'columns'=>['token_hash','user_id','expires_at','used_at','created_at'], 'primary'=>['token_hash'],
            'sql'=>"CREATE TABLE IF NOT EXISTS newsletter_password_resets (
                token_hash CHAR(64) PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
                expires_at DATETIME NOT NULL, used_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ],
    ];
}
function upgrade003_identifier(string $name): string { return '`'.str_replace('`','``',$name).'`'; }
function upgrade003_state(PDO $pdo): array {
    $columns=[];
    $s=$pdo->query("SELECT TABLE_NAME,COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('users','newsletters','newsletter_publications','newsletter_notification_reads','newsletter_password_resets')");
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) $columns[$row['TABLE_NAME']][]=$row['COLUMN_NAME'];
    $missing=[]; $problems=[];
    if (empty($columns['users']) || empty($columns['newsletters'])) $problems[]='The existing newsletter tables were not found. Check the application database configuration.';
    if (!in_array('email',$columns['users'] ?? [],true)) $missing['users.email']='Add optional registered email to existing users';
    foreach (upgrade003_definitions() as $table=>$definition) {
        if (!isset($columns[$table])) { $missing[$table]='Create '.$table; continue; }
        $absent=array_diff($definition['columns'],$columns[$table]);
        if ($absent) $problems[]=$table.' already exists with missing columns: '.implode(', ',$absent).'. Ask your administrator to review it; this page will not rebuild existing tables.';
        $keys=$pdo->query('SHOW INDEX FROM '.upgrade003_identifier($table)." WHERE Key_name='PRIMARY'")->fetchAll(PDO::FETCH_ASSOC);
        usort($keys,fn($a,$b)=>(int)$a['Seq_in_index']<=>(int)$b['Seq_in_index']);
        if (array_column($keys,'Column_name')!==$definition['primary']) $problems[]=$table.' has an unexpected primary key. Ask your administrator to review it.';
    }
    return ['missing'=>$missing,'problems'=>$problems,'ready'=>!$missing && !$problems];
}
function upgrade003_backup(PDO $pdo): never {
    @set_time_limit(300);
    // Preflight before sending a file: no server-side database dump is created.
    $tables=[];
    foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as $row) {
        if (($row[1] ?? '')!=='BASE TABLE') continue;
        $name=(string)$row[0]; $quoted=upgrade003_identifier($name);
        $definition=$pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1];
        $pdo->query('SELECT 1 FROM '.$quoted.' LIMIT 0')->closeCursor();
        $fields=[];
        foreach ($pdo->query('SHOW FULL COLUMNS FROM '.$quoted)->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if (!preg_match('/(?:VIRTUAL|STORED) GENERATED/i',(string)$column['Extra'])) $fields[]=$column['Field'];
        }
        $tables[]=['name'=>$name,'definition'=>$definition,'fields'=>$fields];
    }
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="TTW-database-tables-'.date('Ymd-His').'.sql"');
    echo "-- TTW database TABLES backup. Keep this file private.\n-- Restore into an empty database. Views, triggers, routines and server settings are not included.\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
    try {
        foreach ($tables as $table) {
            $name=upgrade003_identifier($table['name']);
            echo "\n".$table['definition'].";\n";
            if (!$table['fields']) continue;
            $fields=implode(',',array_map('upgrade003_identifier',$table['fields']));
            $s=$pdo->query('SELECT '.$fields.' FROM '.$name);
            while ($row=$s->fetch(PDO::FETCH_NUM)) {
                $values=array_map(static fn($v)=>$v===null ? 'NULL' : "FROM_BASE64('".base64_encode((string)$v)."')",$row);
                echo 'INSERT INTO '.$name.' ('.$fields.') VALUES ('.implode(',',$values).");\n";
            }
            $s->closeCursor();
        }
        $pdo->commit();
        echo "\nSET FOREIGN_KEY_CHECKS=1;\n-- TTW BACKUP COMPLETED\n";
        $_SESSION['ttw_upgrade003_backup_ok']=true;
        session_write_close();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        unset($_SESSION['ttw_upgrade003_backup_ok']);
        echo "\n-- TTW BACKUP FAILED. Do not use this incomplete backup. Retry or contact the hosting administrator.\n";
    }
    exit;
}
function upgrade003_apply(PDO $pdo): array {
    $database=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $lock='ttw-manager-003-'.substr(hash('sha256',$database),0,32);
    $s=$pdo->prepare('SELECT GET_LOCK(?,0)'); $s->execute([$lock]);
    if ((int)$s->fetchColumn()!==1) throw new RuntimeException('Another upgrade is running. Wait, then reload this page.');
    try {
        $state=upgrade003_state($pdo);
        if ($state['problems']) throw new RuntimeException(implode(' ',$state['problems']));
        if (isset($state['missing']['users.email'])) $pdo->exec('ALTER TABLE users ADD COLUMN email VARCHAR(254) NULL');
        foreach (upgrade003_definitions() as $table=>$definition) if (isset($state['missing'][$table])) $pdo->exec($definition['sql']);
        return upgrade003_state($pdo);
    } finally { $s=$pdo->prepare('SELECT RELEASE_LOCK(?)'); $s->execute([$lock]); }
}
$notice=''; $error=''; $state=null;
try {
    $pdo=db();
    if ($method==='POST') {
        verify_csrf();
        $action=(string)($_POST['action'] ?? '');
        if ($action==='backup') upgrade003_backup($pdo);
        if ($action!=='apply') throw new RuntimeException('Unknown action.');
        if (empty($_SESSION['ttw_upgrade003_backup_ok']) || ($_POST['confirm_backup'] ?? '')!=='1') throw new RuntimeException('Download the database tables backup first, refresh this page, then confirm you saved it.');
        $state=upgrade003_apply($pdo);
        $notice=$state['ready'] ? 'Database upgrade completed. Existing users, newsletters and images were kept. Delete upgrade-full-manager.php from /New/ now.' : 'Some required changes are still missing. Review the report below.';
    }
    $state ??= upgrade003_state($pdo);
} catch (PDOException $e) {
    $number=(int)($e->errorInfo[1] ?? 0);
    $error=in_array($number,[1044,1045,1142,1143,1227],true)
        ? 'The database account does not have the required access. Ask the hosting administrator for SELECT, CREATE and ALTER permissions. Uploading PHP cannot bypass database permissions.'
        : 'The database operation could not finish. No existing tables were deleted. Reload to check what remains; completed additions are kept and can be resumed. Contact the hosting administrator if it continues.';
    error_log('TTW temporary upgrade: SQLSTATE='.(string)$e->getCode().'; driver_code='.$number);
} catch (RuntimeException $e) { $error=$e->getMessage(); }
catch (Throwable $e) { $error='Unable to check the database. Verify the existing application configuration or contact the administrator.'; }
$ready=$state['ready'] ?? false;
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TTW temporary database upgrade</title>
<style>body{font:16px system-ui,sans-serif;color:#10204f;background:#f3f7ff;margin:0;padding:24px}main{max-width:780px;margin:24px auto;background:white;border:1px solid #dfe6f4;border-radius:20px;padding:28px}h1{font-size:25px}p,li{line-height:1.6}button,a.button{display:inline-block;background:#085ed7;color:white;padding:13px 20px;border:0;border-radius:10px;font-size:15px;text-decoration:none;cursor:pointer}button:disabled{background:#9aa8bf;cursor:default}.notice{padding:16px;border-radius:12px;background:#e6f8e9}.error{padding:16px;border-radius:12px;background:#fff0e6}.muted{color:#596783}section{border-top:1px solid #e5eaf5;margin-top:24px;padding-top:16px}label{display:block;margin:18px 0}</style></head><body><main>
<h1>TTW database upgrade — full manager 0.3.0</h1>
<p>Temporary page for your existing newsletter database. It uses the current application configuration; no hosting or phpMyAdmin login is needed.</p>
<?php if ($error): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($notice): ?><p class="notice" role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if ($ready): ?>
<p class="notice"><strong>Already up to date.</strong> All four required schema additions are present. Further upgrade actions are disabled.</p>
<p><strong>Remove <code>/New/upgrade-full-manager.php</code> using FileZilla.</strong> Keep your downloaded backup on your computer.</p>
<p>Continue with the v0.3.0 web package and main-website publishing setup. This page does not deploy either application or configure publishing.</p>
<?php elseif ($state): ?>
<h2>Missing changes</h2><ul><?php foreach ($state['missing'] as $label): ?><li><?= h($label) ?></li><?php endforeach; ?></ul>
<?php foreach ($state['problems'] as $problem): ?><p class="error"><?= h($problem) ?></p><?php endforeach; ?>
<p>Only the optional users.email column and the three new manager tables can be added. Existing tables are not emptied or rebuilt. No images are moved or deleted.</p>
<section><h2>1. Download a backup</h2><p>The private download includes database table definitions and rows. It is sent to your browser, not saved in a public server folder. Views, triggers, routines and uploaded image files are not included.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="backup"><button>Download Database Tables Backup</button></form>
<p class="muted">Wait for the download to finish. Keep the SQL file private. Its last line must say “TTW BACKUP COMPLETED”.</p>
<p><a href="upgrade-full-manager.php">Refresh this page after downloading</a></p></section>
<section><h2>2. Apply missing changes</h2>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="apply"><label><input type="checkbox" name="confirm_backup" value="1" required> I saved the completed database tables backup.</label><button <?= empty($_SESSION['ttw_upgrade003_backup_ok']) || $state['problems'] ? 'disabled':'' ?>>Run Database Upgrade</button></form>
<p class="muted">The database account needs CREATE and ALTER permissions. DDL changes commit individually; if a step fails, reload and resume the missing steps. This page accepts no custom SQL.</p></section>
<?php endif; ?>
<section><a href="login.php">Website login</a> · <a href="index.php">Back to newsletter application</a></section>
</main></body></html>
