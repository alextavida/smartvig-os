<?php
/**
 * GET /api/tecnicos/produtividade
 * Retorna estatísticas de produtividade do técnico autenticado.
 * Gestor pode passar ?tecnico_id=X para ver de outro técnico.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro('Metodo nao permitido.', 405);
}

$payload   = exigirAutenticacao();
$pdo       = obterConexao();

// Técnico vê própria produtividade; gestor pode ver de qualquer um
if ($payload['perfil'] === 'gestor' && !empty($_GET['tecnico_id'])) {
    $tecnicoId = (int) $_GET['tecnico_id'];
} else {
    $tecnicoId = (int) $payload['usuario_id'];
}

// Total geral
$stmtTotal = $pdo->prepare(
    "SELECT COUNT(*) FROM ordens_servico
     WHERE (tecnico_id = :tid OR EXISTS (SELECT 1 FROM os_tecnicos ot WHERE ot.os_id = id AND ot.tecnico_id = :tid2))"
);
$stmtTotal->execute(['tid' => $tecnicoId, 'tid2' => $tecnicoId]);
$totalOS = (int) $stmtTotal->fetchColumn();

// Concluídas esta semana (segunda a domingo)
$stmtSemana = $pdo->prepare(
    "SELECT COUNT(*) FROM ordens_servico
     WHERE situacao_local = 'concluido'
       AND YEARWEEK(data_conclusao, 1) = YEARWEEK(CURDATE(), 1)
       AND (tecnico_id = :tid OR EXISTS (SELECT 1 FROM os_tecnicos ot WHERE ot.os_id = id AND ot.tecnico_id = :tid2))"
);
$stmtSemana->execute(['tid' => $tecnicoId, 'tid2' => $tecnicoId]);
$concluidasSemana = (int) $stmtSemana->fetchColumn();

// Concluídas este mês
$stmtMes = $pdo->prepare(
    "SELECT COUNT(*) FROM ordens_servico
     WHERE situacao_local = 'concluido'
       AND MONTH(data_conclusao) = MONTH(CURDATE())
       AND YEAR(data_conclusao) = YEAR(CURDATE())
       AND (tecnico_id = :tid OR EXISTS (SELECT 1 FROM os_tecnicos ot WHERE ot.os_id = id AND ot.tecnico_id = :tid2))"
);
$stmtMes->execute(['tid' => $tecnicoId, 'tid2' => $tecnicoId]);
$concluidasMes = (int) $stmtMes->fetchColumn();

// OS ativa agora (em_andamento)
$stmtAtiva = $pdo->prepare(
    "SELECT os.id, os.cliente_nome, os.situacao_local, os.data_agendamento, os.tempo_atendimento_segundos
     FROM ordens_servico os
     WHERE os.situacao_local = 'em_andamento'
       AND (os.tecnico_id = :tid OR EXISTS (SELECT 1 FROM os_tecnicos ot WHERE ot.os_id = os.id AND ot.tecnico_id = :tid2))
     LIMIT 1"
);
$stmtAtiva->execute(['tid' => $tecnicoId, 'tid2' => $tecnicoId]);
$osAtiva = $stmtAtiva->fetch() ?: null;

// Tempo médio de atendimento (só concluídas com tempo registrado)
$stmtTempo = $pdo->prepare(
    "SELECT AVG(tempo_atendimento_segundos), MAX(tempo_atendimento_segundos), COUNT(*)
     FROM ordens_servico
     WHERE situacao_local = 'concluido'
       AND tempo_atendimento_segundos IS NOT NULL
       AND tempo_atendimento_segundos > 0
       AND (tecnico_id = :tid OR EXISTS (SELECT 1 FROM os_tecnicos ot WHERE ot.os_id = id AND ot.tecnico_id = :tid2))"
);
$stmtTempo->execute(['tid' => $tecnicoId, 'tid2' => $tecnicoId]);
[$tempoMedio, $tempoMax, $totalComTempo] = $stmtTempo->fetch(PDO::FETCH_NUM) ?: [null, null, 0];

// Distribuição por status
$stmtDist = $pdo->prepare(
    "SELECT situacao_local, COUNT(*) AS total
     FROM ordens_servico
     WHERE (tecnico_id = :tid OR EXISTS (SELECT 1 FROM os_tecnicos ot WHERE ot.os_id = id AND ot.tecnico_id = :tid2))
     GROUP BY situacao_local"
);
$stmtDist->execute(['tid' => $tecnicoId, 'tid2' => $tecnicoId]);
$distribuicao = [];
foreach ($stmtDist->fetchAll() as $row) {
    $distribuicao[$row['situacao_local']] = (int) $row['total'];
}

// Últimas 5 OS concluídas
$stmtRecentes = $pdo->prepare(
    "SELECT id, cliente_nome, data_conclusao, tempo_atendimento_segundos
     FROM ordens_servico
     WHERE situacao_local = 'concluido'
       AND (tecnico_id = :tid OR EXISTS (SELECT 1 FROM os_tecnicos ot WHERE ot.os_id = id AND ot.tecnico_id = :tid2))
     ORDER BY data_conclusao DESC
     LIMIT 5"
);
$stmtRecentes->execute(['tid' => $tecnicoId, 'tid2' => $tecnicoId]);
$recentesConcluidas = $stmtRecentes->fetchAll();

responderSucesso([
    'total_os'              => $totalOS,
    'concluidas_semana'     => $concluidasSemana,
    'concluidas_mes'        => $concluidasMes,
    'os_ativa'              => $osAtiva,
    'tempo_medio_segundos'  => $tempoMedio !== null ? (int) round((float) $tempoMedio) : null,
    'tempo_max_segundos'    => $tempoMax !== null ? (int) $tempoMax : null,
    'total_com_tempo'       => (int) $totalComTempo,
    'distribuicao'          => $distribuicao,
    'recentes_concluidas'   => $recentesConcluidas,
]);
