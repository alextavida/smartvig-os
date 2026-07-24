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
    "SELECT os.id, os.cliente_nome, os.situacao_local, os.prioridade, os.data_agendamento, resp.nome AS tecnico_nome
     FROM ordens_servico os
     LEFT JOIN usuarios resp ON resp.id = os.tecnico_id
     ORDER BY os.criado_em DESC
     LIMIT 8"
);
$ultimasOs = $stmtUltimas->fetchAll();

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
  <div style="display:flex;gap:8px;align-items:center;">
    <span id="sync-status" style="font-size:13px;color:#666;"></span>
    <button id="btn-sync-gc" onclick="sincronizarGC()" class="btn btn-primario btn-sm" style="display:flex;align-items:center;gap:6px;">
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
    <div class="vazio"><?= ic('os_vazio') ?><br>Nenhuma OS cadastrada ainda. <a href="/app-tecnicos/admin/os/criar.php">Criar a primeira OS</a></div>
  <?php else: ?>
  <table>
    <thead>
      <tr><th>#</th><th>Cliente</th><th>Tecnico</th><th>Prioridade</th><th>Agendamento</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($ultimasOs as $os): ?>
        <tr>
          <td>#<?= (int) $os['id'] ?></td>
          <td><?= htmlspecialchars($os['cliente_nome'] ?? '-') ?></td>
          <td><?= htmlspecialchars($os['tecnico_nome'] ?? '-') ?></td>
          <td><span class="chip-prioridade <?= $os['prioridade'] ?? 'baixo' ?>"><?= ic('flag', 10) ?> <?= ucfirst($os['prioridade'] ?? 'baixo') ?></span></td>
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
  <?php endif; ?>
</div>

<script>
async function sincronizarGC() {
  const btn = document.getElementById('btn-sync-gc');
  const status = document.getElementById('sync-status');
  btn.disabled = true;
  btn.textContent = 'Sincronizando...';
  status.textContent = '';
  try {
    const jwt = window.APP_JWT || '';
    const r = await fetch('/app-tecnicos/api/os/sincronizar.php', {
      method: 'POST',
      headers: {'Authorization': 'Bearer ' + jwt, 'Content-Type': 'application/json'},
    });
    const d = await r.json();
    if (d.sucesso) {
      status.style.color = '#16803c';
      status.textContent = `✓ ${d.dados.criadas} criadas, ${d.dados.atualizadas} atualizadas`;
      setTimeout(() => location.reload(), 1200);
    } else {
      status.style.color = '#c0392b';
      status.textContent = '✕ ' + (d.mensagem || 'Erro desconhecido');
    }
  } catch (e) {
    status.style.color = '#c0392b';
    status.textContent = '✕ Falha de conexao';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '&#x21bb; Sincronizar GestaoClick';
  }
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
