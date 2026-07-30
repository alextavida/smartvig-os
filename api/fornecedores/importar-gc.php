<?php
/**
 * POST /api/fornecedores/importar-gc
 * Importa um fornecedor do GestaoClick para a tabela local.
 * Corpo: { gc_id, nome, cnpj, email, telefone, contato }
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Metodo nao permitido.', 405);
}

$payload = exigirAutenticacao();
if (!in_array($payload['perfil'], ['gestor', 'supervisor', 'comprador'], true)) {
    responderErro('Acesso negado.', 403);
}

$dados = lerCorpoJson();
if (empty($dados['nome'])) {
    responderErro('Nome do fornecedor obrigatorio.', 422);
}

$pdo  = obterConexao();
$nome = trim((string) $dados['nome']);
$cnpj = !empty($dados['cnpj']) ? trim((string) $dados['cnpj']) : null;

// Verifica se já existe pelo CNPJ
if ($cnpj) {
    $existe = $pdo->prepare('SELECT id FROM fornecedores WHERE cnpj = :cnpj LIMIT 1');
    $existe->execute(['cnpj' => $cnpj]);
    if ($row = $existe->fetch()) {
        responderSucesso(['id' => (int) $row['id'], 'ja_existia' => true]);
    }
}

$stmt = $pdo->prepare(
    'INSERT INTO fornecedores (nome, cnpj, email, telefone, contato)
     VALUES (:nome, :cnpj, :email, :telefone, :contato)'
);
$stmt->execute([
    'nome'     => $nome,
    'cnpj'     => $cnpj,
    'email'    => !empty($dados['email'])    ? trim((string) $dados['email'])    : null,
    'telefone' => !empty($dados['telefone']) ? trim((string) $dados['telefone']) : null,
    'contato'  => !empty($dados['contato'])  ? trim((string) $dados['contato'])  : null,
]);

responderSucesso(['id' => (int) $pdo->lastInsertId(), 'ja_existia' => false]);
