<?php
/**
 * Painel SLA em tempo real — OS no prazo, proximas do vencimento, atrasadas.
 * Auto-refresh a cada 60s.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor', 'supervisor'];
$tituloPagina = 'Painel SLA';
$paginaAtiva  = 'sla';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = obterConexao();

// Busca OS nao concluidas com prazo definido
$stmt = $pdo->query(
    "SELECT os.id, os.codigo, os.cliente_nome, os.cliente_telefone,
            os.situacao_local, os.prioridade, os.data_prazo, os.data_agendamento,
            u.nome AS tecnico_nome, os.tecnico_id,
            DATEDIFF(os.data_prazo, CURDATE()) AS dias_restantes
     FROM ordens_servico os
     LEFT JOIN usuarios u ON u.id = os.tecnico_id
     WHERE os.situacao_local NOT IN ('concluido','cancelado')
       AND os.data_prazo IS NOT NULL
     ORDER BY os.data_prazo ASC, os.prioridade DESC"
);
$todas = $stmt->fetchAll();

// Classifica
$atrasadas  = array_filter($todas, fn($o) => (int)$o['dias_restantes'] < 0);
$criticas   = array_filter($todas, fn($o) => (int)$o['dias_restantes'] >= 0 && (int)$o['dias_restantes'] <= 1);
$atencao    = array_filter($todas, fn($o) => (int)$o['dias_restantes'] >= 2 && (int)$o['dias_restantes'] <= 3);
$noPrazo    = array_filter($todas, fn($o) => (int)$o['dias_restantes'] > 3);

// OS sem prazo definido (em aberto)
$semPrazo = $pdo->query(
    "SELECT COUNT(*) FROM ordens_servico
     WHERE situacao_local NOT IN ('concluido','cancelado') AND data_prazo IS NULL"
)->fetchColumn();

function rotuloStatus(string $s): string {
    return match($s) {
        'aberto'       => 'Aberto',
        'em_andamento' => 'Em andamento',
        'pausado'      => 'Pausado',
        'reagendado'   => 'Reagendado',
        default        => $s,
    };
}

function badgePrioridade(string $p): string {
    return match($p) {
        'urgente'       => '<span class="badge urgente" style="font-size:10px;">Urgente</span>',
        'intermediario' => '<span class="badge em_andamento" style="font-size:10px;">Médio</span>',
        default         => '',
    };
}

function prazoLabel(int $dias): string {
    if ($dias < 0) return '<span style="color:#dc2626;font-weight:700;">🔴 ' . abs($dias) . 'd atrasada</span>';
    if ($dias === 0) return '<span style="color:#dc2626;font-weight:700;">🔴 Vence hoje</span>';
    if ($dias === 1) return '<span style="color:#ea580c;font-weight:700;">🟠 Amanhã</span>';
    return '<span style="color:#ca8a04;font-weight:600;">🟡 ' . $dias . ' dias</span>';
}

function tabelaOs(array $lista, string $corBorda): string {
    if (!$lista) return '<p style="color:#94a3b8;font-size:13px;padding:8px 0;">Nenhuma OS nesta categoria.</p>';
    $html = '<div style="overflow-x:auto;"><table>';
    $html .= '<thead><tr><th>OS</th><th>Cliente</th><th>Técnico</th><th>Prazo</th><th>Status</th><th>Prioridade</th><th></th></tr></thead><tbody>';
    foreach ($lista as $os) {
        $html .= '<tr>';
        $html .= '<td style="font-size:12px;">' . htmlspecialchars($os['codigo'] ?: '#'.$os['id']) . '</td>';
        $html .= '<td>' . htmlspecialchars($os['cliente_nome'] ?? '-') . '</td>';
        $html .= '<td style="font-size:12px;">' . htmlspecialchars($os['tecnico_nome'] ?? '—') . '</td>';
        $html .= '<td>' . prazoLabel((int)$os['dias_restantes']) . '<br><span style="font-size:10px;color:#94a3b8;">' . date('d/m/Y', strtotime($os['data_prazo'])) . '</span></td>';
        $html .= '<td><span class="badge ' . $os['situacao_local'] . '">' . rotuloStatus($os['situacao_local']) . '</span></td>';
        $html .= '<td>' . badgePrioridade($os['prioridade'] ?? '') . '</td>';
        $html .= '<td><a href="/app-tecnicos/admin/os/detalhe.php?id=' . (int)$os['id'] . '" class="btn btn-secundario btn-sm">Ver</a></td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table></div>';
    return $html;
}
?>

<style>
.sla-resumo { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:20px; }
.sla-card {
  background:#fff; border-radius:12px; padding:16px 20px;
  box-shadow:var(--sombra); border-left:4px solid transparent; text-align:center;
}
.sla-card .num { font-size:36px; font-weight:800; }
.sla-card .rot { font-size:12px; color:#64748b; margin-top:2px; font-weight:600; }
.sla-atrasada { border-color:#dc2626; }
.sla-critica  { border-color:#ea580c; }
.sla-atencao  { border-color:#ca8a04; }
.sla-ok       { border-color:#16803c; }
.sla-sem      { border-color:#94a3b8; }
.sla-secao { margin-bottom:20px; }
.sla-secao h3 { font-size:14px; margin:0 0 10px; display:flex; align-items:center; gap:8px; }
.atualizado { font-size:11px; color:#94a3b8; float:right; }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
  <span style="font-size:13px;color:#64748b;">Auto-atualiza a cada 60 segundos</span>
  <span class="atualizado" id="ultima-atualizacao"></span>
  <button onclick="location.reload()" class="btn btn-neutro btn-sm">↻ Atualizar</button>
</div>

<!-- Resumo -->
<div class="sla-resumo">
  <div class="sla-card sla-atrasada">
    <div class="num" style="color:#dc2626;"><?= count($atrasadas) ?></div>
    <div class="rot">🔴 Atrasadas</div>
  </div>
  <div class="sla-card sla-critica">
    <div class="num" style="color:#ea580c;"><?= count($criticas) ?></div>
    <div class="rot">🟠 Críticas (≤1 dia)</div>
  </div>
  <div class="sla-card sla-atencao">
    <div class="num" style="color:#ca8a04;"><?= count($atencao) ?></div>
    <div class="rot">🟡 Atenção (2-3 dias)</div>
  </div>
  <div class="sla-card sla-ok">
    <div class="num" style="color:#16803c;"><?= count($noPrazo) ?></div>
    <div class="rot">🟢 No prazo (&gt;3 dias)</div>
  </div>
  <div class="sla-card sla-sem">
    <div class="num" style="color:#94a3b8;"><?= (int)$semPrazo ?></div>
    <div class="rot">⚪ Sem prazo definido</div>
  </div>
</div>

<!-- Seções por categoria -->
<?php if ($atrasadas): ?>
<div class="card sla-secao" style="border-left:4px solid #dc2626;">
  <h3>🔴 OS Atrasadas <span style="background:#fef2f2;color:#dc2626;border-radius:999px;padding:1px 8px;font-size:12px;"><?= count($atrasadas) ?></span></h3>
  <?= tabelaOs(array_values($atrasadas), '#dc2626') ?>
</div>
<?php endif; ?>

<?php if ($criticas): ?>
<div class="card sla-secao" style="border-left:4px solid #ea580c;">
  <h3>🟠 Críticas — vencem hoje ou amanhã <span style="background:#fff7ed;color:#ea580c;border-radius:999px;padding:1px 8px;font-size:12px;"><?= count($criticas) ?></span></h3>
  <?= tabelaOs(array_values($criticas), '#ea580c') ?>
</div>
<?php endif; ?>

<?php if ($atencao): ?>
<div class="card sla-secao" style="border-left:4px solid #ca8a04;">
  <h3>🟡 Atenção — vencem em 2 a 3 dias <span style="background:#fefce8;color:#ca8a04;border-radius:999px;padding:1px 8px;font-size:12px;"><?= count($atencao) ?></span></h3>
  <?= tabelaOs(array_values($atencao), '#ca8a04') ?>
</div>
<?php endif; ?>

<?php if ($noPrazo): ?>
<div class="card sla-secao" style="border-left:4px solid #16803c;">
  <h3>🟢 No prazo <span style="background:#f0fdf4;color:#16803c;border-radius:999px;padding:1px 8px;font-size:12px;"><?= count($noPrazo) ?></span></h3>
  <?= tabelaOs(array_values($noPrazo), '#16803c') ?>
</div>
<?php endif; ?>

<?php if (!$todas && !$semPrazo): ?>
<div class="card" style="text-align:center;padding:48px;color:#94a3b8;">
  <div style="font-size:48px;margin-bottom:12px;">🎉</div>
  <div style="font-size:16px;font-weight:700;color:#64748b;">Tudo em dia!</div>
  <div style="font-size:13px;margin-top:4px;">Nenhuma OS com prazo pendente.</div>
</div>
<?php endif; ?>

<script>
document.getElementById('ultima-atualizacao').textContent =
  'Última atualização: ' + new Date().toLocaleTimeString('pt-BR');
// Auto-refresh a cada 60s
setTimeout(() => location.reload(), 60000);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
