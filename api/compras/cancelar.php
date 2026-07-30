<?php
/**
 * POST /api/compras/cancelar
 * Body: { "id": int, "motivo"?: string }
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

$stmtR = $pdo->prepare('SELECT role FROM usuario_roles WHERE usuario_id = :id');
$stmtR->execute(['id' => $usuarioId]);
$roles = array_column($stmtR->fetchAll(), 'role');

$sc = $pdo->prepare('SELECT id, numero, status, solicitante_id FROM solicitacoes_compra WHERE id = :id LIMIT 1');
$sc->execute(['id' => $id]);
$sc = $sc->fetch();
if (!$sc) { responderErro('Solicitação não encontrada.', 404); }

$podeCancel = ($perfil === 'gestor')
    || ($usuarioId === (int) $sc['solicitante_id'] && in_array($sc['status'], ['rascunho', 'devolvido'], true));

if (!$podeCancel) { responderErro('Acesso negado ou status não permite cancelamento.', 403); }
if ($sc['status'] === 'cancelado') { responderErro('Já cancelada.', 422); }
if (in_array($sc['status'], ['recebido', 'concluido'], true)) { responderErro('Não é possível cancelar uma solicitação já recebida/concluída.', 422); }

$pdo->prepare('UPDATE solicitacoes_compra SET status = \'cancelado\' WHERE id = :id')->execute(['id' => $id]);
registrarHistoricoCompra($pdo, $id, $usuarioId, $payload['nome'] ?? '', 'Solicitação cancelada', $dados['motivo'] ?? null);

responderSucesso(['mensagem' => 'Solicitação cancelada.']);
