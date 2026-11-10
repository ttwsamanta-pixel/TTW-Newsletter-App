<?php
require_once __DIR__.'/includes/publishing.php';
$user=require_permission('edit');
if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') redirect_to('newsletters.php');
verify_csrf(); $id=(int)($_POST['id'] ?? 0);
try {
    if (($_POST['action'] ?? '')==='approve') {
        $n=load_newsletter($id);
        if (!$n || !trim($n['subject_title']) || !trim($n['header_title'])) throw new InvalidArgumentException('Add Email Title and Header news before approval.');
        db()->prepare('UPDATE newsletters SET approved_at=NOW(),approved_by=? WHERE id=?')->execute([$user['id'],$id]);
        $_SESSION['publish_notice']='Newsletter approved. You can now Publish HTML.';
    } else { publish_newsletter($id,$user,!empty($_POST['replace'])); $_SESSION['publish_notice']='HTML published successfully.'; }
}
catch (Throwable $e) { $_SESSION['publish_notice']=$e instanceof LogicException && $e->getMessage()==='FILE_EXISTS' ? 'HTML already exists. An administrator can explicitly replace it.' : ($e instanceof InvalidArgumentException ? $e->getMessage():'HTML upload failed. Check the main website API configuration and retry.'); }
redirect_to('view.php?id='.$id);
