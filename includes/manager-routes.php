<?php
declare(strict_types=1);
require_once __DIR__.'/manager.php';

function manager_routes(string $route,string $method,array $input,array $user): void {
    if ($route==='/dashboard' && $method==='GET') {
        api_permission('view',$user);
        $stats=db()->query("SELECT COUNT(*) total,COALESCE(SUM(edition='M'),0) morning,COALESCE(SUM(edition='E'),0) evening,COALESCE(SUM(DATE_FORMAT(newsletter_date,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')),0) this_month,COALESCE(SUM(approved_at IS NULL),0) drafts FROM newsletters")->fetch();
        $s=db()->prepare('SELECT id,edition,approved_at,subject_title FROM newsletters WHERE newsletter_date=?'); $s->execute([date('Y-m-d')]);
        api_reply(['stats'=>$stats,'today'=>$s->fetchAll(),'server_date'=>date('Y-m-d'),'recent'=>manager_newsletters([])['newsletters']]);
    }
    if ($route==='/newsletters' && $method==='GET') {
        api_permission('view',$user); $result=manager_newsletters($_GET);
        $s=db()->prepare('SELECT id,edition,approved_at FROM newsletters WHERE newsletter_date=?'); $s->execute([date('Y-m-d')]);
        api_reply($result+['today'=>$s->fetchAll(),'server_date'=>date('Y-m-d')]);
    }
    if (preg_match('~^/newsletters/(\d+)/(publish-html|download-html|reopen|section|delete)$~',$route,$m)) {
        $id=(int)$m[1]; $action=$m[2];
        api_permission($action==='download-html' ? 'download':($action==='delete' ? 'delete':'edit'),$user);
        $n=load_newsletter($id); if (!$n) throw new OutOfBoundsException();
        if ($action==='publish-html' && $method==='POST') {
            api_permission('download',$user); if (!empty($input['replace']) && !is_admin($user)) api_reply(['error'=>'Only administrators can replace published HTML.'],403);
            try { $publication=publish_newsletter($id,$user,!empty($input['replace'])); }
            catch (PDOException|OutOfBoundsException $e) { throw $e; }
            catch (RuntimeException $e) { api_reply(['error'=>'Main website HTML upload failed. Check the upload API configuration and retry.'],502); }
            api_reply(['publication'=>$publication]);
        }
        if ($action==='download-html' && $method==='GET') { header('Content-Type: text/html; charset=utf-8'); header('Content-Disposition: attachment; filename="'.issue_file_name($n['newsletter_date'],$n['edition']).'"'); echo render_newsletter($n); exit; }
        if ($action==='reopen' && $method==='POST') { db()->prepare('UPDATE newsletters SET approved_at=NULL,approved_by=NULL WHERE id=?')->execute([$id]); api_reply(['ok'=>true]); }
        if ($action==='delete' && $method==='POST') {
            $pdo=db(); $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM category_news WHERE category_id IN (SELECT id FROM newsletter_categories WHERE newsletter_id=?)')->execute([$id]);
                foreach (['pick_news','more_travel_news','newsletter_categories','newsletter_publications'] as $table) $pdo->prepare('DELETE FROM '.$table.' WHERE newsletter_id=?')->execute([$id]);
                $pdo->prepare('UPDATE automation_jobs SET newsletter_id=NULL WHERE newsletter_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM newsletters WHERE id=?')->execute([$id]); $pdo->commit();
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
            api_reply(['ok'=>true,'message'=>'Newsletter deleted. Previously published HTML is retained on the main website.']);
        }
        if ($action==='section' && $method==='POST') {
            if ($n['approved_at']) throw new InvalidArgumentException('Reopen this approved newsletter as a draft before editing.');
            $pdo=db(); $pdo->beginTransaction();
            try {
            $s=$pdo->prepare('SELECT approved_at FROM newsletters WHERE id=? FOR UPDATE'); $s->execute([$id]); if ($s->fetchColumn()) throw new InvalidArgumentException('Newsletter was approved. Reload before editing.');
            $n=load_newsletter($id);
            $scope=$input['section'] ?? ''; $data=newsletter_form_data($n);
            $text=static function($value,int $max=10000): string { if (!is_string($value) || mb_strlen($value)>$max) throw new InvalidArgumentException('Invalid text field.'); return trim($value); };
            if ($scope==='basic') { $data['subject_title']=$text($input['subject_title'] ?? '',250); $data['hidden_text']=$text($input['hidden_text'] ?? ''); }
            elseif ($scope==='header') {
                foreach (['header_title','header_description'] as $key) $data[$key]=$text($input[$key] ?? '');
            } elseif (in_array($scope,['pick','more'],true)) {
                $items=$input['items'] ?? null; if (!is_array($items) || count($items)>100) throw new InvalidArgumentException('Invalid article list.');
                $existing=array_column($n[$scope==='pick' ? 'pick_news':'more_news'],null,'id');
                $keys=$scope==='pick' ? ['pick_title','pick_description','pick_url','pick_image_data','pick_existing_image']:['more_title','more_url']; foreach ($keys as $k) $data[$k]=[];
                $seen=[];
                foreach ($items as $item) {
                    $old=$existing[(int)($item['id'] ?? 0)] ?? null;
                    if (!$old || isset($seen[$old['id']])) throw new InvalidArgumentException('Article does not belong to this newsletter or is repeated.'); $seen[$old['id']]=true;
                    $data[$scope.'_title'][]=$text($item['title'] ?? '',5000); $data[$scope.'_url'][]=$old['url'];
                    if ($scope==='pick') { $data['pick_description'][]=$text($item['description'] ?? ''); $data['pick_existing_image'][]=$old['image']; }
                }
            } elseif ($scope==='category') {
                $idToEdit=(int)($input['category_id'] ?? 0); $found=false;
                foreach ($n['categories'] as $i=>$c) if ((int)$c['id']===$idToEdit) {
                    $found=true; $old=array_column($c['items'],null,'id'); $items=$input['items'] ?? null;
                    if (!is_array($items) || count($items)>100) throw new InvalidArgumentException('Invalid category articles.');
                    $key='saved_'.$i; $data['category_news_title'][$key]=[]; $data['category_news_url'][$key]=[]; $seen=[];
                    foreach ($items as $item) { $p=$old[(int)($item['id'] ?? 0)] ?? null; if (!$p || isset($seen[$p['id']])) throw new InvalidArgumentException('Invalid category article.'); $seen[$p['id']]=true; $data['category_news_title'][$key][]=$text($item['title'] ?? '',5000); $data['category_news_url'][$key][]=$p['url']; }
                }
                if (!$found) throw new InvalidArgumentException('Category group not found.');
            } else throw new InvalidArgumentException('Unknown editable section.');
            persist_newsletter($pdo,$data,$user,false,true); $pdo->commit(); }
            catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
            api_reply(['newsletter'=>load_newsletter($id)]);
        }
    }
    if ($route==='/reports' && $method==='GET') {
        api_permission('view',$user); $month=manager_month((string)($_GET['month'] ?? date('Y-m'))); $rows=employee_month_report($month);
        $s=db()->prepare("SELECT newsletter_date, SUM(edition='M') morning,SUM(edition='E') evening,COUNT(*) total FROM newsletters WHERE DATE_FORMAT(newsletter_date,'%Y-%m')=? GROUP BY newsletter_date ORDER BY newsletter_date"); $s->execute([$month]);
        $details=[]; if (isset($_GET['employee'])) $details=employee_month_dates($month,max(0,(int)$_GET['employee']));
        api_reply(['month'=>$month,'totals'=>report_totals($rows),'employees'=>$rows,'days'=>$s->fetchAll(),'details'=>$details]);
    }
    if ($route==='/employees' || preg_match('~^/employees/(\d+)$~',$route,$employeeMatch)) {
        if (!is_admin($user)) api_reply(['error'=>'Administrator permission required.'],403);
        if ($method==='GET') api_reply(['employees'=>array_map('manager_user',db()->query('SELECT * FROM users ORDER BY role,first_name,id')->fetchAll())]);
        if ($method==='POST') { if (isset($employeeMatch[1])) $input['id']=(int)$employeeMatch[1]; $id=manager_save_employee($input,$user); api_reply(['id'=>$id]); }
    }
    if (preg_match('~^/categories/(\d+)/delete$~',$route,$m) && $method==='POST') {
        if (!is_admin($user)) api_reply(['error'=>'Administrator permission required.'],403);
        $id=(int)$m[1];
        $s=db()->prepare('SELECT COUNT(*) FROM automation_articles WHERE category_id=?'); $s->execute([$id]);
        if ($s->fetchColumn()) throw new InvalidArgumentException('This category is referenced by imports. Deactivate it to preserve history.');
        db()->prepare('DELETE FROM newsletter_categories_master WHERE id=?')->execute([$id]); api_reply(['ok'=>true]);
    }
    if ($route==='/notifications' && $method==='GET') { $events=manager_notifications($user); api_reply(['notifications'=>$events,'unread'=>count(array_filter($events,fn($e)=>!$e['read']))]); }
    if ($route==='/notifications/read' && $method==='POST') {
        $events=manager_notifications($user); $key=$input['key'] ?? null;
        $s=db()->prepare('INSERT IGNORE INTO newsletter_notification_reads(user_id,event_key) VALUES(?,?)');
        foreach ($events as $e) if ($key===null || $key===$e['key']) $s->execute([$user['id'],$e['key']]);
        api_reply(['ok'=>true]);
    }
    if ($route==='/automation/status' && $method==='GET') {
        api_permission('create',$user); $s=db()->prepare('SELECT id,status,total,completed,newsletter_id,created_at,updated_at FROM automation_jobs WHERE user_id=? ORDER BY id DESC LIMIT 30'); $s->execute([$user['id']]);
        $heartbeat=@file_get_contents(dirname(__DIR__).'/storage/worker-heartbeat.json'); $last=$heartbeat ? json_decode($heartbeat,true):null;
        $jobs=$s->fetchAll();
        $counts=db()->prepare("SELECT COUNT(*) today,COALESCE(SUM(status='completed'),0) success,COALESCE(SUM(status='failed'),0) failed FROM automation_jobs WHERE user_id=? AND DATE(created_at)=CURDATE()"); $counts->execute([$user['id']]);
        api_reply(['jobs'=>$jobs,'stats'=>$counts->fetch(),'heartbeat'=>$last,'worker_recent'=>$last && (time()-(int)$last['time'])<180,'publishing_configured'=>strlen(publishing_config()['token'])>=32 && publishing_config()['url']!=='']);
    }
    if ($route==='/automation/run' && $method==='POST') {
        if (!is_admin($user)) api_reply(['error'=>'Administrator permission required.'],403);
        api_permission('create',$user); api_rate('run:'.$user['id'],3);
        // Uses the existing database lease; one article only. The cron remains unchanged.
        $worked=worker_tick(); api_reply(['processed'=>$worked ? 1:0,'message'=>$worked ? 'Processed one queued article. The cron continues the queue.':'No queued article was ready.']);
    }
    if ($route==='/profile' && $method==='GET') api_reply(['user'=>manager_user($user)]);
    if ($route==='/profile' && $method==='POST') {
        $first=trim((string)($input['first_name'] ?? '')); $last=trim((string)($input['last_name'] ?? '')); $email=trim((string)($input['email'] ?? ''));
        if (!$first || mb_strlen($first)>100 || mb_strlen($last)>100 || ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))) throw new InvalidArgumentException('Enter a valid name and email.');
        if ($email!==($user['email'] ?? '') && !password_verify((string)($input['current_password'] ?? ''),$user['password_hash'])) throw new InvalidArgumentException('Enter your current password to change the registered email.');
        db()->prepare('UPDATE users SET first_name=?,last_name=?,email=?,contact_no=?,address=? WHERE id=?')->execute([$first,$last,$email ?: null,mb_substr((string)($input['contact_no'] ?? ''),0,50),mb_substr((string)($input['address'] ?? ''),0,2000),$user['id']]);
        $s=db()->prepare('SELECT * FROM users WHERE id=?'); $s->execute([$user['id']]); api_reply(['user'=>manager_user($s->fetch())]);
    }
    if ($route==='/profile/photo' && $method==='POST') {
        $f=$_FILES['photo'] ?? null; if (!$f || $f['error']!==UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) throw new InvalidArgumentException('Choose a profile image.');
        $photo=save_profile_photo($f,$user['photo']); if ($photo===$user['photo']) throw new InvalidArgumentException('Choose a supported image under 8 MB.');
        db()->prepare('UPDATE users SET photo=? WHERE id=?')->execute([$photo,$user['id']]); api_reply(['photo'=>$photo]);
    }
    if ($route==='/auth/password' && $method==='POST') {
        if (!password_verify((string)($input['current_password'] ?? ''),$user['password_hash'])) throw new InvalidArgumentException('Current password is incorrect.');
        $pass=(string)($input['password'] ?? ''); if (strlen($pass)<10 || strlen($pass)>128) throw new InvalidArgumentException('Use a password of 10–128 characters.');
        db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pass,PASSWORD_DEFAULT),$user['id']]);
        db()->prepare('UPDATE api_tokens SET revoked_at=NOW() WHERE user_id=?')->execute([$user['id']]); api_reply(['ok'=>true,'message'=>'Password changed. Sign in again on all devices.']);
    }
    if ($route==='/system/status' && $method==='GET') api_reply(['online'=>true,'server_time'=>date(DATE_ATOM),'publishing_configured'=>strlen(publishing_config()['token'])>=32 && publishing_config()['url']!=='','reset_email_configured'=>(defined('TTW_RESET_FROM') ? TTW_RESET_FROM : getenv('TTW_RESET_FROM')) ? true:false,'version'=>'0.3.0']);
}
