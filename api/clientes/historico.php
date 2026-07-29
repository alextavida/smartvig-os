<?php
/**
 * GET /api/clientes/historico?gc_cliente_id=123
 * Retorna historico de OS do cliente (local) + dados basicos do cliente (GC).
 * Tecnico e gestor podem acessar.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/gestaoclick.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro('Metodo nao permitido. Use GET.', 405);
}

$payload = exigirAutenticacao();

$gcClienteId = (int) ($_GET['gc_cliente_id'] ?? 0);
if ($gcClienteId <= 0) {
    responderErro('gc_cliente_id invalido.', 422);
}

$pdo = obterConexao();

// OS locais do cliente, mais recentes primeiro
$stmt = $pdo->prepare(
    "SELECT os.id, os.situacao_local, os.data_agendamento, os.data_conclusao,
            os.prioridade, os.observacoes, os.gc_os_id, os.criado_em,
            u.nome AS tecnico_nome
     FROM ordens_servico os
     LEFT JOIN usuarios u ON u.id = os.tecnico_id
     WHERE os.gc_cliente_id = :gc_cli
     ORDER BY os.criado_em DESC
     LIMIT 30"
);
$stmt->execute(['gc_cli' => $gcClienteId]);
$osLocais = $stmt->fetchAll();

$ordens = [];
foreach ($osLocais as $os) {
    $ordens[] = [
        'id'               => (int) $os['id'],
        'gc_os_id'         => $os['gc_os_id'] ? (int) $os['gc_os_id'] : null,
        'situacao_local'   => $os['situacao_local'],
        'data_agendamento' => $os['data_agendamento'],
        'data_conclusao'   => $os['data_conclusao'],
        'prioridade'       => $os['prioridade'],
        'observacoes'      => $os['observacoes'],
        'tecnico_nome'     => $os['tecnico_nome'],
        'criado_em'        => $os['criado_em'],
    ];
}

// Dados do cliente no GC
$clienteGc = null;
try {
    $resposta = (new GestaoClickAPI())->visualizarCliente($gcClienteId);
    $c = $resposta['data'] ?? $resposta;
    if (is_array($c) && isset($c['nome'])) {
        $end = $c['enderecos'][0]['endereco'] ?? [];
        $endStr = implode(', ', array_filter([
            trim(($end['logradouro'] ?? '') . ' ' . ($end['numero'] ?? '')),
            $end['bairro'] ?? '',
            $end['nome_cidade'] ?? '',
            $end['estado'] ?? '',
        ]));
        $clienteGc = [
            'id'       => $c['id'] ?? $gcClienteId,
            'nome'     => $c['nome'] ?? $c['razao_social'] ?? '',
            'email'    => $c['email'] ?? '',
            'telefone' => $c['telefone'] ?? $c['celular'] ?? '',
            'endereco' => trim($endStr),
        ];
    }
} catch (GestaoClickApiException $e) {
    // Sem dados do GC — retorna apenas dados locais
}

responderSucesso([
    'cliente'   => $clienteGc,
    'ordens'    => $ordens,
    'total'     => count($ordens),
]);
