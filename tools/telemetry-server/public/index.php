<?php
declare(strict_types=1);
require dirname(__DIR__).'/private/bootstrap.php';
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'");
session_start(['cookie_httponly'=>true, 'cookie_secure'=>true, 'cookie_samesite'=>'Strict', 'use_strict_mode'=>true]);
try { $key = adminKey(); $db = database(); } catch (Throwable $e) { http_response_code(503); exit(escape($e->getMessage())); }
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Atualize a página.'); }
    if (isset($_POST['key'])) {
        if (limitRequests('login', 10) && hash_equals($key, (string) $_POST['key'])) {
            session_regenerate_id(true); $_SESSION['admin'] = true;
        } else { $error = 'Chave inválida ou limite de tentativas atingido.'; }
    } elseif (!empty($_SESSION['admin']) && isset($_POST['delete'])) {
        $db->prepare('DELETE FROM installations WHERE id=?')->execute([(string) $_POST['delete']]);
    } elseif (isset($_POST['logout'])) { $_SESSION = []; session_destroy(); header('Location: /'); exit; }
}
$csrf = escape($_SESSION['csrf']);
?>
<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CREASY — instalações</title>
<style>body{font:16px system-ui;margin:32px;background:#fffaf5;color:#292524}input,button{padding:10px;margin:4px;border:1px solid #ddd;border-radius:6px}button{background:#c45100;color:white;cursor:pointer}table{border-collapse:collapse;width:100%;background:white}td,th{padding:12px;border-bottom:1px solid #ddd;text-align:left}.scroll{overflow:auto}</style>
<h1>CREASY CRM — instalações registradas</h1>
<?php if (empty($_SESSION['admin'])): ?>
<p>Abra <code>private/admin-key.txt</code> pelo gerenciador de arquivos da hospedagem e copie a chave. Nunca publique esse arquivo.</p>
<p><?= escape($error) ?></p><form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><label>Chave administrativa <input type="password" name="key" required autocomplete="current-password"></label><button>Entrar</button></form>
<?php else:
$count = (int) $db->query('SELECT COUNT(*) FROM installations')->fetchColumn();
$domains = (int) $db->query('SELECT COUNT(DISTINCT domain) FROM installations')->fetchColumn();
$page = max(1, (int) ($_GET['page'] ?? 1)); $offset = ($page-1)*100;
$rows = $db->query('SELECT id,domain,version,admin_name,admin_email,registered_at,last_seen_at FROM installations ORDER BY registered_at DESC LIMIT 100 OFFSET '.$offset)->fetchAll(PDO::FETCH_ASSOC);
?>
<p><strong><?= $count ?></strong> instalações · <strong><?= $domains ?></strong> domínios distintos</p>
<p>Online: sinal recebido nos últimos 15 minutos. Offline: sem sinal há mais de 15 minutos. Sem sinal: ainda não recebeu confirmação periódica. Ausência de sinal também pode indicar cron parado ou falha de conexão; não comprova indisponibilidade do site. Atualize a página para recalcular.</p>
<p>Registros autodeclarados. Não representam usuários ativos nem uma contagem garantida de todas as cópias.</p>
<div class="scroll"><table><thead><tr><th>Domínio</th><th>Administrador</th><th>E-mail</th><th>Versão</th><th>Registro</th><th>Status</th><th>Último contato (UTC)</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><?php foreach (['domain','admin_name','admin_email','version','registered_at'] as $field): ?><td><?= escape($row[$field]) ?></td><?php endforeach; ?><td><strong><?= escape(installationStatus($row['last_seen_at'] === null ? null : (int) $row['last_seen_at'])) ?></strong></td><td><?= $row['last_seen_at'] === null ? '—' : escape(gmdate('d/m/Y H:i:s', (int) $row['last_seen_at'])) ?></td><td><form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><button name="delete" value="<?= escape($row['id']) ?>">Excluir registro</button></form></td></tr><?php endforeach; ?>
</tbody></table></div><p><?php if ($page>1): ?><a href="?page=<?= $page-1 ?>">Anterior</a> · <?php endif; ?><a href="?page=<?= $page+1 ?>">Próxima</a></p>
<form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><button name="logout">Sair</button></form>
<?php endif; ?></html>
