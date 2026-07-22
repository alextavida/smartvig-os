<?php
/**
 * GET /api/notificacoes/listar?somente_nao_lidas=1
 * Substitui o push (FCM): o app/painel deve chamar este endpoint via polling
 * periodico (ex: a cada 20-30s) para descobrir novas notificacoes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro('Metodo nao permitido. Use GET.', 405);
}

$payload = exigirAutenticacao();
$pdo = obterConexao();

$somenteNaoLidas = !empty($_GET['somente_nao_lidas']);

$sql = 'SELECT id, os_id, tipo, titulo, mensagem, lida, criado_em FROM notificacoes WHERE usuario_id = :usuario_id';
if ($somenteNaoLidas) {
    $sql .= ' AND lida = 0';
}
$sql .= ' ORDER BY criado_em DESC LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute(['usuario_id' => $payload['usuario_id']]);

$stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0');
$stmtTotal->execute(['usuario_id' => $payload['usuario_id']]);

responderSucesso([
    'notificacoes' => $stmt->fetchAll(),
    'nao_lidas' => (int) $stmtTotal->fetchColumn(),
]);
