<?php
/**
 * POST /api/auth/login
 * Corpo: { "email": "...", "senha": "..." }
 * Retorna JWT + dados do usuario.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Metodo nao permitido. Use POST.', 405);
}

$dados = lerCorpoJson();
exigirCampos($dados, ['email', 'senha']);

$email = trim((string) $dados['email']);
$senha = (string) $dados['senha'];

$pdo = obterConexao();
$stmt = $pdo->prepare('SELECT id, nome, email, senha_hash, perfil, ativo FROM usuarios WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$usuario = $stmt->fetch();

if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
    responderErro('E-mail ou senha invalidos.', 401);
}

if ((int) $usuario['ativo'] !== 1) {
    responderErro('Usuario inativo. Contate o gestor.', 403);
}

$token = gerarJwt((int) $usuario['id'], $usuario['perfil'], $usuario['nome'], $usuario['email']);

responderSucesso([
    'token' => $token,
    'usuario' => [
        'id' => (int) $usuario['id'],
        'nome' => $usuario['nome'],
        'email' => $usuario['email'],
        'perfil' => $usuario['perfil'],
    ],
]);
