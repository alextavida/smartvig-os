<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { responderErro('Metodo nao permitido.', 405); }
$payload = exigirAutenticacao();
if ($payload['perfil'] !== 'gestor') { responderErro('Acesso negado.', 403); }

$pdo   = obterConexao();
$dados = lerCorpoJson();
$id    = (int) ($dados['id'] ?? 0);
if ($id <= 0) { responderErro('ID inválido.', 400); }

$pdo->prepare(
    'UPDATE fornecedores SET nome=:nome, razao_social=:rs, cnpj=:cnpj, telefone=:tel, email=:email,
     contato=:contato, observacoes=:obs, ativo=:ativo WHERE id=:id'
)->execute([
    'nome'    => trim($dados['nome'] ?? ''),
    'rs'      => $dados['razao_social'] ?? null,
    'cnpj'    => $dados['cnpj'] ?? null,
    'tel'     => $dados['telefone'] ?? null,
    'email'   => $dados['email'] ?? null,
    'contato' => $dados['contato'] ?? null,
    'obs'     => $dados['observacoes'] ?? null,
    'ativo'   => isset($dados['ativo']) ? (int) $dados['ativo'] : 1,
    'id'      => $id,
]);
responderSucesso(['mensagem' => 'Fornecedor atualizado.']);
