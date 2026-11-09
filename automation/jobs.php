<?php
declare(strict_types=1);
require_once __DIR__ . '/fetcher.php';
require_once dirname(__DIR__) . '/includes/newsletter-service.php';
require_once dirname(__DIR__) . '/includes/security.php';

function master_categories(): array {
    return db()->query('SELECT * FROM newsletter_categories_master ORDER BY display_order,id')->fetchAll();
}
function create_job(array $input, array $user): int {
    $date=(string)($input['newsletter_date'] ?? ''); $edition=$input['edition'] ?? '';
    if (!valid_issue_date($date) || !in_array($edition,['M','E'],true)) throw new InvalidArgumentException('Choose a valid date and edition.');
    if (trim($input['subject_title'] ?? '')==='' || trim($input['hidden_text'] ?? '')==='') throw new InvalidArgumentException('Email subject and description are required.');
    if (strlen($input['subject_title'])>250 || strlen($input['hidden_text'])>5000) throw new InvalidArgumentException('Subject or description is too long.');
    $parsed=parse_newsletter_input((string)($input['text'] ?? ''),master_categories());
    if ($parsed['errors']) throw new InvalidArgumentException(implode("\n",$parsed['errors']));
    $promotion=(string)($input['promotion_image'] ?? '');
    if ($promotion!=='') {
        // Uploads are bound to their creating account by a signed path.
        $signature=(string)($input['promotion_signature'] ?? '');
        if (!preg_match('~^uploads/\d{4}/[A-Za-z]{3}/auto_[a-f0-9]{24}\.jpg$~',$promotion) || !hash_equals(promotion_signature($promotion,(int)$user['id']),$signature) || !is_file(dirname(__DIR__).'/'.$promotion)) throw new InvalidArgumentException('Upload the promotion again.');
        if (!filter_var($input['promotion_url'] ?? '',FILTER_VALIDATE_URL) || parse_url($input['promotion_url'],PHP_URL_SCHEME)!=='https') throw new InvalidArgumentException('Promotion URL must use HTTPS.');
    }
    $payload=array_intersect_key($input,array_flip(['newsletter_date','edition','subject_title','hidden_text','promotion_image','promotion_url','promotion_text']));
    $payload['warnings']=$parsed['warnings'];
    $pdo=db(); $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('INSERT INTO automation_jobs(user_id,payload,total) VALUES(?,?,?)');
        $stmt->execute([$user['id'],json_encode($payload,JSON_THROW_ON_ERROR),$parsed['total']]); $id=(int)$pdo->lastInsertId();
        $stmt=$pdo->prepare('INSERT INTO automation_articles(job_id,position,section,category_id,url) VALUES(?,?,?,?,?)');
        foreach($parsed['articles'] as $i=>$a) $stmt->execute([$id,$i,$a['section'],$a['category_id'],$a['url']]);
        $pdo->commit(); return $id;
    } catch(Throwable $e) { $pdo->rollBack(); throw $e; }
}
function promotion_signature(string $path,int $userId): string {
    return hash_hmac('sha256',$userId.'|'.$path,TTW_API_SECRET);
}
function read_job(int $id,array $user): array {
    $stmt=db()->prepare('SELECT * FROM automation_jobs WHERE id=?'); $stmt->execute([$id]); $job=$stmt->fetch();
    if (!$job || (!is_admin($user) && (int)$job['user_id']!==(int)$user['id'])) throw new OutOfBoundsException('Job not found.');
    $stmt=db()->prepare('SELECT a.*,c.category_name FROM automation_articles a LEFT JOIN newsletter_categories_master c ON c.id=a.category_id WHERE a.job_id=? ORDER BY a.position'); $stmt->execute([$id]);
    $job['articles']=$stmt->fetchAll();
    foreach($job['articles'] as &$a) $a['result']=$a['result'] ? json_decode($a['result'],true) : null;
    unset($a); $job['payload']=json_decode($job['payload'],true);
    $job['progress']=$job['total'] ? (int)floor(100*$job['completed']/$job['total']) : 0;
    unset($job['lease_token']); return $job;
}

