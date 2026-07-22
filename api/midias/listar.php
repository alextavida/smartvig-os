<?php
/**
 * GET /api/midias/listar?os_id=X
 * Lista fotos e videos de uma OS.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/os_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro('Metodo nao permitido. Use GET.', 405);
}

$payload = exigirAutenticacao();
$osId = (int) ($_GET['os_id'] ?? 0);
if ($osId <= 0) {
    responderErro('Parametro os_id e obrigatorio.', 422);
}

$pdo = obterConexao();
buscarOsOuFalhar($pdo, $osId, $payload);

$stmt = $pdo->prepare(
    'SELECT m.id, m.tipo, m.caminho_arquivo, m.nome_arquivo, m.tamanho_bytes, m.criado_em, u.nome AS enviado_por_nome
     FROM midias_os m
     LEFT JOIN usuarios u ON u.id = m.enviado_por
     WHERE m.os_id = :os_id
     ORDER BY m.criado_em DESC'
);
$stmt->execute(['os_id' => $osId]);
$midias = $stmt->fetchAll();

$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
foreach ($midias as &$midia) {
    $midia['url'] = "{$esquema}://{$host}/app-tecnicos/{$midia['caminho_arquivo']}";
}
unset($midia);

responderSucesso(['midias' => $midias]);
