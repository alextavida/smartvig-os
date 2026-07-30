<?php
declare(strict_types=1);

$perfisPermitidosPagina = ['gestor', 'supervisor', 'solicitante', 'comprador', 'aprovador'];
$tituloPagina = 'Compras — Dashboard';
$paginaAtiva  = 'compras_dash';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/compras_helpers.php';

$pdo      = obterConexao();
$isGestor = in_array($usuarioAtual['perfil'], ['gestor', 'supervisor'], true);

// Contagens por status
$porStatus = [];
$stmt = $pdo->query("SELECT status, COUNT(*) AS total FROM solicitacoes_compra GROUP BY status");
foreach ($stmt->fetchAll() as $r) { $porStatus[$r['status']] = (int) $r['total']; }

// Financeiro do mês
$stmtMes = $pdo->query(
    "SELECT SUM(valor_estimado) AS estimado, SUM(valor_final) AS final, COUNT(*) AS total
     FROM solicitacoes_compra WHERE YEAR(criado_em)=YEAR(CURDATE()) AND MONTH(criado_em)=MONTH(CURDATE())"
);
$finMes = $stmtMes->fetch();

// Economia (estimado - final, concluídas)
$stmtEcon = $pdo->query(
    "SELECT COALESCE(SUM(valor_estimado-valor_final),0) AS economia FROM solicitacoes_compra
     WHERE status IN ('recebido','concluido') AND valor_final IS NOT NULL AND valor_final < valor_estimado"
);
$economia = (float) $stmtEcon->fetchColumn();

// Pendentes de aprovação (urgente/alta primeiro)
$pendentes = $pdo->query(
    "SELECT sc.id, sc.numero, sc.prioridade, sc.justificativa, sc.criado_em, u.nome AS solicitante_nome
     FROM solicitacoes_compra sc
     INNER JOIN usuarios u ON u.id = sc.solicitante_id
     WHERE sc.status = 'aguardando_aprovacao'
     ORDER BY FIELD(sc.prioridade,'urgente','alta','media','baixa'), sc.criado_em ASC
     LIMIT 8"
)->fetchAll();

// Últimas compras em andamento
$emCompra = $pdo->query(
    "SELECT sc.id, sc.numero, sc.prioridade, sc.valor_final, sc.prazo_entrega, f.nome AS fornecedor, u.nome AS comprador_nome
     FROM solicitacoes_compra sc
     LEFT JOIN fornecedores f ON f.id = sc.fornecedor_id
     LEFT JOIN usuarios u ON u.id = sc.comprador_id
     WHERE sc.status = 'em_compra'
     ORDER BY sc.atualizado_em DESC LIMIT 5"
)->fetchAll();

// Compras por mês (Chart.js dados)
$meses = $pdo->query(
    "SELECT DATE_FORMAT(criado_em,'%Y-%m') mes, COUNT(*) total, COALESCE(SUM(valor_final),0) valor
     FROM solicitacoes_compra WHERE criado_em >= DATE_SUB(CURDATE(),INTERVAL 6 MONTH)
     GROUP BY mes ORDER BY mes"
)->fetchAll();

function s(array $arr, string $k, int $default = 0): int {
    return (int) ($arr[$k] ?? $default);
}
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
  <div>
    <h2 style="margin:0;font-size:1.1rem;">Dashboard de Compras</h2>
    <span style="font-size:13px;color:#6b7789;"><?= date('d/m/Y') ?> · <?= date('H:i') ?></span>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="/app-tecnicos/admin/compras/solicitacoes/nova.php" class="btn btn-primario btn-sm">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nova Solicitação
    </a>
    <a href="/app-tecnicos/admin/compras/solicitacoes/lista.php" class="btn btn-secundario btn-sm">Ver todas</a>
  </div>
</div>

