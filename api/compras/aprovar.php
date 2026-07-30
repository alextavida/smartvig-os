<?php
/**
 * POST /api/compras/aprovar
 * Body: { "id": int, "observacao"?: string }
 * Aprova uma solicitação (gestor, supervisor ou aprovador).
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

if ($id <= 0) { responderErro('ID inválido.', 400); }

$stmtR = $pdo->prepare('SELECT role FROM usuario_roles WHERE usuario_id = :id');
$stmtR->execute(['id' => $usuarioId]);
$roles = array_column($stmtR->fetchAll(), 'role');

if (!in_array($perfil, ['gestor', 'supervisor'], true) && !in_array('aprovador', $roles, true)) {
    responderErro('Acesso negado. Somente aprovadores podem aprovar solicitações.', 403);
}

$stmt = $pdo->prepare('SELECT id, numero, status, solicitante_id FROM solicitacoes_compra WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$sc = $stmt->fetch();

if (!$sc) { responderErro('Solicitação não encontrada.', 404); }
if ($sc['status'] !== 'aguardando_aprovacao') {
    responderErro('Apenas solicitações aguardando aprovação podem ser aprovadas.', 422);
}

$pdo->prepare(
    'UPDATE solicitacoes_compra
     SET status = \'aprovado\', aprovador_id = :aid, aprovado_em = NOW()
     WHERE id = :id'
)->execute(['aid' => $usuarioId, 'id' => $id]);

registrarHistoricoCompra($pdo, $id, $usuarioId, $payload['nome'] ?? '', 'Solicitação aprovada', $dados['observacao'] ?? null);

// Notifica solicitante
$pdo->prepare(
    'INSERT INTO notificacoes (usuario_id, os_id, tipo, titulo, mensagem) VALUES (:uid, NULL, :tipo, :titulo, :msg)'
)->execute([
    'uid'   => (int) $sc['solicitante_id'],
    'tipo'  => 'compra_aprovada',
    'titulo'=> 'Solicitação Aprovada ✓',
    'msg'   => 'Sua solicitação ' . $sc['numero'] . ' foi aprovada e será encaminhada ao comprador.',
]);

// Notifica compradores
notificarCompradores($pdo, $id, 'compra_para_compra',
    'Solicitação Aprovada para Compra',
    'A solicitação ' . $sc['numero'] . ' foi aprovada e aguarda processamento.');

responderSucesso(['mensagem' => 'Solicitação aprovada com sucesso.']);
