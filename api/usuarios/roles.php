<?php
/**
 * POST /api/usuarios/roles
 * Gerencia roles adicionais de um usuário.
 * Body: { "usuario_id": int, "roles": ["solicitante","comprador","aprovador"] }
 * Somente gestor pode alterar roles.
 */

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
exigirCampos($dados, ['usuario_id', 'roles']);

$usuarioId = (int) $dados['usuario_id'];
$roles     = array_filter((array) $dados['roles'], fn($r) => in_array($r, ['solicitante','comprador','aprovador'], true));

// Remove todos os roles do usuário e reinclui os selecionados
$pdo->prepare('DELETE FROM usuario_roles WHERE usuario_id = :id')->execute(['id' => $usuarioId]);

$stmt = $pdo->prepare('INSERT INTO usuario_roles (usuario_id, role) VALUES (:uid, :role)');
foreach ($roles as $role) {
    $stmt->execute(['uid' => $usuarioId, 'role' => $role]);
}

// GET para listar roles
responderSucesso(['usuario_id' => $usuarioId, 'roles' => array_values($roles)]);
