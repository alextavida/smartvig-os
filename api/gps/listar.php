<?php
/**
 * GET /api/gps/listar (gestor ou supervisor)
 * Retorna TODOS os tecnicos com OS ativa (em_andamento), com ou sem GPS.
 * Tecnicos sem GPS recente aparecem como "offline" no mapa.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro('Metodo nao permitido. Use GET.', 405);
}

$payload = exigirAutenticacao();
exigirPerfil($payload, ['gestor', 'supervisor']);

$pdo = obterConexao();

try {
// Tecnicos com OS em andamento — subquery garante 1 OS por tecnico (a menor id ativa)
$comOs = $pdo->query(
    "SELECT u.id AS tecnico_id,
            u.nome AS tecnico_nome,
            os.id AS os_id,
            os.cliente_nome,
            g.latitude,
            g.longitude,
            g.atualizado_em,
            'em_andamento' AS status_os
     FROM usuarios u
     INNER JOIN (
         SELECT ot.tecnico_id, MIN(ot.os_id) AS os_id
         FROM os_tecnicos ot
         INNER JOIN ordens_servico osq ON osq.id = ot.os_id
                                      AND osq.situacao_local = 'em_andamento'
         GROUP BY ot.tecnico_id
     ) sub ON sub.tecnico_id = u.id
     INNER JOIN ordens_servico os ON os.id = sub.os_id
     LEFT JOIN gps_tecnicos g ON g.tecnico_id = u.id
     WHERE u.perfil = 'tecnico' AND u.ativo = 1"
)->fetchAll();

// Tecnicos sem OS ativa mas com GPS enviado nos ultimos 30 minutos
$semOs = $pdo->query(
    "SELECT u.id AS tecnico_id,
            u.nome AS tecnico_nome,
            g.os_id,
            NULL AS cliente_nome,
            g.latitude,
            g.longitude,
            g.atualizado_em,
            'disponivel' AS status_os
     FROM gps_tecnicos g
     INNER JOIN usuarios u ON u.id = g.tecnico_id
     WHERE g.atualizado_em >= NOW() - INTERVAL 30 MINUTE
       AND u.perfil = 'tecnico'
       AND u.ativo = 1
       AND u.id NOT IN (
           SELECT ot2.tecnico_id FROM os_tecnicos ot2
           INNER JOIN ordens_servico os2 ON os2.id = ot2.os_id
           WHERE os2.situacao_local = 'em_andamento'
       )"
)->fetchAll();
} catch (\Exception $e) {
    responderErro('Erro ao consultar posicoes: ' . $e->getMessage(), 500);
}

// Mescla e remove duplicatas pelo tecnico_id (prioriza os com OS ativa)
$resultado = [];
foreach ($comOs as $p) {
    $resultado[$p['tecnico_id']] = $p;
}
foreach ($semOs as $p) {
    if (!isset($resultado[$p['tecnico_id']])) {
        $resultado[$p['tecnico_id']] = $p;
    }
}

// Ordena: com GPS primeiro, depois por nome
usort($resultado, function ($a, $b) {
    $aTemGps = $a['latitude'] !== null;
    $bTemGps = $b['latitude'] !== null;
    if ($aTemGps !== $bTemGps) { return $bTemGps <=> $aTemGps; }
    return strcmp($a['tecnico_nome'], $b['tecnico_nome']);
});

responderSucesso(['posicoes' => array_values($resultado)]);
