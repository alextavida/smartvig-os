<?php
/**
 * POST /api/gps/atualizar (tecnico, chamado pelo app a cada ~60s)
 * Corpo: { "latitude": -23.55, "longitude": -46.63, "os_id"?: 10 }
 * Grava a ultima posicao do tecnico em gps_tecnicos (consultada via polling pelo mapa do gestor).
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
exigirPerfil($payload, ['tecnico']);

$dados = lerCorpoJson();
exigirCampos($dados, ['latitude', 'longitude']);

$latitude = (float) $dados['latitude'];
$longitude = (float) $dados['longitude'];
$osId = isset($dados['os_id']) ? (int) $dados['os_id'] : null;

$pdo = obterConexao();

$stmt = $pdo->prepare(
    'INSERT INTO gps_tecnicos (tecnico_id, os_id, latitude, longitude)
     VALUES (:tecnico_id, :os_id, :latitude, :longitude)
     ON DUPLICATE KEY UPDATE os_id = VALUES(os_id), latitude = VALUES(latitude), longitude = VALUES(longitude)'
);
$stmt->execute([
    'tecnico_id' => $payload['usuario_id'],
    'os_id' => $osId,
    'latitude' => $latitude,
    'longitude' => $longitude,
]);

if ($osId) {
    $pdo->prepare('UPDATE ordens_servico SET latitude_atual = :lat, longitude_atual = :lng WHERE id = :id')
        ->execute(['lat' => $latitude, 'lng' => $longitude, 'id' => $osId]);
}

responderSucesso(['mensagem' => 'Posicao atualizada.']);
