<?php
/**
 * GET /api/os/calendario?inicio=2026-07-01&fim=2026-07-31&tecnico_id=
 * Retorna eventos no formato FullCalendar para OS com data_agendamento ou data_prazo no periodo.
 * Requer sessao gestor ou supervisor.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/guard.php';

exigirLoginWeb(['gestor', 'supervisor']);

$pdo     = obterConexao();
$inicio  = $_GET['inicio']     ?? date('Y-m-01');
$fim     = $_GET['fim']        ?? date('Y-m-t');
$tecId   = isset($_GET['tecnico_id']) && $_GET['tecnico_id'] !== '' ? (int) $_GET['tecnico_id'] : null;

// Valida datas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio)) $inicio = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim))    $fim    = date('Y-m-t');

$sql = "SELECT os.id, os.codigo, os.cliente_nome, os.situacao_local, os.prioridade,
               os.data_agendamento, os.data_prazo, os.tecnico_id,
               u.nome AS tecnico_nome
        FROM ordens_servico os
        LEFT JOIN usuarios u ON u.id = os.tecnico_id
        WHERE (
          (os.data_agendamento IS NOT NULL AND os.data_agendamento BETWEEN :ini AND :fim)
          OR
          (os.data_prazo IS NOT NULL AND os.data_prazo BETWEEN :ini2 AND :fim2)
        )";

$params = [':ini' => $inicio, ':fim' => $fim, ':ini2' => $inicio, ':fim2' => $fim];

if ($tecId !== null) {
    $sql .= " AND os.tecnico_id = :tec";
    $params[':tec'] = $tecId;
}

$sql .= " ORDER BY os.data_agendamento";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$coresPorStatus = [
    'aberto'       => '#64748b',
    'em_andamento' => '#1d4ed8',
    'pausado'      => '#d97706',
    'reagendado'   => '#7c3aed',
    'concluido'    => '#16803c',
    'cancelado'    => '#dc2626',
];

$eventos = [];

foreach ($rows as $r) {
    $cor    = $coresPorStatus[$r['situacao_local']] ?? '#64748b';
    $titulo = ($r['codigo'] ?: '#' . $r['id']) . ' — ' . ($r['cliente_nome'] ?? 'Cliente');
    if ($r['prioridade'] === 'urgente') $titulo = '[!] ' . $titulo;

    // Evento de agendamento
    if ($r['data_agendamento']) {
        $eventos[] = [
            'id'         => 'ag_' . $r['id'],
            'osId'       => (int) $r['id'],
            'title'      => $titulo,
            'start'      => $r['data_agendamento'],
            'color'      => $cor,
            'tipo'       => 'agendamento',
            'extendedProps' => [
                'status'      => $r['situacao_local'],
                'prioridade'  => $r['prioridade'],
                'tecnico'     => $r['tecnico_nome'] ?? '',
                'os_id'       => (int) $r['id'],
                'tipo'        => 'agendamento',
            ],
        ];
    }

    // Evento de prazo (SLA) — cor diferente se atrasado
    if ($r['data_prazo'] && $r['situacao_local'] !== 'concluido') {
        $atrasado  = strtotime($r['data_prazo']) < time();
        $eventos[] = [
            'id'         => 'pr_' . $r['id'],
            'osId'       => (int) $r['id'],
            'title'      => 'Prazo: ' . $titulo,
            'start'      => $r['data_prazo'],
            'color'      => $atrasado ? '#dc2626' : '#f59e0b',
            'tipo'       => 'prazo',
            'extendedProps' => [
                'status'     => $r['situacao_local'],
                'prioridade' => $r['prioridade'],
                'tecnico'    => $r['tecnico_nome'] ?? '',
                'os_id'      => (int) $r['id'],
                'tipo'       => 'prazo',
            ],
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($eventos);
