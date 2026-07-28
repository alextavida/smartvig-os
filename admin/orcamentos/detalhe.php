<?php
declare(strict_types=1);

$perfisPermitidosPagina = ['gestor', 'supervisor'];
$tituloPagina = 'Detalhe do Orçamento';
$paginaAtiva  = 'orcamentos';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';

$id  = (int) ($_GET['id'] ?? 0);
if (!$id) { header('Location: /app-tecnicos/admin/orcamentos/lista.php'); exit; }

$pdo  = obterConexao();
$stmt = $pdo->prepare('SELECT o.*, u.nome AS criado_por_nome FROM orcamentos o LEFT JOIN usuarios u ON u.id = o.criado_por WHERE o.id = :id');
$stmt->execute([':id' => $id]);
$orc = $stmt->fetch();
if (!$orc) { header('Location: /app-tecnicos/admin/orcamentos/lista.php'); exit; }

$itens = $pdo->prepare('SELECT * FROM orcamento_itens WHERE orcamento_id = :id ORDER BY id');
$itens->execute([':id' => $id]);
$listaItens = $itens->fetchAll();

$total = array_sum(array_map(fn($i) => (float)$i['quantidade'] * (float)$i['valor_unitario'], $listaItens));
$vencimento = date('d/m/Y', strtotime($orc['criado_em'] . ' +' . $orc['validade_dias'] . ' days'));
$venceu = strtotime($orc['criado_em'] . ' +' . $orc['validade_dias'] . ' days') < time();
$tel    = preg_replace('/\D/', '', $orc['cliente_telefone'] ?? '');
$linkPortal = '/app-tecnicos/portal/orcamento.php?token=' . urlencode($orc['token']);

$rotulosStatus = ['rascunho'=>'Rascunho','enviado'=>'Enviado','aprovado'=>'Aprovado','recusado'=>'Recusado','convertido'=>'Convertido'];
$isGestor = $usuarioAtual['perfil'] === 'gestor';
?>

<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;align-items:center;">
  <a href="/app-tecnicos/admin/orcamentos/lista.php" class="btn btn-neutro btn-sm">← Voltar</a>
  <h2 style="margin:0;font-size:16px;font-weight:800;flex:1;">
    <?= htmlspecialchars($orc['codigo']) ?>
    <span class="badge-orc <?= htmlspecialchars($orc['status']) ?>" style="margin-left:8px;">
      <?= $rotulosStatus[$orc['status']] ?? $orc['status'] ?>
    </span>
  </h2>
  <?php if ($tel): ?>
    <a href="https://wa.me/55<?= $tel ?>?text=<?= urlencode('Olá ' . $orc['cliente_nome'] . '! Segue o orçamento: https://seudominio.com' . $linkPortal) ?>"
       target="_blank" class="btn btn-sm" style="background:#25d366;color:#fff;">
      Enviar WhatsApp
    </a>
  <?php endif; ?>
  <?php if ($isGestor && $orc['status'] === 'aprovado'): ?>
    <button onclick="converter()" class="btn btn-primario btn-sm">→ Converter em OS</button>
  <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
  <div class="card">
    <h3 class="secao-label">Cliente</h3>
    <div style="font-size:15px;font-weight:700;margin-bottom:4px;"><?= htmlspecialchars($orc['cliente_nome']) ?></div>
    <?php if ($orc['cliente_telefone']): ?>
      <div style="font-size:13px;color:#64748b;"><?= htmlspecialchars($orc['cliente_telefone']) ?></div>
    <?php endif; ?>
    <?php if ($orc['cliente_email']): ?>
      <div style="font-size:13px;color:#64748b;"><?= htmlspecialchars($orc['cliente_email']) ?></div>
    <?php endif; ?>
  </div>
  <div class="card">
    <h3 class="secao-label">Detalhes</h3>
    <div style="font-size:13px;line-height:2;">
      <div><b>Criado por:</b> <?= htmlspecialchars($orc['criado_por_nome'] ?? '-') ?></div>
      <div><b>Criado em:</b> <?= date('d/m/Y H:i', strtotime($orc['criado_em'])) ?></div>
      <div><b>Validade:</b> <?= $vencimento ?> <?= $venceu && !in_array($orc['status'],['aprovado','convertido'],true) ? '<span style="color:#dc2626;font-size:11px;">(vencido)</span>' : '' ?></div>
      <?php if ($orc['os_id_gerada']): ?>
        <div><b>OS gerada:</b> <a href="/app-tecnicos/admin/os/detalhe.php?id=<?= (int)$orc['os_id_gerada'] ?>" style="color:#1d4ed8;">#<?= (int)$orc['os_id_gerada'] ?></a></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Link do portal -->
