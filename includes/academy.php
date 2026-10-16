<?php
/**
 * Mentor Academy: the members' learning library.
 *
 * Admins (Management Admin > Mentor Academy: views/admin/academy.php,
 * assets/js/views/admin-academy.js) keep the categories and upload the
 * learning materials: videos (a file, or a YouTube / Vimeo link), documents
 * (PDF, Word, Excel, PowerPoint, pictures), audio, articles and web links.
 * Hidden materials stay out of members' sight until they are published.
 *
 * Members (Backoffice > 11. Mentor Academy: views/backoffice/academy.php,
 * assets/js/views/bo-academy.js) browse the published ones by category,
 * open them and mark them completed. academy_progress keeps who opened and
 * finished what.
 *
 * Uploaded files live in storage/academy/, which the web server never serves
 * directly: academy-file.php hands them to signed-in users only. Cover
 * pictures go to uploads/academy/ (image_upload_store()).
 */
declare(strict_types=1);

const ACADEMY_FILE_DIR = 'storage/academy';
const ACADEMY_COVER_DIR = 'uploads/academy';
/** The largest file the app takes; the server's own upload limit may be lower (academy_upload_limit()). */
const ACADEMY_FILE_MAX = 500 * 1024 * 1024;
const ACADEMY_CATEGORIES_MAX = 50;
const ACADEMY_ARTICLE_MAX = 50000;
/** Materials added in the last … days count as new for members who haven't opened them. */
const ACADEMY_NEW_DAYS = 30;
const ACADEMY_KINDS = ['video' => 'Video', 'document' => 'Document', 'audio' => 'Audio', 'article' => 'Article', 'link' => 'Web link'];
/**
 * Files admins may upload, by extension: the kind of material each suits,
 * the MIME types PHP's fileinfo may report for it (anything else, such as a
 * web page renamed to .pdf, is refused), the type it is served with, and how
 * members see it: in the page (pdf, video, audio, image) or as a download.
 */
