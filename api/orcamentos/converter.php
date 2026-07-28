<?php
/**
 * POST /api/orcamentos/converter
 * Body: { id: int, tecnico_id?: int, data_agendamento?: string }
 * Converte orçamento aprovado em OS. Marca status = 'convertido'.
 */

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

$id  = (int) $dados['id'];
$pdo = obterConexao();

$stmt = $pdo->prepare('SELECT * FROM orcamentos WHERE id = :id');
$stmt->execute([':id' => $id]);
$orc = $stmt->fetch();

if (!$orc) { responderErro('Orçamento não encontrado.', 404); }
if ($orc['status'] !== 'aprovado') {
    responderErro('Apenas orçamentos aprovados podem ser convertidos em OS.', 409);
}

$itens = $pdo->prepare('SELECT * FROM orcamento_itens WHERE orcamento_id = :id ORDER BY id');
$itens->execute([':id' => $id]);
$listaItens = $itens->fetchAll();

// Monta observacoes da OS com os itens do orcamento
$obsLinhas = ["Orçamento convertido: {$orc['codigo']}", ''];
foreach ($listaItens as $i) {
    $subtotal = (float)$i['quantidade'] * (float)$i['valor_unitario'];
    $tipo = $i['tipo'] === 'peca' ? '[Peça]' : '[Serviço]';
    $obsLinhas[] = "{$tipo} {$i['descricao']} — Qtd: {$i['quantidade']} × R$ " . number_format((float)$i['valor_unitario'], 2, ',', '.') . " = R$ " . number_format($subtotal, 2, ',', '.');
}
if ($orc['observacoes']) {
    $obsLinhas[] = '';
    $obsLinhas[] = 'Observações: ' . $orc['observacoes'];
}
$observacoes = implode("\n", $obsLinhas);

$tecnicoId      = isset($dados['tecnico_id']) ? (int)$dados['tecnico_id'] : null;
$dataAgendamento = $dados['data_agendamento'] ?? null;
$portalToken    = bin2hex(random_bytes(16));

// Sequencial de codigo da OS
$seqOs = (int) $pdo->query("SELECT COUNT(*) FROM ordens_servico")->fetchColumn() + 1;
$codigoOs = 'OS-' . date('Y') . '-' . str_pad((string)$seqOs, 4, '0', STR_PAD_LEFT);

$pdo->prepare(
    "INSERT INTO ordens_servico
     (codigo, gc_cliente_id, cliente_nome, cliente_telefone,
      tecnico_id, situacao_local, observacoes, data_agendamento,
      portal_token, criado_em)
     VALUES (:cod, :gc, :nome, :tel, :tec, 'aberto', :obs, :dt, :ptok, NOW())"
)->execute([
    ':cod'  => $codigoOs,
    ':gc'   => $orc['gc_cliente_id'],
    ':nome' => $orc['cliente_nome'],
    ':tel'  => $orc['cliente_telefone'],
    ':tec'  => $tecnicoId,
    ':obs'  => $observacoes,
    ':dt'   => $dataAgendamento,
    ':ptok' => $portalToken,
]);
$osId = (int) $pdo->lastInsertId();

// Marca orcamento como convertido e registra a OS gerada
$pdo->prepare('UPDATE orcamentos SET status = "convertido", os_id_gerada = :os WHERE id = :id')
    ->execute([':os' => $osId, ':id' => $id]);

responderSucesso(['os_id' => $osId, 'codigo_os' => $codigoOs]);
