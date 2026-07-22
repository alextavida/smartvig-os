<?php
/**
 * POST /api/os/descricao (tecnico ou gestor)
 * Corpo: { "os_id": 1, "observacoes": "Laudo/descricao do atendimento" }
 * Salva a descricao/laudo na OS e sincroniza com o GestaoClick.
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
exigirCampos($dados, ['os_id', 'observacoes']);

$osId = (int) $dados['os_id'];
$observacoes = (string) $dados['observacoes'];

$pdo = obterConexao();
$os = buscarOsOuFalhar($pdo, $osId, $payload);

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE ordens_servico SET observacoes = :observacoes WHERE id = :id')
        ->execute(['observacoes' => $observacoes, 'id' => $osId]);

    registrarHistorico($pdo, $osId, (int) $payload['usuario_id'], 'descricao_atualizada', $observacoes);

    $erroSincronizacao = null;
    if ($os['gc_os_id'] > 0) {
        try {
            (new GestaoClickAPI())->atualizarOS((int) $os['gc_os_id'], ['observacoes' => $observacoes]);
            $pdo->prepare('UPDATE ordens_servico SET sincronizado_gc = 1 WHERE id = :id')->execute(['id' => $osId]);
        } catch (GestaoClickApiException $e) {
            $erroSincronizacao = $e->getMessage();
            registrarHistorico($pdo, $osId, null, 'falha_sincronizacao_gc', $erroSincronizacao);
        }
    }

    if ($payload['perfil'] === 'tecnico') {
        notificarGestores($pdo, $osId, 'descricao', 'Descricao atualizada', 'Tecnico ' . $payload['nome'] . ' atualizou a descricao da OS #' . $osId);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderErro('Falha ao salvar descricao: ' . $e->getMessage(), 500);
}

responderSucesso(['os_id' => $osId, 'erro_sincronizacao' => $erroSincronizacao]);