const ACADEMY_FILE_TYPES = [
    'mp4'  => ['kind' => 'video', 'type' => 'video/mp4', 'view' => 'video', 'mimes' => ['video/mp4', 'video/x-m4v', 'application/mp4']],
    'm4v'  => ['kind' => 'video', 'type' => 'video/mp4', 'view' => 'video', 'mimes' => ['video/mp4', 'video/x-m4v']],
    'webm' => ['kind' => 'video', 'type' => 'video/webm', 'view' => 'video', 'mimes' => ['video/webm']],
    'mp3'  => ['kind' => 'audio', 'type' => 'audio/mpeg', 'view' => 'audio', 'mimes' => ['audio/mpeg', 'audio/mp3', 'application/octet-stream']],
    'm4a'  => ['kind' => 'audio', 'type' => 'audio/mp4', 'view' => 'audio', 'mimes' => ['audio/mp4', 'audio/x-m4a', 'audio/m4a', 'video/mp4']],
    'wav'  => ['kind' => 'audio', 'type' => 'audio/wav', 'view' => 'audio', 'mimes' => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave', 'audio/wave']],
    'pdf'  => ['kind' => 'document', 'type' => 'application/pdf', 'view' => 'pdf', 'mimes' => ['application/pdf']],
    'jpg'  => ['kind' => 'document', 'type' => 'image/jpeg', 'view' => 'image', 'mimes' => ['image/jpeg']],
    'jpeg' => ['kind' => 'document', 'type' => 'image/jpeg', 'view' => 'image', 'mimes' => ['image/jpeg']],
    'png'  => ['kind' => 'document', 'type' => 'image/png', 'view' => 'image', 'mimes' => ['image/png']],
    'webp' => ['kind' => 'document', 'type' => 'image/webp', 'view' => 'image', 'mimes' => ['image/webp']],
    'gif'  => ['kind' => 'document', 'type' => 'image/gif', 'view' => 'image', 'mimes' => ['image/gif']],
    'docx' => ['kind' => 'document', 'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'view' => 'download',
        'mimes' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream']],
    'xlsx' => ['kind' => 'document', 'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'view' => 'download',
        'mimes' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream']],
    'pptx' => ['kind' => 'document', 'type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'view' => 'download',
        'mimes' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream']],
    'doc'  => ['kind' => 'document', 'type' => 'application/msword', 'view' => 'download',
        'mimes' => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream']],
    'xls'  => ['kind' => 'document', 'type' => 'application/vnd.ms-excel', 'view' => 'download',
        'mimes' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream']],
    'ppt'  => ['kind' => 'document', 'type' => 'application/vnd.ms-powerpoint', 'view' => 'download',
        'mimes' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream']],
];

/* =====================================================================
 * Limits and small helpers
 * =================================================================== */

/** A php.ini size ("40M") in bytes. */
function academy_ini_bytes(string $key): int
{
    $v = trim((string) ini_get($key));
    if ($v === '' || $v === '-1' || $v === '0') {
        return PHP_INT_MAX;
    }
    $n = (int) $v;
    return match (strtolower(substr($v, -1))) {
        'g' => $n * 1024 ** 3,
        'm' => $n * 1024 ** 2,
        'k' => $n * 1024,
        default => $n,
    };
}

/** The largest file an admin can upload here: the app's limit or the server's, whichever is lower. */
function academy_upload_limit(): int
{
    return max(1, min(ACADEMY_FILE_MAX, academy_ini_bytes('upload_max_filesize'), academy_ini_bytes('post_max_size') - 1024 * 1024));
}

/** "2.4 MB", "820 KB". */
function academy_size(int $bytes): string
{
    if ($bytes >= 1024 ** 3) {
        return rtrim(rtrim(number_format($bytes / 1024 ** 3, 1), '0'), '.') . ' GB';
    }
    if ($bytes >= 1024 ** 2) {
        return rtrim(rtrim(number_format($bytes / 1024 ** 2, 1), '0'), '.') . ' MB';
    }
    return max(1, (int) round($bytes / 1024)) . ' KB';
}

/** The file extensions an admin may upload for a kind of material, e.g. ".pdf, .docx". */
function academy_extensions(string $kind): array
{
    return array_keys(array_filter(ACADEMY_FILE_TYPES, fn ($t) => $t['kind'] === $kind));
}

/** A request whose upload was bigger than PHP accepts arrives with no fields at all. */
function academy_check_request_size(): void
{
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > 0 && $length > academy_ini_bytes('post_max_size') && empty($_POST)) {
        throw new InvalidArgumentException('That file is too big for this server (' . academy_size(academy_upload_limit()) . ' at most). Use a smaller file, or put a video on YouTube or Vimeo and paste its link.');
    }
}

/** YouTube's own picture of a video, or ''. */
function academy_video_thumb(string $url): string
{
    $embed = (string) site_video_embed($url);
    return preg_match('#youtube-nocookie\.com/embed/([A-Za-z0-9_-]{6,20})#', $embed, $m) ? 'https://i.ytimg.com/vi/' . $m[1] . '/hqdefault.jpg' : '';
}

/* =====================================================================
 * Categories
 * =================================================================== */

/** The categories in order, with how many materials each has (all, and published). */
function academy_categories(): array
{
    $rows = db_all('SELECT c.*, COUNT(m.id) AS total, COALESCE(SUM(m.published), 0) AS shown
        FROM academy_categories c LEFT JOIN academy_materials m ON m.category_id = c.id
        GROUP BY c.id ORDER BY c.sort_order, c.name');
    return array_map(fn ($r) => [
        'id' => $r['id'], 'name' => $r['name'], 'description' => (string) $r['description'], 'sortOrder' => (int) $r['sort_order'],
        'materials' => (int) $r['total'], 'published' => (int) $r['shown'],
    ], $rows);
}

function academy_category(string $id): array
{
    $c = db_one('SELECT * FROM academy_categories WHERE id = ?', [$id]);
    if (!$c) {
        throw new InvalidArgumentException('That category no longer exists. Refresh the page.');
    }
    return $c;
}

/** Add ($in['id'] empty) or rename a category. */
function academy_category_save(array $admin, array $in): array
{
    $id = trim((string) ($in['id'] ?? ''));
    $old = $id !== '' ? academy_category($id) : null;
    $name = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($in['name'] ?? '')) ?? ''), 0, 80);
    $description = mb_substr(trim((string) ($in['description'] ?? '')), 0, 300);
    if ($name === '') {
        throw new InvalidArgumentException('Please enter the category name.');
    }
    if (db_value('SELECT 1 FROM academy_categories WHERE name = ? AND id <> ?', [$name, $id])) {
        throw new InvalidArgumentException('There is already a category called ' . $name . '.');
    }
    if ($old) {
        db_run('UPDATE academy_categories SET name = ?, description = ? WHERE id = ?', [$name, $description, $id]);
        add_audit('Mentor Academy category renamed: ' . $old['name'] . ($old['name'] !== $name ? ' → ' . $name : ''), $admin['id']);
    } else {
        if ((int) db_value('SELECT COUNT(*) FROM academy_categories') >= ACADEMY_CATEGORIES_MAX) {
            throw new InvalidArgumentException('Mentor Academy has room for ' . ACADEMY_CATEGORIES_MAX . ' categories. Delete an empty one first.');
        }
        $base = substr(str_replace('-', '_', card_slug_clean($name)) ?: 'category', 0, 24);
        do {
            $id = 'acat_' . $base . '_' . bin2hex(random_bytes(2));
        } while (db_value('SELECT 1 FROM academy_categories WHERE id = ?', [$id]));
        $sort = (int) db_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM academy_categories');
        db_run('INSERT INTO academy_categories (id, name, description, sort_order) VALUES (?, ?, ?, ?)', [$id, $name, $description, $sort]);
        add_audit('Mentor Academy category added: ' . $name, $admin['id']);
    }
    $c = academy_category($id);
    return ['id' => $c['id'], 'name' => $c['name'], 'description' => (string) $c['description']];
}

/** Move a category one place up ($dir -1) or down (+1). */
function academy_category_move(array $admin, string $id, int $dir): void
{
    academy_category($id);
    $ids = array_column(db_all('SELECT id FROM academy_categories ORDER BY sort_order, name'), 'id');
    $i = array_search($id, $ids, true);
    $j = $i + ($dir < 0 ? -1 : 1);
    if ($i === false || $j < 0 || $j >= count($ids)) {
        return;
    }
    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    foreach ($ids as $n => $cid) {
        db_run('UPDATE academy_categories SET sort_order = ? WHERE id = ?', [$n + 1, $cid]);
    }
}

/** Delete an empty category; one with materials must be emptied first. Returns its name. */
function academy_category_delete(array $admin, string $id): string
{
    $c = academy_category($id);
    $n = (int) db_value('SELECT COUNT(*) FROM academy_materials WHERE category_id = ?', [$id]);
    if ($n > 0) {
        throw new InvalidArgumentException($c['name'] . ' still has ' . $n . ' material' . ($n === 1 ? '' : 's') . '. Move ' . ($n === 1 ? 'it' : 'them') . ' to another category or delete ' . ($n === 1 ? 'it' : 'them') . ' first.');
    }
    db_run('DELETE FROM academy_categories WHERE id = ?', [$id]);
    add_audit('Mentor Academy category deleted: ' . $c['name'], $admin['id']);
    return (string) $c['name'];
}

/* =====================================================================
 * Materials
 * =================================================================== */

/**
 * A material for the pages. $progress: the member's academy_progress row
 * (last_viewed_at, completed_at), when there is one.
 */
function academy_material_to_array(array $r, ?array $progress = null): array
{
    $file = null;
    if ($r['file_path'] !== null && $r['file_path'] !== '') {
        $ext = strtolower(pathinfo((string) $r['file_path'], PATHINFO_EXTENSION));
        $file = [
            'url' => base_path() . '/academy-file.php?id=' . rawurlencode($r['id']),
            'download' => base_path() . '/academy-file.php?id=' . rawurlencode($r['id']) . '&download=1',
            'name' => (string) $r['file_name'], 'type' => (string) $r['file_type'], 'size' => (int) $r['file_size'],
            'sizeText' => academy_size((int) $r['file_size']), 'ext' => $ext, 'view' => ACADEMY_FILE_TYPES[$ext]['view'] ?? 'download',
        ];
    }
    $url = (string) ($r['url'] ?? '');
    $created = to_ms($r['created_at']);
    return [
        'id' => $r['id'], 'categoryId' => $r['category_id'], 'title' => $r['title'], 'description' => (string) $r['description'],
        'kind' => $r['kind'], 'kindLabel' => ACADEMY_KINDS[$r['kind']] ?? $r['kind'],
        'url' => $r['kind'] === 'link' ? $url : '', 'videoUrl' => $r['kind'] === 'video' ? $url : '',
        'embed' => $r['kind'] === 'video' && $url !== '' ? (string) site_video_embed($url) : '',
        'body' => $r['kind'] === 'article' ? (string) $r['body'] : '',
        'file' => $file,
        'cover' => $r['cover'] ? media_url($r['cover']) : ($r['kind'] === 'video' && $url !== '' ? academy_video_thumb($url) : ''),
        'coverPath' => (string) ($r['cover'] ?? ''),
        'minutes' => $r['minutes'] === null ? null : (int) $r['minutes'], 'sortOrder' => (int) $r['sort_order'],
        'published' => (bool) $r['published'], 'createdAt' => $created, 'updatedAt' => to_ms($r['updated_at']),
        'isNew' => $created >= (time() - ACADEMY_NEW_DAYS * 86400) * 1000 && empty($progress['last_viewed_at']),
        'viewedAt' => !empty($progress['last_viewed_at']) ? to_ms($progress['last_viewed_at']) : null,
        'completedAt' => !empty($progress['completed_at']) ? to_ms($progress['completed_at']) : null,
    ];
}

/** Every material for the admin page, hidden ones too, with how many members opened and completed each. */
function academy_admin_materials(): array
{
    $rows = db_all('SELECT m.*, COUNT(p.user_id) AS opened, COUNT(p.completed_at) AS completed
        FROM academy_materials m JOIN academy_categories c ON c.id = m.category_id
        LEFT JOIN academy_progress p ON p.material_id = m.id
        GROUP BY m.id ORDER BY c.sort_order, c.name, m.sort_order, m.created_at');
    return array_map(fn ($r) => academy_material_to_array($r) + ['opened' => (int) $r['opened'], 'completed' => (int) $r['completed']], $rows);
}

/** Totals for the admin page. */
function academy_stats(): array
{
    $m = db_one('SELECT COUNT(*) AS n, COALESCE(SUM(published), 0) AS shown FROM academy_materials');
    return [
        'materials' => (int) $m['n'], 'published' => (int) $m['shown'],
        'categories' => (int) db_value('SELECT COUNT(*) FROM academy_categories'),
        'learners' => (int) db_value('SELECT COUNT(DISTINCT user_id) FROM academy_progress'),
        'completions' => (int) db_value('SELECT COUNT(*) FROM academy_progress WHERE completed_at IS NOT NULL'),
    ];
}

function academy_material(string $id): array
{
    $m = db_one('SELECT * FROM academy_materials WHERE id = ?', [$id]);
    if (!$m) {
        throw new InvalidArgumentException('That material no longer exists. Refresh the page.');
    }
    return $m;
}

/**
 * Add ($in['id'] empty) or edit a material. $file: the uploaded file
 * ($_FILES['file']) for a video, document or audio; $cover: an optional
 * picture ($_FILES['cover']). A video is a file or a YouTube / Vimeo link:
 * a newly chosen file wins, then the link, then the file it already had.
 */
function academy_material_save(array $admin, array $in, ?array $file, ?array $cover): array
{
    academy_check_request_size();
    $id = trim((string) ($in['id'] ?? ''));
    $old = $id !== '' ? academy_material($id) : null;
    $title = mb_substr(trim((string) ($in['title'] ?? '')), 0, 150);
    if ($title === '') {
        throw new InvalidArgumentException('Please enter a title.');
    }
    $category = academy_category(trim((string) ($in['categoryId'] ?? '')))['id'];
    $kind = (string) ($in['kind'] ?? '');
    if (!isset(ACADEMY_KINDS[$kind])) {
        throw new InvalidArgumentException('Choose what kind of material it is: video, document, audio, article or web link.');
    }
    $description = mb_substr(trim((string) ($in['description'] ?? '')), 0, 2000);
    $minutesIn = trim((string) ($in['minutes'] ?? ''));
    $minutes = $minutesIn === '' ? null : shop_whole($minutesIn, 'minutes', 1000);
    $minutes = $minutes === 0 ? null : $minutes;
    $sortIn = trim((string) ($in['sortOrder'] ?? ''));
    $sort = $sortIn !== '' && preg_match('/^-?\d{1,6}$/', $sortIn) ? (int) $sortIn
        : ($old && $old['category_id'] === $category ? (int) $old['sort_order'] : (int) db_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM academy_materials WHERE category_id = ?', [$category]));
    $published = !empty($in['published']) && $in['published'] !== 'false' && $in['published'] !== '0';
    $hasFile = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $hasCover = $cover !== null && ($cover['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    // What the material is made of, by kind; the old file stays only if it still suits.
    $oldExt = $old && $old['file_path'] ? strtolower(pathinfo((string) $old['file_path'], PATHINFO_EXTENSION)) : '';
    $oldFileFits = $oldExt !== '' && (ACADEMY_FILE_TYPES[$oldExt]['kind'] ?? '') === $kind;
    $url = '';
    $body = null;
    $useFile = false;
    if ($kind === 'video') {
        $url = trim((string) ($in['videoUrl'] ?? ''));
        if ($hasFile) {
            $url = '';
            $useFile = true;
        } elseif ($url !== '') {
            if (site_video_embed($url) === null) {
                throw new InvalidArgumentException('Paste a YouTube or Vimeo link, e.g. https://www.youtube.com/watch?v=…, or upload the video file.');
            }
        } elseif ($oldFileFits) {
            $useFile = true;
        } else {
            throw new InvalidArgumentException('Upload the video file (' . implode(', ', array_map(fn ($e) => '.' . $e, academy_extensions('video'))) . ') or paste a YouTube or Vimeo link.');
        }
    } elseif ($kind === 'document' || $kind === 'audio') {
        if (!$hasFile && !$oldFileFits) {
            throw new InvalidArgumentException('Please choose the ' . ($kind === 'audio' ? 'audio' : 'document') . ' file (' . implode(', ', array_map(fn ($e) => '.' . $e, academy_extensions($kind))) . ').');
        }
        $useFile = true;
    } elseif ($kind === 'article') {
        $body = trim(str_replace("\r\n", "\n", (string) ($in['body'] ?? '')));
        if ($body === '') {
            throw new InvalidArgumentException('Please write the article.');
        }
        if (mb_strlen($body) > ACADEMY_ARTICLE_MAX) {
            throw new InvalidArgumentException('The article is too long (' . number_format(ACADEMY_ARTICLE_MAX) . ' characters at most). Split it into parts.');
        }
    } else {
        $url = safe_url(mb_substr(trim((string) ($in['url'] ?? '')), 0, 500), false);
        if ($url === '') {
            throw new InvalidArgumentException('Please enter the web address, starting with https://.');
        }
    }

    // New uploads are checked and stored before anything is saved.
    $stored = $hasFile && $useFile ? academy_store_file($file, $kind) : null;
    try {
        $coverPath = $hasCover ? image_upload_store($cover, ACADEMY_COVER_DIR) : ($old && empty($in['removeCover']) ? $old['cover'] : null);
    } catch (Throwable $e) {
        if ($stored) {
            academy_delete_file($stored['path']);
        }
        throw $e;
    }
    $fileRow = $stored ?? ($useFile && $old ? ['path' => $old['file_path'], 'name' => $old['file_name'], 'type' => $old['file_type'], 'size' => $old['file_size']]
        : ['path' => null, 'name' => null, 'type' => null, 'size' => null]);

    if ($old) {
        db_run('UPDATE academy_materials SET category_id = ?, title = ?, description = ?, kind = ?, file_path = ?, file_name = ?, file_type = ?, file_size = ?, url = ?, body = ?, cover = ?, minutes = ?, sort_order = ?, published = ? WHERE id = ?',
            [$category, $title, $description, $kind, $fileRow['path'], $fileRow['name'], $fileRow['type'], $fileRow['size'], $url !== '' ? $url : null, $body, $coverPath, $minutes, $sort, $published ? 1 : 0, $id]);
        if ($old['file_path'] && $old['file_path'] !== $fileRow['path']) {
            academy_delete_file((string) $old['file_path']);
        }
        if ($old['cover'] && $old['cover'] !== $coverPath) {
            academy_delete_cover((string) $old['cover']);
        }
        add_audit('Mentor Academy material updated: ' . $title . ' (' . $id . ')', $admin['id']);
    } else {
        $id = 'am_' . bin2hex(random_bytes(6));
        db_run('INSERT INTO academy_materials (id, category_id, title, description, kind, file_path, file_name, file_type, file_size, url, body, cover, minutes, sort_order, published, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $category, $title, $description, $kind, $fileRow['path'], $fileRow['name'], $fileRow['type'], $fileRow['size'], $url !== '' ? $url : null, $body, $coverPath, $minutes, $sort, $published ? 1 : 0, $admin['id']]);
        add_audit('Mentor Academy material added: ' . $title . ' (' . $id . ')', $admin['id']);
    }
    return academy_material_to_array(academy_material($id));
}

/** Publish a material to members, or hide it. */
function academy_material_set_published(array $admin, string $id, bool $published): array
{
    $m = academy_material($id);
    db_run('UPDATE academy_materials SET published = ? WHERE id = ?', [$published ? 1 : 0, $id]);
    add_audit('Mentor Academy material ' . ($published ? 'published' : 'hidden') . ': ' . $m['title'] . ' (' . $id . ')', $admin['id']);
    return academy_material_to_array(academy_material($id));
}

/** Delete a material with its file, cover and members' progress on it. Returns its title. */
function academy_material_delete(array $admin, string $id): string
{
    $m = academy_material($id);
    db_run('DELETE FROM academy_materials WHERE id = ?', [$id]); // progress goes with it (ON DELETE CASCADE)
    if ($m['file_path']) {
        academy_delete_file((string) $m['file_path']);
    }
    if ($m['cover']) {
        academy_delete_cover((string) $m['cover']);
    }
    add_audit('Mentor Academy material deleted: ' . $m['title'] . ' (' . $id . ')', $admin['id']);
    return (string) $m['title'];
}

/* =====================================================================
 * Files
 * =================================================================== */

/**
 * Check an uploaded file ($_FILES entry) for a kind of material and keep it
 * in storage/academy/ under a random name. Returns its path, own name, MIME
 * type and size.
 */
function academy_store_file(array $file, string $kind): array
{
    $limit = academy_upload_limit();
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new InvalidArgumentException('That file is too big for this server (' . academy_size($limit) . ' at most).');
    }
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        throw new InvalidArgumentException('The file didn\'t upload. Please choose it again.');
    }
    $size = (int) $file['size'];
    if ($size < 1) {
        throw new InvalidArgumentException('That file is empty.');
    }
    if ($size > $limit) {
        throw new InvalidArgumentException('That file is too big (' . academy_size($limit) . ' at most).');
    }
    // The file's own name, for downloads: no folders, no control characters.
    $name = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '', basename(str_replace('\\', '/', (string) ($file['name'] ?? '')))) ?? '';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $spec = ACADEMY_FILE_TYPES[$ext] ?? null;
    $allowed = implode(', ', array_map(fn ($e) => '.' . $e, academy_extensions($kind)));
    if (!$spec || $spec['kind'] !== $kind) {
        throw new InvalidArgumentException('For ' . ($kind === 'audio' ? 'audio' : 'a ' . strtolower(ACADEMY_KINDS[$kind])) . ', upload one of these: ' . $allowed . '.');
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, $spec['mimes'], true)) {
        throw new InvalidArgumentException('That file doesn\'t look like a real .' . $ext . ' file. Save it again from the program that made it and upload that.');
    }
    if ($spec['view'] === 'image' && !@getimagesize($file['tmp_name'])) {
        throw new InvalidArgumentException('That picture can\'t be read. Upload a JPEG, PNG, WebP or GIF.');
    }
    $dir = APP_ROOT . '/' . ACADEMY_FILE_DIR;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create ' . ACADEMY_FILE_DIR);
    }
    $path = ACADEMY_FILE_DIR . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], APP_ROOT . '/' . $path)) {
        throw new RuntimeException('Could not save ' . $path);
    }
    $nameBase = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 180);
    return ['path' => $path, 'name' => ($nameBase !== '' ? $nameBase : 'material') . '.' . $ext, 'type' => $spec['type'], 'size' => $size];
}

