<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/newsletter_renderer.php';
require_once __DIR__.'/security.php';

function publishing_config(): array {
    return ['url'=>defined('TTW_PUBLISH_API_URL') ? TTW_PUBLISH_API_URL : (getenv('TTW_PUBLISH_API_URL') ?: ''),
        'token'=>defined('TTW_PUBLISH_TOKEN') ? TTW_PUBLISH_TOKEN : (getenv('TTW_PUBLISH_TOKEN') ?: '')];
}
function publishing_endpoint_allowed(string $url,bool $localTest=false): bool {
    $p=parse_url($url);
    if (!$p || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment'])) return false;
    if ($localTest && ($p['scheme'] ?? '')==='http' && ($p['host'] ?? '')==='127.0.0.1') return true;
    return ($p['scheme'] ?? '')==='https'
        && strtolower($p['host'] ?? '')==='www.travelandtourworld.com'
        && !isset($p['port'])
        && in_array($p['path'] ?? '',['/newsletter-api/upload.php','/newsletter/newsletter-api/upload.php'],true);
}
function publication_data(int $id): array {
    $s=db()->prepare('SELECT * FROM newsletter_publications WHERE newsletter_id=?'); $s->execute([$id]);
    $p=$s->fetch() ?: ['status'=>'unpublished','public_url'=>null];
    $n=db()->prepare('SELECT approved_at FROM newsletters WHERE id=?'); $n->execute([$id]);
    if ($p['status']==='published' && (!$n->fetchColumn() || (!empty($p['html_sha256']) && !hash_equals($p['html_sha256'],hash('sha256',render_newsletter(load_newsletter($id))))))) $p['status']='stale';
    return $p;
}
function validate_publish_images(string $html): void {
    $doc=new DOMDocument(); @$doc->loadHTML($html,LIBXML_NONET);
    $checked=[]; $resolverChecked=false;
    foreach ($doc->getElementsByTagName('img') as $image) {
        $url=$image->getAttribute('src');
        if (!filter_var($url,FILTER_VALIDATE_URL) || strtolower(parse_url($url,PHP_URL_SCHEME) ?: '')!=='https') throw new InvalidArgumentException('Newsletter images must use public HTTPS URLs.');
        // Our resolver validates local bytes and returns an image even when an old file is absent.
        $local=uploaded_image_path($url);
        if ($local!==null) resolve_uploaded_image($local);
        $host=strtolower(parse_url($url,PHP_URL_HOST) ?: '');
        if (!in_array($host,array_unique([strtolower(parse_url(APP_BASE_URL,PHP_URL_HOST) ?: ''),'www.travelandtourworld.com','travelandtourworld.com']),true)) throw new InvalidArgumentException('Review the external image host before publishing.');
        $isResolver=$host===strtolower(parse_url(APP_BASE_URL,PHP_URL_HOST) ?: '') && str_ends_with(parse_url($url,PHP_URL_PATH) ?: '', '/image.php');
        if (!isset($checked[$url]) && (!$isResolver || !$resolverChecked)) {
            if (getenv('TTW_ALLOW_LOCAL_HTTP')!=='1') publish_probe_image($url);
            $checked[$url]=true; if ($isResolver) $resolverChecked=true;
        }
    }
}
function publish_probe_image(string $url): void {
    $allowed=[strtolower(parse_url(APP_BASE_URL,PHP_URL_HOST) ?: ''),'www.travelandtourworld.com','travelandtourworld.com'];
    for ($i=0;$i<3;$i++) {
        $p=parse_url($url); $host=strtolower($p['host'] ?? '');
        if (($p['scheme'] ?? '')!=='https' || !in_array($host,$allowed,true) || isset($p['user']) || isset($p['port'])) throw new InvalidArgumentException('Unsafe image URL.');
        $ips=gethostbynamel($host) ?: [];
        if (!$ips) throw new InvalidArgumentException('An image host cannot be resolved.');
        foreach ($ips as $ip) if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) throw new InvalidArgumentException('An image points to an unsafe address.');
        $location=''; $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_NOBODY=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROXY=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_RESOLVE=>[$host.':443:'.$ips[0]],CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>5,
            CURLOPT_HEADERFUNCTION=>static function($c,$line) use (&$location) { if (stripos($line,'Location:')===0) $location=trim(substr($line,9)); return strlen($line); }]);
        $ok=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_HTTP_CODE); $type=curl_getinfo($ch,CURLINFO_CONTENT_TYPE) ?: ''; curl_close($ch);
        if ($ok!==false && $status===200 && str_starts_with(strtolower($type),'image/')) return;
        if ($status>=300 && $status<400 && $location!=='') { $url=resolve_remote_url($url,$location); continue; }
        throw new InvalidArgumentException('A newsletter image is not publicly accessible. Check image URLs and retry.');
    }
    throw new InvalidArgumentException('A newsletter image redirects too many times.');
}
function publish_newsletter(int $id,array $user,bool $replace=false): array {
    @set_time_limit(70);
    if (!can('edit',$user) || !can('download',$user) || ($replace && !is_admin($user))) throw new UnexpectedValueException('Publishing permission denied.');
    $config=publishing_config(); $parts=parse_url($config['url']);
    $test=getenv('TTW_ALLOW_LOCAL_HTTP')==='1' && ($parts['scheme'] ?? '')==='http' && ($parts['host'] ?? '')==='127.0.0.1';
    if (!publishing_endpoint_allowed($config['url'],$test)) throw new InvalidArgumentException('Configure the main website publishing API first.');
    if (strlen($config['token'])<32) throw new InvalidArgumentException('Configure the server-side publishing token first.');
    $pdo=db(); $pdo->beginTransaction(); $temp=null;
    try {
        $s=$pdo->prepare('SELECT * FROM newsletters WHERE id=? FOR UPDATE'); $s->execute([$id]); $row=$s->fetch();
        if (!$row) throw new OutOfBoundsException('Newsletter not found.');
        if (!$row['approved_at'] || !trim($row['header_title']) || !trim($row['subject_title'])) throw new InvalidArgumentException('Approve the generated newsletter with Header news and Email Title before publishing.');
        $html=render_newsletter(load_newsletter($id)); validate_publish_images($html);
        if (strlen($html)>2*1024*1024) throw new InvalidArgumentException('Newsletter HTML exceeds 2 MB.');
        $name=issue_file_name($row['newsletter_date'],$row['edition']); $year=date('Y',strtotime($row['newsletter_date'])); $month=date('M',strtotime($row['newsletter_date']));
        $temp=tempnam(sys_get_temp_dir(),'ttw-html-'); file_put_contents($temp,$html);
        $ch=curl_init($config['url']);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>['filename'=>$name,'year'=>$year,'month'=>$month,'approved'=>'1','replace'=>$replace ? '1' : '0','html'=>new CURLFile($temp,'text/html',$name)],
            CURLOPT_HTTPHEADER=>['X-Newsletter-Token: '.$config['token'],'Accept: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>25,
            CURLOPT_PROTOCOLS=>$test ? CURLPROTO_HTTP : CURLPROTO_HTTPS]);
        $body=''; curl_setopt($ch,CURLOPT_WRITEFUNCTION,static function($ch,$chunk) use (&$body) { if (strlen($body)+strlen($chunk)>16384) return 0; $body.=$chunk; return strlen($chunk); });
        $ok=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code===409) throw new LogicException('FILE_EXISTS');
        $response=json_decode($body,true);
        $expected=rtrim(NEWSLETTER_BASE_URL,'/').'/'.$year.'/'.$month.'/'.$name;
        if ($ok===false || $code!==200 || !is_array($response) || empty($response['success']) || ($response['url'] ?? '')!==$expected || ($response['filename'] ?? '')!==$name || !hash_equals(hash('sha256',$html),(string)($response['sha256'] ?? ''))) throw new RuntimeException('Main website HTML upload failed. Check upload API configuration and retry.');
        $pdo->prepare("INSERT INTO newsletter_publications(newsletter_id,status,file_name,public_url,html_sha256,published_at,published_by,error_message) VALUES(?,'published',?,?,?,NOW(),?,NULL) ON DUPLICATE KEY UPDATE status='published',file_name=VALUES(file_name),public_url=VALUES(public_url),html_sha256=VALUES(html_sha256),published_at=NOW(),published_by=VALUES(published_by),error_message=NULL")->execute([$id,$name,$expected,hash('sha256',$html),$user['id']]);
        $pdo->commit(); return publication_data($id);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof RuntimeException && !($e instanceof OutOfBoundsException) && !($e instanceof UnexpectedValueException)) {
            // Preserve an earlier successful publication when a republish attempt fails.
            $pdo->prepare("INSERT INTO newsletter_publications(newsletter_id,status,error_message) VALUES(?,'failed','HTML upload failed. Retry after checking configuration.') ON DUPLICATE KEY UPDATE error_message=VALUES(error_message),status=IF(status='published','published','failed')")->execute([$id]);
        }
        throw $e;
    } finally { if ($temp && is_file($temp)) unlink($temp); }
}
