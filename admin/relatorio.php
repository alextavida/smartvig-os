<?php
/**
 * Relatório financeiro e de desempenho:
 * Resumo por período, técnicos, status e receita estimada de produtos.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor'];
$tituloPagina = 'Relatório';
$paginaAtiva  = 'relatorio';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../config/database.php';

$pdo = obterConexao();

$periodoInicio = $_GET['de']  ?? date('Y-m-01');
$periodoFim    = $_GET['ate'] ?? date('Y-m-d');

// ── Totais gerais ──────────────────────────────────────────────────────────
$totaisPorStatus = $pdo->query(
    "SELECT situacao_local, COUNT(*) AS total FROM ordens_servico GROUP BY situacao_local"
)->fetchAll();
$totalPorStatus = [];
$totalGeral = 0;
foreach ($totaisPorStatus as $row) {
    $totalPorStatus[$row['situacao_local']] = (int) $row['total'];
    $totalGeral += (int) $row['total'];
}

// ── OS no período ──────────────────────────────────────────────────────────
$stmtPeriodo = $pdo->prepare(
    "SELECT os.*, resp.nome AS tecnico_nome
     FROM ordens_servico os
     LEFT JOIN usuarios resp ON resp.id = os.tecnico_id
     WHERE (os.data_agendamento BETWEEN :de AND :ate
            OR (os.situacao_local = 'concluido' AND DATE(os.data_conclusao) BETWEEN :de2 AND :ate2))
     ORDER BY os.data_agendamento IS NULL, os.data_agendamento DESC"
);
$stmtPeriodo->execute([
    'de' => $periodoInicio, 'ate' => $periodoFim,
    'de2' => $periodoInicio, 'ate2' => $periodoFim,
]);
$osNoPeriodo = $stmtPeriodo->fetchAll();

// ── Receita estimada (soma de produtos_json no período) ───────────────────
$receitaTotal = 0.0;
$osConcluidas = [];
foreach ($osNoPeriodo as $os) {
    if ($os['situacao_local'] === 'concluido' && $os['produtos_json']) {
        $prods = json_decode($os['produtos_json'], true) ?? [];
        $subtotal = 0.0;
        foreach ($prods as $p) {
            $subtotal += (float) ($p['valor_venda'] ?? 0) * (float) ($p['quantidade'] ?? 1);
        }
        $receitaTotal += $subtotal;
        $osConcluidas[] = array_merge($os, ['subtotal' => $subtotal, 'produtos' => $prods]);
    }
}

// ── Ranking de técnicos no período ────────────────────────────────────────
$rankingRaw = [];
foreach ($osNoPeriodo as $os) {
    $nome = $os['tecnico_nome'] ?? 'Sem técnico';
    if (!isset($rankingRaw[$nome])) {
        $rankingRaw[$nome] = ['total' => 0, 'concluidas' => 0];
    }
    $rankingRaw[$nome]['total']++;
    if ($os['situacao_local'] === 'concluido') {
        $rankingRaw[$nome]['concluidas']++;
    }
}
arsort($rankingRaw);

// ── OS por dia no período (para sparkline) ────────────────────────────────
$porDia = [];
foreach ($osNoPeriodo as $os) {
    $d = $os['data_agendamento'] ?? ($os['criado_em'] ? date('Y-m-d', strtotime($os['criado_em'])) : null);
    if ($d) { $porDia[$d] = ($porDia[$d] ?? 0) + 1; }
}
ksort($porDia);

$rotulosStatus = [
    'aberto' => 'Abertas', 'em_andamento' => 'Em andamento',
    'pausado' => 'Pausadas', 'reagendado' => 'Reagendadas',
    'concluido' => 'Concluídas', 'cancelado' => 'Canceladas',
];
$coresStatus = [
    'aberto' => '#1462b0', 'em_andamento' => '#b8860b',
    'pausado' => '#6b7789', 'reagendado' => '#c8641a',
    'concluido' => '#1e8e5a', 'cancelado' => '#c62f2f',
];
?>

<style>
.kpi-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:14px; margin-bottom:14px; }
.kpi { background:#fff; border-radius:12px; padding:18px 16px; box-shadow:0 2px 8px rgba(0,0,0,.06); }
.kpi-val { font-size:28px; font-weight:800; line-height:1.1; }
.kpi-lbl { font-size:12px; color:#64748b; margin-top:4px; font-weight:600; }
.sparkline-bar { display:inline-block; background:var(--azul-600,#1a73c7); border-radius:3px 3px 0 0; min-width:4px; vertical-align:bottom; }
.rank-row { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid #f1f5f9; }
.rank-bar { height:8px; border-radius:4px; background:#1462b0; transition:.3s; }
.status-pill { display:inline-block; padding:2px 9px; border-radius:999px; font-size:11px; font-weight:700; }
.periodo-form { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
.periodo-form label { font-size:12px; font-weight:600; color:#64748b; }
.periodo-form input[type=date] { border:1px solid #cbd3dd; border-radius:8px; padding:6px 10px; font-size:13px; }
table.tabela-rel th { font-weight:700; font-size:12px; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
table.tabela-rel td, table.tabela-rel th { padding:8px 10px; }
table.tabela-rel tr:nth-child(even) td { background:#f8fafc; }
</style>

<div class="topbar">
  <h2 style="margin:0; font-size:20px;">Relatório</h2>
  <form class="periodo-form no-print" method="get">
    <div>
      <label>De</label><br>
      <input type="date" name="de" value="<?= htmlspecialchars($periodoInicio) ?>">
    </div>
    <div>
      <label>Até</label><br>
      <input type="date" name="ate" value="<?= htmlspecialchars($periodoFim) ?>">
    </div>
    <button type="submit" class="btn btn-primario btn-sm">Filtrar</button>
    <a href="?de=<?= date('Y-m-01') ?>&ate=<?= date('Y-m-d') ?>" class="btn btn-neutro btn-sm">Mês atual</a>
    <a href="?de=<?= date('Y-01-01') ?>&ate=<?= date('Y-12-31') ?>" class="btn btn-neutro btn-sm">Ano</a>
  </form>
</div>

<!-- KPIs totais -->
<div class="kpi-grid">
  <div class="kpi" style="border-left:4px solid #1462b0;">
    <div class="kpi-val" style="color:#1462b0;"><?= $totalGeral ?></div>
    <div class="kpi-lbl">Total de OS (todas)</div>
  </div>
  <div class="kpi" style="border-left:4px solid #1e8e5a;">
    <div class="kpi-val" style="color:#1e8e5a;"><?= $totalPorStatus['concluido'] ?? 0 ?></div>
    <div class="kpi-lbl">Concluídas (total)</div>
  </div>
  <div class="kpi" style="border-left:4px solid #b8860b;">
    <div class="kpi-val" style="color:#b8860b;"><?= $totalPorStatus['em_andamento'] ?? 0 ?></div>
    <div class="kpi-lbl">Em andamento</div>
  </div>
  <div class="kpi" style="border-left:4px solid #1462b0;">
    <div class="kpi-val" style="color:#1462b0;"><?= count($osNoPeriodo) ?></div>
    <div class="kpi-lbl">OS no período</div>
  </div>
  <div class="kpi" style="border-left:4px solid #1e8e5a;">
    <div class="kpi-val" style="color:#1e8e5a;">R$ <?= number_format($receitaTotal, 2, ',', '.') ?></div>
    <div class="kpi-lbl">Receita est. (produtos)</div>
  </div>
  <div class="kpi" style="border-left:4px solid #c8641a;">
    <div class="kpi-val" style="color:#c8641a;"><?= $totalPorStatus['aberto'] ?? 0 ?></div>
    <div class="kpi-lbl">Em aberto</div>
  </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">

  <!-- Distribuição por status -->
  <div class="card">
    <h3 style="margin-top:0; font-size:14px;">Distribuição por status (todas as OS)</h3>
    <?php foreach ($rotulosStatus as $s => $r): ?>
      <?php $n = $totalPorStatus[$s] ?? 0; $pct = $totalGeral ? round($n / $totalGeral * 100) : 0; ?>
      <div style="margin-bottom:10px;">
        <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">
          <span>
            <span class="status-pill" style="background:<?= $coresStatus[$s] ?>22; color:<?= $coresStatus[$s] ?>;"><?= $r ?></span>
          </span>
          <span style="font-weight:700; color:<?= $coresStatus[$s] ?>;"><?= $n ?> <span style="color:#94a3b8;">(<?= $pct ?>%)</span></span>
        </div>
        <div style="height:6px; background:#f1f5f9; border-radius:3px;">
          <div style="height:6px; width:<?= $pct ?>%; background:<?= $coresStatus[$s] ?>; border-radius:3px; transition:.4s;"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Ranking de técnicos -->
  <div class="card">
    <h3 style="margin-top:0; font-size:14px;">Técnicos no período (<?= date('d/m', strtotime($periodoInicio)) ?> – <?= date('d/m/Y', strtotime($periodoFim)) ?>)</h3>
    <?php if (empty($rankingRaw)): ?>
      <div class="vazio" style="padding:20px;">Sem OS no período.</div>
    <?php else: ?>
      <?php $maxTotal = max(array_column($rankingRaw, 'total') ?: [1]); ?>
      <?php foreach ($rankingRaw as $nome => $dados): ?>
        <div class="rank-row">
          <div style="flex:1; min-width:0;">
            <div style="font-weight:600; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($nome) ?></div>
            <div style="margin-top:4px;">
              <div class="rank-bar" style="width:<?= round($dados['total'] / $maxTotal * 100) ?>%;"></div>
            </div>
          </div>
          <div style="text-align:right; font-size:12px; color:#64748b; min-width:60px;">
            <strong style="color:#1462b0;"><?= $dados['total'] ?></strong> OS<br>
            <span style="color:#1e8e5a;"><?= $dados['concluidas'] ?></span> concl.
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<!-- Sparkline diário -->
<?php if (!empty($porDia)): ?>
<div class="card">
  <h3 style="margin-top:0; font-size:14px;">OS por dia no período</h3>
  <div style="display:flex; align-items:flex-end; gap:3px; height:60px; padding:4px 0;">
    <?php $maxDia = max($porDia); ?>
    <?php foreach ($porDia as $dia => $qtd): ?>
      <?php $h = $maxDia > 0 ? round($qtd / $maxDia * 52) : 4; ?>
      <div title="<?= date('d/m', strtotime($dia)) ?>: <?= $qtd ?> OS"
           class="sparkline-bar"
           style="height:<?= $h ?>px; flex:1; max-width:32px; cursor:default;"></div>
    <?php endforeach; ?>
  </div>
  <div style="display:flex; justify-content:space-between; font-size:11px; color:#94a3b8; margin-top:4px;">
    <span><?= date('d/m', strtotime($periodoInicio)) ?></span>
    <span><?= date('d/m', strtotime($periodoFim)) ?></span>
  </div>
</div>
<?php endif; ?>

<!-- OS concluídas com receita de produtos -->
<?php if (!empty($osConcluidas)): ?>
<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
    <h3 style="margin:0; font-size:14px;">OS concluídas com produtos no período</h3>
    <strong style="color:#1e8e5a;">Total: R$ <?= number_format($receitaTotal, 2, ',', '.') ?></strong>
  </div>
  <div style="overflow-x:auto;">
    <table class="tabela-rel" style="width:100%; border-collapse:collapse;">
      <thead>
        <tr>
          <th>#</th><th>Código GC</th><th>Cliente</th><th>Técnico</th><th>Conclusão</th><th>Produtos</th><th>Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($osConcluidas as $os): ?>
        <tr>
          <td><a href="/app-tecnicos/admin/os/detalhe.php?id=<?= $os['id'] ?>" style="color:#1462b0;">#<?= $os['id'] ?></a></td>
          <td><?= htmlspecialchars($os['codigo'] ?? '-') ?></td>
          <td><?= htmlspecialchars($os['cliente_nome'] ?? '-') ?></td>
          <td><?= htmlspecialchars($os['tecnico_nome'] ?? '-') ?></td>
          <td><?= $os['data_conclusao'] ? date('d/m/Y H:i', strtotime($os['data_conclusao'])) : '-' ?></td>
          <td>
            <?php foreach ($os['produtos'] as $p): ?>
              <div style="font-size:11px;"><?= htmlspecialchars($p['nome']) ?> × <?= $p['quantidade'] ?></div>
            <?php endforeach; ?>
          </td>
          <td style="font-weight:700; color:#1e8e5a;">R$ <?= number_format($os['subtotal'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Todas as OS no período -->
<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
    <h3 style="margin:0; font-size:14px;">Todas as OS no período (<?= count($osNoPeriodo) ?>)</h3>
    <a href="?de=<?= urlencode($periodoInicio) ?>&ate=<?= urlencode($periodoFim) ?>&exportar=csv"
       class="btn btn-neutro btn-sm no-print">⬇ Exportar CSV</a>
  </div>
  <?php if (empty($osNoPeriodo)): ?>
    <div class="vazio" style="padding:30px;">Nenhuma OS no período selecionado.</div>
  <?php else: ?>
  <div style="overflow-x:auto;">
    <table class="tabela-rel" style="width:100%; border-collapse:collapse;">
      <thead>
        <tr><th>#</th><th>Código</th><th>Cliente</th><th>Técnico</th><th>Data</th><th>Prioridade</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($osNoPeriodo as $os): ?>
        <tr>
          <td><a href="/app-tecnicos/admin/os/detalhe.php?id=<?= $os['id'] ?>" style="color:#1462b0;">#<?= $os['id'] ?></a></td>
          <td style="font-size:12px; color:#64748b;"><?= htmlspecialchars($os['codigo'] ?? '-') ?></td>
          <td><?= htmlspecialchars($os['cliente_nome'] ?? '-') ?></td>
          <td><?= htmlspecialchars($os['tecnico_nome'] ?? '-') ?></td>
          <td><?= $os['data_agendamento'] ? date('d/m/Y', strtotime($os['data_agendamento'])) : '-' ?></td>
          <td>
            <?php $pr = $os['prioridade'] ?? 'baixo'; ?>
            <span style="font-size:11px; font-weight:700; color:<?= $pr==='urgente'?'#b71c1c':($pr==='intermediario'?'#e65100':'#2e7d32') ?>;">
              <?= ucfirst($pr) ?>
            </span>
          </td>
          <td>
            <span class="status-pill" style="background:<?= $coresStatus[$os['situacao_local']] ?? '#ddd' ?>22; color:<?= $coresStatus[$os['situacao_local']] ?? '#333' ?>;">
              <?= $rotulosStatus[$os['situacao_local']] ?? $os['situacao_local'] ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php
// Export CSV
if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'Codigo GC', 'Cliente', 'Tecnico', 'Data Agend.', 'Prioridade', 'Status', 'Conclusao', 'Subtotal Produtos'], ';');
    foreach ($osNoPeriodo as $os) {
        $prods = $os['produtos_json'] ? json_decode($os['produtos_json'], true) : [];
        $sub = array_sum(array_map(fn($p) => ($p['valor_venda'] ?? 0) * ($p['quantidade'] ?? 1), $prods));
        fputcsv($out, [
            '#' . $os['id'], $os['codigo'] ?? '', $os['cliente_nome'] ?? '', $os['tecnico_nome'] ?? '',
            $os['data_agendamento'] ? date('d/m/Y', strtotime($os['data_agendamento'])) : '',
            ucfirst($os['prioridade'] ?? ''),
            $rotulosStatus[$os['situacao_local']] ?? $os['situacao_local'],
            $os['data_conclusao'] ? date('d/m/Y H:i', strtotime($os['data_conclusao'])) : '',
            'R$ ' . number_format($sub, 2, ',', '.'),
        ], ';');
    }
    fclose($out);
    exit;
}
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