/** Whether $path is a file academy_store_file() kept. */
function academy_file_is_ours(string $path): bool
{
    return preg_match('#^' . preg_quote(ACADEMY_FILE_DIR, '#') . '/[0-9a-f]{24}\.[a-z0-9]{2,5}$#', $path) === 1;
}

function academy_delete_file(string $path): void
{
    if (academy_file_is_ours($path) && !db_value('SELECT 1 FROM academy_materials WHERE file_path = ?', [$path]) && is_file(APP_ROOT . '/' . $path)) {
        @unlink(APP_ROOT . '/' . $path);
    }
}

function academy_delete_cover(string $path): void
{
    if (image_upload_is_ours($path, ACADEMY_COVER_DIR) && !db_value('SELECT 1 FROM academy_materials WHERE cover = ?', [$path]) && is_file(APP_ROOT . '/' . $path)) {
        @unlink(APP_ROOT . '/' . $path);
    }
}

/** The material behind academy-file.php?id=…, if $user may open it (published, or the viewer is an admin). */
function academy_file_for(?array $user, string $id): ?array
{
    if (!$user || ($user['status'] ?? 'active') !== 'active') {
        return null;
    }
    $m = db_one('SELECT * FROM academy_materials WHERE id = ?', [$id]);
    if (!$m || !$m['file_path'] || (!$m['published'] && !is_admin($user)) || !academy_file_is_ours((string) $m['file_path']) || !is_file(APP_ROOT . '/' . $m['file_path'])) {
        return null;
    }
    return $m;
}