/** Processes one article. A lease prevents overlap; interrupted work can be reclaimed. */
function worker_tick(): bool {
    $pdo=db(); $token=bin2hex(random_bytes(16));
    $stmt=$pdo->prepare("UPDATE automation_jobs SET status='processing',lease_until=DATE_ADD(NOW(),INTERVAL 180 SECOND),lease_token=? WHERE (status='pending' OR (status='processing' AND (lease_until IS NULL OR lease_until<NOW()))) AND newsletter_id IS NULL ORDER BY id LIMIT 1");
    $stmt->execute([$token]); if (!$stmt->rowCount()) return false;
    $stmt=$pdo->prepare('SELECT * FROM automation_jobs WHERE lease_token=?'); $stmt->execute([$token]); $job=$stmt->fetch();
    $stmt=$pdo->prepare("SELECT * FROM automation_articles WHERE job_id=? AND status IN ('pending','processing') ORDER BY position LIMIT 1"); $stmt->execute([$job['id']]); $a=$stmt->fetch();
    if ($a) {
        $pdo->prepare("UPDATE automation_articles SET status='processing',attempts=attempts+1,started_at=NOW() WHERE id=?")->execute([$a['id']]);
        $result=null; $error=null; $status='completed';
        try {
            $payload=json_decode($job['payload'],true);
            // Recheck user state before making network requests.
            $u=$pdo->prepare('SELECT * FROM users WHERE id=? AND status="active"'); $u->execute([$job['user_id']]); $user=$u->fetch();
            if (!$user || !can('create',$user)) throw new RuntimeException('Job owner no longer has create permission.');
            $result=extract_article(safe_fetch(article_url($a['url'])),$a['url']);
            if ($result['title']==='') throw new RuntimeException('Original article title not found.');
            $warnings=[];
            if ($result['description']==='' && in_array($a['section'],['header','pick'])) $warnings[]='Original description not found';
            if (in_array($a['section'],['header','pick'])) {
                [$w,$h]=$a['section']==='header' ? [610,380] : [350,291];
                try {
                    if (!$result['featured_image']) throw new RuntimeException('No featured image.');
                    $result['image']=process_image(safe_fetch($result['featured_image'],true),$payload['newsletter_date'],$w,$h);
                } catch(Throwable $e) {
                    $warnings[]='Featured image could not be retrieved';
                    $result['image']=missing_image($payload['newsletter_date'],$w,$h);
                }
            }
            $result['warnings']=$warnings;
            if ($warnings) { $status='warning'; $error=implode('; ',$warnings); }
        } catch(Throwable $e) { $status='failed'; $error=$e instanceof RuntimeException ? $e->getMessage() : 'Article processing failed.'; }
        // A stale worker must never write over a replacement lease.
        $stmt=$pdo->prepare('UPDATE automation_articles a JOIN automation_jobs j ON j.id=a.job_id SET a.status=?,a.result=?,a.error_message=?,a.completed_at=NOW() WHERE a.id=? AND j.lease_token=?');
        $stmt->execute([$status,$result ? json_encode($result,JSON_THROW_ON_ERROR) : null,mb_substr((string)$error,0,500),$a['id'],$token]);
    }
    $stmt=$pdo->prepare("SELECT COUNT(*) total,SUM(status IN ('completed','warning','failed')) done,SUM(status IN ('warning','failed')) warnings,SUM(status='failed') failures FROM automation_articles WHERE job_id=?"); $stmt->execute([$job['id']]); $counts=$stmt->fetch();
    $done=(int)$counts['done'];
    $status=$done===(int)$counts['total'] ? ((int)$counts['failures'] ? 'failed' : ((int)$counts['warnings'] ? 'completed_with_warnings' : 'completed')) : 'pending';
    $pdo->prepare('UPDATE automation_jobs SET completed=?,status=?,lease_until=NULL,lease_token=NULL WHERE id=? AND lease_token=?')->execute([$done,$status,$job['id'],$token]);
    return true;
}

