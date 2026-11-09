<?php
// Saves a Contact-form message into MySQL (table: contact_messages).
// This file lives in api/ next to config.php and db.php.
// The form in contact.html reaches it through script.js
// (FORM_ENDPOINT = 'api/contact.php').
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

function reply(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(405, ['ok' => false, 'error' => 'POST only']);
}

// Accept JSON (from script.js) or a normal form post
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) { $in = $_POST; }

$name    = trim((string)($in['name'] ?? ''));
$email   = trim((string)($in['email'] ?? ''));
$message = trim((string)($in['message'] ?? ''));

if (!empty($in['website'])) {                      // honeypot: bots fill this in
    reply(200, ['ok' => true]);
}
if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    reply(422, ['ok' => false, 'error' => 'Please enter your name.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    reply(422, ['ok' => false, 'error' => 'Please enter a valid email.']);
}
if (mb_strlen($message) < 10 || mb_strlen($message) > 3000) {
    reply(422, ['ok' => false, 'error' => 'Message must be 10 to 3000 characters.']);
}

// Connect through the shared helper in db.php. If the database itself
// was never imported, create it once and then connect again, so the
// form works even without running portfolio_db.sql first.
function connect_with_database(): PDO {
    try {
        return db();
    } catch (PDOException $e) {
        $code = $e->errorInfo[1] ?? null;
        if ($code !== 1049 && strpos($e->getMessage(), 'Unknown database') === false) {
            throw $e;
        }
        $bootstrap = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $bootstrap->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        return db();
    }
}

// Plain-English hint for the most common connection failures, shown
// on the Contact page while DEBUG is true in config.php.
function connection_hint(Throwable $e): string {
    $code = ($e instanceof PDOException) ? ($e->errorInfo[1] ?? null) : null;
    if ($code === 1045) {
        return 'Access denied: the password in api/config.php (DB_PASS) does not match '
             . 'the MySQL root password on this computer, or the site is reaching a '
             . 'different MySQL server. Check the port at the top of phpMyAdmin '
             . '(Server: 127.0.0.1:3307) and set DB_PORT to match it.';
    }
    if ($code === 2002) {
        return 'Cannot reach MySQL at ' . DB_HOST . ':' . DB_PORT . '. Start MySQL in the '
             . 'XAMPP control panel and check the port at the top of phpMyAdmin.';
    }
    if ($code === 1049) {
        return 'Database ' . DB_NAME . ' does not exist and could not be created. '
             . 'Import portfolio_db.sql in phpMyAdmin first.';
    }
    return '';
}

try {
    $pdo = connect_with_database();
    // Make sure the contact_messages table exists (no import needed).
    ensure_schema($pdo);
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    // Simple spam limit: max 5 messages per IP per hour
    $q = $pdo->prepare('SELECT COUNT(*) FROM contact_messages WHERE ip_address = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
    $q->execute([$ip]);
    if ((int)$q->fetchColumn() >= 5) {
        reply(429, ['ok' => false, 'error' => 'Too many messages. Please try again later.']);
    }

    // Prepared statement = safe from SQL injection
    $ins = $pdo->prepare('INSERT INTO contact_messages (name, email, message, ip_address) VALUES (?, ?, ?, ?)');
    $ins->execute([$name, $email, $message, $ip]);

    reply(200, ['ok' => true]);
} catch (Throwable $e) {
    error_log('contact.php: ' . $e->getMessage());
    $body = ['ok' => false, 'error' => 'Could not save your message to the database.'];
    if (DEBUG) {
        $hint = connection_hint($e);
        $body['detail'] = ($hint !== '' ? $hint . ' ' : '') . '(' . $e->getMessage() . ')';
    }
    reply(500, $body);
}
