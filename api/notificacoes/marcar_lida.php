<?php
/**
 * POST /api/notificacoes/marcar_lida
 * Corpo: { "id": 5 }  OU  { "todas": true }
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

if (!empty($dados['todas'])) {
    $pdo->prepare('UPDATE notificacoes SET lida = 1 WHERE usuario_id = :usuario_id')
        ->execute(['usuario_id' => $payload['usuario_id']]);
    responderSucesso(['mensagem' => 'Todas as notificacoes foram marcadas como lidas.']);
}

exigirCampos($dados, ['id']);

$pdo->prepare('UPDATE notificacoes SET lida = 1 WHERE id = :id AND usuario_id = :usuario_id')
    ->execute(['id' => (int) $dados['id'], 'usuario_id' => $payload['usuario_id']]);

responderSucesso(['mensagem' => 'Notificacao marcada como lida.']);
