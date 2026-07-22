<?php
/**
 * POST /api/produtos/adicionar_os
 * Corpo: { "os_id": 1, "produto_id": 45, "nome": "...", "quantidade": 2, "valor_venda": 150.00 }
 * Adiciona um produto ao array produtos_json da OS local.
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

$payload = exigirAutenticacao();
$dados = lerCorpoJson();
exigirCampos($dados, ['os_id', 'produto_id', 'nome', 'quantidade', 'valor_venda']);

$osId = (int) $dados['os_id'];
$pdo = obterConexao();
$os = buscarOsOuFalhar($pdo, $osId, $payload);

$produtos = $os['produtos_json'] ? json_decode($os['produtos_json'], true) : [];

$produtos[] = [
    'produto_id' => (int) $dados['produto_id'],
    'nome' => (string) $dados['nome'],
    'quantidade' => (float) $dados['quantidade'],
    'valor_venda' => (float) $dados['valor_venda'],
];

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE ordens_servico SET produtos_json = :produtos WHERE id = :id')
        ->execute(['produtos' => json_encode($produtos, JSON_UNESCAPED_UNICODE), 'id' => $osId]);

    registrarHistorico(
        $pdo,
        $osId,
        (int) $payload['usuario_id'],
        'produto_adicionado',
        $dados['nome'] . ' x' . $dados['quantidade'] . ' (R$ ' . number_format((float) $dados['valor_venda'], 2, ',', '.') . ')'
    );

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderErro('Falha ao adicionar produto: ' . $e->getMessage(), 500);
}

responderSucesso(['produtos' => $produtos]);
