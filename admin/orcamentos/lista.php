<?php
declare(strict_types=1);

$perfisPermitidosPagina = ['gestor', 'supervisor'];
$tituloPagina = 'Orçamentos';
$paginaAtiva  = 'orcamentos';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';

$pdo    = obterConexao();
$status = $_GET['status'] ?? '';
$validos = ['', 'rascunho', 'enviado', 'aprovado', 'recusado', 'convertido'];
if (!in_array($status, $validos, true)) { $status = ''; }

$sql = "SELECT o.id, o.codigo, o.cliente_nome, o.cliente_telefone, o.status,
               o.validade_dias, o.criado_em, o.os_id_gerada, o.token,
               u.nome AS criado_por_nome,
               COALESCE((SELECT SUM(i.quantidade * i.valor_unitario)
                         FROM orcamento_itens i WHERE i.orcamento_id = o.id), 0) AS total
        FROM orcamentos o LEFT JOIN usuarios u ON u.id = o.criado_por";
$params = [];
if ($status) { $sql .= " WHERE o.status = :s"; $params[':s'] = $status; }
$sql .= " ORDER BY o.criado_em DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lista = $stmt->fetchAll();

$rotulosStatus = [
    'rascunho' => 'Rascunho', 'enviado' => 'Enviado',
    'aprovado' => 'Aprovado', 'recusado' => 'Recusado', 'convertido' => 'Convertido',
];
$isGestor = $usuarioAtual['perfil'] === 'gestor';
$baseUrl  = '/app-tecnicos';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
  <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <label class="form-label" style="margin-bottom:0;">Status:</label>
    <select name="status" class="form-control" style="width:auto;" onchange="this.form.submit()">
      <option value="">Todos</option>
      <?php foreach ($rotulosStatus as $k => $v): ?>
        <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php if ($isGestor): ?>
    <a href="<?= $baseUrl ?>/admin/orcamentos/criar.php" class="btn btn-primario">+ Novo Orçamento</a>
  <?php endif; ?>
</div>

<div class="card" style="padding:0;overflow:hidden;">
  <?php if (!$lista): ?>
    <div style="text-align:center;padding:48px;color:#94a3b8;">
      <div style="font-size:40px;margin-bottom:12px;">📄</div>
      <div style="font-weight:600;">Nenhum orçamento encontrado.</div>
    </div>
  <?php else: ?>
  <div style="overflow-x:auto;">
    <table>
      <thead>
        <tr>
          <th>Código</th>
          <th>Cliente</th>
          <th>Total</th>
          <th>Validade</th>
          <th>Status</th>
          <th>Criado por</th>
          <th>Data</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lista as $orc): ?>
        <?php
          $vencimento = date('d/m/Y', strtotime($orc['criado_em'] . ' +' . $orc['validade_dias'] . ' days'));
          $venceu = strtotime($orc['criado_em'] . ' +' . $orc['validade_dias'] . ' days') < time()
                    && !in_array($orc['status'], ['aprovado', 'convertido'], true);
          $tel = preg_replace('/\D/', '', $orc['cliente_telefone'] ?? '');
          $linkPortal = $baseUrl . '/portal/orcamento.php?token=' . urlencode($orc['token']);
        ?>
        <tr>
          <td style="font-size:12px;font-weight:700;color:#1d4ed8;"><?= htmlspecialchars($orc['codigo']) ?></td>
          <td>
            <?= htmlspecialchars($orc['cliente_nome']) ?>
            <?php if ($orc['cliente_telefone']): ?>
              <br><span style="font-size:11px;color:#64748b;"><?= htmlspecialchars($orc['cliente_telefone']) ?></span>
            <?php endif; ?>
          </td>
          <td style="font-weight:700;">R$ <?= number_format((float)$orc['total'], 2, ',', '.') ?></td>
          <td style="font-size:12px;<?= $venceu ? 'color:#dc2626;' : 'color:#64748b;' ?>">
            <?= $vencimento ?><?= $venceu ? ' <span style="font-size:10px;">(vencido)</span>' : '' ?>
          </td>
          <td>
            <span class="badge-orc <?= htmlspecialchars($orc['status']) ?>">
              <?= $rotulosStatus[$orc['status']] ?? $orc['status'] ?>
            </span>
          </td>
          <td style="font-size:12px;color:#64748b;"><?= htmlspecialchars($orc['criado_por_nome'] ?? '-') ?></td>
          <td style="font-size:11px;color:#94a3b8;"><?= date('d/m/Y', strtotime($orc['criado_em'])) ?></td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:nowrap;">
              <a href="<?= $baseUrl ?>/admin/orcamentos/detalhe.php?id=<?= (int)$orc['id'] ?>"
                 class="btn btn-secundario btn-sm">Ver</a>
              <?php if ($tel && $orc['status'] !== 'convertido'): ?>
                <a href="https://wa.me/55<?= $tel ?>?text=<?= urlencode('Olá! Segue o link do seu orçamento: ' . $linkPortal) ?>"
                   target="_blank" class="btn btn-sm"
                   style="background:#25d366;color:#fff;border:none;cursor:pointer;">
                  WhatsApp
                </a>
              <?php endif; ?>
              <?php if ($orc['status'] === 'aprovado' && $isGestor): ?>
                <button onclick="converter(<?= (int)$orc['id'] ?>)"
                        class="btn btn-sm btn-primario">→ OS</button>
              <?php endif; ?>
              <?php if ($orc['os_id_gerada']): ?>
                <a href="<?= $baseUrl ?>/admin/os/detalhe.php?id=<?= (int)$orc['os_id_gerada'] ?>"
                   class="btn btn-sm" style="background:#ede9fe;color:#7c3aed;border:none;">OS criada</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
async function converter(id) {
  if (!confirm('Converter este orçamento em OS?')) return;
  try {
    const r = await apiPost('/orcamentos/converter.php', {id});
    if (r && r.os_id) {
      window.location.href = '/app-tecnicos/admin/os/detalhe.php?id=' + r.os_id;
    }
  } catch (e) {
    alert('Erro: ' + e.message);
  }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
