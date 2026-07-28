<?php
/**
 * POST /api/os/cancelar
 * Corpo: { "os_id": 1, "motivo": "..." }
 * Cancela uma OS (define situacao_local = 'cancelado'). Apenas gestor.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/os_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Metodo nao permitido. Use POST.', 405);
}

try {
    $payload = exigirAutenticacao();
    exigirPerfil($payload, ['gestor']);
    $dados = lerCorpoJson();
    exigirCampos($dados, ['os_id']);

    $osId   = (int) $dados['os_id'];
    $motivo = trim((string) ($dados['motivo'] ?? ''));
    $pdo    = obterConexao();

    $stmt = $pdo->prepare('SELECT id, situacao_local FROM ordens_servico WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $osId]);
    $os = $stmt->fetch();

    if (!$os) { responderErro('OS nao encontrada.', 404); }
    if ($os['situacao_local'] === 'cancelado') { responderErro('OS ja esta cancelada.', 409); }

    $pdo->prepare('UPDATE ordens_servico SET situacao_local = "cancelado" WHERE id = :id')
        ->execute(['id' => $osId]);

    registrarHistorico($pdo, $osId, (int) $payload['usuario_id'], 'os_cancelada', $motivo ?: 'Cancelada pelo gestor');

    responderSucesso(['os_id' => $osId]);

} catch (Throwable $e) {
    responderErro('Erro interno: ' . $e->getMessage(), 500);
}
