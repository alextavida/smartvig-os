<?php
/**
 * POST /api/os/reagendar (tecnico ou gestor)
 * Corpo: { "os_id": 1, "nova_data": "2025-08-15", "motivo"?: "..." }
 * Reagenda a OS e notifica a outra parte (tecnico->gestor ou gestor->tecnicos).
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
exigirCampos($dados, ['os_id', 'nova_data']);

$osId = (int) $dados['os_id'];
$novaData = (string) $dados['nova_data'];
$motivo = (string) ($dados['motivo'] ?? '');

$pdo = obterConexao();
$os = buscarOsOuFalhar($pdo, $osId, $payload);

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE ordens_servico SET situacao_local = "reagendado", data_agendamento = :data WHERE id = :id')
        ->execute(['data' => $novaData, 'id' => $osId]);

    registrarHistorico($pdo, $osId, (int) $payload['usuario_id'], 'os_reagendada', "Nova data: {$novaData}. " . $motivo);

    $detalheNotif = 'Nova data: ' . $novaData . ($motivo !== '' ? ' - Motivo: ' . $motivo : '');
    if ($payload['perfil'] === 'tecnico') {
        notificarGestores($pdo, $osId, 'reagendada', 'OS reagendada pelo tecnico', 'Tecnico ' . $payload['nome'] . ' reagendou a OS #' . $osId . '. ' . $detalheNotif);
    } else {
        notificarTecnicosDaOs($pdo, $osId, 'reagendada', 'OS reagendada pelo gestor', $detalheNotif);
    }

    $erroSincronizacao = null;
    $situacaoGcId = obterSituacaoGcId('reagendado');
    if ($os['gc_os_id'] > 0) {
        try {
            $gc = new GestaoClickAPI();
            $dadosGc = ['data' => $novaData];
            if ($situacaoGcId !== null) {
                $dadosGc['situacao_id'] = $situacaoGcId;
            }
            $gc->atualizarOS((int) $os['gc_os_id'], $dadosGc);
            $pdo->prepare('UPDATE ordens_servico SET sincronizado_gc = 1 WHERE id = :id')->execute(['id' => $osId]);
        } catch (GestaoClickApiException $e) {
            $erroSincronizacao = $e->getMessage();
            registrarHistorico($pdo, $osId, null, 'falha_sincronizacao_gc', $erroSincronizacao);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderErro('Falha ao reagendar OS: ' . $e->getMessage(), 500);
}

responderSucesso(['os_id' => $osId, 'erro_sincronizacao' => $erroSincronizacao]);