function retry_job(int $id,array $user): void {
    $pdo=db(); $pdo->beginTransaction();
    try {
        $pdo->prepare('SELECT id FROM automation_jobs WHERE id=? FOR UPDATE')->execute([$id]);
        $job=read_job($id,$user);
        if ($job['newsletter_id'] || !in_array($job['status'],['failed','completed_with_warnings'],true)) throw new InvalidArgumentException('Only finished jobs with problems can be retried.');
        $pdo->prepare("UPDATE automation_articles SET status='pending',error_message=NULL WHERE job_id=? AND status IN ('warning','failed')")->execute([$id]);
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM automation_articles WHERE job_id=? AND status='completed'"); $stmt->execute([$id]);
        $pdo->prepare("UPDATE automation_jobs SET status='pending',completed=?,error_message=NULL WHERE id=?")->execute([(int)$stmt->fetchColumn(),$id]);
        $pdo->commit();
    } catch(Throwable $e) { $pdo->rollBack(); throw $e; }
}

function generate_job(int $id,array $user,array $options): int {
    $pdo=db(); $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT id FROM automation_jobs WHERE id=? FOR UPDATE'); $stmt->execute([$id]);
        $job=read_job($id,$user);
        if ($job['newsletter_id']) { $pdo->commit(); return (int)$job['newsletter_id']; }
        if (!in_array($job['status'],['completed','completed_with_warnings'],true)) throw new InvalidArgumentException('Finish or retry failed articles before generation.');
        if ($job['status']==='completed_with_warnings' && empty($options['accept_warnings'])) throw new InvalidArgumentException('Review and acknowledge article warnings first.');
        $data=$job['payload']; $data['id']=0;
        $stmt=$pdo->prepare('SELECT * FROM newsletters WHERE newsletter_date=? AND edition=? FOR UPDATE'); $stmt->execute([$data['newsletter_date'],$data['edition']]); $existing=$stmt->fetch();
        if ($existing) {
            if (empty($options['replace_draft']) || !can('edit',$user) || $existing['approved_at']) throw new DomainException((string)$existing['id']);
            $data['id']=(int)$existing['id'];
        }
        $data['promotion_enabled']=empty($data['promotion_image']) ? 0 : 1;
        if (!empty($data['promotion_image'])) {
            $bytes=file_get_contents(dirname(__DIR__).'/'.$data['promotion_image']);
            $data['promotion_image_data']='data:image/jpeg;base64,'.base64_encode($bytes);
        }
        $data['category_name']=$data['category_key']=$data['category_url']=[];
        $categories=[];
        foreach($job['articles'] as $a) {
            $r=$a['result']; $url=$r['canonical_url'];
            if ($a['section']==='header') {
                $data['header_title']=$r['title']; $data['header_description']=$r['description']; $data['header_url']=$url;
                $data['header_image_data']='data:image/jpeg;base64,'.base64_encode(file_get_contents(dirname(__DIR__).'/'.$r['image']));
            } elseif ($a['section']==='pick') {
                $data['pick_title'][]=$r['title']; $data['pick_description'][]=$r['description']; $data['pick_url'][]=$url;
                $data['pick_image_data'][]='data:image/jpeg;base64,'.base64_encode(file_get_contents(dirname(__DIR__).'/'.$r['image']));
            } elseif ($a['section']==='more') { $data['more_title'][]=$r['title']; $data['more_url'][]=$url; }
            else {
                $key='master_'.$a['category_id'];
                if (!isset($categories[$key])) {
                    $m=$pdo->prepare('SELECT * FROM newsletter_categories_master WHERE id=? AND active=1'); $m->execute([$a['category_id']]); $master=$m->fetch();
                    if (!$master) throw new InvalidArgumentException('A category was deactivated. Create a new job.');
                    $categories[$key]=true; $data['category_name'][]=$master['category_name']; $data['category_key'][]=$key;
                    $data['category_url'][]='https://www.travelandtourworld.com/news/article/category/'.$master['category_slug'].'/';
                }
                $data['category_news_title'][$key][]=$r['title']; $data['category_news_url'][$key][]=$url;
            }
        }
        $newsletterId=persist_newsletter($pdo,$data,$user,false,true);
        $pdo->prepare('UPDATE automation_jobs SET newsletter_id=? WHERE id=?')->execute([$newsletterId,$id]);
        $pdo->commit(); return $newsletterId;
    } catch(Throwable $e) { $pdo->rollBack(); throw $e; }
}
