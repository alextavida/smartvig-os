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

$sql = "SELECT os.id, os.cliente_nome, os.situacao_local, os.prioridade, os.data_agendamento, resp.nome AS tecnico_nome
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

  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
    <span style="font-size:13px; color:var(--cinza-500);"><?= $total ?> OS encontrada<?= $total !== 1 ? 's' : '' ?></span>
    <a href="<?= montarQuery(['exportar' => 'csv']) ?>" class="btn btn-neutro btn-sm no-print">
      <?= ic('exportar', 14) ?> Exportar CSV
    </a>
  </div>

  <?php if (empty($osLista)): ?>
    <div class="vazio"><?= ic('os_vazio') ?><br>Nenhuma OS encontrada com esses filtros.</div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Cliente</th>
        <th>Tecnico</th>
        <th>Prioridade</th>
        <th>Agendamento</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($osLista as $os): ?>
        <tr>
          <td><strong>#<?= (int) $os['id'] ?></strong></td>
          <td><?= htmlspecialchars($os['cliente_nome'] ?? '-') ?></td>
          <td><?= htmlspecialchars($os['tecnico_nome'] ?? '-') ?></td>
          <td>
            <span class="chip-prioridade <?= $os['prioridade'] ?>">
              <?= ic('flag', 10) ?>
              <?= ucfirst($os['prioridade']) ?>
            </span>
          </td>
          <td><?= $os['data_agendamento'] ? htmlspecialchars(date('d/m/Y', strtotime($os['data_agendamento']))) : '-' ?></td>
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

  <?php if ($totalPaginas > 1): ?>
  <div class="paginacao">
    <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
      <a href="<?= montarQuery(['pagina' => $p]) ?>" class="<?= $p === $pagina ? 'ativo' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
