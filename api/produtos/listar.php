<?php
/**
 * GET /api/produtos/listar?busca=texto&pagina=1
 * Busca produtos diretamente na API do GestaoClick.
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

exigirAutenticacao();

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$busca = trim((string) ($_GET['busca'] ?? ''));

try {
    $gc = new GestaoClickAPI();
    $resposta = $gc->listarProdutos($pagina, $busca);
} catch (GestaoClickApiException $e) {
    responderErro('Falha ao buscar produtos no GestaoClick: ' . $e->getMessage(), 502);
}

responderSucesso(['produtos' => $resposta['data'] ?? $resposta['dados'] ?? $resposta]);
