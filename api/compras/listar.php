<?php
/**
 * GET /api/compras/listar
 * Retorna lista de solicitações de compra com filtros.
 * Acesso: gestor, supervisor, aprovador → todas; solicitante → próprias; comprador → aprovadas+.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/compras_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro('Metodo nao permitido.', 405);
}

$payload = exigirAutenticacao();
$pdo     = obterConexao();

$usuarioId = (int) $payload['usuario_id'];
$perfil    = $payload['perfil'];

// Carrega roles adicionais
$stmtR = $pdo->prepare('SELECT role FROM usuario_roles WHERE usuario_id = :id');
$stmtR->execute(['id' => $usuarioId]);
$roles = array_column($stmtR->fetchAll(), 'role');

$isGestor  = $perfil === 'gestor';
$isComprador  = in_array('comprador', $roles, true);
$isSolicitante = in_array('solicitante', $roles, true) || $perfil === 'tecnico';

// Parâmetros de filtro
$status    = $_GET['status']    ?? '';
$prioridade= $_GET['prioridade']?? '';
$busca     = $_GET['busca']     ?? '';
$pagina    = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = min(50, max(10, (int) ($_GET['por_pagina'] ?? 20)));
$offset    = ($pagina - 1) * $porPagina;

$where  = ['1=1'];
$params = [];

// Controle de acesso: solicitante vê só as próprias; comprador vê aprovadas+; gestor vê tudo
if ($isGestor || in_array('aprovador', $roles, true) || $perfil === 'supervisor') {
    // vê tudo
} elseif ($isComprador) {
    $where[] = "sc.status IN ('aprovado','em_compra','recebido','concluido')";
} else {
    // Solicitante vê apenas as próprias
    $where[] = 'sc.solicitante_id = :solicitante_id';
    $params['solicitante_id'] = $usuarioId;
}

if ($status !== '') {
    $where[]          = 'sc.status = :status';
    $params['status'] = $status;
}
if ($prioridade !== '') {
    $where[]              = 'sc.prioridade = :prioridade';
    $params['prioridade'] = $prioridade;
}
if ($busca !== '') {
    $where[]       = '(sc.numero LIKE :busca OR sc.justificativa LIKE :busca OR sol.nome LIKE :busca OR f.nome LIKE :busca)';
    $params['busca'] = '%' . $busca . '%';
}

$whereStr = implode(' AND ', $where);

$total = (int) $pdo->prepare("SELECT COUNT(*) FROM solicitacoes_compra sc LEFT JOIN usuarios sol ON sol.id = sc.solicitante_id LEFT JOIN fornecedores f ON f.id = sc.fornecedor_id WHERE $whereStr")->execute($params) ? $pdo->prepare("SELECT COUNT(*) FROM solicitacoes_compra sc LEFT JOIN usuarios sol ON sol.id = sc.solicitante_id LEFT JOIN fornecedores f ON f.id = sc.fornecedor_id WHERE $whereStr")->fetchColumn() : 0;

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM solicitacoes_compra sc LEFT JOIN usuarios sol ON sol.id = sc.solicitante_id LEFT JOIN fornecedores f ON f.id = sc.fornecedor_id WHERE $whereStr");
$stmtTotal->execute($params);
$total = (int) $stmtTotal->fetchColumn();

$sql = "
    SELECT sc.id, sc.numero, sc.status, sc.prioridade, sc.destino, sc.destino_referencia,
           sc.justificativa, sc.valor_estimado, sc.valor_negociado, sc.valor_final, sc.criado_em, sc.atualizado_em,
           sol.nome AS solicitante_nome,
           comp.nome AS comprador_nome,
           f.nome AS fornecedor_nome,
           cc.nome AS centro_custo_nome,
           cat.nome AS categoria_nome,
           (SELECT COUNT(*) FROM solicitacao_itens si WHERE si.solicitacao_id = sc.id) AS total_itens
    FROM solicitacoes_compra sc
    LEFT JOIN usuarios sol  ON sol.id  = sc.solicitante_id
    LEFT JOIN usuarios comp ON comp.id = sc.comprador_id
    LEFT JOIN fornecedores f ON f.id   = sc.fornecedor_id
    LEFT JOIN centros_custo cc ON cc.id = sc.centro_custo_id
    LEFT JOIN categorias_compra cat ON cat.id = sc.categoria_id
    WHERE $whereStr
    ORDER BY FIELD(sc.prioridade,'urgente','alta','media','baixa'), sc.criado_em DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v); }
$stmt->bindValue(':limit',  $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
$stmt->execute();
$itens = $stmt->fetchAll();

responderSucesso([
    'solicitacoes' => $itens,
    'total'        => $total,
    'pagina'       => $pagina,
    'por_pagina'   => $porPagina,
    'paginas'      => (int) ceil($total / $porPagina),
]);
