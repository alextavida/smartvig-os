<?php
/**
 * Dashboard do tecnico: OS atribuidas com filtro por status e exibicao de prioridade.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['tecnico'];
$tituloPagina = 'Minhas Ordens de Servico';
$paginaAtiva = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../config/database.php';

$pdo = obterConexao();
$tecnicoId = $usuarioAtual['usuario_id'];

$status = $_GET['status'] ?? '';

$condicoes = ['ot.tecnico_id = :tecnico_id'];
$parametros = ['tecnico_id' => $tecnicoId];
if ($status !== '') {
    $condicoes[] = 'os.situacao_local = :status';
    $parametros['status'] = $status;
}
$whereSql = 'WHERE ' . implode(' AND ', $condicoes);

$sql = "SELECT DISTINCT os.id, os.cliente_nome, os.cliente_endereco, os.situacao_local, os.prioridade, os.data_agendamento
        FROM ordens_servico os
        INNER JOIN os_tecnicos ot ON ot.os_id = os.id
        {$whereSql}
        ORDER BY
          CASE os.situacao_local WHEN 'em_andamento' THEN 0 WHEN 'aberto' THEN 1 WHEN 'pausado' THEN 2 WHEN 'reagendado' THEN 3 ELSE 4 END,
          CASE os.prioridade WHEN 'urgente' THEN 0 WHEN 'intermediario' THEN 1 ELSE 2 END,
          os.data_agendamento IS NULL, os.data_agendamento ASC, os.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$osLista = $stmt->fetchAll();

function rotuloStatusTec(string $s): string
{
    $mapa = ['aberto' => 'Aberto', 'em_andamento' => 'Em andamento', 'pausado' => 'Pausado',
             'reagendado' => 'Reagendado', 'concluido' => 'Concluido', 'cancelado' => 'Cancelado'];
    return $mapa[$s] ?? $s;
}

$abas = ['' => 'Todas', 'em_andamento' => 'Em andamento', 'aberto' => 'Abertas', 'pausado' => 'Pausadas', 'reagendado' => 'Reagendadas', 'concluido' => 'Concluidas'];
?>

<div class="filtros" style="margin-bottom:18px;">
  <?php foreach ($abas as $valor => $rotulo): ?>
    <a href="?status=<?= urlencode($valor) ?>" class="btn <?= $status === $valor ? 'btn-primario' : 'btn-neutro' ?> btn-sm"><?= $rotulo ?></a>
  <?php endforeach; ?>
</div>

<?php if (empty($osLista)): ?>
  <div class="card">
    <div class="vazio"><?= ic('os_vazio') ?><br>Nenhuma OS encontrada para este filtro.</div>
  </div>
<?php else: ?>
  <div class="grid-cards" style="grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));">
    <?php foreach ($osLista as $os): ?>
      <a href="os/detalhe.php?id=<?= (int) $os['id'] ?>" class="card" style="text-decoration:none; color:inherit; display:block; transition:box-shadow .15s, transform .15s;" onmouseover="this.style.boxShadow='var(--sombra-hover)';this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='';this.style.transform=''">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
          <strong style="font-size:14.5px;">#<?= (int) $os['id'] ?> &middot; <?= htmlspecialchars($os['cliente_nome'] ?? '-') ?></strong>
          <span class="badge <?= $os['situacao_local'] ?>"><?= rotuloStatusTec($os['situacao_local']) ?></span>
        </div>
        <div style="display:flex; align-items:center; gap:6px; font-size:12.5px; color:var(--cinza-500); margin-bottom:5px;">
          <?= ic('mapa', 13) ?> <?= htmlspecialchars($os['cliente_endereco'] ?? 'Endereco nao informado') ?>
        </div>
        <div style="display:flex; align-items:center; gap:6px; font-size:12.5px; color:var(--cinza-700); margin-bottom:10px;">
          <?= ic('calendario', 13) ?> <?= $os['data_agendamento'] ? date('d/m/Y', strtotime($os['data_agendamento'])) : 'Sem data definida' ?>
        </div>
        <div>
          <span class="chip-prioridade <?= $os['prioridade'] ?>"><?= ic('flag', 11) ?> <?= ucfirst($os['prioridade']) ?></span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
