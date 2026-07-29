<?php
/**
 * POST /api/orcamentos/criar
 * Body: { cliente_nome, cliente_email?, cliente_telefone?, gc_cliente_id?,
 *          observacoes?, validade_dias?, itens: [{tipo,descricao,quantidade,valor_unitario}] }
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/gestaoclick.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Use POST.', 405);
}

$payload = exigirAutenticacao();
exigirPerfil($payload, ['gestor', 'supervisor', 'tecnico']);
$dados = lerCorpoJson();
exigirCampos($dados, ['cliente_nome', 'itens']);

$pdo = obterConexao();

// Codigo sequencial: ORC-2026-0001
$seq    = (int) $pdo->query("SELECT COUNT(*) FROM orcamentos")->fetchColumn() + 1;
$codigo = 'ORC-' . date('Y') . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
$token  = bin2hex(random_bytes(16));

$stmt = $pdo->prepare(
    "INSERT INTO orcamentos
     (codigo, gc_cliente_id, cliente_nome, cliente_email, cliente_telefone,
      observacoes, validade_dias, token, criado_por)
     VALUES (:codigo, :gc_cli, :cli_nome, :cli_email, :cli_tel, :obs, :val, :token, :por)"
);
$stmt->execute([
    ':codigo'    => $codigo,
    ':gc_cli'    => $dados['gc_cliente_id']   ?? null,
    ':cli_nome'  => trim($dados['cliente_nome']),
    ':cli_email' => $dados['cliente_email']    ?? null,
    ':cli_tel'   => $dados['cliente_telefone'] ?? null,
    ':obs'       => $dados['observacoes']      ?? null,
    ':val'       => max(1, (int) ($dados['validade_dias'] ?? 7)),
    ':token'     => $token,
    ':por'       => (int) $payload['usuario_id'],
]);
$orcId = (int) $pdo->lastInsertId();

$stmtItem = $pdo->prepare(
    "INSERT INTO orcamento_itens (orcamento_id, tipo, descricao, quantidade, valor_unitario)
     VALUES (:orc, :tipo, :desc, :qtd, :vl)"
);
foreach ((array) $dados['itens'] as $item) {
    if (empty($item['descricao'])) { continue; }
    $stmtItem->execute([
        ':orc'  => $orcId,
        ':tipo' => in_array($item['tipo'] ?? '', ['servico', 'peca']) ? $item['tipo'] : 'servico',
        ':desc' => trim($item['descricao']),
        ':qtd'  => max(0.01, (float) ($item['quantidade'] ?? 1)),
        ':vl'   => max(0, (float) ($item['valor_unitario'] ?? 0)),
    ]);
}

// Envia para o GestaoClick se houver cliente GC vinculado
$gcOrcamentoId = null;
$gcClienteIdOrc = (int) ($dados['gc_cliente_id'] ?? 0);
if ($gcClienteIdOrc > 0) {
    try {
        $itensProdutos = [];
        foreach ((array) $dados['itens'] as $item) {
            if (empty($item['descricao'])) { continue; }
            $itensProdutos[] = [
                'descricao'      => trim($item['descricao']),
                'quantidade'     => max(0.01, (float) ($item['quantidade'] ?? 1)),
                'valor_unitario' => max(0, (float) ($item['valor_unitario'] ?? 0)),
            ];
        }
        $gcResp = (new GestaoClickAPI())->criarOrcamento([
            'cliente_id'  => $gcClienteIdOrc,
            'observacoes' => $dados['observacoes'] ?? '',
            'itens'       => $itensProdutos,
        ]);
        $gcOrcamentoId = $gcResp['id'] ?? ($gcResp['data']['id'] ?? null);
        if ($gcOrcamentoId) {
            $pdo->prepare('UPDATE orcamentos SET gc_orcamento_id = :gcid WHERE id = :id')
                ->execute(['gcid' => $gcOrcamentoId, 'id' => $orcId]);
        }
    } catch (GestaoClickApiException $e) {
        // Falha no GC nao impede criacao local
    }
}

responderSucesso(['id' => $orcId, 'codigo' => $codigo, 'token' => $token, 'gc_orcamento_id' => $gcOrcamentoId]);
