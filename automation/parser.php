<?php
declare(strict_types=1);

function normalize_category(string $name): string {
    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)), 'UTF-8');
}
function article_url(string $url): string {
    $p = parse_url(trim($url));
    if (!$p || ($p['scheme'] ?? '') !== 'https' || !in_array(strtolower($p['host'] ?? ''), ['travelandtourworld.com','www.travelandtourworld.com'], true)
        || isset($p['user']) || isset($p['pass']) || isset($p['port']) || !preg_match('~^/news/article/[^\s]+/?$~', $p['path'] ?? '')
        || preg_match('/[\x00-\x20\\\\]/', $url)) {
        throw new InvalidArgumentException('Only HTTPS TravelAndTourWorld article URLs are allowed.');
    }
    return 'https://www.travelandtourworld.com' . rtrim($p['path'], '/') . '/';
}
function category_map(array $masters): array {
    $map = [];
    foreach ($masters as $m) {
        if (!(int)$m['active']) continue;
        foreach (array_merge([$m['category_name']], explode(',', $m['aliases'] ?? '')) as $name) {
            $key = normalize_category($name);
            if ($key !== '') {
                if (isset($map[$key]) && (int)$map[$key]['id'] !== (int)$m['id']) throw new InvalidArgumentException('Category aliases overlap: ' . $name);
                $map[$key] = $m;
            }
        }
    }
    return $map;
}
function parse_newsletter_input(string $text, array $masters): array {
    if (strlen($text) > 65536) throw new InvalidArgumentException('Input exceeds 64 KB.');
    $map = category_map($masters);
    $text = preg_replace('/^([^\r\n:：]{1,190})\s*[:：]\s*(?=https?:\/\/)/miu', "$1:\n", $text);
    $articles = $warnings = $errors = $seen = [];
    $section = ''; $category = null;
    foreach (preg_split('/\R/u', $text) as $i => $raw) {
        $line = trim($raw);
        if ($line === '') continue;
        if (preg_match('~https?://~i', $line)) {
            preg_match_all('~https?://[^\s<>]+~i', $line, $matches);
            foreach ($matches[0] as $rawUrl) {
                try { $url = article_url(rtrim($rawUrl, ',;')); }
                catch (InvalidArgumentException $e) { $errors[] = 'Line '.($i+1).': invalid URL'; continue; }
                if (isset($seen[$url])) { $warnings[] = 'Duplicate URL: '.$url; continue; }
                if (!$section || ($section === 'category' && !$category)) { $errors[] = 'Line '.($i+1).': choose a section/category first'; continue; }
                $seen[$url] = true;
                $articles[] = ['section'=>$section, 'category_id'=>$category ? (int)$category['id'] : null, 'category_name'=>$category['category_name'] ?? null, 'url'=>$url];
            }
            continue;
        }
        $label = normalize_category(preg_replace('/^[\s#]+|[\s:：\-–—]+$/u', '', $line));
        $category = null;
        switch ($label) {
            case 'header': case 'header story': $section='header'; break;
            case 'pick news': $section='pick'; break;
            case 'more news': case 'more travel news': $section='more'; break;
            case 'categories': $section='category'; break;
            default:
                $section='category'; $category=$map[$label] ?? null;
                if (!$category) $errors[] = 'Unknown category: '.$line;
        }
    }
    $headers = count(array_filter($articles, fn($a)=>$a['section']==='header'));
    if ($headers !== 1) $errors[] = 'Exactly one header article is required.';
    if (count($articles) > 50) $errors[] = 'Maximum 50 articles per job.';
    return ['articles'=>$articles,'total'=>count($articles),'warnings'=>$warnings,'errors'=>$errors,'status'=>$errors ? 'warning' : 'ready'];
}