<!-- Cards de status -->
<div class="grid-cards" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:22px;">
  <?php
  $cards = [
      ['label' => 'Rascunhos',      'key' => 'rascunho',             'cor' => '#94a3b8'],
      ['label' => 'Aguard. Aprov.', 'key' => 'aguardando_aprovacao', 'cor' => '#b8860b'],
      ['label' => 'Aprovadas',      'key' => 'aprovado',             'cor' => '#1462b0'],
      ['label' => 'Em Compra',      'key' => 'em_compra',            'cor' => '#8b5cf6'],
      ['label' => 'Recebidas',      'key' => 'recebido',             'cor' => '#1e8e5a'],
      ['label' => 'Concluídas',     'key' => 'concluido',            'cor' => '#16803c'],
      ['label' => 'Reprovadas',     'key' => 'reprovado',            'cor' => '#c62f2f'],
      ['label' => 'Canceladas',     'key' => 'cancelado',            'cor' => '#c62f2f'],
  ];
  foreach ($cards as $c): ?>
    <div class="stat-card" style="border-top-color:<?= $c['cor'] ?>;">
      <div class="valor"><?= s($porStatus, $c['key']) ?></div>
      <div class="rotulo"><?= $c['label'] ?></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Indicadores financeiros -->
<div class="grid-cards" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:22px;">
  <div class="card" style="border-left:4px solid #8b5cf6;padding:16px 20px;">
    <div style="font-size:11px;font-weight:700;color:#8b5cf6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Valor Estimado (mês)</div>
    <div style="font-size:24px;font-weight:800;color:#1c2430;"><?= formatarMoeda((float)($finMes['estimado'] ?? 0)) ?></div>
  </div>
  <div class="card" style="border-left:4px solid #1462b0;padding:16px 20px;">
    <div style="font-size:11px;font-weight:700;color:#1462b0;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Valor Comprado (mês)</div>
    <div style="font-size:24px;font-weight:800;color:#1c2430;"><?= formatarMoeda((float)($finMes['final'] ?? 0)) ?></div>
  </div>
  <div class="card" style="border-left:4px solid #1e8e5a;padding:16px 20px;">
    <div style="font-size:11px;font-weight:700;color:#1e8e5a;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Economia Total</div>
    <div style="font-size:24px;font-weight:800;color:#1c2430;"><?= formatarMoeda($economia) ?></div>
  </div>
  <div class="card" style="border-left:4px solid #c8641a;padding:16px 20px;">
    <div style="font-size:11px;font-weight:700;color:#c8641a;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Solicitações no Mês</div>
    <div style="font-size:24px;font-weight:800;color:#1c2430;"><?= (int)($finMes['total'] ?? 0) ?></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;" class="responsive-2col">

  <!-- Pendentes de aprovação -->
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
      <h3 style="margin:0;font-size:.95rem;display:flex;align-items:center;gap:6px;">
        <span style="background:#fdf3d9;color:#b8860b;border-radius:50%;width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;"><?= count($pendentes) ?></span>
        Aguardando Aprovação
      </h3>
      <?php if ($isGestor): ?>
      <a href="/app-tecnicos/admin/compras/solicitacoes/lista.php?status=aguardando_aprovacao" style="font-size:12px;color:#1462b0;">Ver todas →</a>
      <?php endif; ?>
    </div>
    <?php if (empty($pendentes)): ?>
      <div style="text-align:center;color:#94a3b8;padding:20px 0;font-size:13px;">Nenhuma aguardando aprovação</div>
    <?php else: ?>
      <?php foreach ($pendentes as $p): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eef1f5;gap:10px;">
          <div>
            <div style="font-size:12px;font-weight:700;color:#1c2430;">
              <?= htmlspecialchars($p['numero']) ?>
              <span class="badge <?= cssStatusCompra('aguardando_aprovacao') ?>" style="margin-left:6px;font-size:10px;padding:2px 8px;">
                <?= htmlspecialchars(mb_strtoupper($p['prioridade'])) ?>
              </span>
            </div>
            <div style="font-size:12px;color:#6b7789;margin-top:2px;"><?= htmlspecialchars($p['solicitante_nome']) ?> · <?= date('d/m', strtotime($p['criado_em'])) ?></div>
            <div style="font-size:12px;color:#475569;margin-top:2px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($p['justificativa']) ?></div>
          </div>
          <?php if ($isGestor): ?>
          <div style="display:flex;gap:4px;flex-shrink:0;">
            <button onclick="aprovarRapido(<?= (int)$p['id'] ?>, '<?= htmlspecialchars($p['numero']) ?>')"
                    class="btn btn-sucesso btn-sm" title="Aprovar">✓</button>
            <a href="/app-tecnicos/admin/compras/solicitacoes/detalhe.php?id=<?= (int)$p['id'] ?>" class="btn btn-secundario btn-sm">Ver</a>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Em compra -->
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
      <h3 style="margin:0;font-size:.95rem;">Em Compra</h3>
      <a href="/app-tecnicos/admin/compras/solicitacoes/lista.php?status=em_compra" style="font-size:12px;color:#1462b0;">Ver todas →</a>
    </div>
    <?php if (empty($emCompra)): ?>
      <div style="text-align:center;color:#94a3b8;padding:20px 0;font-size:13px;">Nenhuma compra em andamento</div>
    <?php else: ?>
      <?php foreach ($emCompra as $c): ?>
        <div style="padding:8px 0;border-bottom:1px solid #eef1f5;">
          <div style="display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:12px;font-weight:700;"><?= htmlspecialchars($c['numero']) ?></span>
            <span style="font-size:12px;color:#1e8e5a;font-weight:700;"><?= formatarMoeda((float)($c['valor_final'] ?? 0)) ?></span>
          </div>
          <div style="font-size:12px;color:#6b7789;margin-top:2px;">
            <?= htmlspecialchars($c['fornecedor'] ?? 'Fornecedor não definido') ?>
            <?php if ($c['prazo_entrega']): ?> · Entrega: <?= date('d/m', strtotime($c['prazo_entrega'])) ?><?php endif; ?>
          </div>
          <a href="/app-tecnicos/admin/compras/solicitacoes/detalhe.php?id=<?= (int)$c['id'] ?>" style="font-size:12px;color:#1462b0;">Ver detalhes →</a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Gráfico de compras por mês -->
