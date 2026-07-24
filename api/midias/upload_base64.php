<?php
/**
 * POST /api/midias/upload_base64
 * Body JSON: { os_id, tipo, base64, nome_arquivo? }
 * Salva imagem em base64 (ex: assinatura digital) como mídia da OS.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/os_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Metodo nao permitido.', 405);
}

$payload = exigirAutenticacao();
$body    = json_decode(file_get_contents('php://input'), true) ?? [];

$osId          = (int) ($body['os_id'] ?? 0);
$tipo          = (string) ($body['tipo'] ?? 'foto');
$base64Raw     = (string) ($body['base64'] ?? '');
$nomeOriginal  = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) ($body['nome_arquivo'] ?? 'assinatura.png'));

if ($osId <= 0 || $base64Raw === '') {
    responderErro('os_id e base64 sao obrigatorios.', 422);
}
if (!in_array($tipo, ['foto', 'video'], true)) {
    responderErro('Tipo invalido. Use foto ou video.', 422);
}

// Remove prefixo data:image/...;base64,
$base64Limpo = preg_replace('/^data:[^;]+;base64,/', '', $base64Raw);
$binario     = base64_decode($base64Limpo, true);

if ($binario === false || strlen($binario) < 4) {
    responderErro('Dados de imagem invalidos (base64 corrompido).', 422);
}

$pdo = obterConexao();
buscarOsOuFalhar($pdo, $osId, $payload);

// Detecta extensão pelo magic byte
$extensao = 'png';
if (strlen($binario) > 1 && ord($binario[0]) === 0xFF && ord($binario[1]) === 0xD8) {
    $extensao = 'jpg';
} elseif (strlen($binario) > 3 && substr($binario, 0, 4) === 'GIF8') {
    $extensao = 'gif';
}

$pasta = dirname(__DIR__, 2) . '/imgs/os/' . $osId;
if (!is_dir($pasta) && !mkdir($pasta, 0755, true) && !is_dir($pasta)) {
    responderErro('Nao foi possivel criar pasta de destino.', 500);
}

$nomeArquivo     = time() . '_' . bin2hex(random_bytes(4)) . '.' . $extensao;
$caminhoDestino  = $pasta . '/' . $nomeArquivo;
$caminhoRelativo = "imgs/os/{$osId}/{$nomeArquivo}";
$tamanhoBytes    = strlen($binario);

if (file_put_contents($caminhoDestino, $binario) === false) {
    responderErro('Falha ao salvar a imagem no servidor.', 500);
}

$pdo->beginTransaction();
try {
    $pdo->prepare(
        'INSERT INTO midias_os (os_id, tipo, caminho_arquivo, nome_arquivo, tamanho_bytes, enviado_por)
         VALUES (:os_id, :tipo, :caminho, :nome, :tamanho, :uid)'
    )->execute([
        'os_id'   => $osId,
        'tipo'    => $tipo,
        'caminho' => $caminhoRelativo,
        'nome'    => $nomeArquivo,
        'tamanho' => $tamanhoBytes,
        'uid'     => $payload['usuario_id'],
    ]);

    registrarHistorico($pdo, $osId, (int) $payload['usuario_id'], 'midia_enviada', 'Assinatura digital do cliente registrada');

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    @unlink($caminhoDestino);
    responderErro('Falha ao registrar midia: ' . $e->getMessage(), 500);
}

$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';

responderSucesso([
    'url'     => "{$esquema}://{$host}/app-tecnicos/{$caminhoRelativo}",
    'caminho' => $caminhoRelativo,
], 201);
