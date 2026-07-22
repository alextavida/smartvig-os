<?php
/**
 * POST /api/os/pausar (tecnico)
 * Corpo: { "os_id": 1, "motivo": "..." }
 * Pausa a OS e notifica os gestores.
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
exigirCampos($dados, ['os_id', 'motivo']);

$osId = (int) $dados['os_id'];
$motivo = (string) $dados['motivo'];

$pdo = obterConexao();
$os = buscarOsOuFalhar($pdo, $osId, $payload);

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE ordens_servico SET situacao_local = "pausado", motivo_pausa = :motivo WHERE id = :id')
        ->execute(['motivo' => $motivo, 'id' => $osId]);

    registrarHistorico($pdo, $osId, (int) $payload['usuario_id'], 'os_pausada', $motivo);

    notificarGestores($pdo, $osId, 'pausada', 'OS pausada', 'Tecnico ' . $payload['nome'] . ' pausou a OS #' . $osId . '. Motivo: ' . $motivo);

    $erroSincronizacao = null;
    $situacaoGcId = obterSituacaoGcId('pausado');
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
    responderErro('Falha ao pausar OS: ' . $e->getMessage(), 500);
}

responderSucesso(['os_id' => $osId, 'erro_sincronizacao' => $erroSincronizacao]);
