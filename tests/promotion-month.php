<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$ch=curl_init('http://127.0.0.1:8090/New/api/index.php?route=/auth/login');curl_setopt_array($ch,[CURLOPT_POSTFIELDS=>json_encode(['username'=>'manager_admin','password'=>'fixture_password_123']),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true]);$auth=json_decode(curl_exec($ch),true);curl_close($ch);
if(empty($auth['token']))throw new RuntimeException('Fixture login failed.');
$temp=tempnam(sys_get_temp_dir(),'ttw-promo-');$im=imagecreatetruecolor(40,30);imagepng($im,$temp);imagedestroy($im);
try {
    foreach(['2099-03-12'=>200,'not-a-date'=>422] as $date=>$expected) {
        $ch=curl_init('http://127.0.0.1:8090/New/api/index.php?route=/uploads/promotion');curl_setopt_array($ch,[CURLOPT_POSTFIELDS=>['newsletter_date'=>$date,'banner'=>new CURLFile($temp,'image/png','banner.png')],CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$auth['token']],CURLOPT_RETURNTRANSFER=>true]);$r=json_decode(curl_exec($ch),true);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($code!==$expected)throw new RuntimeException('Promotion date validation failed.');
        if($expected===200 && !str_starts_with($r['path'],'uploads/temporary/2099/Mar/'))throw new RuntimeException('Promotion used the upload month instead of selected issue month.');
    }
    echo "PASS: 2 promotion issue-month API checks\n";
} finally {unlink($temp);}
