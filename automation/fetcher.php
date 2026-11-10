<?php
declare(strict_types=1);
require_once __DIR__ . '/parser.php';
require_once dirname(__DIR__) . '/config.php';

function resolve_remote_url(string $base, string $value): string {
    $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (str_starts_with($value, 'https://')) return $value;
    $b = parse_url($base);
    if (str_starts_with($value, '//')) return 'https:' . $value;
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $value)) throw new RuntimeException('Unsupported URL scheme.');
    $path = str_starts_with($value, '/') ? $value : dirname($b['path'] ?? '/') . '/' . $value;
    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '..') array_pop($parts);
        elseif ($part !== '' && $part !== '.') $parts[] = $part;
    }
    return 'https://' . $b['host'] . '/' . implode('/', $parts);
}

/** DNS is checked and pinned on every hop; no automatic redirects or proxy. */
function safe_fetch(string $url, bool $image = false): string {
    $allowed = ['www.travelandtourworld.com','travelandtourworld.com'];
    if ($image) $allowed = array_merge($allowed, array_filter(array_map('trim', explode(',', getenv('TTW_IMAGE_HOSTS') ?: ''))));
    for ($hop=0; $hop<4; $hop++) {
        $p = parse_url($url);
        $host = strtolower($p['host'] ?? '');
        if (($p['scheme'] ?? '') !== 'https' || !in_array($host, $allowed, true) || isset($p['user']) || isset($p['pass']) || isset($p['port']) || preg_match('/[\x00-\x20\\\\]/', $url)) throw new RuntimeException('Remote host is not allowed.');
        $ips = gethostbynamel($host) ?: [];
        if (!$ips) throw new RuntimeException('Remote host could not be resolved.');
        foreach ($ips as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) throw new RuntimeException('Unsafe remote address.');
        $body=''; $location=''; $max=$image ? 8*1024*1024 : 3*1024*1024;
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false, CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_RESOLVE=>[$host.':443:'.$ips[0]], CURLOPT_PROXY=>'', CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>18,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_USERAGENT=>'TTW-Newsletter/1.0',
            CURLOPT_ENCODING=>'', CURLOPT_HEADERFUNCTION=>function($c,$h) use (&$location) { if (stripos($h,'Location:')===0) $location=trim(substr($h,9)); return strlen($h); },
            CURLOPT_WRITEFUNCTION=>function($c,$chunk) use (&$body,$max) { if (strlen($body)+strlen($chunk)>$max) return 0; $body.=$chunk; return strlen($chunk); }]);
        $ok=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE); curl_close($ch);
        if ($ok===false) throw new RuntimeException('Remote request failed or exceeded its limit.');
        if ($code>=300 && $code<400 && $location!=='') { $url=resolve_remote_url($url,$location); continue; }
        if ($code!==200) throw new RuntimeException('Remote server returned HTTP '.$code.'.');
        if (!$image && !preg_match('~^(text/html|application/xhtml\+xml)~i',$type)) throw new RuntimeException('Article response is not HTML.');
        return $body;
    }
    throw new RuntimeException('Too many redirects.');
}

