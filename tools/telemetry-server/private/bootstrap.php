<?php
declare(strict_types=1);

function database(): PDO
{
    static $db;
    if ($db) return $db;
    if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('Habilite pdo_sqlite no painel PHP da hospedagem.');
    $db = new PDO('sqlite:'.__DIR__.'/registrations.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout=3000');
    $db->exec('CREATE TABLE IF NOT EXISTS installations (id TEXT PRIMARY KEY, secret_hash TEXT NOT NULL, domain TEXT NOT NULL, version TEXT NOT NULL, admin_name TEXT NOT NULL, admin_email TEXT NOT NULL, registered_at TEXT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS limits (bucket TEXT PRIMARY KEY, hits INTEGER NOT NULL, expires INTEGER NOT NULL)');
    $db->exec('BEGIN IMMEDIATE');
    try {
        $columns = $db->query('PRAGMA table_info(installations)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('last_seen_at', $columns, true)) $db->exec('ALTER TABLE installations ADD COLUMN last_seen_at INTEGER NULL');
        $db->exec('COMMIT');
    } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
    @chmod(__DIR__.'/registrations.sqlite', 0600);
    return $db;
}

function limitRequests(string $scope, int $maximum): bool
{
    $db = database();
    $now = time();
    $db->prepare('DELETE FROM limits WHERE expires < ?')->execute([$now]);
    // Only a rotating hash is retained for up to one hour, never the IP itself.
    $bucket = hash_hmac('sha256', $scope.'|'.($_SERVER['REMOTE_ADDR'] ?? '').'|'.intdiv($now, 3600), adminKey());
    $db->prepare('INSERT INTO limits(bucket,hits,expires) VALUES(?,1,?) ON CONFLICT(bucket) DO UPDATE SET hits=hits+1')->execute([$bucket, $now+3600]);
    $q = $db->prepare('SELECT hits FROM limits WHERE bucket=?'); $q->execute([$bucket]);
    return (int) $q->fetchColumn() <= $maximum;
}

function adminKey(): string
{
    $path = __DIR__.'/admin-key.txt';
    $lock = fopen(__DIR__.'/setup.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Permita escrita na pasta private.');
    try {
        if (!is_file($path)) {
            if (file_put_contents($path, bin2hex(random_bytes(32)), LOCK_EX) === false) throw new RuntimeException('Permita escrita na pasta private.');
            @chmod($path, 0600);
        }
        $key = trim((string) file_get_contents($path));
        if (strlen($key) !== 64) throw new RuntimeException('Chave administrativa inválida.');
        return $key;
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

function escape(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }

function installationStatus(?int $lastSeen, ?int $now = null): string
{
    if ($lastSeen === null) return 'Sem sinal';
    return $lastSeen >= ($now ?? time()) - 900 ? 'Online' : 'Offline';
}
