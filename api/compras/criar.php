<?php
/**
 * POST /api/compras/criar
 * Cria uma nova solicitação de compra.
 * Acesso: qualquer usuário ativo com role solicitante ou gestor.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/compras_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Metodo nao permitido.', 405);
}

$payload   = exigirAutenticacao();
$pdo       = obterConexao();
$dados     = lerCorpoJson();
$usuarioId = (int) $payload['usuario_id'];

exigirCampos($dados, ['justificativa', 'itens']);

if (empty($dados['itens']) || !is_array($dados['itens'])) {
    responderErro('Informe ao menos um item na solicitacao.', 422);
}

$numero         = gerarNumeroSolicitacao($pdo);
$prioridade     = in_array($dados['prioridade'] ?? 'media', ['baixa','media','alta','urgente'], true) ? $dados['prioridade'] : 'media';
$destino        = in_array($dados['destino'] ?? 'estoque', ['cliente','condominio','obra','estoque','manutencao','veiculo','outro'], true) ? $dados['destino'] : 'estoque';
$status         = isset($dados['enviar']) && $dados['enviar'] ? 'aguardando_aprovacao' : 'rascunho';
$centroCustoId  = isset($dados['centro_custo_id']) ? (int) $dados['centro_custo_id'] : null;
$categoriaId    = isset($dados['categoria_id']) ? (int) $dados['categoria_id'] : null;

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO solicitacoes_compra
         (numero, solicitante_id, prioridade, destino, destino_referencia,
          centro_custo_id, categoria_id, justificativa, observacoes, status)
         VALUES
         (:numero, :solicitante_id, :prioridade, :destino, :destino_ref,
          :cc_id, :cat_id, :justificativa, :obs, :status)'
    );
    $stmt->execute([
        'numero'        => $numero,
        'solicitante_id'=> $usuarioId,
        'prioridade'    => $prioridade,
        'destino'       => $destino,
        'destino_ref'   => $dados['destino_referencia'] ?? null,
        'cc_id'         => $centroCustoId ?: null,
        'cat_id'        => $categoriaId ?: null,
        'justificativa' => trim($dados['justificativa']),
        'obs'           => $dados['observacoes'] ?? null,
        'status'        => $status,
    ]);
    $solicitacaoId = (int) $pdo->lastInsertId();

    $stmtItem = $pdo->prepare(
        'INSERT INTO solicitacao_itens
         (solicitacao_id, produto_codigo, produto_nome, produto_unidade, quantidade, valor_estimado, observacao, gc_produto_id)
         VALUES (:sid, :cod, :nome, :un, :qtd, :vest, :obs, :gcid)'
    );
    foreach ($dados['itens'] as $item) {
        if (empty($item['produto_nome'])) { continue; }
        $stmtItem->execute([
            'sid'  => $solicitacaoId,
            'cod'  => $item['produto_codigo'] ?? null,
            'nome' => trim($item['produto_nome']),
            'un'   => $item['produto_unidade'] ?? 'UN',
            'qtd'  => max(0.001, (float) ($item['quantidade'] ?? 1)),
            'vest' => isset($item['valor_estimado']) && $item['valor_estimado'] !== '' ? (float) $item['valor_estimado'] : null,
            'obs'  => $item['observacao'] ?? null,
            'gcid' => isset($item['gc_produto_id']) ? (int) $item['gc_produto_id'] : null,
        ]);
    }

    recalcularValorEstimado($pdo, $solicitacaoId);

    $acao = $status === 'aguardando_aprovacao' ? 'Solicitação enviada para aprovação' : 'Solicitação criada como rascunho';
    registrarHistoricoCompra($pdo, $solicitacaoId, $usuarioId, $payload['nome'] ?? '', $acao);

    if ($status === 'aguardando_aprovacao') {
        notificarAprovadores($pdo, $solicitacaoId, 'compra_nova', 'Nova Solicitação de Compra',
            ($payload['nome'] ?? 'Um usuário') . ' criou a solicitação ' . $numero . '.');
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderErro('Erro ao criar solicitacao: ' . $e->getMessage(), 500);
}

responderSucesso(['id' => $solicitacaoId, 'numero' => $numero, 'status' => $status]);
