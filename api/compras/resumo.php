<?php
/**
 * GET /api/compras/resumo
 * Estatísticas para o dashboard de compras.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') { responderErro('Metodo nao permitido.', 405); }

$payload = exigirAutenticacao();
$pdo     = obterConexao();

// Contagens por status
$stmtStatus = $pdo->query(
    "SELECT status, COUNT(*) AS total FROM solicitacoes_compra GROUP BY status"
);
$porStatus = [];
foreach ($stmtStatus->fetchAll() as $row) {
    $porStatus[$row['status']] = (int) $row['total'];
}

// Valores financeiros do mês atual
$stmtMes = $pdo->query(
    "SELECT
        SUM(valor_estimado) AS total_estimado,
        SUM(valor_final)    AS total_final,
        COUNT(*)            AS total_solicitacoes
     FROM solicitacoes_compra
     WHERE YEAR(criado_em) = YEAR(CURDATE()) AND MONTH(criado_em) = MONTH(CURDATE())"
);
$financeiro = $stmtMes->fetch();

// Compras por mês (últimos 6 meses)
$stmtMeses = $pdo->query(
    "SELECT
        DATE_FORMAT(criado_em, '%Y-%m') AS mes,
        COUNT(*) AS total,
        SUM(valor_final) AS valor
     FROM solicitacoes_compra
     WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(criado_em, '%Y-%m')
     ORDER BY mes ASC"
);
$porMes = $stmtMeses->fetchAll();

// Últimas solicitações pendentes de aprovação
$stmtPendentes = $pdo->query(
    "SELECT sc.id, sc.numero, sc.prioridade, sc.justificativa, u.nome AS solicitante_nome, sc.criado_em
     FROM solicitacoes_compra sc
     INNER JOIN usuarios u ON u.id = sc.solicitante_id
     WHERE sc.status = 'aguardando_aprovacao'
     ORDER BY FIELD(sc.prioridade,'urgente','alta','media','baixa'), sc.criado_em ASC
     LIMIT 5"
);
$pendentesAprovacao = $stmtPendentes->fetchAll();

// Economia (estimado - final para concluídas)
$stmtEcon = $pdo->query(
    "SELECT SUM(valor_estimado - valor_final) AS economia
     FROM solicitacoes_compra
     WHERE status IN ('recebido','concluido')
       AND valor_estimado IS NOT NULL AND valor_final IS NOT NULL
       AND valor_final < valor_estimado"
);
$economia = (float) ($stmtEcon->fetchColumn() ?? 0);

responderSucesso([
    'por_status'          => $porStatus,
    'financeiro_mes'      => $financeiro,
    'por_mes'             => $porMes,
    'pendentes_aprovacao' => $pendentesAprovacao,
    'economia_total'      => $economia,
]);
