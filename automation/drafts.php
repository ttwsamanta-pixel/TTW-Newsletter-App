<?php
declare(strict_types=1);

function blank_newsletter(string $date, string $edition): array {
    return ['id'=>0,'newsletter_date'=>$date,'edition'=>$edition,'subject_title'=>'','hidden_text'=>'',
        'header_title'=>'','header_description'=>'','header_url'=>'','header_image'=>null,
        'promotion_enabled'=>0,'promotion_text'=>'','promotion_url'=>'','promotion_image'=>null,
        'promotion_width'=>200,'promotion_height'=>170,'pick_news'=>[],'more_news'=>[],'categories'=>[]];
}
function is_automation_image_path(string $path): bool {
    // Accept pre-update working files so existing queued jobs and uploads still work.
    return preg_match('~^uploads/(?:temporary/)?\d{4}/[A-Za-z]{3}/auto_[a-f0-9]{24}\.jpg$~', $path)===1;
}
function validate_promotion_upload(array $input, array $user): void {
    $path = (string)($input['promotion_image'] ?? '');
    if ($path === '') return;
    if (!is_automation_image_path($path)
        || !hash_equals(promotion_signature($path,(int)$user['id']), (string)($input['promotion_signature'] ?? ''))
        || !upload_disk_path($path)) throw new InvalidArgumentException('Upload the promotion again.');
    if (!filter_var($input['promotion_url'] ?? '', FILTER_VALIDATE_URL) || parse_url($input['promotion_url'],PHP_URL_SCHEME) !== 'https') throw new InvalidArgumentException('Promotion URL must use HTTPS.');
}
function automation_image_data(string $path): string {
    if (!is_automation_image_path($path)) throw new InvalidArgumentException('Processed image path is invalid.');
    $file=upload_disk_path($path);
    $bytes = $file ? @file_get_contents($file) : false;
    if ($bytes === false) throw new InvalidArgumentException('Processed image is missing. Retry this import.');
    return 'data:image/jpeg;base64,' . base64_encode($bytes);
}
function save_newsletter_draft(array $input, array $user): int {
    $date = (string)($input['newsletter_date'] ?? ''); $edition = (string)($input['edition'] ?? '');
    if (!valid_issue_date($date) || !in_array($edition,['M','E'],true)) throw new InvalidArgumentException('Choose a valid date and edition.');
    $id = (int)($input['id'] ?? 0);
    if (!can($id ? 'edit' : 'create',$user)) throw new InvalidArgumentException('You do not have permission to save this draft.');
    if (isset($input['subject_title']) && (!is_string($input['subject_title']) || strlen($input['subject_title']) > 250)) throw new InvalidArgumentException('Email Title must be at most 250 characters.');
    validate_promotion_upload($input,$user);
    $pdo = db(); $pdo->beginTransaction();
    try {
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM newsletters WHERE id=? FOR UPDATE'); $stmt->execute([$id]); $row=$stmt->fetch();
            if (!$row) throw new OutOfBoundsException('Draft not found.');
            if ($row['approved_at']) throw new InvalidArgumentException('This issue is approved. Use Edit Draft to reopen it before importing sections.');
            if ($row['newsletter_date'] !== $date || $row['edition'] !== $edition) throw new InvalidArgumentException('The selected date and edition do not match this draft.');
            $n = load_newsletter($id);
        } else {
            $stmt = $pdo->prepare('SELECT id FROM newsletters WHERE newsletter_date=? AND edition=? FOR UPDATE'); $stmt->execute([$date,$edition]);
            $existing = $stmt->fetchColumn();
            if ($existing) throw new DomainException((string)$existing);
            $n = blank_newsletter($date,$edition);
        }
        if (array_key_exists('subject_title',$input)) $n['subject_title']=trim($input['subject_title']);
        $data = newsletter_form_data($n);
        foreach (['promotion_url','promotion_text'] as $key) {
            if (array_key_exists($key,$input)) {
                if (!is_string($input[$key]) || strlen($input[$key])>3000) throw new InvalidArgumentException('Promotion text or URL is too long.');
                if ($key==='promotion_url' && $n['promotion_enabled'] && $input[$key]!==$n[$key] && (!filter_var($input[$key],FILTER_VALIDATE_URL) || parse_url($input[$key],PHP_URL_SCHEME)!=='https')) throw new InvalidArgumentException('Promotion URL must use HTTPS.');
                $data[$key]=$input[$key];
            }
        }
        if (array_key_exists('promotion_image',$input)) {
            $data['promotion_enabled'] = $input['promotion_image'] ? 1 : 0;
            $data['promotion_url'] = (string)($input['promotion_url'] ?? '');
            $data['promotion_text'] = (string)($input['promotion_text'] ?? '');
            if ($data['promotion_enabled']) $data['promotion_image_data']=automation_image_data($input['promotion_image']);
        }
        $id = persist_newsletter($pdo,$data,$user,false,true);
        $pdo->commit(); return $id;
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}

