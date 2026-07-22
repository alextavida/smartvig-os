<?php
/**
 * GET /api/gps/listar (somente gestor)
 * Retorna a ultima posicao conhecida de todos os tecnicos.
 * O painel do gestor deve chamar este endpoint periodicamente (polling, ex: a cada 15s)
 * para simular tempo real no mapa, sem depender de Firebase.
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
exigirPerfil($payload, ['gestor']);

$pdo = obterConexao();
$stmt = $pdo->query(
    'SELECT g.tecnico_id, u.nome AS tecnico_nome, g.os_id, os.cliente_nome, g.latitude, g.longitude, g.atualizado_em
     FROM gps_tecnicos g
     INNER JOIN usuarios u ON u.id = g.tecnico_id
     LEFT JOIN ordens_servico os ON os.id = g.os_id
     ORDER BY g.atualizado_em DESC'
);

responderSucesso(['posicoes' => $stmt->fetchAll()]);
