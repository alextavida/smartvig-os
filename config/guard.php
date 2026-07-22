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

    if (empty($_SESSION['usuario_id'])) {
        $caminhoBase = obterCaminhoBaseApp();
        header('Location: ' . $caminhoBase . '/login.php');
        exit;
    }

    if (!empty($perfisPermitidos) && !in_array($_SESSION['usuario_perfil'], $perfisPermitidos, true)) {
        $caminhoBase = obterCaminhoBaseApp();
        $destino = $_SESSION['usuario_perfil'] === 'gestor' ? '/admin/' : '/tecnico/';
        header('Location: ' . $caminhoBase . $destino);
        exit;
    }

    // Atualiza foto_perfil da sessao se ainda nao estiver carregada
    if (!isset($_SESSION['usuario_foto_perfil'])) {
        $pdo = obterConexao();
        $row = $pdo->prepare('SELECT foto_perfil FROM usuarios WHERE id = :id');
        $row->execute(['id' => (int) $_SESSION['usuario_id']]);
        $dados = $row->fetch();
        $_SESSION['usuario_foto_perfil'] = $dados['foto_perfil'] ?? null;
    }

    return [
        'usuario_id'  => (int) $_SESSION['usuario_id'],
        'nome'        => $_SESSION['usuario_nome'],
        'email'       => $_SESSION['usuario_email'],
        'perfil'      => $_SESSION['usuario_perfil'],
        'jwt'         => $_SESSION['usuario_jwt'],
        'foto_perfil' => $_SESSION['usuario_foto_perfil'] ?? null,
    ];
}

/**
 * Caminho base do projeto (ex: /app-tecnicos), para montar links absolutos corretamente.
 */
function obterCaminhoBaseApp(): string
{
    return '/app-tecnicos';
}