<div class="card">
  <h3 style="margin-top:0;">Solicitações nos últimos 6 meses</h3>
  <canvas id="chartMeses" height="80"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const mesesDados = <?= json_encode($meses) ?>;
const labels  = mesesDados.map(m => { const [y,mo] = m.mes.split('-'); return ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'][parseInt(mo)-1]+'/'+y.slice(2); });
const totais  = mesesDados.map(m => parseInt(m.total));
const valores = mesesDados.map(m => parseFloat(m.valor));

new Chart(document.getElementById('chartMeses'), {
  type: 'bar',
  data: {
    labels,
    datasets: [
      {label: 'Solicitações', data: totais, backgroundColor: 'rgba(20,98,176,.75)', borderRadius: 6, yAxisID: 'y'},
      {label: 'Valor (R$)',   data: valores, type: 'line', borderColor: '#1e8e5a', backgroundColor: 'rgba(30,142,90,.1)', tension:.35, fill:true, yAxisID: 'y2'},
    ],
  },
  options: {
    responsive: true,
    interaction: {mode:'index', intersect:false},
    scales: {
      y:  {position:'left',  title:{display:true, text:'Qtd'}, grid:{color:'#f1f5f9'}},
      y2: {position:'right', title:{display:true, text:'R$'}, grid:{drawOnChartArea:false},
           ticks:{callback: v => 'R$ '+v.toLocaleString('pt-BR',{minimumFractionDigits:0})}},
    },
    plugins:{legend:{position:'top'}, tooltip:{callbacks:{label: c => c.dataset.label+': '+(c.dataset.yAxisID==='y2' ? 'R$ '+c.raw.toLocaleString('pt-BR',{minimumFractionDigits:2}) : c.raw)}}},
  },
});

async function aprovarRapido(id, numero) {
  if (!confirm('Aprovar a solicitação '+numero+'?')) return;
  const r = await fetch('/app-tecnicos/api/compras/aprovar.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','Authorization':'Bearer '+(window.APP_JWT||'')},
    body: JSON.stringify({id}),
  });
  const d = await r.json();
  if (d.sucesso) { location.reload(); }
  else { alert(d.erro || 'Erro ao aprovar.'); }
}
</script>
<style>
.responsive-2col { }
@media(max-width:768px) { .responsive-2col { grid-template-columns:1fr !important; } }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