/* =====================================================================
 * Members: the library and their progress
 * =================================================================== */

/**
 * The published materials by category for a member, with their progress:
 * ['categories' => [... 'materials' => [...]], 'total', 'completed', 'resume' => the
 * material they opened last and haven't finished, or null].
 */
function academy_library(string $userId): array
{
    $progress = [];
    foreach (db_all('SELECT material_id, last_viewed_at, completed_at FROM academy_progress WHERE user_id = ?', [$userId]) as $p) {
        $progress[$p['material_id']] = $p;
    }
    $byCategory = [];
    foreach (db_all('SELECT m.* FROM academy_materials m WHERE m.published = 1 ORDER BY m.sort_order, m.created_at') as $r) {
        $byCategory[$r['category_id']][] = academy_material_to_array($r, $progress[$r['id']] ?? null);
    }
    $categories = [];
    $total = 0;
    $completed = 0;
    $resume = null;
    foreach (academy_categories() as $c) {
        $items = $byCategory[$c['id']] ?? [];
        if (!$items) {
            continue;
        }
        $done = count(array_filter($items, fn ($m) => $m['completedAt'] !== null));
        $categories[] = ['id' => $c['id'], 'name' => $c['name'], 'description' => $c['description'], 'materials' => $items, 'completed' => $done];
        $total += count($items);
        $completed += $done;
        foreach ($items as $m) {
            if ($m['viewedAt'] !== null && $m['completedAt'] === null && ($resume === null || $m['viewedAt'] > $resume['viewedAt'])) {
                $resume = $m;
            }
        }
    }
    return ['categories' => $categories, 'total' => $total, 'completed' => $completed, 'resume' => $resume];
}

