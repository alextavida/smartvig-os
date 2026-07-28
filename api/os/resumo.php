<?php
/**
 * GET /api/os/resumo
 * Retorna contagens de OS por status, alertas SLA e técnicos online.
 * Apenas gestor.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

$payload = exigirAutenticacao();
exigirPerfil($payload, ['gestor']);

$pdo = obterConexao();

// Contagens por status
$contagens = $pdo->query(
    "SELECT situacao_local, COUNT(*) AS total FROM ordens_servico GROUP BY situacao_local"
)->fetchAll(PDO::FETCH_KEY_PAIR);

// SLA: OS com prazo definido e nao concluidas/canceladas
$slaRows = $pdo->query(
    "SELECT DATEDIFF(data_prazo, CURDATE()) AS dias
     FROM ordens_servico
     WHERE data_prazo IS NOT NULL
       AND situacao_local NOT IN ('concluido','cancelado')"
)->fetchAll(PDO::FETCH_COLUMN);

$slaAtrasadas = 0; $slaCriticas = 0; $slaAtencao = 0; $slaNoPrazo = 0;
foreach ($slaRows as $d) {
    $d = (int)$d;
    if ($d < 0)         { $slaAtrasadas++; }
    elseif ($d <= 1)    { $slaCriticas++; }
    elseif ($d <= 3)    { $slaAtencao++; }
    else                { $slaNoPrazo++; }
}

// Técnicos online (GPS atualizado há menos de 10 min)
$r = $pdo->query(
    "SELECT
       COUNT(*) AS total,
       SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL
                 AND atualizado_em > DATE_SUB(NOW(), INTERVAL 10 MINUTE) THEN 1 ELSE 0 END) AS online
     FROM gps_tecnicos"
)->fetch();

responderSucesso([
    'os' => [
        'total'        => array_sum($contagens),
        'aberto'       => (int)($contagens['aberto']       ?? 0),
        'em_andamento' => (int)($contagens['em_andamento'] ?? 0),
        'pausado'      => (int)($contagens['pausado']      ?? 0),
        'reagendado'   => (int)($contagens['reagendado']   ?? 0),
        'concluido'    => (int)($contagens['concluido']    ?? 0),
        'cancelado'    => (int)($contagens['cancelado']    ?? 0),
    ],
    'sla' => [
        'atrasadas' => $slaAtrasadas,
        'criticas'  => $slaCriticas,
        'atencao'   => $slaAtencao,
        'no_prazo'  => $slaNoPrazo,
    ],
    'tecnicos' => [
        'total'  => (int)($r['total']  ?? 0),
        'online' => (int)($r['online'] ?? 0),
    ],
]);
