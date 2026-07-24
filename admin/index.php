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

// Auto-sync silencioso ao carregar a página
window.addEventListener('DOMContentLoaded', () => sincronizarGC(true));
// Auto-refresh a cada 5 minutos
setInterval(() => sincronizarGC(true), 5 * 60 * 1000);
</script>
<style>
@keyframes spin { from {transform:rotate(0deg)} to {transform:rotate(360deg)} }
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
