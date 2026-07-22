<?php
/**
 * POST /api/midias/upload (multipart/form-data)
 * Campos do form: os_id, tipo (foto|video), arquivo (file)
 * Salva o arquivo localmente em imgs/os/{os_id}/ e registra no banco.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/os_helpers.php';
require_once __DIR__ . '/../../config/upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Metodo nao permitido. Use POST.', 405);
}

$payload = exigirAutenticacao();

$osId = (int) ($_POST['os_id'] ?? 0);
$tipo = (string) ($_POST['tipo'] ?? '');

if ($osId <= 0 || $tipo === '') {
    responderErro('Campos os_id e tipo sao obrigatorios.', 422);
}

if (!isset($_FILES['arquivo'])) {
    responderErro('Nenhum arquivo enviado no campo "arquivo".', 422);
}

$pdo = obterConexao();
buscarOsOuFalhar($pdo, $osId, $payload);

$resultado = salvarMidiaLocal($_FILES['arquivo'], $osId, $tipo);

$pdo->beginTransaction();
try {
    $pdo->prepare(
        'INSERT INTO midias_os (os_id, tipo, caminho_arquivo, nome_arquivo, tamanho_bytes, enviado_por)
         VALUES (:os_id, :tipo, :caminho, :nome, :tamanho, :enviado_por)'
    )->execute([
        'os_id' => $osId,
        'tipo' => $tipo,
        'caminho' => $resultado['caminho_relativo'],
        'nome' => $resultado['nome_arquivo'],
        'tamanho' => $resultado['tamanho_bytes'],
        'enviado_por' => $payload['usuario_id'],
    ]);

    registrarHistorico($pdo, $osId, (int) $payload['usuario_id'], 'midia_enviada', ucfirst($tipo) . ' enviado: ' . $resultado['nome_arquivo']);
    notificarGestores($pdo, $osId, 'midia', 'Nova midia enviada', 'Tecnico ' . $payload['nome'] . ' enviou um(a) ' . $tipo . ' na OS #' . $osId);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderErro('Falha ao registrar midia: ' . $e->getMessage(), 500);
}

responderSucesso(['url' => $resultado['url'], 'caminho' => $resultado['caminho_relativo']], 201);
