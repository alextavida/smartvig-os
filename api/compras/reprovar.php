<?php
/**
 * POST /api/compras/reprovar
 * Body: { "id": int, "motivo": string }
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/compras_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { responderErro('Metodo nao permitido.', 405); }

$payload   = exigirAutenticacao();
$pdo       = obterConexao();
$dados     = lerCorpoJson();
$id        = (int) ($dados['id'] ?? 0);
$usuarioId = (int) $payload['usuario_id'];
$perfil    = $payload['perfil'];

exigirCampos($dados, ['motivo']);

$stmtR = $pdo->prepare('SELECT role FROM usuario_roles WHERE usuario_id = :id');
$stmtR->execute(['id' => $usuarioId]);
$roles = array_column($stmtR->fetchAll(), 'role');

if (!in_array($perfil, ['gestor', 'supervisor'], true) && !in_array('aprovador', $roles, true)) {
    responderErro('Acesso negado.', 403);
}

$sc = $pdo->prepare('SELECT id, numero, status, solicitante_id FROM solicitacoes_compra WHERE id = :id LIMIT 1');
$sc->execute(['id' => $id]);
$sc = $sc->fetch();
if (!$sc) { responderErro('Solicitação não encontrada.', 404); }
if (!in_array($sc['status'], ['aguardando_aprovacao', 'rascunho'], true)) {
    responderErro('Status não permite reprovação.', 422);
}

$pdo->prepare(
    'UPDATE solicitacoes_compra
     SET status = \'reprovado\', aprovador_id = :aid, aprovado_em = NOW(), motivo_reprovacao = :motivo
     WHERE id = :id'
)->execute(['aid' => $usuarioId, 'motivo' => trim($dados['motivo']), 'id' => $id]);

registrarHistoricoCompra($pdo, $id, $usuarioId, $payload['nome'] ?? '', 'Solicitação reprovada', $dados['motivo']);

$pdo->prepare(
    'INSERT INTO notificacoes (usuario_id, os_id, tipo, titulo, mensagem) VALUES (:uid, NULL, :tipo, :titulo, :msg)'
)->execute([
    'uid'   => (int) $sc['solicitante_id'],
    'tipo'  => 'compra_reprovada',
    'titulo'=> 'Solicitação Reprovada',
    'msg'   => 'Sua solicitação ' . $sc['numero'] . ' foi reprovada. Motivo: ' . $dados['motivo'],
]);

responderSucesso(['mensagem' => 'Solicitação reprovada.']);
