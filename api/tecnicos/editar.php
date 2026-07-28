<?php
/**
 * POST /api/tecnicos/editar (somente gestor)
 * Corpo: { tecnico_id, nome?, email?, telefone?, ativo? }
 * Atualiza dados cadastrais do tecnico. Nao altera senha (use alterar_senha).
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
exigirCampos($dados, ['tecnico_id']);

$tecnicoId = (int) $dados['tecnico_id'];
$pdo = obterConexao();

$stmtVerifica = $pdo->prepare('SELECT id FROM usuarios WHERE id = :id');
$stmtVerifica->execute(['id' => $tecnicoId]);
if (!$stmtVerifica->fetch()) {
    responderErro('Usuario nao encontrado.', 404);
}

// Impede que o gestor altere o proprio perfil (evitar auto-lockout)
if (isset($dados['perfil']) && (int)($payload['usuario_id'] ?? 0) === $tecnicoId) {
    responderErro('Nao e possivel alterar o proprio perfil.', 403);
}
if (isset($dados['perfil']) && !in_array($dados['perfil'], ['gestor', 'supervisor', 'tecnico'], true)) {
    responderErro('Perfil invalido.', 422);
}

$camposPermitidos = ['nome', 'email', 'telefone', 'ativo', 'perfil'];
$sets = [];
$parametros = ['id' => $tecnicoId];

foreach ($camposPermitidos as $campo) {
    if (array_key_exists($campo, $dados)) {
        if ($campo === 'ativo') {
            $parametros['ativo'] = $dados['ativo'] ? 1 : 0;
        } else {
            $parametros[$campo] = $dados[$campo];
        }
        $sets[] = "{$campo} = :{$campo}";
    }
}

if (empty($sets)) {
    responderErro('Nenhum campo para atualizar.', 422);
}

if (isset($dados['email'])) {
    $stmtEmail = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email AND id <> :id');
    $stmtEmail->execute(['email' => $dados['email'], 'id' => $tecnicoId]);
    if ($stmtEmail->fetch()) {
        responderErro('Este e-mail ja esta em uso por outro usuario.', 409);
    }
}

$sql = 'UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = :id';
$pdo->prepare($sql)->execute($parametros);

responderSucesso(['tecnico_id' => $tecnicoId, 'campos_atualizados' => array_keys($dados)]);
