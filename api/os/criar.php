<?php
/**
 * POST /api/os/criar (somente gestor)
 * Corpo: {
 *   cliente_nome, cliente_endereco, cliente_telefone, data_agendamento,
 *   observacoes (opcional), gc_cliente_id (opcional),
 *   tecnicos: [ { "tecnico_id": 1, "responsavel": true }, ... ]
 * }
 * Cria a OS localmente, atribui os tecnicos e tenta sincronizar com o GestaoClick.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/gestaoclick.php';
require_once __DIR__ . '/../../config/os_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Metodo nao permitido. Use POST.', 405);
}

$payload = exigirAutenticacao();
exigirPerfil($payload, ['gestor']);

$dados = lerCorpoJson();
exigirCampos($dados, ['cliente_nome', 'data_agendamento', 'tecnicos']);

$prioridade = $dados['prioridade'] ?? 'baixo';
if (!in_array($prioridade, ['baixo', 'intermediario', 'urgente'], true)) {
    $prioridade = 'baixo';
}

$tecnicos = $dados['tecnicos'];
if (!is_array($tecnicos) || empty($tecnicos)) {
    responderErro('Informe ao menos um tecnico em "tecnicos".', 422);
}

$temResponsavel = false;
foreach ($tecnicos as $t) {
    if (!empty($t['responsavel'])) {
        $temResponsavel = true;
        break;
    }
}
if (!$temResponsavel) {
    $tecnicos[0]['responsavel'] = true;
}

$pdo = obterConexao();
$pdo->beginTransaction();

try {
    $tecnicoResponsavelId = null;
    foreach ($tecnicos as $t) {
        if (!empty($t['responsavel'])) {
            $tecnicoResponsavelId = (int) $t['tecnico_id'];
            break;
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO ordens_servico
            (gc_os_id, gc_cliente_id, codigo, cliente_nome, cliente_endereco, cliente_telefone,
             tecnico_id, situacao_local, prioridade, data_agendamento, observacoes, sincronizado_gc)
         VALUES (0, :gc_cliente_id, :codigo, :cliente_nome, :cliente_endereco, :cliente_telefone,
                 :tecnico_id, "aberto", :prioridade, :data_agendamento, :observacoes, 0)'
    );
    $stmt->execute([
        'gc_cliente_id' => $dados['gc_cliente_id'] ?? null,
        'codigo' => $dados['codigo'] ?? null,
        'cliente_nome' => $dados['cliente_nome'],
        'cliente_endereco' => $dados['cliente_endereco'] ?? null,
        'cliente_telefone' => $dados['cliente_telefone'] ?? null,
        'tecnico_id' => $tecnicoResponsavelId,
        'prioridade' => $prioridade,
        'data_agendamento' => $dados['data_agendamento'],
        'observacoes' => $dados['observacoes'] ?? null,
    ]);

    $osId = (int) $pdo->lastInsertId();

    $stmtTec = $pdo->prepare(
        'INSERT INTO os_tecnicos (os_id, tecnico_id, responsavel) VALUES (:os_id, :tecnico_id, :responsavel)'
    );
    foreach ($tecnicos as $t) {
        $stmtTec->execute([
            'os_id' => $osId,
            'tecnico_id' => (int) $t['tecnico_id'],
            'responsavel' => !empty($t['responsavel']) ? 1 : 0,
        ]);
    }

    registrarHistorico($pdo, $osId, (int) $payload['usuario_id'], 'os_criada', 'OS criada pelo gestor ' . $payload['nome']);

    foreach ($tecnicos as $t) {
        criarNotificacao(
            $pdo,
            (int) $t['tecnico_id'],
            $osId,
            'nova_os',
            'Nova OS atribuida a voce',
            'Cliente: ' . $dados['cliente_nome'] . ' - Agendada para ' . $dados['data_agendamento']
        );
    }

    // Tenta sincronizar a criacao com o GestaoClick. Falha aqui NAO desfaz a criacao local.
    $gcOsId = 0;
    $erroSincronizacao = null;
    try {
        $gc = new GestaoClickAPI();
        $situacaoAbertoId = obterSituacaoGcId('aberto');
        $respostaGc = $gc->criarOS([
            'cliente_id' => $dados['gc_cliente_id'] ?? null,
            'data' => $dados['data_agendamento'],
            'observacoes' => $dados['observacoes'] ?? '',
            'situacao_id' => $situacaoAbertoId,
        ]);
        $gcOsId = (int) ($respostaGc['data']['id'] ?? $respostaGc['id'] ?? 0);

        if ($gcOsId > 0) {
            $stmtSync = $pdo->prepare('UPDATE ordens_servico SET gc_os_id = :gc_os_id, sincronizado_gc = 1 WHERE id = :id');
            $stmtSync->execute(['gc_os_id' => $gcOsId, 'id' => $osId]);
        }
    } catch (GestaoClickApiException $e) {
        $erroSincronizacao = $e->getMessage();
        registrarHistorico($pdo, $osId, null, 'falha_sincronizacao_gc', $erroSincronizacao);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderErro('Falha ao criar OS: ' . $e->getMessage(), 500);
}

responderSucesso([
    'os_id' => $osId,
    'gc_os_id' => $gcOsId,
    'sincronizado_gc' => $gcOsId > 0,
    'erro_sincronizacao' => $erroSincronizacao,
], 201);
