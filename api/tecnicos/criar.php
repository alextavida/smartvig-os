<?php
/**
 * POST /api/tecnicos/criar (somente gestor)
 * Corpo: { "nome": "...", "email": "...", "senha": "...", "telefone"?: "..." }
 * Cadastra um novo usuario com perfil tecnico.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Metodo nao permitido. Use POST.', 405);
}

$payload = exigirAutenticacao();
exigirPerfil($payload, ['gestor']);

$dados = lerCorpoJson();
exigirCampos($dados, ['nome', 'email', 'senha']);

$email = trim((string) $dados['email']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responderErro('E-mail invalido.', 422);
}

if (strlen((string) $dados['senha']) < 6) {
    responderErro('Senha deve ter ao menos 6 caracteres.', 422);
}

$pdo = obterConexao();

$stmtExiste = $pdo->prepare('SELECT 1 FROM usuarios WHERE email = :email LIMIT 1');
$stmtExiste->execute(['email' => $email]);
if ($stmtExiste->fetch()) {
    responderErro('Ja existe um usuario com este e-mail.', 409);
}

$senhaHash = password_hash((string) $dados['senha'], PASSWORD_BCRYPT);

$stmt = $pdo->prepare(
    'INSERT INTO usuarios (nome, email, senha_hash, perfil, telefone, ativo)
     VALUES (:nome, :email, :senha_hash, "tecnico", :telefone, 1)'
);
$stmt->execute([
    'nome' => $dados['nome'],
    'email' => $email,
    'senha_hash' => $senhaHash,
    'telefone' => $dados['telefone'] ?? null,
]);

responderSucesso(['id' => (int) $pdo->lastInsertId()], 201);
