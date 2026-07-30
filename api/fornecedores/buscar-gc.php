<?php
/**
 * GET /api/fornecedores/buscar-gc?busca=X&pagina=1
 * Busca fornecedores no GestaoClick por nome.
 * Acessivel apenas por gestor e comprador.
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

$payload = exigirAutenticacao();
if (!in_array($payload['perfil'], ['gestor', 'supervisor', 'comprador'], true)) {
    responderErro('Acesso negado.', 403);
}

$busca  = trim($_GET['busca'] ?? '');
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

try {
    $gc = new GestaoClickAPI();
    $resposta = $gc->listarFornecedores($pagina, $busca);

    // GC retorna { data: [...], meta: {...} } ou { data: [...] }
    $itens = $resposta['data'] ?? $resposta['dados'] ?? $resposta ?? [];
    if (!is_array($itens)) { $itens = []; }

    $lista = [];
    foreach ($itens as $f) {
        $lista[] = [
            'gc_id'      => $f['id']             ?? null,
            'nome'       => $f['nome']            ?? $f['razao_social'] ?? '',
            'cnpj'       => $f['cnpj']            ?? $f['cpf_cnpj']    ?? '',
            'email'      => $f['email']           ?? '',
            'telefone'   => $f['telefone']        ?? $f['celular']      ?? '',
            'contato'    => $f['contato']         ?? $f['responsavel']  ?? '',
        ];
    }

    // Filtra itens sem nome
    $lista = array_values(array_filter($lista, fn($f) => $f['nome'] !== ''));

    // Informa quais já estão cadastrados localmente (por CNPJ)
    if (!empty($lista)) {
        $pdo = obterConexao();
        $cnpjs = array_filter(array_column($lista, 'cnpj'));
        $jaExistem = [];
        if (!empty($cnpjs)) {
            $placeholders = implode(',', array_fill(0, count($cnpjs), '?'));
            $stmt = $pdo->prepare("SELECT cnpj FROM fornecedores WHERE cnpj IN ({$placeholders})");
            $stmt->execute(array_values($cnpjs));
            $jaExistem = array_column($stmt->fetchAll(), 'cnpj');
        }
        foreach ($lista as &$f) {
            $f['ja_cadastrado'] = $f['cnpj'] && in_array($f['cnpj'], $jaExistem, true);
        }
        unset($f);
    }

    $proxima = isset($resposta['meta']['proxima_pagina']) ? (bool) $resposta['meta']['proxima_pagina'] : false;

    responderSucesso([
        'fornecedores' => $lista,
        'pagina'       => $pagina,
        'tem_mais'     => $proxima,
    ]);
} catch (GestaoClickApiException $e) {
    responderErro($e->getMessage(), 502);
}