function clean_article_text(string $text): string {
    $text = str_replace(["\u{200B}", "\u{FEFF}", "\u{00AD}"], '', $text);
    return trim(preg_replace('/\s+/u', ' ', $text));
}
function clean_article_title(string $title): string {
    $title = clean_article_text($title);
    do {
        $previous = $title;
        $title = trim(preg_replace('/\s*[-–—|]\s*Travel\s+(?:And|&)\s+Tour\s+World\s*$/iu', '', $title));
    } while ($previous !== $title);
    return $title;
}
function article_snippet(string $content, int $words): string {
    $content = clean_article_text($content);
    if ($content === '') return '';
    $parts = preg_split('/\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY);
    return rtrim(implode(' ', array_slice($parts, 0, $words)), " \t\n\r.,;:!?…") . '...';
}
function extract_article(string $html, string $url, string $section = 'header'): array {
    $doc=new DOMDocument();
    $previous=libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    $xp=new DOMXPath($doc);
    $meta=function(string $key) use ($xp): string {
        $node=$xp->query('//meta[@property="'.$key.'" or @name="'.$key.'"]/@content')->item(0);
        return trim($node ? $node->nodeValue : '');
    };
    $title = trim($xp->evaluate('string((//article//h1)[1])'));
    if ($title === '') $title = trim($xp->evaluate('string((//*[@itemprop="headline"])[1])'));
    $body = null;
    foreach ([
        '//*[contains(concat(" ",normalize-space(@class)," ")," single-news-content ")]',
        '//*[contains(concat(" ",normalize-space(@class)," ")," entry-content ")]',
        '//*[@itemprop="articleBody"]',
        '//article[.//h1]',
    ] as $selector) {
        $candidate = $xp->query('(' . $selector . ')[1]')->item(0);
        if ($candidate instanceof DOMElement) { $body = $candidate; break; }
    }
    $content = '';
    if ($body) {
        if ($title === '') $title = trim($xp->evaluate('string((//main//h1 | //h1)[1])'));
        if ($title === '') $title = $meta('og:title') ?: trim($xp->evaluate('string(//title)'));
        $copy = $body->cloneNode(true);
        $cleanDoc = new DOMDocument(); $cleanDoc->appendChild($cleanDoc->importNode($copy, true));
        $clean = new DOMXPath($cleanDoc);
        $remove = [];
        foreach ($clean->query('//script|//style|//nav|//aside|//form|//button|//figure|//figcaption|//iframe|//svg|//ins|//*[contains(@class,"advert") or contains(@class,"adsbygoogle") or contains(@class,"ad-container") or contains(@class,"related-post") or contains(@class,"share-buttons")]') as $node) $remove[] = $node;
        foreach ($remove as $node) if ($node->parentNode) $node->parentNode->removeChild($node);
        $paragraphs = [];
        foreach ($clean->query('//p') as $paragraph) {
            $text = clean_article_text($paragraph->textContent);
            if ($text !== '' && !preg_match('/^(?:Advertisement|Also read\s*:|Read more\s*:|Image (?:credit|courtesy|generated)|Written by\s*:)/iu', $text)) $paragraphs[] = $text;
        }
        $content = clean_article_text(implode(' ', $paragraphs));
        // Bound the stored source text; snippets only need its opening words.
        $content = mb_substr($content, 0, 30000);
    }
    // A news-list redirect is not an article. Do not import its generic heading/cards.
    if (!$body) $title = '';
    $title = clean_article_title($title);
    $description = article_snippet($content, $section === 'header' ? 35 : 16);
    $image=$meta('og:image') ?: $meta('twitter:image');
    $canonical=trim($xp->evaluate('string(//link[@rel="canonical"]/@href)'));
    try { $canonical=$canonical ? article_url(resolve_remote_url($url,$canonical)) : $url; } catch (Throwable $e) { $canonical=$url; }
    return ['title'=>$title,'description'=>$description,'hidden_text'=>article_snippet($content,16),'content'=>$content,'featured_image'=>$image ? resolve_remote_url($url,$image) : '', 'canonical_url'=>$canonical];
}

function process_image(string $bytes, string $date, int $width, int $height): string {
    if (strlen($bytes)>8*1024*1024) throw new RuntimeException('Image exceeds 8 MB.');
    $info=@getimagesizefromstring($bytes);
    if (!$info || !in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true) || $info[0]*$info[1]>20000000) throw new RuntimeException('Invalid image or image exceeds 20 megapixels.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    if (!in_array($mime,['image/jpeg','image/png','image/webp'],true)) throw new RuntimeException('Unsupported image MIME.');
    $src=@imagecreatefromstring($bytes);
    if (!$src) throw new RuntimeException('Image decoding failed.');
    $target=imagecreatetruecolor($width,$height);
    $scale=max($width/$info[0],$height/$info[1]);
    $sw=(int)round($width/$scale); $sh=(int)round($height/$scale);
    imagecopyresampled($target,$src,0,0,(int)(($info[0]-$sw)/2),(int)(($info[1]-$sh)/2),$width,$height,$sw,$sh);
    ob_start(); imagejpeg($target,null,90); $output=ob_get_clean(); imagedestroy($src); imagedestroy($target);
    // Working files are separate from the final issue images, grouped by issue month.
    $directory='uploads/temporary/' . date('Y/M',strtotime($date));
    $path=$directory.'/auto_'.bin2hex(random_bytes(12)).'.jpg';
    if (@file_put_contents(upload_disk_path($path,true),$output,LOCK_EX)!==strlen($output)) throw new RuntimeException('Image could not be saved.');
    return $path;
}
function missing_image(string $date, int $width, int $height): string {
    $im=imagecreatetruecolor($width,$height); imagefill($im,0,0,imagecolorallocate($im,239,240,247));
    imagestring($im,5,20,(int)($height/2),'Image unavailable',imagecolorallocate($im,45,46,85));
    ob_start(); imagejpeg($im); $bytes=ob_get_clean(); imagedestroy($im);
    return process_image($bytes,$date,$width,$height);
}
