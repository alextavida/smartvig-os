<?php
/**
 * Push stub — notificações agora são locais no app via @notifee/react-native.
 * O backend não envia push externo; as notificações são detectadas por polling.
 * Esta função existe apenas para não quebrar os callers em os_helpers.php e compras_helpers.php.
 */

declare(strict_types=1);

function enviarPushParaUsuarios(
    PDO    $pdo,
    array  $usuarioIds,
    string $titulo,
    string $corpo,
    array  $dados = []
): void {
    // Notificações entregues via polling no app — nenhuma ação necessária aqui.
}
