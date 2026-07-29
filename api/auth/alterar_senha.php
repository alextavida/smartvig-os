<?php
/**
 * POST /api/auth/alterar_senha
 * Corpo: { senha_atual, nova_senha }
 *   — OU para gestores resetando senha de tecnico:
 * Corpo: { tecnico_id, nova_senha }  (gestor nao precisa de senha_atual)
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
$dados = lerCorpoJson();

$pdo = obterConexao();
$isReset = isset($dados['tecnico_id']) && $payload['perfil'] === 'gestor';

if ($isReset) {
    // Gestor redefinindo senha de tecnico
    $tecnicoId = (int) $dados['tecnico_id'];
    exigirCampos($dados, ['nova_senha']);

    if (strlen($dados['nova_senha']) < 6) {
        responderErro('A nova senha deve ter ao menos 6 caracteres.', 422);
    }

    $stmtVerifica = $pdo->prepare('SELECT id FROM usuarios WHERE id = :id');
    $stmtVerifica->execute(['id' => $tecnicoId]);
    if (!$stmtVerifica->fetch()) {
        responderErro('Usuario nao encontrado.', 404);
    }

    $hash = password_hash($dados['nova_senha'], PASSWORD_BCRYPT);
    $pdo->prepare('UPDATE usuarios SET senha_hash = :h WHERE id = :id')->execute(['h' => $hash, 'id' => $tecnicoId]);

    responderSucesso(['mensagem' => 'Senha redefinida com sucesso.', 'usuario_id' => $tecnicoId]);
} else {
    // Usuario alterando a propria senha
    exigirCampos($dados, ['senha_atual', 'nova_senha']);

    if (strlen($dados['nova_senha']) < 6) {
        responderErro('A nova senha deve ter ao menos 6 caracteres.', 422);
    }

    $userId = (int) $payload['usuario_id'];
    $stmtUser = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = :id');
    $stmtUser->execute(['id' => $userId]);
    $usuario = $stmtUser->fetch();

    if (!$usuario || !password_verify($dados['senha_atual'], $usuario['senha_hash'])) {
        responderErro('Senha atual incorreta.', 401);
    }

    $hash = password_hash($dados['nova_senha'], PASSWORD_BCRYPT);
    $pdo->prepare('UPDATE usuarios SET senha_hash = :h WHERE id = :id')->execute(['h' => $hash, 'id' => $userId]);

    responderSucesso(['mensagem' => 'Senha alterada com sucesso.']);
}
