<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/report_helpers.php';
require_once __DIR__.'/publishing.php';

function manager_user(array $u): array {
    return ['id'=>(int)$u['id'],'name'=>current_user_name($u),'username'=>$u['username'],'email'=>$u['email'] ?? '','first_name'=>$u['first_name'],'last_name'=>$u['last_name'] ?? '',
        'contact_no'=>$u['contact_no'] ?? '','address'=>$u['address'] ?? '','photo'=>$u['photo'] ?? null,'role'=>$u['role'],'status'=>$u['status'],'permissions'=>user_permissions($u)];
}
function manager_month(string $month): string {
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$month)) throw new InvalidArgumentException('Choose a valid month.'); return $month;
}
function manager_newsletters(array $filter): array {
    $where=[]; $args=[];
    foreach (['from'=>'>=','to'=>'<='] as $key=>$op) if (!empty($filter[$key])) {
        if (!valid_issue_date($filter[$key])) throw new InvalidArgumentException('Choose valid filter dates.');
        $where[]='n.newsletter_date '.$op.' ?'; $args[]=$filter[$key];
    }
    if (!empty($filter['edition'])) { if (!in_array($filter['edition'],['M','E'],true)) throw new InvalidArgumentException('Choose a valid edition.'); $where[]='n.edition=?'; $args[]=$filter['edition']; }
    if (!empty($filter['q'])) { $where[]='(n.subject_title LIKE ? OR n.header_title LIKE ?)'; $term='%'.mb_substr((string)$filter['q'],0,150).'%'; array_push($args,$term,$term); }
    $status=$filter['status'] ?? '';
    if ($status==='draft') $where[]='n.approved_at IS NULL';
    elseif ($status==='approved') $where[]='n.approved_at IS NOT NULL';
    elseif ($status==='published') $where[]="n.approved_at IS NOT NULL AND p.status='published' AND p.published_at>=n.updated_at";
    elseif ($status!=='' && $status!=='all') throw new InvalidArgumentException('Unknown newsletter status.');
    $page=max(1,min(100000,(int)($filter['page'] ?? 1))); $offset=($page-1)*30;
    $sql=' FROM newsletters n LEFT JOIN newsletter_publications p ON p.newsletter_id=n.id'.($where ? ' WHERE '.implode(' AND ',$where) : '');
    $s=db()->prepare('SELECT COUNT(*)'.$sql); $s->execute($args); $total=(int)$s->fetchColumn();
    $s=db()->prepare("SELECT n.id,n.newsletter_date,n.edition,n.subject_title,n.header_title,n.header_image,n.approved_at,IF(n.approved_at IS NOT NULL AND p.published_at>=n.updated_at,p.status,'unpublished') AS publish_status,p.public_url,
        (SELECT COUNT(*) FROM pick_news x WHERE x.newsletter_id=n.id)+(SELECT COUNT(*) FROM more_travel_news x WHERE x.newsletter_id=n.id)+(SELECT COUNT(*) FROM category_news x JOIN newsletter_categories c ON c.id=x.category_id WHERE c.newsletter_id=n.id)+IF(n.header_title<>'',1,0) AS article_count".$sql.' ORDER BY n.newsletter_date DESC,n.edition DESC,n.id DESC LIMIT 30 OFFSET '.$offset);
    $s->execute($args); return ['newsletters'=>$s->fetchAll(),'total'=>$total,'page'=>$page,'has_more'=>$offset+30<$total];
}
function manager_notifications(array $user): array {
    $events=[];
    if (can('view',$user)) {
        foreach (db()->query('SELECT id,newsletter_date,edition,subject_title,approved_at,updated_at FROM newsletters ORDER BY updated_at DESC LIMIT 30')->fetchAll() as $n) {
            $approved=(bool)$n['approved_at']; $time=$approved ? $n['approved_at'] : $n['updated_at'];
            $events[]=['key'=>'newsletter-'.$n['id'].'-'.strtotime($time).'-'.($approved ? 'approved':'draft'),'title'=>$approved ? 'Newsletter Approved' : 'Draft Saved','message'=>$n['newsletter_date'].' · '.($n['edition']==='M' ? 'Morning' : 'Evening'),'time'=>$time,'newsletter_id'=>(int)$n['id'],'kind'=>$approved ? 'success':'draft'];
        }
    }
    if (can('create',$user)) {
        $s=db()->prepare("SELECT id,status,total,completed,updated_at FROM automation_jobs WHERE user_id=? AND status IN ('completed','completed_with_warnings','failed') ORDER BY updated_at DESC LIMIT 30"); $s->execute([$user['id']]);
        foreach ($s->fetchAll() as $j) $events[]=['key'=>'job-'.$j['id'].'-'.strtotime($j['updated_at']),'title'=>$j['status']==='completed' ? 'Processing Completed' : ($j['status']==='failed' ? 'Processing Failed' : 'Article Import Warning'),'message'=>$j['completed'].'/'.$j['total'].' articles processed','time'=>$j['updated_at'],'job_id'=>(int)$j['id'],'kind'=>$j['status']==='completed' ? 'success':'warning'];
    }
    $s=db()->prepare('SELECT event_key FROM newsletter_notification_reads WHERE user_id=?'); $s->execute([$user['id']]); $read=array_flip($s->fetchAll(PDO::FETCH_COLUMN));
    foreach ($events as &$e) $e['read']=isset($read[$e['key']]); unset($e);
    usort($events,fn($a,$b)=>strcmp($b['time'],$a['time'])); return array_slice($events,0,50);
}
function manager_save_employee(array $input,array $admin): int {
    if (!is_admin($admin)) throw new UnexpectedValueException('Administrator permission required.');
    $id=(int)($input['id'] ?? 0); $old=null;
    if ($id) { $s=db()->prepare('SELECT * FROM users WHERE id=?'); $s->execute([$id]); $old=$s->fetch(); if (!$old) throw new OutOfBoundsException(); if ($old['role']!=='employee') throw new InvalidArgumentException('Administrator accounts cannot be changed here.'); }
    if (isset($input['email']) && $input['email']!=='' && !filter_var($input['email'],FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email.');
    $username=trim((string)($input['username'] ?? $old['username'] ?? '')); $first=trim((string)($input['first_name'] ?? $old['first_name'] ?? ''));
    if ($username==='' || strlen($username)>100 || $first==='' || mb_strlen($first)>100) throw new InvalidArgumentException('Enter username and first name.');
    $pass=(string)($input['password'] ?? ''); if ((!$id || $pass!=='') && (strlen($pass)<10 || strlen($pass)>128)) throw new InvalidArgumentException('Use a password of 10–128 characters.');
    $permissions=$input['permissions'] ?? ($old ? user_permissions($old):['view']);
    if (!is_array($permissions) || array_diff($permissions,PERMISSIONS)) throw new InvalidArgumentException('Unknown permission.');
    $last=mb_substr(trim((string)($input['last_name'] ?? $old['last_name'] ?? '')),0,100); $contact=mb_substr((string)($input['contact_no'] ?? $old['contact_no'] ?? ''),0,50); $address=mb_substr((string)($input['address'] ?? $old['address'] ?? ''),0,2000);
    $status=$input['status'] ?? $old['status'] ?? 'active'; if (!in_array($status,['active','inactive'],true)) throw new InvalidArgumentException('Invalid status.');
    $s=db()->prepare('SELECT id FROM users WHERE username=? AND id<>?'); $s->execute([$username,$id]); if ($s->fetch()) throw new InvalidArgumentException('Username already exists.');
    if ($id) db()->prepare('UPDATE users SET username=?,first_name=?,last_name=?,contact_no=?,address=?,permissions=?,status=?,password_hash=? WHERE id=? AND role="employee"')->execute([$username,$first,$last,$contact,$address,json_encode(array_values($permissions)),$status,$pass!=='' ? password_hash($pass,PASSWORD_DEFAULT):$old['password_hash'],$id]);
    else { db()->prepare('INSERT INTO users(username,password_hash,role,first_name,last_name,contact_no,address,permissions,status) VALUES(?,?,"employee",?,?,?,?,?,?)')->execute([$username,password_hash($pass,PASSWORD_DEFAULT),$first,$last,$contact,$address,json_encode(array_values($permissions)),$status]); $id=(int)db()->lastInsertId(); }
    if (array_key_exists('email',$input)) {
        $email=trim((string)$input['email']); if ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email.');
        db()->prepare('UPDATE users SET email=? WHERE id=?')->execute([$email ?: null,$id]);
    }
    return $id;
}
