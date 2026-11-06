<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth.php';

/** Shared persistence for the existing editor and automation. Caller owns the transaction. */
function persist_newsletter(PDO $pdo, array $data, array $currentUser, bool $autosave = false, bool $strict = false): int
{
    $data += ['hidden_text'=>'', 'header_title'=>'', 'header_description'=>'', 'header_url'=>'', 'promotion_enabled'=>0, 'promotion_text'=>'', 'promotion_url'=>''];
    $id = (int) ($data['id'] ?? 0);

    $date = ($data['newsletter_date'] ?? '') ?: date('Y-m-d');
    $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dateValue || $dateValue->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Choose a valid newsletter date.');
    }
    $edition = strtoupper($data['edition'] ?? 'M') === 'E' ? 'E' : 'M';
    $code = issue_code($date, $edition);

    if (!$id) {
        $stmt = $pdo->prepare('SELECT * FROM newsletters WHERE newsletter_date = ? AND edition = ? LIMIT 1');
        $stmt->execute([$date, $edition]);
        $existingIssue = $stmt->fetch();
        if ($existingIssue) {
            if ($autosave) {
                throw new RuntimeException('A newsletter already exists for this date and edition. Open that newsletter to edit it.');
            }
            if (!can('edit', $currentUser)) { throw new RuntimeException('Editing this issue is not permitted.'); }
            if ($strict) { throw new RuntimeException('Newsletter already exists.'); }
            $id = (int) $existingIssue['id'];
        }
    }

    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM newsletters WHERE id = ?');
        $stmt->execute([$id]);
        $existing = $stmt->fetch() ?: [];
        if (!$existing) {
            throw new RuntimeException('This newsletter no longer exists. Please reload the editor.');
        }
    } else {
        $existing = [];
    }

    $headerImage = save_cropped_image($data['header_image_data'] ?? null, 'Header_' . $code . '.jpg', $date, $existing['header_image'] ?? null);
    $promotionImage = save_cropped_image($data['promotion_image_data'] ?? null, 'Promotion_' . $code . '.jpg', $date, $existing['promotion_image'] ?? null);
    $promotionWidth = (int) ($data['promotion_width'] ?? 200);
    $promotionHeight = (int) ($data['promotion_height'] ?? 170);

    $subjectTitle = trim((string) ($data['subject_title'] ?? ''));
    if ($subjectTitle === '') {
        $subjectTitle = 'Draft Newsletter ' . date('d M Y', strtotime($date)) . ' ' . ($edition === 'M' ? 'Morning' : 'Evening');
    }

    if ($id) {
        $stmt = $pdo->prepare('UPDATE newsletters SET newsletter_date=?, edition=?, subject_title=?, hidden_text=?, header_title=?, header_description=?, header_url=?, header_image=?, promotion_enabled=?, promotion_text=?, promotion_url=?, promotion_image=?, promotion_width=?, promotion_height=?, updated_by=? WHERE id=?');
        $stmt->execute([$date, $edition, $subjectTitle, $data['hidden_text'], $data['header_title'], $data['header_description'], $data['header_url'], $headerImage, (int) $data['promotion_enabled'], $data['promotion_text'], $data['promotion_url'], $promotionImage, $promotionWidth, $promotionHeight, (int) $currentUser['id'], $id]);
        $pdo->prepare('DELETE FROM pick_news WHERE newsletter_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM more_travel_news WHERE newsletter_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM category_news WHERE category_id IN (SELECT id FROM newsletter_categories WHERE newsletter_id=?)')->execute([$id]);
        $pdo->prepare('DELETE FROM newsletter_categories WHERE newsletter_id=?')->execute([$id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO newsletters (newsletter_date, edition, subject_title, hidden_text, header_title, header_description, header_url, header_image, promotion_enabled, promotion_text, promotion_url, promotion_image, promotion_width, promotion_height, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$date, $edition, $subjectTitle, $data['hidden_text'], $data['header_title'], $data['header_description'], $data['header_url'], $headerImage, (int) $data['promotion_enabled'], $data['promotion_text'], $data['promotion_url'], $promotionImage, $promotionWidth, $promotionHeight, (int) $currentUser['id'], (int) $currentUser['id']]);
        $id = (int) $pdo->lastInsertId();
    }

    if ($strict || array_key_exists('approved_at', $existing)) {
        $pdo->prepare('UPDATE newsletters SET approved_at=NULL, approved_by=NULL WHERE id=?')->execute([$id]);
    }

    $pickTitles = $data['pick_title'] ?? [];
    $pickDescriptions = $data['pick_description'] ?? [];
    $pickUrls = $data['pick_url'] ?? [];
    $pickImages = $data['pick_image_data'] ?? [];
    $pickExisting = $data['pick_existing_image'] ?? [];
    $stmt = $pdo->prepare('INSERT INTO pick_news (newsletter_id, sort_order, title, description, url, image) VALUES (?,?,?,?,?,?)');
    foreach ($pickTitles as $i => $title) {
        if (trim($title) === '') {
            continue;
        }
        $image = save_cropped_image($pickImages[$i] ?? null, 'news' . ($i + 1) . '_' . $code . '.jpg', $date, $pickExisting[$i] ?? null);
        $stmt->execute([$id, $i + 1, $title, $pickDescriptions[$i] ?? '', $pickUrls[$i] ?? '', $image]);
    }

    $stmt = $pdo->prepare('INSERT INTO more_travel_news (newsletter_id, sort_order, title, url) VALUES (?,?,?,?)');
    foreach (($data['more_title'] ?? []) as $i => $title) {
        if (trim($title) !== '') {
            $stmt->execute([$id, $i + 1, $title, $data['more_url'][$i] ?? '']);
        }
    }

    $categoryStmt = $pdo->prepare('INSERT INTO newsletter_categories (newsletter_id, sort_order, category_name, category_url) VALUES (?,?,?,?)');
    $itemStmt = $pdo->prepare('INSERT INTO category_news (category_id, sort_order, title, url) VALUES (?,?,?,?)');
    $categoryKeys = $data['category_key'] ?? [];
    $newsTitles = $data['category_news_title'] ?? [];
    $newsUrls = $data['category_news_url'] ?? [];
    foreach (($data['category_name'] ?? []) as $i => $name) {
        if (trim($name) === '') {
            continue;
        }
        $categoryStmt->execute([$id, $i + 1, $name, $data['category_url'][$i] ?? '']);
        $categoryId = (int) $pdo->lastInsertId();
        $key = $categoryKeys[$i] ?? '';
        foreach (($newsTitles[$key] ?? []) as $j => $title) {
            if (trim($title) !== '') {
                $itemStmt->execute([$categoryId, $j + 1, $title, $newsUrls[$key][$j] ?? '']);
            }
        }
    }

    return $id;
}
