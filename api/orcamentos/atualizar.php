<?php
/** POST /api/orcamentos/atualizar — Edita orçamento + substitui itens. */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { responderErro('Use POST.', 405); }

$payload = exigirAutenticacao();
exigirPerfil($payload, ['gestor']);
$dados = lerCorpoJson();
exigirCampos($dados, ['id']);

$id = (int) $dados['id'];
$pdo = obterConexao();

$stmt = $pdo->prepare('SELECT id, status FROM orcamentos WHERE id = :id');
$stmt->execute([':id' => $id]);
$orc = $stmt->fetch();
if (!$orc) { responderErro('Orçamento não encontrado.', 404); }
if (in_array($orc['status'], ['aprovado', 'convertido'], true)) {
    responderErro('Não é possível editar um orçamento já aprovado ou convertido.', 409);
}

$pdo->prepare(
    "UPDATE orcamentos SET
       cliente_nome     = COALESCE(:nome, cliente_nome),
       cliente_email    = COALESCE(:email, cliente_email),
       cliente_telefone = COALESCE(:tel, cliente_telefone),
       observacoes      = :obs,
       validade_dias    = COALESCE(:val, validade_dias)
     WHERE id = :id"
)->execute([
    ':nome'  => $dados['cliente_nome']      ?? null,
    ':email' => $dados['cliente_email']     ?? null,
    ':tel'   => $dados['cliente_telefone']  ?? null,
    ':obs'   => $dados['observacoes']       ?? null,
    ':val'   => isset($dados['validade_dias']) ? (int)$dados['validade_dias'] : null,
    ':id'    => $id,
]);

// Substitui itens se enviados
if (isset($dados['itens'])) {
    $pdo->prepare('DELETE FROM orcamento_itens WHERE orcamento_id = :id')->execute([':id' => $id]);
    $stmtItem = $pdo->prepare(
        "INSERT INTO orcamento_itens (orcamento_id, tipo, descricao, quantidade, valor_unitario)
         VALUES (:orc, :tipo, :desc, :qtd, :vl)"
    );
    foreach ((array) $dados['itens'] as $item) {
        if (empty($item['descricao'])) { continue; }
        $stmtItem->execute([
            ':orc'  => $id,
            ':tipo' => in_array($item['tipo'] ?? '', ['servico','peca']) ? $item['tipo'] : 'servico',
            ':desc' => trim($item['descricao']),
            ':qtd'  => max(0.01, (float) ($item['quantidade'] ?? 1)),
            ':vl'   => max(0, (float) ($item['valor_unitario'] ?? 0)),
        ]);
    }
}

responderSucesso(['id' => $id]);
