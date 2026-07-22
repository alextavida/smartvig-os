<?php
/**
 * POST /api/clientes/criar
 * Corpo: { nome, telefone, email, endereco, numero, bairro, cidade, estado, cep }
 * Cria um novo cliente no GestaoClick e devolve o ID criado.
 * Requer autenticacao (gestor).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/gestaoclick.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Metodo nao permitido. Use POST.', 405);
}

$payload = exigirAutenticacao();
exigirPerfil($payload, ['gestor']);

$dados = lerCorpoJson();
exigirCampos($dados, ['nome']);

try {
    $gc = new GestaoClickAPI();
    $resultado = $gc->criarCliente([
        'nome'     => $dados['nome'],
        'telefone' => $dados['telefone'] ?? '',
        'email'    => $dados['email'] ?? '',
        'endereco' => $dados['endereco'] ?? '',
        'numero'   => $dados['numero'] ?? '',
        'bairro'   => $dados['bairro'] ?? '',
        'cidade'   => $dados['cidade'] ?? '',
        'estado'   => $dados['estado'] ?? '',
        'cep'      => $dados['cep'] ?? '',
    ]);

    $idCriado = $resultado['data']['id'] ?? $resultado['id'] ?? null;
    $nomeCriado = $resultado['data']['nome'] ?? $resultado['nome'] ?? $dados['nome'];

    responderSucesso([
        'id'   => $idCriado,
        'nome' => $nomeCriado,
    ], 201);
} catch (GestaoClickApiException $e) {
    responderErro('Falha ao criar cliente no GestaoClick: ' . $e->getMessage(), 502);
}
