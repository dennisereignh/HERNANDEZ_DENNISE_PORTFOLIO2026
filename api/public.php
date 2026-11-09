<?php
// Read-only content for the public pages (no private data, no messages).
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $pdo = db();

    $settings = [];
    foreach ($pdo->query('SELECT setting_key, setting_value FROM site_settings') as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }

    $projects = $pdo->query('SELECT id, title, category, description, tags, link, icon, image FROM projects WHERE visible = 1 ORDER BY sort_order, id')->fetchAll();
    $skills   = $pdo->query("SELECT name, percent, icon, kind FROM skills ORDER BY sort_order, id")->fetchAll();
    $edu      = $pdo->query('SELECT period, title, description FROM education ORDER BY sort_order, id')->fetchAll();

    echo json_encode([
        'ok' => true,
        'settings' => $settings,
        'projects' => $projects,
        'skills' => $skills,
        'education' => $edu,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
