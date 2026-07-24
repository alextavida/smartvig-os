<?php
/**
 * GET /api/produtos/buscar-gc?busca=X
 * Busca produtos no catalogo do GestaoClick por nome/codigo.
 * Acessivel por gestor e tecnico.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/gestaoclick.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro('Metodo nao permitido.', 405);
}

exigirAutenticacao();

$busca = trim($_GET['busca'] ?? '');
if (strlen($busca) < 2) {
    responderErro('Informe ao menos 2 caracteres para busca.', 422);
}

try {
    $gc = new GestaoClickAPI();
    $resposta = $gc->listarProdutos(1, $busca);
    $itens = $resposta['data'] ?? $resposta['dados'] ?? [];

    $lista = [];
    foreach ((is_array($itens) ? $itens : []) as $p) {
        $lista[] = [
            'id'          => $p['id']          ?? null,
            'nome'        => $p['nome']         ?? $p['descricao'] ?? '',
            'valor_venda' => (float) ($p['valor_venda'] ?? $p['preco'] ?? $p['valor'] ?? 0),
            'codigo'      => $p['codigo']       ?? '',
        ];
    }

    responderSucesso(['produtos' => $lista]);
} catch (GestaoClickApiException $e) {
    responderErro($e->getMessage(), 502);
}
