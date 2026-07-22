<?php
/**
 * POST /api/os/atualizar (somente gestor)
 * Corpo: { "os_id": 1, "cliente_nome"?, "cliente_endereco"?, "cliente_telefone"?,
 *          "data_agendamento"?, "observacoes"?, "situacao_local"? }
 * Atualiza campos da OS localmente e sincroniza com o GestaoClick.
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
exigirCampos($dados, ['os_id']);

$osId = (int) $dados['os_id'];
$pdo = obterConexao();
$os = buscarOsOuFalhar($pdo, $osId, $payload);

$camposPermitidos = ['cliente_nome', 'cliente_endereco', 'cliente_telefone', 'data_agendamento', 'observacoes', 'situacao_local', 'prioridade'];
$situacoesValidas = ['aberto', 'em_andamento', 'pausado', 'reagendado', 'concluido', 'cancelado'];
$prioridadesValidas = ['baixo', 'intermediario', 'urgente'];

$sets = [];
$parametros = ['id' => $osId];
foreach ($camposPermitidos as $campo) {
    if (array_key_exists($campo, $dados)) {
        if ($campo === 'situacao_local' && !in_array($dados[$campo], $situacoesValidas, true)) {
            responderErro('situacao_local invalida.', 422);
        }
        if ($campo === 'prioridade' && !in_array($dados[$campo], $prioridadesValidas, true)) {
            responderErro('prioridade invalida. Use: baixo, intermediario ou urgente.', 422);
        }
        $sets[] = "{$campo} = :{$campo}";
        $parametros[$campo] = $dados[$campo];
    }
}

if (empty($sets)) {
    responderErro('Nenhum campo para atualizar foi informado.', 422);
}

$pdo->beginTransaction();
try {
    $sql = 'UPDATE ordens_servico SET ' . implode(', ', $sets) . ' WHERE id = :id';
    $pdo->prepare($sql)->execute($parametros);

    registrarHistorico($pdo, $osId, (int) $payload['usuario_id'], 'os_atualizada', 'Campos atualizados: ' . implode(', ', array_keys($parametros)));

    $erroSincronizacao = null;
    if ($os['gc_os_id'] > 0) {
        try {
            $gc = new GestaoClickAPI();
            $dadosGc = [];
            if (isset($dados['data_agendamento'])) {
                $dadosGc['data'] = $dados['data_agendamento'];
            }
            if (isset($dados['observacoes'])) {
                $dadosGc['observacoes'] = $dados['observacoes'];
            }
            if (isset($dados['situacao_local'])) {
                $situacaoGcId = obterSituacaoGcId($dados['situacao_local']);
                if ($situacaoGcId !== null) {
                    $dadosGc['situacao_id'] = $situacaoGcId;
                }
            }
            if (!empty($dadosGc)) {
                $gc->atualizarOS((int) $os['gc_os_id'], $dadosGc);
                $pdo->prepare('UPDATE ordens_servico SET sincronizado_gc = 1 WHERE id = :id')->execute(['id' => $osId]);
            }
        } catch (GestaoClickApiException $e) {
            $erroSincronizacao = $e->getMessage();
            registrarHistorico($pdo, $osId, null, 'falha_sincronizacao_gc', $erroSincronizacao);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderErro('Falha ao atualizar OS: ' . $e->getMessage(), 500);
}

responderSucesso(['os_id' => $osId, 'erro_sincronizacao' => $erroSincronizacao]);