/** How many published materials from the last 30 days the member hasn't opened (the tab's "new" badge). */
function academy_new_count(string $userId): int
{
    return (int) db_value('SELECT COUNT(*) FROM academy_materials m LEFT JOIN academy_progress p ON p.material_id = m.id AND p.user_id = ?
        WHERE m.published = 1 AND m.created_at >= NOW() - INTERVAL ' . ACADEMY_NEW_DAYS . ' DAY AND p.user_id IS NULL', [$userId]);
}

/** A published material a member may open (admins may open hidden ones too). */
function academy_member_material(array $user, string $id): array
{
    $m = db_one('SELECT * FROM academy_materials WHERE id = ?', [$id]);
    if (!$m || (!$m['published'] && !is_admin($user))) {
        throw new InvalidArgumentException('That lesson is no longer available. Refresh the page.');
    }
    return $m;
}

/** The member opened a material. */
function academy_record_view(array $user, string $id): void
{
    academy_member_material($user, $id);
    db_run('INSERT INTO academy_progress (user_id, material_id) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE views = views + 1, last_viewed_at = NOW()', [$user['id'], $id]);
}

/** Mark a material completed ($done) or not yet. Returns when it was completed (ms), or null. */
function academy_set_completed(array $user, string $id, bool $done): ?int
{
    academy_member_material($user, $id);
    db_run('INSERT INTO academy_progress (user_id, material_id, completed_at) VALUES (?, ?, ' . ($done ? 'NOW()' : 'NULL') . ')
            ON DUPLICATE KEY UPDATE completed_at = ' . ($done ? 'COALESCE(completed_at, NOW())' : 'NULL'), [$user['id'], $id]);
    $at = db_value('SELECT completed_at FROM academy_progress WHERE user_id = ? AND material_id = ?', [$user['id'], $id]);
    return $at ? to_ms($at) : null;
}
