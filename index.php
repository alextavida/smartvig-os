<?php
/**
 * Ponto de entrada do sistema. Redireciona para a tela de login do painel
 * apropriado. A propria pagina de login decide, apos autenticar, se leva
 * o usuario para /admin (gestor) ou /tecnico (tecnico).
 */

declare(strict_types=1);

require_once __DIR__ . '/config/guard.php';
iniciarSessaoSegura();

if (!empty($_SESSION['usuario_perfil'])) {
    $destino = $_SESSION['usuario_perfil'] === 'gestor' ? 'admin/' : 'tecnico/';
    header('Location: ' . $destino);
    exit;
}

header('Location: login.php');
exit;
