<?php
/**
 * GET /api/os/listar
 * Filtros opcionais (querystring): status, tecnico_id, data_inicio, data_fim, cliente, pagina, por_pagina
 * Gestor ve todas as OSs; tecnico ve somente as OSs as quais esta atribuido.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/os_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro('Metodo nao permitido. Use GET.', 405);
}

$payload = exigirAutenticacao();
$pdo = obterConexao();

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = min(100, max(1, (int) ($_GET['por_pagina'] ?? 20)));
$offset = ($pagina - 1) * $porPagina;

$condicoes = [];
$parametros = [];

if ($payload['perfil'] === 'tecnico') {
    $condicoes[] = 'EXISTS (SELECT 1 FROM os_tecnicos ot WHERE ot.os_id = os.id AND ot.tecnico_id = :tecnico_logado)';
    $parametros['tecnico_logado'] = $payload['usuario_id'];
}

if (!empty($_GET['status'])) {
    $condicoes[] = 'os.situacao_local = :status';
    $parametros['status'] = $_GET['status'];
}

if (!empty($_GET['tecnico_id'])) {
    $condicoes[] = 'EXISTS (SELECT 1 FROM os_tecnicos ot2 WHERE ot2.os_id = os.id AND ot2.tecnico_id = :tecnico_filtro)';
    $parametros['tecnico_filtro'] = (int) $_GET['tecnico_id'];
}

if (!empty($_GET['data_inicio'])) {
    $condicoes[] = 'os.data_agendamento >= :data_inicio';
    $parametros['data_inicio'] = $_GET['data_inicio'];
}

if (!empty($_GET['data_fim'])) {
    $condicoes[] = 'os.data_agendamento <= :data_fim';
    $parametros['data_fim'] = $_GET['data_fim'];
}

if (!empty($_GET['cliente'])) {
    $condicoes[] = 'os.cliente_nome LIKE :cliente';
    $parametros['cliente'] = '%' . $_GET['cliente'] . '%';
}

$whereSql = empty($condicoes) ? '' : ('WHERE ' . implode(' AND ', $condicoes));

$sqlTotal = "SELECT COUNT(*) FROM ordens_servico os {$whereSql}";
$stmtTotal = $pdo->prepare($sqlTotal);
$stmtTotal->execute($parametros);
$total = (int) $stmtTotal->fetchColumn();

$sql = "SELECT os.id, os.gc_os_id, os.codigo, os.cliente_nome, os.cliente_endereco, os.cliente_telefone,
               os.situacao_local, os.prioridade, os.data_agendamento, os.data_conclusao, os.tecnico_id,
               os.latitude_atual, os.longitude_atual, os.criado_em, os.atualizado_em,
               resp.nome AS tecnico_responsavel_nome
        FROM ordens_servico os
        LEFT JOIN usuarios resp ON resp.id = os.tecnico_id
        {$whereSql}
        ORDER BY os.data_agendamento IS NULL, os.data_agendamento DESC, os.id DESC
        LIMIT :limite OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($parametros as $chave => $valor) {
    $stmt->bindValue($chave, $valor);
}
$stmt->bindValue('limite', $porPagina, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$osLista = $stmt->fetchAll();

responderSucesso([
    'ordens_servico' => $osLista,
    'paginacao' => [
        'pagina' => $pagina,
        'por_pagina' => $porPagina,
        'total' => $total,
        'total_paginas' => (int) ceil($total / $porPagina),
    ],
]);
