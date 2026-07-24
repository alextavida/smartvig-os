<?php
/**
 * POST /api/os/salvar_tempo
 * Body: { os_id, segundos }
 * Soma o tempo trabalhado ao campo tempo_atendimento_segundos da OS.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Metodo nao permitido.', 405);
}

$payload = exigirAutenticacao();
$body    = json_decode(file_get_contents('php://input'), true) ?? [];

$osId     = (int) ($body['os_id'] ?? 0);
$segundos = (int) ($body['segundos'] ?? 0);

if ($osId <= 0 || $segundos <= 0) {
    responderErro('os_id e segundos validos sao obrigatorios.', 422);
}

$pdo = obterConexao();

// Técnico só pode salvar tempo de OS que lhe pertence
if ($payload['perfil'] === 'tecnico') {
    $tid = (int) $payload['usuario_id'];
    $stmt = $pdo->prepare(
        'SELECT 1 FROM ordens_servico os
         WHERE os.id = :os_id
           AND (os.tecnico_id = :tid
                OR EXISTS (SELECT 1 FROM os_tecnicos ot WHERE ot.os_id = os.id AND ot.tecnico_id = :tid2))
         LIMIT 1'
    );
    $stmt->execute(['os_id' => $osId, 'tid' => $tid, 'tid2' => $tid]);
    if (!$stmt->fetch()) {
        responderErro('Acesso negado a esta OS.', 403);
    }
}

$pdo->prepare(
    'UPDATE ordens_servico
        SET tempo_atendimento_segundos = COALESCE(tempo_atendimento_segundos, 0) + :segundos
      WHERE id = :os_id'
)->execute(['segundos' => $segundos, 'os_id' => $osId]);

responderSucesso(['tempo_salvo' => true, 'segundos_adicionados' => $segundos]);
