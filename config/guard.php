<?php
/**
 * Protecao das paginas web (admin/ e tecnico/) via sessao PHP.
 * A sessao guarda tambem o JWT gerado no login, para que as paginas
 * possam embutir o token e as telas facam chamadas AJAX para /api/.
 */

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

function iniciarSessaoSegura(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

/**
 * Exige que o usuario esteja logado na sessao web. Redireciona para o login se nao estiver.
 * Se $perfisPermitidos for informado, tambem valida o perfil (senao, redireciona para o painel correto).
 */
function exigirLoginWeb(array $perfisPermitidos = []): array
{
    iniciarSessaoSegura();

    $base = obterCaminhoBaseApp();

    if (empty($_SESSION['usuario_id'])) {
        header('Location: ' . $base . '/login.php');
        exit;
    }

    $perfil = $_SESSION['usuario_perfil'] ?? '';

    // Carrega roles adicionais do banco se ainda nao estiverem na sessao
    if (!array_key_exists('usuario_roles', $_SESSION)) {
        $pdo   = obterConexao();
        $stmtR = $pdo->prepare('SELECT role FROM usuario_roles WHERE usuario_id = :id');
        $stmtR->execute(['id' => (int) $_SESSION['usuario_id']]);
        $_SESSION['usuario_roles'] = array_column($stmtR->fetchAll(), 'role');
    }
    $roles = $_SESSION['usuario_roles'] ?? [];

    // Verifica acesso: gestor tem acesso total; outros checam perfil + roles
    if (!empty($perfisPermitidos)) {
        $todosRoles = array_merge([$perfil], $roles);
        $temAcesso  = ($perfil === 'gestor') || !empty(array_intersect($todosRoles, $perfisPermitidos));
        if (!$temAcesso) {
            $temRoleCompras = !empty(array_intersect($roles, ['solicitante', 'comprador', 'aprovador']));
            $isAdmin        = in_array($perfil, ['gestor', 'supervisor'], true);
            $destino = ($isAdmin || $temRoleCompras) ? '/admin/compras/' : '/tecnico/';
            header('Location: ' . $base . $destino);
            exit;
        }
    }

    // Carrega foto_perfil se nao estiver na sessao
    if (!array_key_exists('usuario_foto_perfil', $_SESSION)) {
        $pdo  = obterConexao();
        $stmtF = $pdo->prepare('SELECT foto_perfil FROM usuarios WHERE id = :id');
        $stmtF->execute(['id' => (int) $_SESSION['usuario_id']]);
        $dados = $stmtF->fetch();
        $_SESSION['usuario_foto_perfil'] = $dados['foto_perfil'] ?? null;
    }

    return [
        'id'          => (int) $_SESSION['usuario_id'],
        'usuario_id'  => (int) $_SESSION['usuario_id'],
        'nome'        => $_SESSION['usuario_nome'],
        'email'       => $_SESSION['usuario_email'],
        'perfil'      => $perfil,
        'roles'       => $roles,
        'jwt'         => $_SESSION['usuario_jwt'],
        'foto_perfil' => $_SESSION['usuario_foto_perfil'] ?? null,
    ];
}

/**
 * Verifica se o usuário atual (retornado por exigirLoginWeb) tem o papel informado.
 * Gestor sempre retorna true.
 */
function temRole(array $usuario, string ...$roles): bool
{
    if (($usuario['perfil'] ?? '') === 'gestor') {
        return true;
    }
    $todosRoles = array_merge([$usuario['perfil'] ?? ''], $usuario['roles'] ?? []);
    foreach ($roles as $r) {
        if (in_array($r, $todosRoles, true)) {
            return true;
        }
    }
    return false;
}

/**
 * Caminho base do projeto (ex: /app-tecnicos), para montar links absolutos corretamente.
 */
function obterCaminhoBaseApp(): string
{
    return '/app-tecnicos';
}
