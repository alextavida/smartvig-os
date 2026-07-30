<?php
/**
 * POST /api/compras/receber
 * Registra o recebimento de uma compra.
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
if ($sc['status'] !== 'em_compra') { responderErro('Apenas solicitações em compra podem ser recebidas.', 422); }

$pdo->prepare(
    'UPDATE solicitacoes_compra SET
        status = \'recebido\',
        recebido_por_id = :uid,
        recebido_em = NOW(),
        observacoes_recebimento = :obs
     WHERE id = :id'
)->execute([
    'uid' => $usuarioId,
    'obs' => $dados['observacoes'] ?? null,
    'id'  => $id,
]);

// Atualiza qtd recebida nos itens
if (!empty($dados['itens']) && is_array($dados['itens'])) {
    $stmtItem = $pdo->prepare('UPDATE solicitacao_itens SET quantidade_recebida = :qtd WHERE id = :id AND solicitacao_id = :sid');
    foreach ($dados['itens'] as $item) {
        if (empty($item['id'])) { continue; }
        $stmtItem->execute(['qtd' => (float) ($item['quantidade_recebida'] ?? 0), 'id' => (int) $item['id'], 'sid' => $id]);
    }
}

registrarHistoricoCompra($pdo, $id, $usuarioId, $payload['nome'] ?? '', 'Material recebido',
    $dados['observacoes'] ?? null);

// Notifica solicitante
$pdo->prepare(
    'INSERT INTO notificacoes (usuario_id, os_id, tipo, titulo, mensagem) VALUES (:uid, NULL, :tipo, :titulo, :msg)'
)->execute([
    'uid'   => (int) $sc['solicitante_id'],
    'tipo'  => 'compra_recebida',
    'titulo'=> 'Material Recebido',
    'msg'   => 'Os materiais da solicitação ' . $sc['numero'] . ' foram recebidos.',
]);

responderSucesso(['mensagem' => 'Recebimento registrado com sucesso.']);
