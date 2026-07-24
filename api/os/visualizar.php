<?php
/**
 * GET /api/os/visualizar?id=X
 * Retorna detalhes completos da OS: dados, tecnicos atribuidos, historico e midias.
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
$pdo = obterConexao();

$osId = (int) ($_GET['id'] ?? 0);
if ($osId <= 0) {
    responderErro('Parametro id e obrigatorio.', 422);
}

$os = buscarOsOuFalhar($pdo, $osId, $payload);

$tecnicos = listarTecnicosDaOs($pdo, $osId);

$stmtHistorico = $pdo->prepare(
    'SELECT h.id, h.acao, h.detalhe, h.criado_em, u.nome AS usuario_nome
     FROM historico_os h
     LEFT JOIN usuarios u ON u.id = h.usuario_id
     WHERE h.os_id = :os_id
     ORDER BY h.criado_em DESC'
);
$stmtHistorico->execute(['os_id' => $osId]);
$historico = $stmtHistorico->fetchAll();

$stmtMidias = $pdo->prepare(
    'SELECT id, tipo, caminho_arquivo, nome_arquivo, tamanho_bytes, criado_em
     FROM midias_os WHERE os_id = :os_id ORDER BY criado_em DESC'
);
$stmtMidias->execute(['os_id' => $osId]);
$midias = $stmtMidias->fetchAll();

$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
foreach ($midias as &$midia) {
    $midia['url'] = "{$esquema}://{$host}/app-tecnicos/{$midia['caminho_arquivo']}";
}
unset($midia);

$os['produtos'] = $os['produtos_json'] ? json_decode($os['produtos_json'], true) : [];
unset($os['produtos_json'], $os['midias_json']);

// Retorna tudo flat: OSDetalhe = campos da OS + tecnicos + historico + midias
responderSucesso(array_merge($os, [
    'tecnicos' => $tecnicos,
    'historico' => $historico,
    'midias'   => $midias,
]));
