<?php
/**
 * POST /api/os/deletar
 * Corpo: { "os_id": 1 }
 * Remove permanentemente uma OS e todos os dados associados. Apenas gestor.
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
exigirPerfil($payload, ['gestor']);
$dados = lerCorpoJson();
exigirCampos($dados, ['os_id']);

$osId = (int) $dados['os_id'];
$pdo  = obterConexao();

$stmt = $pdo->prepare('SELECT id, gc_os_id FROM ordens_servico WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $osId]);
$os = $stmt->fetch();
if (!$os) { responderErro('OS nao encontrada.', 404); }

// Tenta cancelar no GestaoClick antes de deletar localmente (nao fatal)
if ((int) ($os['gc_os_id'] ?? 0) > 0) {
    try {
        require_once __DIR__ . '/../../config/os_helpers.php';
        $situacaoGcId = obterSituacaoGcId('cancelado');
        if ($situacaoGcId !== null) {
            (new GestaoClickAPI())->atualizarOS((int) $os['gc_os_id'], [
                'situacao_id' => $situacaoGcId,
                'observacoes' => 'OS removida do SmartVig.',
            ]);
        }
    } catch (Throwable $gcErr) {
        // Falha no GC nao impede a delecao local
    }
}

$pdo->beginTransaction();
try {
    // Remove dados relacionados (ignora erros de tabelas que possam nao existir)
    foreach ([
        'DELETE FROM historico_os WHERE os_id = :id',
        'DELETE FROM os_tecnicos WHERE os_id = :id',
        'DELETE FROM midias_os WHERE os_id = :id',
        'DELETE FROM notificacoes WHERE os_id = :id',
    ] as $sql) {
        try {
            $pdo->prepare($sql)->execute(['id' => $osId]);
        } catch (PDOException $e) {
            // Tabela pode nao existir — continua
        }
    }

    $pdo->prepare('DELETE FROM ordens_servico WHERE id = :id')->execute(['id' => $osId]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderErro('Falha ao deletar OS: ' . $e->getMessage(), 500);
}

responderSucesso(['os_id' => $osId, 'deletado' => true]);
