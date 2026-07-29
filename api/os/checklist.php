<?php
/**
 * GET  /api/os/checklist.php?os_id=1    — retorna itens do checklist da OS
 * POST /api/os/checklist.php            — salva/atualiza checklist
 *   Body (gestor, define itens): { "os_id":1, "itens":[{"texto":"Verificar tensao","concluido":false},...] }
 *   Body (tecnico, marca item): { "os_id":1, "item_idx":0, "concluido":true }
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/os_helpers.php';

$payload = exigirAutenticacao();
$pdo     = obterConexao();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $osId = (int) ($_GET['os_id'] ?? 0);
    if ($osId <= 0) { responderErro('os_id invalido.', 422); }

    $stmt = $pdo->prepare('SELECT checklist_json FROM ordens_servico WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $osId]);
    $row = $stmt->fetch();
    if (!$row) { responderErro('OS nao encontrada.', 404); }

    $itens = $row['checklist_json'] ? json_decode($row['checklist_json'], true) : [];
    responderSucesso(['os_id' => $osId, 'itens' => $itens ?? []]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = lerCorpoJson();
    exigirCampos($dados, ['os_id']);
    $osId = (int) $dados['os_id'];

    $os = buscarOsOuFalhar($pdo, $osId, $payload);

    // Gestor define a lista completa de itens
    if (isset($dados['itens']) && is_array($dados['itens'])) {
        exigirPerfil($payload, ['gestor', 'supervisor']);
        $itens = array_values(array_map(function ($item) {
            return [
                'texto'     => trim((string) ($item['texto'] ?? '')),
                'concluido' => (bool) ($item['concluido'] ?? false),
            ];
        }, $dados['itens']));

        $pdo->prepare('UPDATE ordens_servico SET checklist_json = :j WHERE id = :id')
            ->execute(['j' => json_encode($itens, JSON_UNESCAPED_UNICODE), 'id' => $osId]);

        registrarHistorico($pdo, $osId, (int) $payload['usuario_id'], 'checklist_atualizado', count($itens) . ' itens definidos');
        responderSucesso(['os_id' => $osId, 'itens' => $itens]);
    }

    // Tecnico ou gestor marca/desmarca um item
    if (isset($dados['item_idx'])) {
        $idx       = (int) $dados['item_idx'];
        $concluido = (bool) ($dados['concluido'] ?? true);

        $stmt = $pdo->prepare('SELECT checklist_json FROM ordens_servico WHERE id = :id');
        $stmt->execute(['id' => $osId]);
        $row = $stmt->fetch();
        $itens = $row && $row['checklist_json'] ? json_decode($row['checklist_json'], true) : [];

        if (!isset($itens[$idx])) { responderErro('Item nao encontrado no checklist.', 404); }

        $itens[$idx]['concluido'] = $concluido;
        $pdo->prepare('UPDATE ordens_servico SET checklist_json = :j WHERE id = :id')
            ->execute(['j' => json_encode($itens, JSON_UNESCAPED_UNICODE), 'id' => $osId]);

        responderSucesso(['os_id' => $osId, 'item_idx' => $idx, 'concluido' => $concluido]);
    }

    responderErro('Forneça "itens" (para definir checklist) ou "item_idx" (para marcar item).', 422);
}

responderErro('Metodo nao permitido.', 405);
