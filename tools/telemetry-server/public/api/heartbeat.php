<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/private/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function result(int $status, bool $ok): never { http_response_code($status); echo json_encode(['ok'=>$ok]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') result(405, false);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 1024) result(413, false);
try {
    $raw = file_get_contents('php://input', false, null, 0, 1025);
    if (strlen($raw) > 1024) result(413, false);
    try { $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR); } catch (JsonException) { result(422, false); }
    $fields = ['installation_id','installation_secret'];
    if (!is_array($data) || array_diff(array_keys($data), $fields) || array_diff($fields, array_keys($data))) result(422, false);
    if (!is_string($data['installation_id']) || !is_string($data['installation_secret']) || !preg_match('/^[0-9a-f-]{36}$/i', $data['installation_id']) || !preg_match('/^[0-9a-f]{64}$/', $data['installation_secret'])) result(422, false);
    if (!limitRequests('heartbeat-origin', 1200)) result(429, false);
    $db = database();
    $query = $db->prepare('SELECT secret_hash FROM installations WHERE id=?');
    $query->execute([$data['installation_id']]);
    $hash = $query->fetchColumn();
    if (!$hash || !hash_equals($hash, hash('sha256', $data['installation_secret']))) result(403, false);
    if (!limitRequests('heartbeat-'.$data['installation_id'], 30)) result(429, false);
    $db->prepare('UPDATE installations SET last_seen_at=? WHERE id=?')->execute([time(), $data['installation_id']]);
    result(200, true);
} catch (Throwable) { result(503, false); }
