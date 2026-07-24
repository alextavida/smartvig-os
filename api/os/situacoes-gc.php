<?php
/**
 * GET /api/os/situacoes-gc
 * Retorna situações cadastradas no GestãoClick + contagem local de OS por situacao_local.
 * Usado pelo Dashboard do gestor para exibir o painel "Situações GestãoClick".
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/gestaoclick.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro('Metodo nao permitido.', 405);
}

$payload = exigirAutenticacao();
if ($payload['perfil'] !== 'gestor') {
    responderErro('Acesso restrito ao gestor.', 403);
}

$pdo = obterConexao();

// Contagem local por situacao_local
$stmtLocal = $pdo->query(
    "SELECT situacao_local, COUNT(*) AS total FROM ordens_servico GROUP BY situacao_local"
);
$contagensLocal = [];
foreach ($stmtLocal->fetchAll() as $row) {
    $contagensLocal[$row['situacao_local']] = (int) $row['total'];
}

// Total de OS sincronizadas com GC
$totalSinc = (int) $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE gc_os_id > 0")->fetchColumn();

// Situações do GestãoClick (silencioso se falhar)
$gcSituacoes = [];
try {
    $gc      = new GestaoClickAPI();
    $resposta = $gc->listarSituacoes();
    $itens   = $resposta['data'] ?? $resposta ?? [];
    foreach ($itens as $sit) {
        if (!is_array($sit)) { continue; }
        $gcSituacoes[] = [
            'id'   => $sit['id'] ?? null,
            'nome' => $sit['nome'] ?? $sit['descricao'] ?? '—',
        ];
    }
} catch (GestaoClickApiException) {
    // Retorna sem situações GC mas com contagens locais
}

// Mapeamento local → GC (aproximado)
$mapa = [
    'aberto'       => ['Aberto', 'Aguardando', 'Pendente'],
    'em_andamento' => ['Em andamento', 'Em atendimento', 'Em execucao'],
    'pausado'      => ['Pausado', 'Aguardando retorno'],
    'reagendado'   => ['Reagendado', 'Remarcado'],
    'concluido'    => ['Concluido', 'Finalizado', 'Encerrado'],
    'cancelado'    => ['Cancelado', 'Recusado'],
];

responderSucesso([
    'gc_situacoes'    => $gcSituacoes,
    'contagens_local' => $contagensLocal,
    'total_sincronizado' => $totalSinc,
    'mapa_local_gc'   => $mapa,
]);
