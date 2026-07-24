<?php
/**
 * Dashboard do gestor: cards de totais por status + ultimas OS.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor'];
$tituloPagina = 'Dashboard';
$paginaAtiva = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../config/database.php';

$pdo = obterConexao();

$statusLista = ['aberto', 'em_andamento', 'pausado', 'reagendado', 'concluido', 'cancelado'];
$rotulos = [
    'aberto' => 'Abertas',
    'em_andamento' => 'Em andamento',
    'pausado' => 'Pausadas',
    'reagendado' => 'Reagendadas',
    'concluido' => 'Concluidas hoje',
    'cancelado' => 'Canceladas',
];

$contagens = array_fill_keys($statusLista, 0);
$stmt = $pdo->query('SELECT situacao_local, COUNT(*) AS total FROM ordens_servico GROUP BY situacao_local');
foreach ($stmt->fetchAll() as $linha) {
    $contagens[$linha['situacao_local']] = (int) $linha['total'];
}

$stmtConcluidasHoje = $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE situacao_local = 'concluido' AND DATE(data_conclusao) = CURDATE()");
$concluidasHoje = (int) $stmtConcluidasHoje->fetchColumn();

$stmtTecnicosAtivos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'tecnico' AND ativo = 1");
$tecnicosAtivos = (int) $stmtTecnicosAtivos->fetchColumn();

$stmtUltimas = $pdo->query(
    "SELECT os.id, os.gc_os_id, os.codigo, os.cliente_nome, os.situacao_local, os.prioridade,
            os.data_agendamento, os.observacoes, os.sincronizado_gc, resp.nome AS tecnico_nome
     FROM ordens_servico os
     LEFT JOIN usuarios resp ON resp.id = os.tecnico_id
     ORDER BY os.criado_em DESC
     LIMIT 10"
);
$ultimasOs = $stmtUltimas->fetchAll();

// Última vez que houve sincronização com o GC
$stmtSync = $pdo->query("SELECT MAX(atualizado_em) FROM ordens_servico WHERE sincronizado_gc = 1");
$ultimaSync = $stmtSync->fetchColumn();

function rotuloStatus(string $s): string
{
    $mapa = [
        'aberto' => 'Aberto', 'em_andamento' => 'Em andamento', 'pausado' => 'Pausado',
        'reagendado' => 'Reagendado', 'concluido' => 'Concluido', 'cancelado' => 'Cancelado',
    ];
    return $mapa[$s] ?? $s;
}
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
  <h2 style="margin:0;font-size:1.1rem;">Visao geral</h2>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <span id="sync-status" style="font-size:12px;color:#666;">
      <?php if ($ultimaSync): ?>
        Última sync: <?= date('d/m H:i', strtotime($ultimaSync)) ?>
      <?php endif; ?>
    </span>
    <button id="btn-sync-gc" onclick="sincronizarGC(false)" class="btn btn-primario btn-sm" style="display:flex;align-items:center;gap:6px;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
      Sincronizar GestaoClick
    </button>
  </div>
</div>

<div class="grid-cards">
  <?php foreach ($statusLista as $s): ?>
    <div class="stat-card <?= $s ?>">
      <div class="valor"><?= $s === 'concluido' ? $concluidasHoje : $contagens[$s] ?></div>
      <div class="rotulo"><?= $rotulos[$s] ?></div>
    </div>
  <?php endforeach; ?>
  <div class="stat-card" style="border-top-color:#1a56a0;">
    <div class="valor"><?= $tecnicosAtivos ?></div>
    <div class="rotulo">Tecnicos ativos</div>
  </div>
</div>

<div class="card">
  <h3 style="margin-top:0;">Ultimas ordens de servico</h3>
  <?php if (empty($ultimasOs)): ?>
    <div class="vazio" style="text-align:center;padding:32px;">
      <?= ic('os_vazio') ?><br>
      Nenhuma OS encontrada.<br>
      <button onclick="sincronizarGC(false)" class="btn btn-primario" style="margin-top:12px;">Importar do GestaoClick agora</button>
    </div>
  <?php else: ?>
  <div style="overflow-x:auto;">
  <table>
    <thead>
      <tr>
        <th>#GC</th>
        <th>Cliente</th>
        <th>Descricao</th>
        <th>Tecnico</th>
        <th>Agendamento</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($ultimasOs as $os): ?>
        <tr>
          <td style="font-size:12px;color:#64748b;">
            <?= $os['codigo'] ? htmlspecialchars($os['codigo']) : '#' . (int) $os['id'] ?>
            <?php if ($os['gc_os_id']): ?>
              <br><span style="font-size:10px;color:#94a3b8;">GC:<?= (int) $os['gc_os_id'] ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?= htmlspecialchars($os['cliente_nome'] ?? '-') ?>
          </td>
          <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:#475569;">
            <?= htmlspecialchars(mb_strtolower(mb_substr($os['observacoes'] ?? '', 0, 60))) ?>
          </td>
          <td><?= htmlspecialchars($os['tecnico_nome'] ?? '-') ?></td>
          <td><?= $os['data_agendamento'] ? date('d/m/Y', strtotime($os['data_agendamento'])) : '-' ?></td>
          <td><span class="badge <?= $os['situacao_local'] ?>"><?= rotuloStatus($os['situacao_local']) ?></span></td>
          <td>
            <div class="acoes-tabela">
              <a href="/app-tecnicos/admin/os/detalhe.php?id=<?= (int) $os['id'] ?>" class="btn btn-secundario btn-sm">Ver</a>
              <a href="/app-tecnicos/admin/os/imprimir.php?id=<?= (int) $os['id'] ?>" target="_blank" class="btn-icone" title="Imprimir"><?= ic('imprimir', 14) ?></a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div style="margin-top:10px;text-align:right;">
    <a href="/app-tecnicos/admin/os/" style="font-size:13px;color:#1d4ed8;">Ver todas as OS →</a>
  </div>
  <?php endif; ?>
</div>

<!-- Dashboard GC: situações ao vivo -->
<div class="card" id="card-gc-situacoes" style="margin-bottom:16px;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
    <h3 style="margin:0;font-size:1rem;">Situações no GestãoClick</h3>
    <span id="gc-sit-status" style="font-size:12px;color:#64748b;">Carregando...</span>
  </div>
  <div id="gc-sit-grid" style="display:flex;flex-wrap:wrap;gap:8px;min-height:40px;"></div>
</div>

<script>
async function sincronizarGC(silencioso) {
  const btn = document.getElementById('btn-sync-gc');
  const statusEl = document.getElementById('sync-status');
  btn.disabled = true;
  if (!silencioso) {
    btn.innerHTML = '<span style="display:inline-block;animation:spin 1s linear infinite;">&#8635;</span> Sincronizando...';
    statusEl.textContent = '';
  }
  try {
    const r = await fetch('/app-tecnicos/api/os/sincronizar.php', {
      method: 'POST',
      headers: {'Authorization': 'Bearer ' + (window.APP_JWT || ''), 'Content-Type': 'application/json'},
      body: JSON.stringify({ force: true }),
    });
    const d = await r.json();
    if (d.sucesso) {
      const {criadas, atualizadas} = d.dados;
      const agora = new Date().toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'});
      statusEl.style.color = '#16803c';
      statusEl.textContent = `✓ ${criadas} criadas, ${atualizadas} atualizadas — ${agora}`;
      if (criadas > 0) { setTimeout(() => location.reload(), 1000); }
    } else {
      if (!silencioso) {
        statusEl.style.color = '#c0392b';
        statusEl.textContent = '✕ ' + (d.erro || d.mensagem || 'Erro desconhecido');
      }
    }
  } catch (e) {
    if (!silencioso) {
      statusEl.style.color = '#c0392b';
      statusEl.textContent = '✕ Falha de conexao';
    }
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg> Sincronizar GestaoClick';
  }
}

async function carregarSituacoesGC() {
  const grid   = document.getElementById('gc-sit-grid');
  const status = document.getElementById('gc-sit-status');
  try {
    const r = await fetch('/app-tecnicos/api/os/situacoes-gc.php', {
      headers: {'Authorization': 'Bearer ' + (window.APP_JWT || '')},
    });
    const d = await r.json();
    if (!d.sucesso) { throw new Error(d.erro || 'Erro'); }

    const sits    = d.dados.gc_situacoes   || [];
    const locais  = d.dados.contagens_local || {};
    const totalGC = d.dados.total_sincronizado || 0;

    status.textContent = `${totalGC} OS sincronizadas com GC`;
    status.style.color = '#16803c';

    if (sits.length === 0) {
      grid.innerHTML = '<span style="font-size:13px;color:#64748b;">Situações GC não disponíveis no momento.</span>';
    } else {
      grid.innerHTML = sits.map(s => `
        <div style="background:#f4f9fe;border:1px solid #e8f1fc;border-radius:8px;padding:10px 14px;min-width:130px;text-align:center;">
          <div style="font-size:11px;color:#6b7789;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">${s.nome}</div>
          <div style="font-size:20px;font-weight:800;color:#1462b0;">${s.id || '—'}</div>
        </div>`).join('');
    }

    // Cards de totais locais por situacao
    const rotulosLocais = {aberto:'Abertas',em_andamento:'Em andamento',pausado:'Pausadas',reagendado:'Reagendadas',concluido:'Concluídas',cancelado:'Canceladas'};
    const cores = {aberto:'#1462b0',em_andamento:'#b8860b',pausado:'#5a6472',reagendado:'#c8641a',concluido:'#1e8e5a',cancelado:'#c62f2f'};
    const localCards = Object.entries(locais).map(([k,v]) => `
      <div style="background:#fff;border:2px solid ${cores[k]||'#e2e8f0'};border-radius:8px;padding:10px 14px;min-width:120px;text-align:center;">
        <div style="font-size:10px;color:${cores[k]||'#64748b'};font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">${rotulosLocais[k]||k}</div>
        <div style="font-size:22px;font-weight:800;color:${cores[k]||'#1c2430'};">${v}</div>
      </div>`).join('');
    if (localCards) {
      grid.insertAdjacentHTML('beforeend', '<div style="width:100%;height:1px;background:#eef1f5;margin:10px 0;"></div>' + localCards);
    }

  } catch (e) {
    status.textContent = '✕ Não foi possível carregar situações GC';
    status.style.color = '#c62f2f';
    grid.innerHTML = '';
  }
}

// Auto-sync silencioso ao carregar a página
window.addEventListener('DOMContentLoaded', () => { sincronizarGC(true); carregarSituacoesGC(); });
// Auto-refresh a cada 5 minutos
setInterval(() => { sincronizarGC(true); carregarSituacoesGC(); }, 5 * 60 * 1000);
</script>
<style>
@keyframes spin { from {transform:rotate(0deg)} to {transform:rotate(360deg)} }
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
