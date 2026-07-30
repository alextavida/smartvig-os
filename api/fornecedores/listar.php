<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') { responderErro('Metodo nao permitido.', 405); }
exigirAutenticacao();
$pdo   = obterConexao();
$busca = $_GET['busca'] ?? '';

if ($busca !== '') {
    $stmt = $pdo->prepare("SELECT id, nome, razao_social, cnpj, telefone, email, contato, ativo FROM fornecedores WHERE ativo = 1 AND (nome LIKE :b OR cnpj LIKE :b) ORDER BY nome LIMIT 50");
    $stmt->execute(['b' => '%' . $busca . '%']);
} else {
    $stmt = $pdo->query("SELECT id, nome, razao_social, cnpj, telefone, email, contato, ativo FROM fornecedores ORDER BY nome");
}
responderSucesso($stmt->fetchAll());
