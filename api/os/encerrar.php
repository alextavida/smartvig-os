<?php
/**
 * POST /api/os/encerrar (tecnico)
 * Corpo: { "os_id": 1, "laudo_final": "...", "forma_pagamento"?: "dinheiro" }
 * Encerra a OS, sincroniza com o GestaoClick e gera recebimento se houver produtos com valor.
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
exigirCampos($dados, ['os_id', 'laudo_final']);

$osId = (int) $dados['os_id'];
$laudoFinal = (string) $dados['laudo_final'];

$pdo = obterConexao();
$os = buscarOsOuFalhar($pdo, $osId, $payload);

$pdo->beginTransaction();
try {
    $pdo->prepare(
        'UPDATE ordens_servico
         SET situacao_local = "concluido", observacoes = :observacoes, data_conclusao = NOW()
         WHERE id = :id'
    )->execute(['observacoes' => $laudoFinal, 'id' => $osId]);

    registrarHistorico($pdo, $osId, (int) $payload['usuario_id'], 'os_encerrada', $laudoFinal);
    notificarGestores($pdo, $osId, 'encerrada', 'OS encerrada', 'Tecnico ' . $payload['nome'] . ' finalizou o atendimento da OS #' . $osId);

    // Gera token NPS para avaliacao do cliente (se ainda nao tiver)
    if (!$os['nps_token']) {
        $npsToken = bin2hex(random_bytes(16));
        $pdo->prepare('UPDATE ordens_servico SET nps_token = :t WHERE id = :id')
            ->execute([':t' => $npsToken, ':id' => $osId]);
    }

    $erroSincronizacao = null;
    if ($os['gc_os_id'] > 0) {
        try {
            $gc = new GestaoClickAPI();
            $situacaoGcId = obterSituacaoGcId('concluido');
            $dadosGc = ['observacoes' => $laudoFinal];
            if ($situacaoGcId !== null) {
                $dadosGc['situacao_id'] = $situacaoGcId;
            }
            $gc->atualizarOS((int) $os['gc_os_id'], $dadosGc);
            $pdo->prepare('UPDATE ordens_servico SET sincronizado_gc = 1 WHERE id = :id')->execute(['id' => $osId]);

            $produtos = $os['produtos_json'] ? json_decode($os['produtos_json'], true) : [];
            $valorTotal = 0.0;
            foreach ($produtos as $produto) {
                $valorTotal += (float) ($produto['valor_venda'] ?? 0) * (float) ($produto['quantidade'] ?? 1);
            }

            if ($valorTotal > 0) {
                $gc->criarRecebimento([
                    'ordem_servico_id' => $os['gc_os_id'],
                    'valor' => $valorTotal,
                    'forma_pagamento' => $dados['forma_pagamento'] ?? 'dinheiro',
                ]);
                registrarHistorico($pdo, $osId, null, 'recebimento_gerado', 'Valor: ' . number_format($valorTotal, 2, ',', '.'));
            }
        } catch (GestaoClickApiException $e) {
            $erroSincronizacao = $e->getMessage();
            registrarHistorico($pdo, $osId, null, 'falha_sincronizacao_gc', $erroSincronizacao);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderErro('Falha ao encerrar OS: ' . $e->getMessage(), 500);
}

responderSucesso(['os_id' => $osId, 'erro_sincronizacao' => $erroSincronizacao]);
