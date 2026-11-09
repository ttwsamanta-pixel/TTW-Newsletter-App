<?php
declare(strict_types=1);
$fixture=__DIR__.'/.runtime/image-unit-'.bin2hex(random_bytes(4));
define('IMAGE_UPLOAD_ROOT',$fixture.'/New/uploads');
define('LEGACY_IMAGE_UPLOAD_ROOT',$fixture.'/uploads');
require_once dirname(__DIR__).'/newsletter_renderer.php';
$checks=0;
function image_check(bool $ok,string $message): void {global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function fixture_png(string $path,int $color): string {
    if(!is_dir(dirname($path)))mkdir(dirname($path),0775,true);
    $im=imagecreatetruecolor(10,8);imagefill($im,0,0,$color);imagepng($im,$path);imagedestroy($im);return file_get_contents($path);
}
$relative='uploads/2026/Nov/shared.png';$new=IMAGE_UPLOAD_ROOT.'/2026/Nov/shared.png';$old=LEGACY_IMAGE_UPLOAD_ROOT.'/2026/Nov/shared.png';
$newBytes=fixture_png($new,0xff0000);$oldBytes=fixture_png($old,0x0000ff);
$r=resolve_uploaded_image($relative);image_check($r['source']==='primary'&&$r['bytes']===$newBytes,'New image must win when both exist');
unlink($new);$r=resolve_uploaded_image($relative);image_check($r['source']==='legacy'&&$r['bytes']===$oldBytes,'Original application image is used when New is missing');
file_put_contents($new,'broken image');$r=resolve_uploaded_image($relative);image_check($r['source']==='legacy','Corrupt New image must fall back to original');
file_put_contents($new,substr($newBytes,0,33));$r=resolve_uploaded_image($relative);image_check($r['source']==='legacy','A truncated image with readable header must not block fallback');
unlink($new);unlink($old);
foreach(['header'=>[610,380],'pick'=>[350,291],'promotion'=>[200,170],'profile'=>[128,128]] as $kind=>$expected){$r=resolve_uploaded_image($relative,$kind);$info=getimagesizefromstring($r['bytes']);image_check($r['source']==='placeholder'&&[$info[0],$info[1]]===$expected,'Missing images produce a real appropriately sized placeholder');}
foreach([$relative,'/'.$relative,rtrim(APP_BASE_URL,'/').'/'.$relative,rtrim(LEGACY_APP_BASE_URL,'/').'/'.$relative] as $path)image_check(uploaded_image_path($path)===$relative,'Relative, New and original absolute paths resolve to the same upload');
foreach(['uploads/../config.php','uploads/%2e%2e/config.php','uploads/a/../../config.php','uploads\\2026\\x.png','https://evil.example/uploads/test.png','file:///etc/passwd','uploads/test%00.png','//evil.example/uploads/test.png'] as $unsafe)image_check(uploaded_image_path($unsafe)===null,'Unsafe file paths and foreign origins rejected');
$url=public_upload_preview_url(rtrim(LEGACY_APP_BASE_URL,'/').'/'.$relative,'pick');parse_str(parse_url($url,PHP_URL_QUERY),$query);
image_check(str_starts_with($url,rtrim(APP_BASE_URL,'/').'/image.php?')&&$query['path']===$relative&&$query['kind']==='pick','Original absolute URLs use the New-first resolver');
image_check(public_upload_url('https://external.example/banner.jpg')==='https://external.example/banner.jpg','Unrelated external image URLs remain unchanged');
image_check(resolve_uploaded_image('uploads/../config.php')['source']==='placeholder','Resolver validates paths even if called directly');
$n=['newsletter_date'=>'2026-11-09','edition'=>'M','subject_title'=>'Image test','hidden_text'=>'','header_title'=>'Header','header_description'=>'','header_url'=>'https://www.travelandtourworld.com','header_image'=>$relative,'pick_news'=>[['title'=>'Pick','description'=>'','url'=>'https://www.travelandtourworld.com','image'=>$relative]],'more_news'=>[],'categories'=>[],'promotion_enabled'=>1,'promotion_image'=>$relative,'promotion_url'=>'https://www.travelandtourworld.com','promotion_text'=>''];
$html=render_newsletter($n);image_check(substr_count($html,'/image.php?')===3&&!str_contains($html,'onerror='),'Header, Pick and Promotion use server-side fallback without JavaScript');
$n['header_image']=null;$n['pick_news'][0]['image']=null;$n['promotion_enabled']=0;$html=render_newsletter($n);
image_check(str_contains($html,rawurlencode('uploads/2026/Nov/Header_09M.jpg'))&&str_contains($html,rawurlencode('uploads/2026/Nov/news1_09M.jpg')),'Missing stored paths try inferred upload names before placeholders');
echo "PASS: $checks image fallback checks\n";