/** Merge only the imported section. Unrelated text, images and category groups survive. */
function automation_draft_data(array $job, ?array $existing): array {
    $payload = $job['payload']; $partial = ($payload['input_mode'] ?? 'all') === 'section';
    $n = $partial && $existing ? $existing : blank_newsletter($payload['newsletter_date'],$payload['edition']);
    $n['id'] = (int)($existing['id'] ?? 0);
    if (!$partial || trim((string)($payload['subject_title'] ?? '')) !== '') $n['subject_title']=trim((string)($payload['subject_title'] ?? ''));
    $imported = ['pick'=>[],'more'=>[],'category'=>[]]; $newHeader = null;
    $masters = master_categories(); $names = category_map($masters);
    foreach ($job['articles'] as $article) {
        $r = $article['result'];
        $title = clean_article_title($r['title']); $url = $r['canonical_url'];
        $description = isset($r['content']) ? article_snippet($r['content'],$article['section']==='header' ? 35 : 16) : ($r['description'] ?? '');
        if ($article['section'] === 'header') {
            $n['header_title']=$title; $n['header_description']=$description; $n['header_url']=$url;
            $n['hidden_text']=isset($r['content']) ? article_snippet($r['content'],16) : ($r['hidden_text'] ?? article_snippet($description,16));
            $newHeader=$r['image'];
        } elseif ($article['section'] === 'pick') {
            $imported['pick'][]=['title'=>$title,'description'=>$description,'url'=>$url,'image'=>$r['image']];
        } elseif ($article['section'] === 'more') {
            $imported['more'][]=['title'=>$title,'url'=>$url];
        } else {
            $id=(int)$article['category_id'];
            if (!isset($imported['category'][$id])) {
                $matches=array_values(array_filter($masters,fn($m)=>(int)$m['id']===$id && (int)$m['active']===1));
                if (!$matches) throw new InvalidArgumentException('A category was deactivated. Create a new import.');
                $m=$matches[0];
                $imported['category'][$id]=['category_name'=>$m['category_name'],'category_url'=>'https://www.travelandtourworld.com/news/article/category/'.$m['category_slug'].'/','items'=>[]];
            }
            $imported['category'][$id]['items'][]=['title'=>$title,'url'=>$url];
        }
    }
    $scope=$payload['section'] ?? '';
    if (!$partial || $scope==='pick') $n['pick_news']=$imported['pick'];
    if (!$partial || $scope==='more') $n['more_news']=$imported['more'];
    if (!$partial) $n['categories']=array_values($imported['category']);
    elseif ($scope==='category') {
        $merged=[]; $remaining=$imported['category'];
        foreach ($n['categories'] as $category) {
            $id=(int)($names[normalize_category($category['category_name'])]['id'] ?? 0);
            if ($id && isset($imported['category'][$id])) {
                if (isset($remaining[$id])) { $merged[]=$remaining[$id]; unset($remaining[$id]); }
            } else $merged[]=$category;
        }
        $n['categories']=array_merge($merged,array_values($remaining));
    }
    if (!$partial || !empty($payload['promotion_image'])) {
        $n['promotion_enabled']=empty($payload['promotion_image']) ? 0 : 1;
        $n['promotion_url']=$payload['promotion_url'] ?? '';
        $n['promotion_text']=$payload['promotion_text'] ?? '';
    }
    $data=newsletter_form_data($n);
    if ($newHeader) $data['header_image_data']=automation_image_data($newHeader);
    if (!$partial || $scope==='pick') {
        foreach ($imported['pick'] as $i=>$item) {
            $data['pick_image_data'][$i]=automation_image_data($item['image']);
            $data['pick_existing_image'][$i]=null;
        }
    }
    if (!empty($payload['promotion_image'])) $data['promotion_image_data']=automation_image_data($payload['promotion_image']);
    return $data;
}
