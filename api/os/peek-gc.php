<?php
/**
 * GET /api/os/peek-gc (somente gestor)
 * Retorna a estrutura bruta de 1 OS do GestaoClick para depuracao.
 * Nao grava nada no banco.
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
exigirPerfil($payload, ['gestor']);

try {
    $gc     = new GestaoClickAPI();
    $result = $gc->listarOS(1);
    $meta   = $result['meta'] ?? null;
    $sample = $result['data'][0] ?? $result['dados'][0] ?? null;

    // Chaves disponíveis no primeiro OS (para depuração)
    $chavesOs = $sample ? array_keys($sample) : [];

    responderSucesso([
        'meta'        => $meta,
        'chaves_os'   => $chavesOs,
        'sample_os'   => $sample,
    ]);
} catch (GestaoClickApiException $e) {
    responderErro($e->getMessage(), 502);
}
