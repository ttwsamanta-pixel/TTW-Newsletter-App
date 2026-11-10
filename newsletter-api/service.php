<?php
declare(strict_types=1);
function receive_newsletter_html(array $input,array $upload): array {
    $name=(string)($input['filename'] ?? '');
    if (!preg_match('/^TTW_newsletter_(\d{2})(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)(\d{4})(M|E)\.html$/D',$name,$m)) throw new InvalidArgumentException('Invalid newsletter filename.');
    $date=DateTimeImmutable::createFromFormat('!dMY',$m[1].$m[2].$m[3]);
    if (!$date || $date->format('dMY')!==$m[1].$m[2].$m[3] || (int)$m[3]<2000 || (int)$m[3]>2199 || (string)($input['year'] ?? '')!==$m[3] || ($input['month'] ?? '')!==$m[2]) throw new InvalidArgumentException('Filename and issue folder do not match.');
    if (($input['approved'] ?? '')!=='1') throw new InvalidArgumentException('Only approved newsletters may be uploaded.');
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'] ?? '') || strtolower(pathinfo($upload['name'] ?? '',PATHINFO_EXTENSION))!=='html') throw new InvalidArgumentException('Choose a newsletter HTML file.');
    $size=filesize($upload['tmp_name']);
    if (!$size || $size>2*1024*1024) throw new LengthException('HTML must be under 2 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $html=file_get_contents($upload['tmp_name']);
    if (!in_array($mime,['text/html','text/plain'],true) || !preg_match('/<html\b/i',$html) || !preg_match('/<body\b/i',$html) || preg_match('/<\?(?:php|=)|<(?:script|iframe|object|embed|form|base)\b|\bon[a-z]+\s*=|javascript\s*:/i',$html)) throw new InvalidArgumentException('Invalid or active HTML content.');
    $root=NEWSLETTER_HTML_ROOT;
    if (!is_dir($root) && !@mkdir($root,0755,true) && !is_dir($root)) throw new RuntimeException('Publication folder is unavailable.');
    $root=realpath($root); $directory=$root;
    foreach ([$m[3],$m[2]] as $part) {
        $next=$directory.DIRECTORY_SEPARATOR.$part;
        if (is_link($next)) throw new InvalidArgumentException('Invalid destination folder.');
        if (!is_dir($next) && !@mkdir($next,0755) && !is_dir($next)) throw new RuntimeException('Publication folder is unavailable.');
        $directory=realpath($next);
        if (!$directory || !str_starts_with($directory,$root.DIRECTORY_SEPARATOR)) throw new InvalidArgumentException('Invalid destination folder.');
    }
    $target=$directory.DIRECTORY_SEPARATOR.$name;
    $lock=fopen($root.'/.newsletter-publish.lock','c');
    if (!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('Publication is busy. Try again.');
    $temp=null;
    try {
        if (is_link($target)) throw new InvalidArgumentException('Invalid destination file.');
        $hash=hash('sha256',$html); $same=is_file($target) && hash_equals($hash,hash_file('sha256',$target));
        if (is_file($target) && !$same && ($input['replace'] ?? '')!=='1') throw new DomainException('FILE_EXISTS');
        if (!$same) {
            $temp=tempnam($directory,'.newsletter-');
            if (!$temp || file_put_contents($temp,$html,LOCK_EX)!==strlen($html)) throw new RuntimeException('Unable to save newsletter.');
            chmod($temp,0644);
            if (!rename($temp,$target)) throw new RuntimeException('Unable to save newsletter.');
        }
        return ['success'=>true,'filename'=>$name,'year'=>$m[3],'month'=>$m[2],'sha256'=>$hash,'unchanged'=>$same,'url'=>rtrim(NEWSLETTER_PUBLIC_BASE,'/').'/'.$m[3].'/'.$m[2].'/'.$name];
    } finally { if ($temp && is_file($temp)) unlink($temp); flock($lock,LOCK_UN); fclose($lock); }
}
