<?php
declare(strict_types=1);
define('IMAGE_UPLOAD_ROOT',__DIR__.'/.runtime/temp-test-uploads');
require_once dirname(__DIR__).'/automation/jobs.php';
$checks=0; $cleanup=[];
function temp_check(bool $value,string $label): void { global $checks; if (!$value) throw new RuntimeException($label); $checks++; }
try {
    $im=imagecreatetruecolor(40,30); ob_start(); imagepng($im); $bytes=ob_get_clean(); imagedestroy($im);
    $path=process_image($bytes,'2096-12-17',350,291); $cleanup[]=$path;
    temp_check(str_starts_with($path,'uploads/temporary/2096/Dec/auto_'),'New images use separate issue-month folder');
    temp_check(is_automation_image_path($path),'New path accepted for imports');
    $data=automation_image_data($path);
    temp_check(str_starts_with($data,'data:image/jpeg;base64,'),'New path read for draft apply');
    $size=getimagesize(upload_disk_path($path));
    temp_check([$size[0],$size[1]]===[350,291],'Processed dimensions retained');
    $final=save_cropped_image($data,'temporary-test-'.bin2hex(random_bytes(4)).'.jpg','2096-12-17'); $cleanup[]=$final;
    temp_check(str_starts_with($final,'uploads/2096/Dec/'),'Final images stay outside temporary folder');
    temp_check(file_get_contents(upload_disk_path($final))===file_get_contents(upload_disk_path($path)),'Final copy preserves working bytes');
    $legacy='uploads/2096/Dec/auto_'.bin2hex(random_bytes(12)).'.jpg'; $cleanup[]=$legacy;
    copy(upload_disk_path($path),upload_disk_path($legacy,true));
    temp_check(automation_image_data($legacy)===$data,'Old pending-job images still readable');
    foreach ([$path,$legacy] as $candidate) {
        validate_promotion_upload(['promotion_image'=>$candidate,'promotion_signature'=>promotion_signature($candidate,123),'promotion_url'=>'https://example.com'],['id'=>123]);
        temp_check(true,'Signed promotion accepts old and new paths');
    }
    foreach (['uploads/temporary/../2096/Dec/auto_'.str_repeat('a',24).'.jpg','uploads/temporary/2096/Dec/other.jpg','uploads/temporary/2096/Dec/auto_bad.jpg'] as $bad) temp_check(!is_automation_image_path($bad),'Unsafe or unrelated working path rejected');
    delete_local_upload($path);
    temp_check(is_file(upload_disk_path($final)),'Removing working image leaves final newsletter image intact');
    echo "PASS: $checks temporary image checks\n";
} finally {
    foreach ($cleanup as $file) delete_local_upload($file);
    foreach (['uploads/temporary/2096/Dec','uploads/temporary/2096','uploads/2096/Dec','uploads/2096'] as $dir) @rmdir(IMAGE_UPLOAD_ROOT.'/'.substr($dir,8));
}
