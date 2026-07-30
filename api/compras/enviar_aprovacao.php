<?php
/**
 * POST /api/compras/enviar_aprovacao
 * Envia um rascunho ou solicitação devolvida para aprovação.
 * Body: { "id": int }
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

$sc = $pdo->prepare('SELECT id, numero, status, solicitante_id FROM solicitacoes_compra WHERE id = :id LIMIT 1');
$sc->execute(['id' => $id]);
$sc = $sc->fetch();
if (!$sc) { responderErro('Solicitação não encontrada.', 404); }
if ((int) $sc['solicitante_id'] !== $usuarioId && $payload['perfil'] !== 'gestor') {
    responderErro('Acesso negado.', 403);
}
if (!in_array($sc['status'], ['rascunho', 'devolvido'], true)) {
    responderErro('Apenas rascunhos ou devolvidos podem ser enviados para aprovação.', 422);
}

$pdo->prepare(
    'UPDATE solicitacoes_compra SET status = \'aguardando_aprovacao\', motivo_reprovacao = NULL WHERE id = :id'
)->execute(['id' => $id]);

registrarHistoricoCompra($pdo, $id, $usuarioId, $payload['nome'] ?? '', 'Enviada para aprovação');
notificarAprovadores($pdo, $id, 'compra_nova', 'Nova Solicitação para Aprovar',
    'A solicitação ' . $sc['numero'] . ' aguarda sua aprovação.');

responderSucesso(['mensagem' => 'Solicitação enviada para aprovação.']);
