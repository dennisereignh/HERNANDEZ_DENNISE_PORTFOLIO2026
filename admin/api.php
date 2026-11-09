<?php
// All admin actions go through here. Requires a logged-in session + CSRF token.
require __DIR__ . '/../api/db.php';
require __DIR__ . '/../api/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function out(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/* ---------- validation helpers (throw RuntimeException => 422) ---------- */
function str_field(string $key, int $max, bool $required = false, string $label = ''): string {
    $label = $label ?: ucfirst(str_replace('_', ' ', $key));
    $v = trim((string)($_POST[$key] ?? ''));
    if ($required && $v === '') { throw new RuntimeException("$label is required."); }
    if (mb_strlen($v) > $max) { throw new RuntimeException("$label must be $max characters or fewer."); }
    return $v;
}
function url_value(string $v, string $label): string {
    if ($v !== '' && !preg_match('#^https?://[^\s<>"\']+$#i', $v)) {
        throw new RuntimeException("$label must start with http:// or https://");
    }
    return $v;
}
function link_value(string $v): string {
    if ($v === '') { return ''; }
    if (preg_match('#^https?://[^\s<>"\']+$#i', $v)) { return $v; }
    if (preg_match('#^[A-Za-z0-9_\-/]+\.(html|php)$#', $v)) { return $v; }
    throw new RuntimeException('Link must be a page name like web.html or a full https:// address.');
}
function icon_value(string $key = 'icon', string $fallback = 'folder'): string {
    $v = (string)($_POST[$key] ?? '');
    return in_array($v, ICON_KEYS, true) ? $v : $fallback;
}
function int_value(string $key, int $min, int $max, ?int $default = null): int {
    $raw = trim((string)($_POST[$key] ?? ''));
    if ($raw === '') {
        if ($default !== null) { return $default; }
        throw new RuntimeException(ucfirst(str_replace('_', ' ', $key)) . ' is required.');
    }
    if (!preg_match('/^-?\d+$/', $raw) || (int)$raw < $min || (int)$raw > $max) {
        throw new RuntimeException(ucfirst(str_replace('_', ' ', $key)) . " must be a number from $min to $max.");
    }
    return (int)$raw;
}
function next_sort(PDO $pdo, string $table): int {
    $allowed = ['projects', 'skills', 'education'];
    if (!in_array($table, $allowed, true)) { throw new RuntimeException('Bad table.'); }
    return (int)$pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM `$table`")->fetchColumn();
}

/* ---------- image upload ---------- */
function upload_image(string $field): ?string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) { return null; }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) { throw new RuntimeException('Upload failed. The image must be 3 MB or smaller.'); }
    if ($f['size'] > 3 * 1024 * 1024) { throw new RuntimeException('Image must be 3 MB or smaller.'); }
    $info = @getimagesize($f['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($types[$info[2]])) { throw new RuntimeException('Only JPG, PNG, WEBP or GIF images are allowed.'); }
    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) { throw new RuntimeException('Could not create the uploads folder.'); }
    $name = bin2hex(random_bytes(8)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) { throw new RuntimeException('Could not save the image. Check folder permissions.'); }
    return 'uploads/' . $name;
}
function delete_upload(?string $path): void {
    if ($path && preg_match('#^uploads/[a-f0-9]{16}\.(jpg|png|webp|gif)$#', $path)) {
        @unlink(dirname(__DIR__) . '/' . $path);
    }
}

