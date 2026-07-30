<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { responderErro('Metodo nao permitido.', 405); }
$payload = exigirAutenticacao();
if (!in_array($payload['perfil'], ['gestor', 'supervisor'], true)) { responderErro('Acesso negado.', 403); }

$pdo   = obterConexao();
$dados = lerCorpoJson();
exigirCampos($dados, ['nome']);

$stmt = $pdo->prepare(
    'INSERT INTO fornecedores (nome, razao_social, cnpj, telefone, email, contato, observacoes)
     VALUES (:nome, :rs, :cnpj, :tel, :email, :contato, :obs)'
);
$stmt->execute([
    'nome'    => trim($dados['nome']),
    'rs'      => $dados['razao_social'] ?? null,
    'cnpj'    => $dados['cnpj'] ?? null,
    'tel'     => $dados['telefone'] ?? null,
    'email'   => $dados['email'] ?? null,
    'contato' => $dados['contato'] ?? null,
    'obs'     => $dados['observacoes'] ?? null,
]);
responderSucesso(['id' => (int) $pdo->lastInsertId(), 'mensagem' => 'Fornecedor cadastrado.']);
