<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/automation/jobs.php';
require_once dirname(__DIR__) . '/newsletter_renderer.php';
require_once dirname(__DIR__) . '/includes/api-diagnostics.php';

function api_reply(array $data,int $code=200): never {
    http_response_code($code); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data,JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
function api_permission(string $permission,array $user): void {
    if (!can($permission,$user)) api_reply(['error'=>'Permission denied.'],403);
}
function api_rate(string $scope,int $limit): void {
    $key=hash('sha256',$scope); $window=(int)(floor(time()/60)*60);
    $stmt=db()->prepare('INSERT INTO api_rate_limits(bucket,window_start,hits) VALUES(?,?,1) ON DUPLICATE KEY UPDATE hits=IF(window_start=VALUES(window_start),hits+1,1),window_start=VALUES(window_start)');
    $stmt->execute([$key,$window]);
    $stmt=db()->prepare('SELECT hits FROM api_rate_limits WHERE bucket=?'); $stmt->execute([$key]);
    if ((int)$stmt->fetchColumn()>$limit) { header('Retry-After: 60'); api_reply(['error'=>'Too many requests. Try again in a minute.'],429); }
}
function api_user(): array {
    $auth=(string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^Bearer ([a-f0-9]{64})$/',$auth,$m)) {
        $stmt=db()->prepare('SELECT u.*,t.id AS token_id,t.password_fingerprint FROM api_tokens t JOIN users u ON u.id=t.user_id WHERE t.token_hash=? AND t.revoked_at IS NULL AND t.expires_at>NOW() AND u.status="active"');
        $stmt->execute([hash('sha256',$m[1])]); $user=$stmt->fetch();
        if ($user && hash_equals($user['password_fingerprint'],hash('sha256',$user['password_hash']))) return $user;
        api_reply(['error'=>'Session expired. Sign in again.'],401);
    }
    if ($auth!=='') api_reply(['error'=>'Invalid authorization.'],401);
    $user=auth_user();
    if (!$user) api_reply(['error'=>'Authentication required.'],401);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='GET') {
        $csrf=(string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!hash_equals(csrf_token(),$csrf)) api_reply(['error'=>'Reload the page and try again.'],403);
    }
    return $user;
}