/* ---------- request gate ---------- */
start_admin_session();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { out(405, ['ok' => false, 'error' => 'POST only']); }
if (!admin_logged_in()) { out(401, ['ok' => false, 'error' => 'Session expired. Please log in again.']); }
if (!csrf_ok($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) { out(403, ['ok' => false, 'error' => 'Security check failed. Refresh the page.']); }

$action = (string)($_POST['action'] ?? '');

try {
    $pdo = db();

    switch ($action) {

    case 'load': {
        $settings = [];
        foreach ($pdo->query('SELECT setting_key, setting_value FROM site_settings') as $r) {
            $settings[$r['setting_key']] = $r['setting_value'];
        }
        $messages = $pdo->query('SELECT id, name, email, message, is_read, created_at FROM contact_messages ORDER BY created_at DESC, id DESC LIMIT 500')->fetchAll();
        foreach ($messages as &$m) { $m['is_read'] = (int)$m['is_read']; }
        unset($m);
        $projects = $pdo->query('SELECT * FROM projects ORDER BY sort_order, id')->fetchAll();
        foreach ($projects as &$p) { $p['visible'] = (int)$p['visible']; }
        unset($p);
        out(200, [
            'ok' => true,
            'user' => $_SESSION['admin_user'] ?? '',
            'settings' => $settings,
            'projects' => $projects,
            'skills' => $pdo->query('SELECT * FROM skills ORDER BY sort_order, id')->fetchAll(),
            'education' => $pdo->query('SELECT * FROM education ORDER BY sort_order, id')->fetchAll(),
            'messages' => $messages,
        ]);
    }

    case 'save': {
        $entity = (string)($_POST['entity'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        if ($entity === 'projects') {
            $title = str_field('title', 150, true, 'Title');
            $cats = array_values(array_intersect(preg_split('/\s+/', trim((string)($_POST['category'] ?? ''))) ?: [], array_keys(CATEGORIES)));
            $category = implode(' ', $cats);
            $desc = str_field('description', 500, false, 'Description');
            $tagList = array_filter(array_map('trim', explode(',', (string)($_POST['tags'] ?? ''))), 'strlen');
            $tagList = array_slice(array_unique($tagList), 0, 8);
            foreach ($tagList as $t) { if (mb_strlen($t) > 30) { throw new RuntimeException('Each tag must be 30 characters or fewer.'); } }
            $tags = implode(', ', $tagList);
            $link = link_value(str_field('link', 255, false, 'Link'));
            $icon = icon_value();
            $sort = $id ? 0 : next_sort($pdo, 'projects');
            $visible = !empty($_POST['visible']) ? 1 : 0;

            $old = null;
            if ($id) {
                $st = $pdo->prepare('SELECT image FROM projects WHERE id = ?');
                $st->execute([$id]);
                $row = $st->fetch();
                if (!$row) { out(404, ['ok' => false, 'error' => 'Project not found.']); }
                $old = $row['image'];
            }
            $image = $old;
            $new = upload_image('image');
            if ($new) { $image = $new; delete_upload($old); }
            elseif (!empty($_POST['remove_image'])) { delete_upload($old); $image = null; }

            if ($id) {
                $pdo->prepare('UPDATE projects SET title=?, category=?, description=?, tags=?, link=?, icon=?, image=?, visible=? WHERE id=?')
                    ->execute([$title, $category, $desc, $tags, $link, $icon, $image, $visible, $id]);
            } else {
                $pdo->prepare('INSERT INTO projects (title, category, description, tags, link, icon, image, sort_order, visible) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$title, $category, $desc, $tags, $link, $icon, $image, $sort, $visible]);
            }
            out(200, ['ok' => true]);
        }

        if ($entity === 'skills') {
            $kind = ($_POST['kind'] ?? '') === 'tool' ? 'tool' : 'skill';
            $name = str_field('name', 100, true, 'Name');
            $percent = $kind === 'skill' ? int_value('percent', 0, 100) : 0;
            $icon = icon_value('icon', 'code');
            $sort = $id ? 0 : next_sort($pdo, 'skills');
            if ($id) {
                $pdo->prepare('UPDATE skills SET name=?, percent=?, icon=? WHERE id=? AND kind=?')
                    ->execute([$name, $percent, $icon, $id, $kind]);
            } else {
                $pdo->prepare('INSERT INTO skills (kind, name, percent, icon, sort_order) VALUES (?,?,?,?,?)')
                    ->execute([$kind, $name, $percent, $icon, $sort]);
            }
            out(200, ['ok' => true]);
        }

        if ($entity === 'education') {
            $period = str_field('period', 60, false, 'Period');
            $title = str_field('title', 150, true, 'Title');
            $desc = str_field('description', 300, false, 'Description');
            $sort = $id ? 0 : next_sort($pdo, 'education');
            if ($id) {
                $pdo->prepare('UPDATE education SET period=?, title=?, description=? WHERE id=?')
                    ->execute([$period, $title, $desc, $id]);
            } else {
                $pdo->prepare('INSERT INTO education (period, title, description, sort_order) VALUES (?,?,?,?)')
                    ->execute([$period, $title, $desc, $sort]);
            }
            out(200, ['ok' => true]);
        }

        out(400, ['ok' => false, 'error' => 'Unknown item type.']);
    }

    case 'delete': {
        $entity = (string)($_POST['entity'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        $tables = ['projects' => 'projects', 'skills' => 'skills', 'education' => 'education'];
        if (!isset($tables[$entity]) || $id < 1) { out(400, ['ok' => false, 'error' => 'Bad request.']); }
        if ($entity === 'projects') {
            $st = $pdo->prepare('SELECT image FROM projects WHERE id = ?');
            $st->execute([$id]);
            $row = $st->fetch();
            if ($row) { delete_upload($row['image']); }
        }
        $pdo->prepare('DELETE FROM `' . $tables[$entity] . '` WHERE id = ?')->execute([$id]);
        out(200, ['ok' => true]);
    }

    case 'save_settings': {
        $text = [
            'full_name' => [100, 'Name'], 'footer_tagline' => [150, 'Footer tagline'],
            'hero_eyebrow' => [100, 'Hero label'], 'hero_headline' => [200, 'Hero headline'], 'hero_text' => [600, 'Hero text'],
            'home_about_heading' => [100, 'Home about heading'], 'home_about_text' => [800, 'Home about text'],
            'about_heading' => [100, 'About heading'], 'about_p1' => [800, 'About paragraph 1'], 'about_p2' => [800, 'About paragraph 2'],
            'phone' => [30, 'Phone'], 'facebook_label' => [100, 'Facebook label'], 'instagram_label' => [100, 'Instagram label'],
        ];
        $vals = [];
        foreach ($text as $k => [$max, $label]) { $vals[$k] = str_field($k, $max, false, $label); }
        if ($vals['full_name'] === '') { throw new RuntimeException('Name is required.'); }
        $email = str_field('email', 150, false, 'Email');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Please enter a valid email.'); }
        $vals['email'] = $email;
        $vals['facebook_url'] = url_value(str_field('facebook_url', 255, false, 'Facebook link'), 'Facebook link');
        $vals['instagram_url'] = url_value(str_field('instagram_url', 255, false, 'Instagram link'), 'Instagram link');

        $old = (string)($pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'profile_photo'")->fetchColumn() ?: '');
        $photo = $old;
        $new = upload_image('profile_photo');
        if ($new) { $photo = $new; delete_upload($old); }
        elseif (!empty($_POST['remove_profile_photo'])) { delete_upload($old); $photo = ''; }
        $vals['profile_photo'] = $photo;

        $pdo->beginTransaction();
        $st = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($vals as $k => $v) { $st->execute([$k, $v]); }
        $pdo->commit();
        out(200, ['ok' => true]);
    }

    case 'msg_read': {
        $id = (int)($_POST['id'] ?? 0);
        $val = !empty($_POST['value']) ? 1 : 0;
        $pdo->prepare('UPDATE contact_messages SET is_read = ? WHERE id = ?')->execute([$val, $id]);
        out(200, ['ok' => true]);
    }

    case 'msg_delete': {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([$id]);
        out(200, ['ok' => true]);
    }

    case 'change_password': {
        $current = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        $confirm = (string)($_POST['confirm'] ?? '');
        if (strlen($new) < 8) { throw new RuntimeException('New password must be at least 8 characters.'); }
        if ($new !== $confirm) { throw new RuntimeException('New passwords do not match.'); }
        $st = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
        $st->execute([(int)$_SESSION['admin_id']]);
        $row = $st->fetch();
        if (!$row || !password_verify($current, $row['password_hash'])) { throw new RuntimeException('Current password is incorrect.'); }
        $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$_SESSION['admin_id']]);
        session_regenerate_id(true);
        out(200, ['ok' => true]);
    }

    case 'logout': {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
        out(200, ['ok' => true]);
    }

    default:
        out(400, ['ok' => false, 'error' => 'Unknown action.']);
    }
} catch (RuntimeException $ex) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    out(422, ['ok' => false, 'error' => $ex->getMessage()]);
} catch (Throwable $ex) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('admin/api.php: ' . $ex->getMessage());
    $body = ['ok' => false, 'error' => 'Server error. Please try again.'];
    if (DEBUG) { $body['error'] .= ' (' . $ex->getMessage() . ')'; }
    out(500, $body);
}
