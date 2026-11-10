<?php
declare(strict_types=1);
$fixture=__DIR__.'/.runtime/shared-'.bin2hex(random_bytes(5));
define('IMAGE_UPLOAD_ROOT',$fixture.'/uploads');
define('LEGACY_IMAGE_UPLOAD_ROOT',$fixture.'/New/uploads');
require_once dirname(__DIR__).'/automation/jobs.php';
$checks=0; $files=[];
function shared_check(bool $ok,string $label): void { global $checks; $checks++; if (!$ok) throw new RuntimeException($label); }
try {
    $path=missing_image('2095-10-03',610,380); $files[]=upload_disk_path($path);
    shared_check(str_starts_with(str_replace('\\','/',upload_disk_path($path)),str_replace('\\','/',$fixture).'/uploads/temporary/2095/Oct/'),'Temporary image saved in root shared folder');
    shared_check(!is_dir($fixture.'/New'),'New app does not create separate uploads');
    $data=automation_image_data($path);
    $final=save_cropped_image($data,'Header_03M.jpg','2095-10-03'); $files[]=upload_disk_path($final);
    shared_check(is_file($fixture.'/uploads/2095/Oct/Header_03M.jpg'),'Original app can read final file at its unchanged path');
    shared_check(resolve_uploaded_image($final)['source']==='primary','Preview uses canonical shared image');
    $old='uploads/temporary/2095/Oct/auto_'.str_repeat('b',24).'.jpg';
    $oldFile=$fixture.'/New/'.$old; mkdir(dirname($oldFile),0775,true); file_put_contents($oldFile,base64_decode(substr($data,strpos($data,',')+1))); $files[]=$oldFile;
    shared_check(automation_image_data($old)===$data,'Previously queued New image remains readable');
    validate_promotion_upload(['promotion_image'=>$old,'promotion_signature'=>promotion_signature($old,1),'promotion_url'=>'https://example.com'],['id'=>1]);
    shared_check(true,'Existing signed New promotion accepted');
    shared_check(resolve_uploaded_image($old)['source']==='legacy','Old New-only image previews still work');
    delete_local_upload($old);
    shared_check(is_file($oldFile),'Deletion does not unexpectedly remove legacy fallback files');
    $copy=upload_disk_path($old,true); file_put_contents($copy,'shared'); $files[]=$copy;
    shared_check(upload_disk_path($old)===$copy,'Shared files take precedence over old New paths');
    try { upload_disk_path('uploads/../config.local.php',true); shared_check(false,'Traversal must fail'); } catch (InvalidArgumentException $e) { shared_check(true,'Traversal rejected'); }
    delete_local_upload($path);
    shared_check(is_file($fixture.'/uploads/2095/Oct/Header_03M.jpg'),'Removing temporary file preserves saved newsletter');
    shared_check(str_contains(public_upload_url($final),'/image.php?'),'Mobile and HTML still use resolver URLs');
    echo "PASS: $checks shared upload checks\n";
} finally {
    foreach ($files as $file) if (is_file($file)) unlink($file);
    if (is_dir($fixture)) {
        $dirs=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($dirs as $dir) if ($dir->isDir()) rmdir($dir->getPathname());
        rmdir($fixture);
    }
}