header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
try {
    $https=!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
    $local=in_array($_SERVER['REMOTE_ADDR'] ?? '',['127.0.0.1','::1'],true);
    if (!$https && !(getenv('TTW_ALLOW_LOCAL_HTTP')==='1' && $local)) api_reply(['error'=>'HTTPS is required.'],426);
    $method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
    $route='/'.trim((string)($_GET['route'] ?? ''),'/');
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>12*1024*1024) api_reply(['error'=>'Request too large.'],413);
    $input=[];
    if ($method!=='GET' && stripos($_SERVER['CONTENT_TYPE'] ?? '','application/json')!==false) {
        $raw=file_get_contents('php://input',false,null,0,131073);
        if (strlen($raw)>131072) api_reply(['error'=>'JSON exceeds 128 KB.'],413);
        $input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if (!is_array($input)) throw new InvalidArgumentException('Expected a JSON object.');
    }
    if ($route==='/auth/login' && $method==='POST') {
        api_rate('login-ip:'.($_SERVER['REMOTE_ADDR'] ?? ''),10);
        $username=trim((string)($input['username'] ?? ''));
        $stmt=db()->prepare('SELECT * FROM users WHERE username=? AND status="active" LIMIT 1'); $stmt->execute([$username]); $user=$stmt->fetch();
        $valid=password_verify((string)($input['password'] ?? ''),$user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$user || !$valid) api_reply(['error'=>'Invalid username or password.'],401);
        $token=bin2hex(random_bytes(32));
        db()->prepare('INSERT INTO api_tokens(user_id,token_hash,password_fingerprint,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 7 DAY))')->execute([$user['id'],hash('sha256',$token),hash('sha256',$user['password_hash'])]);
        api_reply(['token'=>$token,'expires_in'=>604800,'user'=>['id'=>(int)$user['id'],'name'=>current_user_name($user),'role'=>$user['role'],'permissions'=>user_permissions($user)]]);
    }
    $user=api_user();
    // Check setup before throttling: the rate-limit table itself may be missing.
    if ($route==='/system/check' && $method==='GET') {
        if (!is_admin($user)) api_reply(['error'=>'Administrator permission required.'],403);
        api_reply(automation_setup_check(db()));
    }
    api_rate('user:'.$user['id'],120);
    if ($route==='/auth/me' && $method==='GET') api_reply(['id'=>(int)$user['id'],'name'=>current_user_name($user),'role'=>$user['role'],'permissions'=>user_permissions($user)]);
    if ($route==='/auth/logout' && $method==='POST') {
        if (isset($user['token_id'])) db()->prepare('UPDATE api_tokens SET revoked_at=NOW() WHERE id=?')->execute([$user['token_id']]);
        api_reply(['ok'=>true]);
    }
    if ($route==='/categories' && $method==='GET') {
        api_permission('view',$user); api_reply(['categories'=>array_values(array_filter(master_categories(),fn($m)=>is_admin($user) || (int)$m['active']))]);
    }
    if ($route==='/categories' && $method==='POST') {
        if (!is_admin($user)) api_reply(['error'=>'Administrator permission required.'],403);
        $name=trim((string)($input['category_name'] ?? '')); $slug=trim((string)($input['category_slug'] ?? '')); $aliases=trim((string)($input['aliases'] ?? ''));
        if ($name==='' || mb_strlen($name)>190 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug) || strlen($slug)>190 || strlen($aliases)>2000) throw new InvalidArgumentException('Enter a name, URL slug, and comma-separated aliases.');
        $id=(int)($input['id'] ?? 0); $active=empty($input['active']) ? 0 : 1; $order=(int)($input['display_order'] ?? 0);
        $proposed=array_values(array_filter(master_categories(),fn($m)=>(int)$m['id']!==$id));
        $proposed[]=['id'=>$id ?: -1,'category_name'=>$name,'aliases'=>$aliases,'active'=>$active]; category_map($proposed);
        if ($id) db()->prepare('UPDATE newsletter_categories_master SET category_name=?,category_slug=?,aliases=?,active=?,display_order=? WHERE id=?')->execute([$name,$slug,$aliases,$active,$order,$id]);
        else db()->prepare('INSERT INTO newsletter_categories_master(category_name,category_slug,aliases,active,display_order) VALUES(?,?,?,?,?)')->execute([$name,$slug,$aliases,$active,$order]);
        api_reply(['ok'=>true]);
    }
    if ($route==='/newsletters/draft' && $method==='POST') {
        api_permission(empty($input['id']) ? 'create' : 'edit',$user);
        $id=save_newsletter_draft($input,$user);
        api_reply(['id'=>$id,'newsletter'=>load_newsletter($id)]);
    }
    if ($route==='/newsletters' && $method==='GET') {
        api_permission('view',$user); $page=max(1,(int)($_GET['page'] ?? 1)); $offset=($page-1)*30;
        $rows=db()->query('SELECT id,newsletter_date,edition,subject_title,approved_at FROM newsletters ORDER BY newsletter_date DESC,edition DESC LIMIT 30 OFFSET '.$offset)->fetchAll();
        $today=db()->prepare('SELECT id,edition,approved_at FROM newsletters WHERE newsletter_date=?'); $today->execute([date('Y-m-d')]);
        api_reply(['newsletters'=>$rows,'today'=>$today->fetchAll(),'server_date'=>date('Y-m-d'),'page'=>$page]);
    }
    if (preg_match('~^/newsletters/(\d+)(/preview|/approve)?$~',$route,$m)) {
        $id=(int)$m[1]; $action=$m[2] ?? ''; api_permission($action==='/approve' || $method==='POST' ? 'edit' : 'view',$user);
        $n=load_newsletter($id); if (!$n) api_reply(['error'=>'Newsletter not found.'],404);
        if ($action==='/preview' && $method==='GET') {
            header('Content-Type: text/html; charset=utf-8');
            header("Content-Security-Policy: default-src 'none'; img-src 'self' https: data:; style-src 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");
            echo render_newsletter($n); exit;
        }
        if ($action==='/approve' && $method==='POST') {
            if (trim($n['header_title'])==='' || trim($n['subject_title'])==='') throw new InvalidArgumentException('Add Header news and a manually entered Email Title before approval.');
            db()->prepare('UPDATE newsletters SET approved_at=NOW(),approved_by=? WHERE id=?')->execute([$user['id'],$id]); api_reply(['ok'=>true]);
        }
        if ($action==='' && $method==='GET') api_reply(['newsletter'=>$n]);
        if ($action==='' && $method==='POST') {
            // Metadata edits preserve the existing article URLs, images and link collections.
            $data=newsletter_form_data($n);
            foreach(['subject_title','hidden_text','header_title','header_description'] as $key) {
                if (isset($input[$key])) {
                    if (!is_string($input[$key]) || strlen($input[$key])>5000) throw new InvalidArgumentException('Invalid text field.');
                    $data[$key]=trim($input[$key]);
                }
            }
            if (strlen($data['subject_title'])>250) throw new InvalidArgumentException('Email Title must be at most 250 characters.');
            $edits=[];
            foreach(($input['pick_news'] ?? []) as $p) {
                if (!is_array($p) || !is_string($p['title'] ?? null) || !is_string($p['description'] ?? null) || strlen($p['title'])>5000 || strlen($p['description'])>10000) throw new InvalidArgumentException('Invalid article edit.');
                $edits[(int)($p['id'] ?? 0)]=$p;
            }
            foreach($n['pick_news'] as $i=>$p) {
                $edit=$edits[(int)$p['id']] ?? $p;
                $data['pick_title'][$i]=$edit['title']; $data['pick_description'][$i]=$edit['description'];
            }
            db()->beginTransaction();
            try { persist_newsletter(db(),$data,$user,false,true); db()->commit(); }
            catch(Throwable $e) { db()->rollBack(); throw $e; }
            api_reply(['ok'=>true]);
        }
    }
    if ($route==='/automation/parse' && $method==='POST') { api_permission('create',$user); api_reply(parse_automation_input($input,master_categories())); }
    if ($route==='/automation/jobs' && $method==='POST') { api_permission('create',$user); if(!empty($input['target_id'])) api_permission('edit',$user); api_rate('jobs:'.$user['id'],5); api_reply(['id'=>create_job($input,$user)],202); }
    if ($route==='/automation/jobs' && $method==='GET') {
        api_permission('create',$user); $stmt=db()->prepare('SELECT id,status,total,completed,newsletter_id,created_at FROM automation_jobs WHERE user_id=? ORDER BY id DESC LIMIT 20'); $stmt->execute([$user['id']]); api_reply(['jobs'=>$stmt->fetchAll()]);
    }
    if (preg_match('~^/automation/jobs/(\d+)(/generate|/retry)?$~',$route,$m)) {
        api_permission('create',$user); $id=(int)$m[1]; $action=$m[2] ?? '';
        if ($action==='' && $method==='GET') api_reply(read_job($id,$user));
        if ($action==='/retry' && $method==='POST') { retry_job($id,$user); api_reply(['ok'=>true]); }
        if ($action==='/generate' && $method==='POST') api_reply(['newsletter_id'=>generate_job($id,$user,$input)]);
    }
    if ($route==='/uploads/promotion' && $method==='POST') {
        api_permission('create',$user); $f=$_FILES['banner'] ?? null;
        if (!$f || $f['error']!==UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || $f['size']>8*1024*1024 || !in_array(strtolower(pathinfo($f['name'],PATHINFO_EXTENSION)),['jpg','jpeg','png','webp'],true)) throw new InvalidArgumentException('Choose a JPEG, PNG or WebP banner under 8 MB.');
        $bytes=file_get_contents($f['tmp_name']); $info=@getimagesizefromstring($bytes);
        if (!$info) throw new InvalidArgumentException('Invalid banner image.');
        $path=process_image($bytes,date('Y-m-d'),$info[0],$info[1]);
        api_reply(['path'=>$path,'url'=>public_upload_url($path),'signature'=>promotion_signature($path,(int)$user['id'])]);
    }
    api_reply(['error'=>'Endpoint or method not found.'],404);
} catch(DomainException $e) { api_reply(['error'=>'Newsletter already exists. Open existing, replace an unapproved draft, or cancel.','existing_id'=>(int)$e->getMessage()],409); }
catch(OutOfBoundsException $e) { api_reply(['error'=>'Resource not found.'],404); }
catch(InvalidArgumentException | JsonException $e) { api_reply(['error'=>$e instanceof JsonException ? 'Invalid JSON.' : $e->getMessage()],422); }
catch(PDOException $e) {
    error_log('TTW API database failure: SQLSTATE=' . (string)$e->getCode() . '; driver_code=' . (int)($e->errorInfo[1] ?? 0));
    [$status,$message]=api_database_failure($e);
    api_reply(['error'=>$message],$status);
}
catch(Throwable $e) {
    error_log('TTW API failure: ' . get_class($e));
    api_reply(['error'=>'Request failed. Check server configuration or contact the administrator.'],500);
}
