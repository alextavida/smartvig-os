<?php
/**
 * GET /api/compras/visualizar?id=
 * Retorna detalhes completos de uma solicitação.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/compras_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') { responderErro('Metodo nao permitido.', 405); }

$payload = exigirAutenticacao();
$pdo     = obterConexao();
$id      = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { responderErro('ID inválido.', 400); }

$stmt = $pdo->prepare(
    "SELECT sc.*,
            sol.nome  AS solicitante_nome,  sol.email  AS solicitante_email,
            comp.nome AS comprador_nome,
            apr.nome  AS aprovador_nome,
            rec.nome  AS recebedor_nome,
            f.nome    AS fornecedor_nome,   f.cnpj     AS fornecedor_cnpj,
            f.telefone AS fornecedor_telefone, f.email AS fornecedor_email, f.contato AS fornecedor_contato,
            cc.nome   AS centro_custo_nome,
            cat.nome  AS categoria_nome
     FROM solicitacoes_compra sc
     LEFT JOIN usuarios sol  ON sol.id  = sc.solicitante_id
     LEFT JOIN usuarios comp ON comp.id = sc.comprador_id
     LEFT JOIN usuarios apr  ON apr.id  = sc.aprovador_id
     LEFT JOIN usuarios rec  ON rec.id  = sc.recebido_por_id
     LEFT JOIN fornecedores f  ON f.id   = sc.fornecedor_id
     LEFT JOIN centros_custo cc ON cc.id = sc.centro_custo_id
     LEFT JOIN categorias_compra cat ON cat.id = sc.categoria_id
     WHERE sc.id = :id LIMIT 1"
);
$stmt->execute(['id' => $id]);
$sc = $stmt->fetch();
if (!$sc) { responderErro('Solicitação não encontrada.', 404); }

// Controle de acesso
$usuarioId = (int) $payload['usuario_id'];
$perfil    = $payload['perfil'];
$stmtR     = $pdo->prepare('SELECT role FROM usuario_roles WHERE usuario_id = :id');
$stmtR->execute(['id' => $usuarioId]);
$roles     = array_column($stmtR->fetchAll(), 'role');

$isGestor  = $perfil === 'gestor' || in_array('aprovador', $roles, true) || $perfil === 'supervisor';
$isComprador = in_array('comprador', $roles, true);
$ehSolicitante = (int) $sc['solicitante_id'] === $usuarioId;

if (!$isGestor && !$isComprador && !$ehSolicitante) {
    responderErro('Acesso negado.', 403);
}

// Itens
$itens = $pdo->prepare('SELECT * FROM solicitacao_itens WHERE solicitacao_id = :id ORDER BY id');
$itens->execute(['id' => $id]);
$sc['itens'] = $itens->fetchAll();

// Histórico
$hist = $pdo->prepare('SELECT * FROM solicitacao_historico WHERE solicitacao_id = :id ORDER BY criado_em ASC');
$hist->execute(['id' => $id]);
$sc['historico'] = $hist->fetchAll();

// Anexos
$anx = $pdo->prepare('SELECT id, nome_original, tamanho, tipo_mime, criado_em FROM solicitacao_anexos WHERE solicitacao_id = :id ORDER BY criado_em');
$anx->execute(['id' => $id]);
$sc['anexos'] = $anx->fetchAll();

// Fotos de recebimento
$fotos = $pdo->prepare('SELECT id, caminho, criado_em FROM recebimento_fotos WHERE solicitacao_id = :id ORDER BY criado_em');
$fotos->execute(['id' => $id]);
$sc['fotos_recebimento'] = $fotos->fetchAll();

responderSucesso($sc);
