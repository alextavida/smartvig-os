<?php
/**
 * POST /api/os/iniciar (tecnico)
 * Corpo: { "os_id": 1 }
 * Marca a OS como "em_andamento" quando o tecnico inicia o atendimento.
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
$dados = lerCorpoJson();
exigirCampos($dados, ['os_id']);

$osId = (int) $dados['os_id'];
$pdo = obterConexao();
$os = buscarOsOuFalhar($pdo, $osId, $payload);

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE ordens_servico SET situacao_local = "em_andamento" WHERE id = :id')->execute(['id' => $osId]);

    registrarHistorico($pdo, $osId, (int) $payload['usuario_id'], 'os_iniciada', 'Tecnico ' . $payload['nome'] . ' iniciou o atendimento.');
    notificarGestores($pdo, $osId, 'iniciada', 'OS iniciada', 'Tecnico ' . $payload['nome'] . ' iniciou a OS #' . $osId);

    $erroSincronizacao = null;
    $situacaoGcId = obterSituacaoGcId('em_andamento');
    if ($os['gc_os_id'] > 0 && $situacaoGcId !== null) {
        try {
            (new GestaoClickAPI())->atualizarOS((int) $os['gc_os_id'], ['situacao_id' => $situacaoGcId]);
            $pdo->prepare('UPDATE ordens_servico SET sincronizado_gc = 1 WHERE id = :id')->execute(['id' => $osId]);
        } catch (GestaoClickApiException $e) {
            $erroSincronizacao = $e->getMessage();
            registrarHistorico($pdo, $osId, null, 'falha_sincronizacao_gc', $erroSincronizacao);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderErro('Falha ao iniciar OS: ' . $e->getMessage(), 500);
}

responderSucesso(['os_id' => $osId, 'erro_sincronizacao' => $erroSincronizacao]);
