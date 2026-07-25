<?php
/**
 * Painel SLA em tempo real — OS no prazo, proximas do vencimento, atrasadas.
 * Auto-refresh a cada 60s. Indicadores 100% CSS (sem emojis).
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor', 'supervisor'];
$tituloPagina = 'Painel SLA';
$paginaAtiva  = 'sla';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = obterConexao();

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

$atrasadas  = array_filter($todas, fn($o) => (int)$o['dias_restantes'] < 0);
$criticas   = array_filter($todas, fn($o) => (int)$o['dias_restantes'] >= 0 && (int)$o['dias_restantes'] <= 1);
$atencao    = array_filter($todas, fn($o) => (int)$o['dias_restantes'] >= 2 && (int)$o['dias_restantes'] <= 3);
$noPrazo    = array_filter($todas, fn($o) => (int)$o['dias_restantes'] > 3);

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
    if ($dias < 0) {
        return '<span class="sla-label sla-atrasada-label">'
             . '<span class="sla-dot dot-vermelho"></span>'
             . abs($dias) . 'd em atraso</span>';
    }
    if ($dias === 0) {
        return '<span class="sla-label sla-atrasada-label">'
             . '<span class="sla-dot dot-vermelho"></span>'
             . 'Vence hoje</span>';
    }
    if ($dias === 1) {
        return '<span class="sla-label sla-critica-label">'
             . '<span class="sla-dot dot-laranja"></span>'
             . 'Amanhã</span>';
    }
    return '<span class="sla-label sla-atencao-label">'
         . '<span class="sla-dot dot-amarelo"></span>'
         . $dias . ' dias</span>';
}

function tabelaOs(array $lista): string {
    if (!$lista) {
        return '<p style="color:#94a3b8;font-size:13px;padding:8px 0;">Nenhuma OS nesta categoria.</p>';
    }
    $html  = '<div style="overflow-x:auto;"><table>';
    $html .= '<thead><tr><th>OS</th><th>Cliente</th><th>Técnico</th><th>Prazo</th><th>Status</th><th>Prioridade</th><th></th></tr></thead><tbody>';
    foreach ($lista as $os) {
        $html .= '<tr>';
        $html .= '<td style="font-size:12px;">' . htmlspecialchars($os['codigo'] ?: '#' . $os['id']) . '</td>';
        $html .= '<td>' . htmlspecialchars($os['cliente_nome'] ?? '-') . '</td>';
        $html .= '<td style="font-size:12px;">' . htmlspecialchars($os['tecnico_nome'] ?? '—') . '</td>';
        $html .= '<td>' . prazoLabel((int)$os['dias_restantes'])
               . '<br><span style="font-size:10px;color:#94a3b8;">'
               . date('d/m/Y', strtotime($os['data_prazo'])) . '</span></td>';
        $html .= '<td><span class="badge ' . $os['situacao_local'] . '">' . rotuloStatus($os['situacao_local']) . '</span></td>';
        $html .= '<td>' . badgePrioridade($os['prioridade'] ?? '') . '</td>';
        $html .= '<td><a href="/app-tecnicos/admin/os/detalhe.php?id=' . (int)$os['id']
               . '" class="btn btn-secundario btn-sm">Ver</a></td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table></div>';
    return $html;
}
?>

<style>
/* Indicadores de status SLA — sem emojis */
.sla-dot {
  display:inline-block; width:9px; height:9px; border-radius:50%;
  flex-shrink:0; margin-right:5px; vertical-align:middle;
}
.dot-vermelho { background:#dc2626; box-shadow:0 0 0 2px #fecaca; }
.dot-laranja  { background:#ea580c; box-shadow:0 0 0 2px #fed7aa; }
.dot-amarelo  { background:#ca8a04; box-shadow:0 0 0 2px #fef08a; }
.dot-verde    { background:#16803c; box-shadow:0 0 0 2px #bbf7d0; }
.dot-cinza    { background:#94a3b8; box-shadow:0 0 0 2px #e2e8f0; }

.sla-label {
  display:inline-flex; align-items:center; font-weight:700; font-size:12px;
}
.sla-atrasada-label { color:#dc2626; }
.sla-critica-label  { color:#ea580c; }
.sla-atencao-label  { color:#ca8a04; }

/* Cards de resumo */
.sla-resumo {
  display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
  gap:12px; margin-bottom:20px;
}
.sla-card {
  background:#fff; border-radius:12px; padding:16px 20px;
  box-shadow:var(--sombra); border-left:4px solid transparent; text-align:center;
}
.sla-card .num { font-size:36px; font-weight:800; }
.sla-card .rot { font-size:12px; color:#64748b; margin-top:2px; font-weight:600; display:flex; align-items:center; justify-content:center; gap:5px; }
.sla-atrasada-card { border-color:#dc2626; }
.sla-critica-card  { border-color:#ea580c; }
.sla-atencao-card  { border-color:#ca8a04; }
.sla-ok-card       { border-color:#16803c; }
.sla-sem-card      { border-color:#94a3b8; }

/* Seções */
.sla-secao { margin-bottom:20px; }
.sla-secao h3 {
  font-size:14px; margin:0 0 10px; display:flex; align-items:center; gap:8px;
}
.sla-secao-titulo-indicador {
  display:inline-flex; align-items:center; gap:6px;
}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
  <span style="font-size:13px;color:#64748b;">Auto-atualiza a cada 60 segundos — <span id="ultima-atualizacao"></span></span>
  <button onclick="location.reload()" class="btn btn-neutro btn-sm">Atualizar agora</button>
</div>

<!-- Resumo numérico -->
<div class="sla-resumo">
  <div class="sla-card sla-atrasada-card">
    <div class="num" style="color:#dc2626;"><?= count($atrasadas) ?></div>
    <div class="rot"><span class="sla-dot dot-vermelho"></span> Atrasadas</div>
  </div>
  <div class="sla-card sla-critica-card">
    <div class="num" style="color:#ea580c;"><?= count($criticas) ?></div>
    <div class="rot"><span class="sla-dot dot-laranja"></span> Críticas (até 1 dia)</div>
  </div>
  <div class="sla-card sla-atencao-card">
    <div class="num" style="color:#ca8a04;"><?= count($atencao) ?></div>
    <div class="rot"><span class="sla-dot dot-amarelo"></span> Atenção (2–3 dias)</div>
  </div>
  <div class="sla-card sla-ok-card">
    <div class="num" style="color:#16803c;"><?= count($noPrazo) ?></div>
    <div class="rot"><span class="sla-dot dot-verde"></span> No prazo (acima de 3 dias)</div>
  </div>
  <div class="sla-card sla-sem-card">
    <div class="num" style="color:#94a3b8;"><?= (int)$semPrazo ?></div>
    <div class="rot"><span class="sla-dot dot-cinza"></span> Sem prazo definido</div>
  </div>
</div>

<!-- Seções por categoria -->
<?php if ($atrasadas): ?>
<div class="card sla-secao" style="border-left:4px solid #dc2626;">
  <h3>
    <span class="sla-dot dot-vermelho" style="width:11px;height:11px;"></span>
    OS Atrasadas
    <span style="background:#fef2f2;color:#dc2626;border-radius:999px;padding:1px 8px;font-size:12px;font-weight:700;"><?= count($atrasadas) ?></span>
  </h3>
  <?= tabelaOs(array_values($atrasadas)) ?>
</div>
<?php endif; ?>

<?php if ($criticas): ?>
<div class="card sla-secao" style="border-left:4px solid #ea580c;">
  <h3>
    <span class="sla-dot dot-laranja" style="width:11px;height:11px;"></span>
    Críticas — vencem hoje ou amanhã
    <span style="background:#fff7ed;color:#ea580c;border-radius:999px;padding:1px 8px;font-size:12px;font-weight:700;"><?= count($criticas) ?></span>
  </h3>
  <?= tabelaOs(array_values($criticas)) ?>
</div>
<?php endif; ?>

<?php if ($atencao): ?>
<div class="card sla-secao" style="border-left:4px solid #ca8a04;">
  <h3>
    <span class="sla-dot dot-amarelo" style="width:11px;height:11px;"></span>
    Atenção — vencem em 2 a 3 dias
    <span style="background:#fefce8;color:#ca8a04;border-radius:999px;padding:1px 8px;font-size:12px;font-weight:700;"><?= count($atencao) ?></span>
  </h3>
  <?= tabelaOs(array_values($atencao)) ?>
</div>
<?php endif; ?>

<?php if ($noPrazo): ?>
<div class="card sla-secao" style="border-left:4px solid #16803c;">
  <h3>
    <span class="sla-dot dot-verde" style="width:11px;height:11px;"></span>
    No prazo
    <span style="background:#f0fdf4;color:#16803c;border-radius:999px;padding:1px 8px;font-size:12px;font-weight:700;"><?= count($noPrazo) ?></span>
  </h3>
  <?= tabelaOs(array_values($noPrazo)) ?>
</div>
<?php endif; ?>

<?php if (!$todas && !$semPrazo): ?>
<div class="card" style="text-align:center;padding:48px;color:#94a3b8;">
  <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#16803c" stroke-width="1.5" style="margin-bottom:16px;">
    <circle cx="12" cy="12" r="10"/>
    <path d="M9 12l2 2 4-4"/>
  </svg>
  <div style="font-size:16px;font-weight:700;color:#16803c;">Tudo em dia!</div>
  <div style="font-size:13px;margin-top:4px;">Nenhuma OS com prazo pendente.</div>
</div>
<?php endif; ?>

<script>
document.getElementById('ultima-atualizacao').textContent =
  'Última atualização: ' + new Date().toLocaleTimeString('pt-BR');
setTimeout(() => location.reload(), 60000);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