<div class="card" style="margin-bottom:16px;background:#f0f9ff;border:1px solid #bae6fd;">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <span style="font-size:13px;font-weight:600;color:#0369a1;">🔗 Link do orçamento para o cliente:</span>
    <input readonly value="<?= htmlspecialchars('https://seudominio.com' . $linkPortal) ?>"
           class="form-control" style="flex:1;min-width:200px;font-size:12px;background:#fff;">
    <button onclick="copiarLink()" class="btn btn-secundario btn-sm">Copiar</button>
  </div>
</div>

<!-- Itens -->
<div class="card" style="margin-bottom:16px;">
  <h3 style="margin:0 0 14px;font-size:14px;font-weight:700;">Itens</h3>
  <div style="overflow-x:auto;">
    <table>
      <thead>
        <tr>
          <th>Tipo</th>
          <th>Descrição</th>
          <th style="text-align:right;">Qtd</th>
          <th style="text-align:right;">Valor Unit.</th>
          <th style="text-align:right;">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($listaItens as $item): ?>
        <tr>
          <td><span style="background:#f1f5f9;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;color:#475569;">
            <?= $item['tipo'] === 'peca' ? 'Peça' : 'Serviço' ?>
          </span></td>
          <td><?= htmlspecialchars($item['descricao']) ?></td>
          <td style="text-align:right;"><?= number_format((float)$item['quantidade'], 2, ',', '.') ?></td>
          <td style="text-align:right;">R$ <?= number_format((float)$item['valor_unitario'], 2, ',', '.') ?></td>
          <td style="text-align:right;font-weight:700;">R$ <?= number_format((float)$item['quantidade'] * (float)$item['valor_unitario'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="background:#f8fafc;">
          <td colspan="4" style="text-align:right;font-weight:800;font-size:15px;">Total</td>
          <td style="text-align:right;font-weight:800;font-size:17px;color:#1d4ed8;">R$ <?= number_format($total, 2, ',', '.') ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php if ($orc['observacoes']): ?>
<div class="card">
  <h3 class="secao-label">Observações</h3>
  <p style="font-size:13px;color:#475569;white-space:pre-wrap;margin:0;"><?= htmlspecialchars($orc['observacoes']) ?></p>
</div>
<?php endif; ?>

<!-- Modal converter -->
<div id="modal-converter" class="modal-overlay">
  <div class="modal-box" style="max-width:400px;">
    <h3>Converter em OS</h3>
    <div style="margin-bottom:12px;">
      <label class="form-label">Agendamento (opcional)</label>
      <input type="date" id="data-agendamento" class="form-control">
    </div>
    <div style="display:flex;gap:8px;">
      <button onclick="confirmarConverter()" class="btn btn-primario" style="flex:1;">Converter</button>
      <button onclick="document.getElementById('modal-converter').classList.remove('aberto')"
              class="btn btn-neutro" style="flex:1;">Cancelar</button>
    </div>
  </div>
</div>

<script>
function converter() {
  document.getElementById('modal-converter').classList.add('aberto');
}

async function confirmarConverter() {
  const dt = document.getElementById('data-agendamento').value;
  try {
    const r = await apiPost('/orcamentos/converter.php', {
      id: <?= $id ?>,
      data_agendamento: dt || null,
    });
    if (r && r.os_id) {
      window.location.href = '/app-tecnicos/admin/os/detalhe.php?id=' + r.os_id;
    }
  } catch (e) {
    alert('Erro: ' + e.message);
  }
}

function copiarLink() {
  const input = document.querySelector('input[readonly]');
  input.select();
  navigator.clipboard.writeText(input.value).catch(() => {
    document.execCommand('copy');
  });
  const btn = event.currentTarget;
  btn.textContent = 'Copiado!';
  setTimeout(() => btn.textContent = 'Copiar', 2000);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
