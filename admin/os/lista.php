<?php
/**
 * Lista de OSs com filtros (status, prioridade, tecnico, data, cliente) e paginacao.
 * Inclui exportacao CSV.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor'];
$tituloPagina = 'Ordens de Servico';
$paginaAtiva = 'os_lista';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/icons.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = obterConexao();

$status = $_GET['status'] ?? '';
$prioridade = $_GET['prioridade'] ?? '';
$tecnicoId = $_GET['tecnico_id'] ?? '';
$dataInicio = $_GET['data_inicio'] ?? '';
$dataFim = $_GET['data_fim'] ?? '';
$cliente = trim((string) ($_GET['cliente'] ?? ''));
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 15;
$offset = ($pagina - 1) * $porPagina;

$condicoes = [];
$parametros = [];

if ($status !== '') { $condicoes[] = 'os.situacao_local = :status'; $parametros['status'] = $status; }
if ($prioridade !== '') { $condicoes[] = 'os.prioridade = :prioridade'; $parametros['prioridade'] = $prioridade; }
if ($tecnicoId !== '') { $condicoes[] = 'os.tecnico_id = :tecnico_id'; $parametros['tecnico_id'] = $tecnicoId; }
if ($dataInicio !== '') { $condicoes[] = 'os.data_agendamento >= :data_inicio'; $parametros['data_inicio'] = $dataInicio; }
if ($dataFim !== '') { $condicoes[] = 'os.data_agendamento <= :data_fim'; $parametros['data_fim'] = $dataFim; }
if ($cliente !== '') { $condicoes[] = 'os.cliente_nome LIKE :cliente'; $parametros['cliente'] = '%' . $cliente . '%'; }

$whereSql = empty($condicoes) ? '' : ('WHERE ' . implode(' AND ', $condicoes));

// Exportar CSV antes de qualquer saida HTML
if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ordens_servico_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'Cliente', 'Telefone', 'Endereco', 'Tecnico', 'Agendamento', 'Prioridade', 'Status'], ';');
    $sqlCsv = "SELECT os.id, os.cliente_nome, os.cliente_telefone, os.cliente_endereco, resp.nome AS tecnico_nome, os.data_agendamento, os.prioridade, os.situacao_local
               FROM ordens_servico os LEFT JOIN usuarios resp ON resp.id = os.tecnico_id {$whereSql}
               ORDER BY os.data_agendamento IS NULL, os.data_agendamento DESC, os.id DESC";
    $stmtCsv = $pdo->prepare($sqlCsv);
    $stmtCsv->execute($parametros);
    foreach ($stmtCsv->fetchAll() as $row) {
        fputcsv($out, [
            '#' . $row['id'], $row['cliente_nome'], $row['cliente_telefone'], $row['cliente_endereco'],
            $row['tecnico_nome'] ?? '-',
            $row['data_agendamento'] ? date('d/m/Y', strtotime($row['data_agendamento'])) : '-',
            ucfirst($row['prioridade']),
            ucfirst(str_replace('_', ' ', $row['situacao_local'])),
        ], ';');
    }
    fclose($out);
    exit;
}

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM ordens_servico os {$whereSql}");
$stmtTotal->execute($parametros);
$total = (int) $stmtTotal->fetchColumn();

$sql = "SELECT os.id, os.gc_os_id, os.codigo, os.cliente_nome, os.cliente_telefone,
               os.situacao_local, os.prioridade, os.data_agendamento, os.data_prazo, os.sincronizado_gc,
               resp.nome AS tecnico_nome
        FROM ordens_servico os
        LEFT JOIN usuarios resp ON resp.id = os.tecnico_id
        {$whereSql}
        ORDER BY
          CASE os.prioridade WHEN 'urgente' THEN 0 WHEN 'intermediario' THEN 1 ELSE 2 END,
          os.data_agendamento IS NULL, os.data_agendamento DESC, os.id DESC
        LIMIT :limite OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($parametros as $chave => $valor) { $stmt->bindValue($chave, $valor); }
$stmt->bindValue('limite', $porPagina, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$osLista = $stmt->fetchAll();

$totalPaginas = (int) ceil($total / $porPagina);

$tecnicos = $pdo->query("SELECT id, nome FROM usuarios WHERE perfil = 'tecnico' ORDER BY nome")->fetchAll();

function rotuloStatusLista(string $s): string
{
    $mapa = ['aberto' => 'Aberto', 'em_andamento' => 'Em andamento', 'pausado' => 'Pausado',
             'reagendado' => 'Reagendado', 'concluido' => 'Concluido', 'cancelado' => 'Cancelado'];
    return $mapa[$s] ?? $s;
}

function montarQuery(array $sobrescrever = []): string
{
    $atual = $_GET;
    unset($atual['exportar']);
    foreach ($sobrescrever as $k => $v) { $atual[$k] = $v; }
    return htmlspecialchars('?' . http_build_query($atual));
}
?>

<div class="card">
  <form method="get" class="filtros">
    <div class="campo">
      <label>Status</label>
      <select name="status">
        <option value="">Todos</option>
        <?php foreach (['aberto','em_andamento','pausado','reagendado','concluido','cancelado'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= rotuloStatusLista($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo">
      <label>Prioridade</label>
      <select name="prioridade">
        <option value="">Todas</option>
        <option value="urgente" <?= $prioridade === 'urgente' ? 'selected' : '' ?>>Urgente</option>
        <option value="intermediario" <?= $prioridade === 'intermediario' ? 'selected' : '' ?>>Intermediario</option>
        <option value="baixo" <?= $prioridade === 'baixo' ? 'selected' : '' ?>>Baixo</option>
      </select>
    </div>
    <div class="campo">
      <label>Tecnico</label>
      <select name="tecnico_id">
        <option value="">Todos</option>
        <?php foreach ($tecnicos as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= (string) $tecnicoId === (string) $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo">
      <label>De</label>
      <input type="date" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
    </div>
    <div class="campo">
      <label>Ate</label>
      <input type="date" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
    </div>
    <div class="campo">
      <label>Cliente</label>
      <input type="text" name="cliente" placeholder="Buscar por nome..." value="<?= htmlspecialchars($cliente) ?>">
    </div>
    <div class="campo" style="display:flex; gap:8px; align-items:flex-end;">
      <button type="submit" class="btn btn-primario"><?= ic('buscar', 15) ?> Filtrar</button>
      <a href="lista.php" class="btn btn-neutro">Limpar</a>
    </div>
  </form>

  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
    <span style="font-size:13px; color:var(--cinza-500);"><?= $total ?> OS encontrada<?= $total !== 1 ? 's' : '' ?></span>
    <div style="display:flex;gap:8px;align-items:center;">
      <span id="sync-status-lista" style="font-size:12px;color:#666;"></span>
      <button onclick="sincronizarGCLista()" class="btn btn-primario btn-sm no-print" style="display:flex;align-items:center;gap:5px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
        Sincronizar GC
      </button>
      <a href="<?= montarQuery(['exportar' => 'csv']) ?>" class="btn btn-neutro btn-sm no-print">
        <?= ic('exportar', 14) ?> CSV
      </a>
    </div>
  </div>

  <?php if (empty($osLista)): ?>
    <div class="vazio"><?= ic('os_vazio') ?><br>Nenhuma OS encontrada com esses filtros.</div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Codigo</th>
        <th>Cliente</th>
        <th>Telefone</th>
        <th>Tecnico</th>
        <th>Prioridade</th>
        <th>Agendamento</th>
        <th>Prazo/SLA</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($osLista as $os): ?>
        <tr>
          <td>
            <strong><?= $os['codigo'] ? htmlspecialchars($os['codigo']) : '#' . (int) $os['id'] ?></strong>
            <?php if ($os['gc_os_id'] && !$os['codigo']): ?>
              <div style="font-size:10px;color:#94a3b8;">GC:<?= (int) $os['gc_os_id'] ?></div>
            <?php endif; ?>
            <?php if (!$os['sincronizado_gc']): ?>
              <span title="Criado localmente" style="font-size:10px;color:#f59e0b;">● local</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($os['cliente_nome'] ?? '-') ?></td>
          <td style="font-size:12px;color:#475569;"><?= htmlspecialchars($os['cliente_telefone'] ?? '-') ?></td>
          <td><?= htmlspecialchars($os['tecnico_nome'] ?? 'Sem atribuição') ?></td>
          <td>
            <span class="chip-prioridade <?= $os['prioridade'] ?>">
              <?= ic('flag', 10) ?>
              <?= ucfirst($os['prioridade']) ?>
            </span>
          </td>
          <td><?= $os['data_agendamento'] ? htmlspecialchars(date('d/m/Y', strtotime($os['data_agendamento']))) : '-' ?></td>
          <td>
            <?php
              $dp = $os['data_prazo'] ?? null;
              if ($dp) {
                $hoje   = new DateTimeImmutable('today');
                $prazo  = new DateTimeImmutable($dp);
                $diff   = (int) $hoje->diff($prazo)->format('%r%a');
                if ($diff < 0) {
                  echo '<span style="background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;">🔴 Vencido</span>';
                } elseif ($diff <= 2) {
                  echo '<span style="background:#fef9c3;color:#92400e;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;">🟡 Vencendo</span>';
                } else {
                  echo '<span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;">🟢 ' . htmlspecialchars(date('d/m', strtotime($dp))) . '</span>';
                }
              } else {
                echo '<span style="color:#94a3b8;font-size:12px;">—</span>';
              }
            ?>
          </td>
          <td><span class="badge <?= $os['situacao_local'] ?>"><?= rotuloStatusLista($os['situacao_local']) ?></span></td>
          <td>
            <div class="acoes-tabela">
              <a href="detalhe.php?id=<?= (int) $os['id'] ?>" class="btn btn-secundario btn-sm">Ver</a>
              <a href="imprimir.php?id=<?= (int) $os['id'] ?>" target="_blank" class="btn-icone no-print" title="Imprimir"><?= ic('imprimir', 15) ?></a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($totalPaginas > 1):
    // Janela de paginação: sempre mostra 1, última e até 3 em torno da atual
    $exibir = array_unique(array_filter(array_merge(
      [1, 2],
      range(max(1, $pagina - 2), min($totalPaginas, $pagina + 2)),
      [$totalPaginas - 1, $totalPaginas]
    )));
    sort($exibir);
  ?>
  <div class="paginacao">
    <?php if ($pagina > 1): ?>
      <a href="<?= montarQuery(['pagina' => $pagina - 1]) ?>">&laquo;</a>
    <?php endif; ?>
    <?php $anterior = 0; foreach ($exibir as $p):
      if ($anterior && $p - $anterior > 1): ?>
        <span style="padding:0 4px;color:#94a3b8;">…</span>
      <?php endif; ?>
      <a href="<?= montarQuery(['pagina' => $p]) ?>" class="<?= $p === $pagina ? 'ativo' : '' ?>"><?= $p ?></a>
    <?php $anterior = $p; endforeach; ?>
    <?php if ($pagina < $totalPaginas): ?>
      <a href="<?= montarQuery(['pagina' => $pagina + 1]) ?>">&raquo;</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<script>
async function sincronizarGCLista() {
  const el = document.getElementById('sync-status-lista');
  el.textContent = 'Sincronizando...';
  el.style.color = '#64748b';
  try {
    const r = await fetch('/app-tecnicos/api/os/sincronizar.php', {
      method: 'POST',
      headers: {'Authorization': 'Bearer ' + (window.APP_JWT || ''), 'Content-Type': 'application/json'},
    });
    const d = await r.json();
    if (d.sucesso) {
      el.style.color = '#16803c';
      el.textContent = `✓ ${d.dados.criadas} criadas, ${d.dados.atualizadas} atualizadas`;
      if (d.dados.criadas > 0) { setTimeout(() => location.reload(), 900); }
    } else {
      el.style.color = '#c0392b';
      el.textContent = '✕ ' + (d.erro || 'Erro');
    }
  } catch {
    el.style.color = '#c0392b';
    el.textContent = '✕ Falha de conexao';
  }
}
// Auto-sync silencioso ao abrir a lista
window.addEventListener('DOMContentLoaded', async () => {
  try {
    const r = await fetch('/app-tecnicos/api/os/sincronizar.php', {
      method: 'POST',
      headers: {'Authorization': 'Bearer ' + (window.APP_JWT || ''), 'Content-Type': 'application/json'},
    });
    const d = await r.json();
    if (d.sucesso && d.dados.criadas > 0) { location.reload(); }
  } catch {}
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
