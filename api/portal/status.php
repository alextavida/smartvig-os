<?php
/**
 * GET /api/portal/status?token=XXX
 * Endpoint PÚBLICO (sem autenticação) — retorna dados limitados da OS para o portal do cliente.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro('Metodo nao permitido.', 405);
}

$token = trim($_GET['token'] ?? '');
if ($token === '' || strlen($token) < 8) {
    responderErro('Token invalido.', 422);
}

$pdo = obterConexao();

$stmt = $pdo->prepare(
    "SELECT os.id, os.cliente_nome, os.situacao_local, os.data_agendamento, os.data_conclusao,
            os.prioridade, u.nome AS tecnico_nome
     FROM ordens_servico os
     LEFT JOIN usuarios u ON u.id = os.tecnico_id
     WHERE os.portal_token = :token
     LIMIT 1"
);
$stmt->execute(['token' => $token]);
$os = $stmt->fetch();

if (!$os) {
    responderErro('Link invalido ou expirado.', 404);
}

// Histórico filtrado: só ações visíveis ao cliente (sem detalhes internos)
$acoesVisiveis = [
    'os_criada'    => 'OS aberta',
    'os_iniciada'  => 'Técnico a caminho / em atendimento',
    'os_pausada'   => 'Atendimento pausado',
    'os_reagendada'=> 'OS reagendada',
    'os_encerrada' => 'Atendimento concluído',
];

$stmtHist = $pdo->prepare(
    "SELECT acao, criado_em
     FROM historico_os
     WHERE os_id = :os_id AND acao IN (" .
    implode(',', array_fill(0, count($acoesVisiveis), '?'))
    . ") ORDER BY criado_em ASC"
);
$stmtHist->execute([$os['id'], ...array_keys($acoesVisiveis)]);
$historico = [];
foreach ($stmtHist->fetchAll() as $h) {
    $historico[] = [
        'descricao' => $acoesVisiveis[$h['acao']] ?? $h['acao'],
        'quando'    => $h['criado_em'],
    ];
}

// Progresso: 0=aberto, 1=iniciado, 2=concluído
$progressoMapa = [
    'aberto' => 0, 'reagendado' => 0,
    'em_andamento' => 1, 'pausado' => 1,
    'concluido' => 2, 'cancelado' => -1,
];

responderSucesso([
    // Dados limitados — não expõe telefone, endereço, produtos, valores
    'cliente_nome_curto' => explode(' ', trim($os['cliente_nome']))[0],
    'situacao'           => $os['situacao_local'],
    'data_agendamento'   => $os['data_agendamento'],
    'data_conclusao'     => $os['data_conclusao'],
    'tecnico_nome'       => $os['tecnico_nome'] ?? null,
    'progresso'          => $progressoMapa[$os['situacao_local']] ?? 0,
    'historico'          => $historico,
]);
