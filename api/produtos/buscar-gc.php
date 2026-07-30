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
        // Usa preço de CUSTO para solicitações internas de compra.
        // GC retorna valor_custo; valor_venda é o preço de varejo.
        $valorCusto = (float) ($p['valor_custo'] ?? $p['preco_custo'] ?? $p['custo'] ?? 0);
        $lista[] = [
            'id'          => $p['id']          ?? null,
            'nome'        => $p['nome']         ?? $p['descricao'] ?? '',
            'valor_custo' => $valorCusto,
            'codigo'      => $p['codigo']       ?? $p['referencia'] ?? '',
            'unidade'     => $p['unidade']      ?? $p['unidade_medida'] ?? 'UN',
        ];
    }

    responderSucesso(['produtos' => $lista]);
} catch (GestaoClickApiException $e) {
    responderErro($e->getMessage(), 502);
}
