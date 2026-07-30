<?php
/**
 * POST /api/compras/concluir — Marca solicitação como concluída (gestor/comprador).
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

if ($perfil !== 'gestor' && !in_array('comprador', $roles, true)) { responderErro('Acesso negado.', 403); }

$sc = $pdo->prepare('SELECT id, numero, status FROM solicitacoes_compra WHERE id = :id LIMIT 1');
$sc->execute(['id' => $id]);
$sc = $sc->fetch();
if (!$sc) { responderErro('Não encontrada.', 404); }
if ($sc['status'] !== 'recebido') { responderErro('Apenas solicitações recebidas podem ser concluídas.', 422); }

$pdo->prepare('UPDATE solicitacoes_compra SET status = \'concluido\' WHERE id = :id')->execute(['id' => $id]);
registrarHistoricoCompra($pdo, $id, $usuarioId, $payload['nome'] ?? '', 'Solicitação concluída');

responderSucesso(['mensagem' => 'Solicitação concluída.']);
