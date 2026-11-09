<?php
declare(strict_types=1);

function legacy_image_base_url(string $base): string {
    return preg_replace('~/New$~i', '', rtrim($base, '/'));
}

/** Only uploaded images in this app or the configured original app are local candidates. */
function uploaded_image_path(string $value): ?string {
    if (preg_match('/[\x00-\x1f\x7f\\\\]/', $value)) return null;
    $parts = parse_url($value);
    if ($parts === false || isset($parts['user']) || isset($parts['pass'])) return null;
    $path = $parts['path'] ?? '';
    $bases = array_unique([rtrim(APP_BASE_URL,'/'), rtrim(LEGACY_APP_BASE_URL,'/')]);
    if (isset($parts['scheme']) || isset($parts['host'])) {
        if (!in_array(strtolower($parts['scheme'] ?? ''), ['https','http'], true)) return null;
        $origin = strtolower($parts['scheme'] . '://' . ($parts['host'] ?? '')) . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $matched = false;
        foreach ($bases as $base) {
            $b = parse_url($base);
            $baseOrigin = strtolower(($b['scheme'] ?? '') . '://' . ($b['host'] ?? '')) . (isset($b['port']) ? ':' . $b['port'] : '');
            $prefix = rtrim($b['path'] ?? '', '/') . '/uploads/';
            if ($origin === $baseOrigin && str_starts_with($path, $prefix)) {
                $path = 'uploads/' . substr($path, strlen($prefix)); $matched = true; break;
            }
        }
        if (!$matched) return null;
    } else {
        foreach ($bases as $base) {
            $prefix = rtrim(parse_url($base,PHP_URL_PATH) ?: '', '/') . '/uploads/';
            if (str_starts_with($path,$prefix)) { $path='uploads/' . substr($path,strlen($prefix)); break; }
        }
    }
    $path = rawurldecode(ltrim($path,'/'));
    if (!str_starts_with($path,'uploads/') || preg_match('/[\x00-\x1f\x7f\\\\]/',$path)) return null;
    foreach (explode('/',$path) as $part) if ($part === '' || $part === '.' || $part === '..') return null;
    return $path;
}

function image_placeholder(string $kind): array {
    $sizes=['header'=>[610,380],'pick'=>[350,291],'promotion'=>[200,170],'profile'=>[128,128]];
    [$width,$height]=$sizes[$kind] ?? $sizes['header'];
    if (!function_exists('imagecreatetruecolor')) {
        return ['bytes'=>base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='),'mime'=>'image/png','source'=>'placeholder','modified'=>0];
    }
    $image=imagecreatetruecolor($width,$height);
    imagefill($image,0,0,imagecolorallocate($image,239,240,247));
    $label=$kind==='profile' ? 'TTW' : 'Image unavailable';
    $x=max(5,(int)(($width-imagefontwidth(4)*strlen($label))/2));
    imagestring($image,4,$x,(int)(($height-imagefontheight(4))/2),$label,imagecolorallocate($image,65,67,103));
    ob_start(); imagepng($image); $bytes=ob_get_clean(); imagedestroy($image);
    return ['bytes'=>$bytes,'mime'=>'image/png','source'=>'placeholder','modified'=>0];
}

/** New uploads first, original application's uploads second, real placeholder last. No HTTP requests. */
function resolve_uploaded_image(string $path, string $kind='header'): array {
    $normalized=uploaded_image_path($path);
    if ($normalized===null) return image_placeholder($kind);
    $relative=substr($normalized,strlen('uploads/'));
    foreach (['primary'=>IMAGE_UPLOAD_ROOT,'legacy'=>LEGACY_IMAGE_UPLOAD_ROOT] as $source=>$directory) {
        if ($directory==='') continue;
        $root=realpath($directory);
        if (!$root) continue;
        $file=realpath($root . DIRECTORY_SEPARATOR . str_replace('/',DIRECTORY_SEPARATOR,$relative));
        if (!$file || !str_starts_with($file,$root . DIRECTORY_SEPARATOR) || !is_file($file) || !is_readable($file)) continue;
        $size=filesize($file);
        if (!$size || $size>12*1024*1024) continue;
        $bytes=@file_get_contents($file);
        $info=$bytes===false ? false : @getimagesizefromstring($bytes);
        $types=[IMAGETYPE_JPEG=>'image/jpeg',IMAGETYPE_PNG=>'image/png',IMAGETYPE_GIF=>'image/gif',IMAGETYPE_WEBP=>'image/webp'];
        if (!$info || !isset($types[$info[2]]) || $info[0]*$info[1]>20000000) continue;
        if (function_exists('imagecreatefromstring')) {
            $decoded=@imagecreatefromstring($bytes);
            if (!$decoded) continue;
            imagedestroy($decoded);
        }
        return ['bytes'=>$bytes,'mime'=>$types[$info[2]],'source'=>$source,'modified'=>(int)filemtime($file)];
    }
    return image_placeholder($kind);
}
