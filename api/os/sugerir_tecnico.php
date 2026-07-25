<?php
/**
 * GET /api/os/sugerir_tecnico?os_id=X
 * Retorna lista de tecnicos rankeada para distribuicao automatica:
 * 1. Livres (sem OS em andamento) primeiro
 * 2. Mais proximo por GPS (se OS tiver lat/lng de referencia)
 * 3. Mais recente GPS
 * Requer sessao gestor.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/guard.php';

exigirLoginWeb(['gestor']);

$pdo  = obterConexao();
$osId = isset($_GET['os_id']) ? (int) $_GET['os_id'] : null;

// Coordenadas de referencia (OS alvo, se disponivel)
$latRef = null;
$lngRef = null;
if ($osId) {
    $row = $pdo->prepare('SELECT latitude_atual, longitude_atual FROM ordens_servico WHERE id = :id');
    $row->execute([':id' => $osId]);
    $osData = $row->fetch();
    if ($osData && $osData['latitude_atual']) {
        $latRef = (float) $osData['latitude_atual'];
        $lngRef = (float) $osData['longitude_atual'];
    }
}

// Busca todos os tecnicos ativos com ultima posicao GPS
$stmt = $pdo->query(
    "SELECT u.id, u.nome, u.email, u.foto_perfil,
            g.latitude, g.longitude, g.atualizado_em AS gps_em,
            (SELECT COUNT(*) FROM ordens_servico os
             WHERE os.tecnico_id = u.id AND os.situacao_local = 'em_andamento') AS os_ativas
     FROM usuarios u
     LEFT JOIN gps_tecnicos g ON g.tecnico_id = u.id
     WHERE u.perfil = 'tecnico' AND u.ativo = 1
     ORDER BY g.atualizado_em DESC"
);
$tecnicos = $stmt->fetchAll();

// Funcao Haversine
function haversineKm(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?float {
    if ($lat1 === null || $lat2 === null) return null;
    $r  = 6371;
    $dl = deg2rad($lat2 - $lat1);
    $dg = deg2rad($lng2 - $lng1);
    $a  = sin($dl/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dg/2)**2;
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

$resultado = [];
foreach ($tecnicos as $t) {
    $temGps  = $t['gps_em'] !== null;
    $minAtras = $temGps ? round((time() - strtotime($t['gps_em'])) / 60) : null;
    $ativo   = $temGps && $minAtras < 10;
    $distKm  = haversineKm($latRef, $lngRef, $t['latitude'] ? (float)$t['latitude'] : null, $t['longitude'] ? (float)$t['longitude'] : null);

    $resultado[] = [
        'id'          => (int) $t['id'],
        'nome'        => $t['nome'],
        'email'       => $t['email'],
        'foto_perfil' => $t['foto_perfil'],
        'os_ativas'   => (int) $t['os_ativas'],
        'livre'       => (int) $t['os_ativas'] === 0,
        'gps_ativo'   => $ativo,
        'gps_min_atras' => $minAtras,
        'distancia_km'  => $distKm !== null ? round($distKm, 1) : null,
        'gps_em'      => $t['gps_em'],
    ];
}

// Ordenacao: livres primeiro, despois mais proximo (ou mais recente GPS)
usort($resultado, function ($a, $b) {
    // Livres antes de ocupados
    if ($a['livre'] !== $b['livre']) return $b['livre'] <=> $a['livre'];
    // Se ambos tem distancia, ordena por distancia
    if ($a['distancia_km'] !== null && $b['distancia_km'] !== null) {
        return $a['distancia_km'] <=> $b['distancia_km'];
    }
    // Se nenhum tem distancia, ordena por GPS mais recente
    if ($a['gps_em'] && $b['gps_em']) return strcmp($b['gps_em'], $a['gps_em']);
    return 0;
});

header('Content-Type: application/json');
echo json_encode(['tecnicos' => $resultado, 'total' => count($resultado)]);
