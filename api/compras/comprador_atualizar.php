<?php
/**
 * POST /api/compras/comprador_atualizar
 * Registra dados da compra: fornecedor, valor, pedido, NF.
 * Acesso: comprador, gestor.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/compras_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { responderErro('Metodo nao permitido.', 405); }

$payload   = exigirAutenticacao();
$pdo       = obterConexao();
$dados     = lerCorpoJson();
$id        = (int) ($dados['id'] ?? 0);
$usuarioId = (int) $payload['usuario_id'];
$perfil    = $payload['perfil'];

$stmtR = $pdo->prepare('SELECT role FROM usuario_roles WHERE usuario_id = :id');
$stmtR->execute(['id' => $usuarioId]);
$roles = array_column($stmtR->fetchAll(), 'role');

$podeComprar = $perfil === 'gestor' || in_array('comprador', $roles, true);
if (!$podeComprar) { responderErro('Acesso negado.', 403); }

$sc = $pdo->prepare('SELECT id, numero, status, solicitante_id FROM solicitacoes_compra WHERE id = :id LIMIT 1');
$sc->execute(['id' => $id]);
$sc = $sc->fetch();
if (!$sc) { responderErro('Solicitação não encontrada.', 404); }
if (!in_array($sc['status'], ['aprovado', 'em_compra'], true)) {
    responderErro('Apenas solicitações aprovadas podem ser processadas pelo comprador.', 422);
}

$freteGratis = !empty($dados['frete_gratis']) ? 1 : 0;
$valorFrete  = $freteGratis ? 0.00 : (isset($dados['valor_frete']) ? (float) $dados['valor_frete'] : 0.00);
$fornecedorId = isset($dados['fornecedor_id']) && $dados['fornecedor_id'] > 0 ? (int) $dados['fornecedor_id'] : null;

// Calcula valor_final = negociado + frete
$valorNegociado = isset($dados['valor_negociado']) && $dados['valor_negociado'] !== '' ? (float) $dados['valor_negociado'] : null;
$valorFinal     = $valorNegociado !== null ? $valorNegociado + $valorFrete : null;

$pdo->prepare(
    'UPDATE solicitacoes_compra SET
        status            = \'em_compra\',
        comprador_id      = :cid,
        fornecedor_id     = :fid,
        valor_negociado   = :vn,
        valor_frete       = :vf,
        frete_gratis      = :fg,
        valor_final       = :vfinal,
        prazo_entrega     = :prazo,
        numero_pedido     = :pedido,
        data_compra       = :data_compra,
        observacoes_compra= :obs_comp,
        nota_fiscal_numero= :nf_num,
        nota_fiscal_data  = :nf_data
     WHERE id = :id'
)->execute([
    'cid'       => $usuarioId,
    'fid'       => $fornecedorId,
    'vn'        => $valorNegociado,
    'vf'        => $valorFrete,
    'fg'        => $freteGratis,
    'vfinal'    => $valorFinal,
    'prazo'     => $dados['prazo_entrega'] ?? null,
    'pedido'    => $dados['numero_pedido'] ?? null,
    'data_compra'=> $dados['data_compra'] ?? date('Y-m-d'),
    'obs_comp'  => $dados['observacoes_compra'] ?? null,
    'nf_num'    => $dados['nota_fiscal_numero'] ?? null,
    'nf_data'   => $dados['nota_fiscal_data'] ?? null,
    'id'        => $id,
]);

$detalhe = 'Compra registrada';
if ($valorNegociado !== null) { $detalhe .= ' | Valor: R$ ' . number_format($valorNegociado, 2, ',', '.'); }
if ($fornecedorId) {
    $nf = $pdo->prepare('SELECT nome FROM fornecedores WHERE id = :id');
    $nf->execute(['id' => $fornecedorId]);
    $nomeForn = $nf->fetchColumn();
    if ($nomeForn) { $detalhe .= ' | Fornecedor: ' . $nomeForn; }
}

registrarHistoricoCompra($pdo, $id, $usuarioId, $payload['nome'] ?? '', 'Compra processada pelo comprador', $detalhe);

// Notifica solicitante
$pdo->prepare(
    'INSERT INTO notificacoes (usuario_id, os_id, tipo, titulo, mensagem) VALUES (:uid, NULL, :tipo, :titulo, :msg)'
)->execute([
    'uid'   => (int) $sc['solicitante_id'],
    'tipo'  => 'compra_realizada',
    'titulo'=> 'Compra Realizada',
    'msg'   => 'A solicitação ' . $sc['numero'] . ' foi comprada. Aguarde o recebimento.',
]);

responderSucesso(['mensagem' => 'Compra registrada com sucesso.']);
