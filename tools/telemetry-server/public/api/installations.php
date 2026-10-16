<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/private/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function result(int $status, bool $ok): never { http_response_code($status); echo json_encode(['ok'=>$ok]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') result(405, false);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) result(413, false);
try {
    if (!limitRequests('register', 30)) result(429, false);
    $raw = file_get_contents('php://input', false, null, 0, 8193);
    if (strlen($raw) > 8192) result(413, false);
    $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    $fields = ['installation_id','installation_secret','domain','version','admin_name','admin_email'];
    if (!is_array($data) || array_diff(array_keys($data), $fields) || array_diff($fields, array_keys($data))) result(422, false);
    foreach ($fields as $key) if (!is_string($data[$key]) || strlen($data[$key]) > 255 || trim($data[$key]) === '') result(422, false);
    if (!preg_match('/^[0-9a-f-]{36}$/i', $data['installation_id']) || !preg_match('/^[0-9a-f]{64}$/', $data['installation_secret']) || !filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) result(422, false);
    if (!preg_match('/^[a-z0-9.-]+$/i', $data['domain'])) result(422, false);
    $db = database();
    $hash = hash('sha256', $data['installation_secret']);
    $query = $db->prepare('INSERT OR IGNORE INTO installations (id,secret_hash,domain,version,admin_name,admin_email,registered_at) VALUES(?,?,?,?,?,?,?)');
    $query->execute([$data['installation_id'], $hash, strtolower($data['domain']), $data['version'], $data['admin_name'], $data['admin_email'], gmdate(DATE_ATOM)]);
    $query = $db->prepare('SELECT secret_hash FROM installations WHERE id=?');
    $query->execute([$data['installation_id']]);
    if (!hash_equals((string) $query->fetchColumn(), $hash)) result(409, false);
    result(200, true);
} catch (Throwable) { result(503, false); }
