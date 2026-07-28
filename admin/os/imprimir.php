<?php
/**
 * Versao de impressao da OS: sem sidebar, com foto dos tecnicos,
 * lista de produtos e historico resumido.
 * Dispara window.print() automaticamente ao carregar.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/guard.php';
require_once __DIR__ . '/../../config/database.php';

exigirLoginWeb(['gestor']);

$pdo = obterConexao();
$osId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT os.*, resp.nome AS tecnico_responsavel_nome
     FROM ordens_servico os LEFT JOIN usuarios resp ON resp.id = os.tecnico_id
     WHERE os.id = :id LIMIT 1'
);
$stmt->execute(['id' => $osId]);
$os = $stmt->fetch();

if (!$os) { echo 'OS nao encontrada.'; exit; }

$tecnicosAtribuidos = $pdo->prepare(
    'SELECT u.id, u.nome, u.telefone, u.foto_perfil, ot.responsavel
     FROM os_tecnicos ot INNER JOIN usuarios u ON u.id = ot.tecnico_id
     WHERE ot.os_id = :id ORDER BY ot.responsavel DESC, u.nome'
);
$tecnicosAtribuidos->execute(['id' => $osId]);
$tecnicosAtribuidos = $tecnicosAtribuidos->fetchAll();

$produtos = $os['produtos_json'] ? json_decode($os['produtos_json'], true) : [];

$midias = $pdo->prepare(
    'SELECT tipo, caminho_arquivo, nome_arquivo FROM midias_os
     WHERE os_id = :id AND tipo = "foto" ORDER BY criado_em DESC'
);
$midias->execute(['id' => $osId]);
$todasFotos = $midias->fetchAll();
// Separar assinaturas digitais das fotos de trabalho
$fotos       = array_filter($todasFotos, fn($f) => !str_contains($f['nome_arquivo'], 'assinatura'));
$assinaturas = array_filter($todasFotos, fn($f) =>  str_contains($f['nome_arquivo'], 'assinatura'));

function formatSegundos(int $seg): string {
    $h = intdiv($seg, 3600);
    $m = intdiv($seg % 3600, 60);
    return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
}

$historico = $pdo->prepare(
    'SELECT h.acao, h.detalhe, h.criado_em, u.nome AS usuario_nome
     FROM historico_os h LEFT JOIN usuarios u ON u.id = h.usuario_id
     WHERE h.os_id = :id ORDER BY h.criado_em ASC LIMIT 30'
);
$historico->execute(['id' => $osId]);
$historico = $historico->fetchAll();

$rotulosStatus = ['aberto' => 'Aberto', 'em_andamento' => 'Em andamento', 'pausado' => 'Pausado',
                  'reagendado' => 'Reagendado', 'concluido' => 'Concluido', 'cancelado' => 'Cancelado'];
$rotulosPrioridade = ['baixo' => 'Baixo', 'intermediario' => 'Intermediario', 'urgente' => 'URGENTE'];
$rotulosAcao = [
    'os_criada' => 'OS criada', 'os_iniciada' => 'OS iniciada', 'os_pausada' => 'OS pausada',
    'os_reagendada' => 'OS reagendada', 'os_encerrada' => 'OS encerrada', 'os_atualizada' => 'OS atualizada',
    'tecnicos_atribuidos' => 'Tecnicos redefinidos', 'midia_enviada' => 'Midia enviada',
    'produto_adicionado' => 'Produto adicionado', 'descricao_atualizada' => 'Descricao atualizada',
];

function inicialImpressao(string $nome): string {
    $i = ''; foreach (explode(' ', trim($nome)) as $p) { if ($p !== '') { $i .= mb_strtoupper(mb_substr($p, 0, 1)); } if (mb_strlen($i) >= 2) { break; } } return $i;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>OS #<?= (int) $os['id'] ?> - SmartVig</title>
<style>
*, *::before, *::after { box-sizing: border-box; }
body { font-family: "Segoe UI", Arial, sans-serif; margin: 0; padding: 20px; color: #1c2430; font-size: 13px; background: #fff; }
h1, h2, h3 { margin: 0 0 8px 0; }
.cabecalho { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0f4c92; padding-bottom: 14px; margin-bottom: 16px; }
.cabecalho .logo { display: flex; align-items: center; gap: 10px; }
.cabecalho .logo img { width: 52px; height: 52px; object-fit: contain; }
.cabecalho .logo-titulo { font-size: 22px; font-weight: 800; color: #0f4c92; }
.cabecalho .logo-sub { font-size: 11px; color: #6b7789; }
.cabecalho .os-num { font-size: 28px; font-weight: 800; color: #0f4c92; text-align: right; }
.cabecalho .os-data { font-size: 11px; color: #6b7789; text-align: right; }
.secao { background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 14px 16px; margin-bottom: 12px; page-break-inside: avoid; }
.secao h3 { font-size: 12px; text-transform: uppercase; letter-spacing: .6px; color: #6b7789; margin-bottom: 10px; font-weight: 700; }
.linha { display: flex; gap: 6px; margin-bottom: 6px; }
.campo-label { font-size: 11px; color: #6b7789; font-weight: 700; min-width: 110px; }
.campo-val { font-size: 13px; color: #1c2430; }
.badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; border: 1px solid currentColor; }
.aberto { color: #1462b0; background: #e8f1fc; }
.em_andamento { color: #b8860b; background: #fdf3d9; }
.pausado { color: #5a6472; background: #eceff2; }
.reagendado { color: #c8641a; background: #fbe7d6; }
.concluido { color: #1e8e5a; background: #e5f5ec; }
.cancelado { color: #c62f2f; background: #fbe4e4; }
.prioridade-urgente { color: #b71c1c; background: #ffebee; border-color: #b71c1c; }
.prioridade-intermediario { color: #e65100; background: #fff8e1; border-color: #e65100; }
.prioridade-baixo { color: #2e7d32; background: #e8f5e9; border-color: #2e7d32; }
.tecnicos-lista { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 6px; }
.tecnico-card { display: flex; align-items: center; gap: 8px; border: 1px solid #ddd; border-radius: 8px; padding: 8px 12px; }
.tecnico-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid #e8f1fc; }
.avatar-init { width: 44px; height: 44px; border-radius: 50%; background: #0f4c92; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px; }
.tecnico-nome { font-weight: 700; font-size: 13px; }
.tecnico-resp { font-size: 10px; color: #1462b0; font-weight: 700; }
table { width: 100%; border-collapse: collapse; font-size: 12.5px; margin-top: 6px; }
th, td { text-align: left; padding: 7px 10px; border-bottom: 1px solid #eee; }
th { color: #6b7789; font-size: 11px; text-transform: uppercase; letter-spacing: .4px; font-weight: 700; }
tfoot td { font-weight: 700; }
.fotos-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 8px; }
.fotos-grid img { width: 100%; height: 110px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd; }
.timeline { list-style: none; padding: 0; margin: 6px 0 0 0; }
.timeline li { padding: 4px 0 4px 18px; border-left: 2px solid #e8f1fc; position: relative; }
.timeline li::before { content: ""; position: absolute; left: -5px; top: 6px; width: 8px; height: 8px; border-radius: 50%; background: #1462b0; }
.timeline .acao { font-weight: 700; font-size: 12px; }
.timeline .quando { font-size: 10.5px; color: #6b7789; }
.rodape { margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; display: flex; justify-content: space-between; font-size: 10.5px; color: #aaa; }
@media print {
  body { padding: 10px 14px; }
  .btn-imprimir { display: none; }
}
</style>
</head>
<body>

<div class="cabecalho">
  <div class="logo">
    <img src="/app-tecnicos/imgs/logo.png" alt="SmartVig">
    <div>
      <div class="logo-titulo">SmartVig</div>
      <div class="logo-sub">Vigilancia Inteligente 24h</div>
    </div>
  </div>
  <div>
    <div class="os-num">OS #<?= (int) $os['id'] ?></div>
    <div class="os-data">Gerado em <?= date('d/m/Y \a\s H:i') ?></div>
    <div style="margin-top:4px; display:flex; gap:6px; justify-content:flex-end; flex-wrap:wrap;">
      <span class="badge <?= $os['situacao_local'] ?>"><?= $rotulosStatus[$os['situacao_local']] ?? $os['situacao_local'] ?></span>
      <span class="badge prioridade-<?= $os['prioridade'] ?>"><?= $rotulosPrioridade[$os['prioridade']] ?? $os['prioridade'] ?></span>
    </div>
  </div>
</div>

<!-- Dados do cliente -->
<div class="secao">
  <h3>Dados do cliente</h3>
  <div class="linha"><span class="campo-label">Nome</span><span class="campo-val"><?= htmlspecialchars($os['cliente_nome'] ?? '-') ?></span></div>
  <div class="linha"><span class="campo-label">Telefone</span><span class="campo-val"><?= htmlspecialchars($os['cliente_telefone'] ?? '-') ?></span></div>
  <div class="linha"><span class="campo-label">Endereco</span><span class="campo-val"><?= htmlspecialchars($os['cliente_endereco'] ?? '-') ?></span></div>
  <div class="linha"><span class="campo-label">Agendamento</span><span class="campo-val"><?= $os['data_agendamento'] ? date('d/m/Y', strtotime($os['data_agendamento'])) : '-' ?></span></div>
  <?php if ($os['data_conclusao']): ?>
  <div class="linha"><span class="campo-label">Concluido em</span><span class="campo-val"><?= date('d/m/Y H:i', strtotime($os['data_conclusao'])) ?></span></div>
  <?php endif; ?>
  <?php if (!empty($os['data_prazo'])): ?>
  <?php $prazoVencido = strtotime($os['data_prazo']) < time() && $os['situacao_local'] !== 'concluido'; ?>
  <div class="linha">
    <span class="campo-label">Prazo (SLA)</span>
    <span class="campo-val" style="<?= $prazoVencido ? 'color:#c62f2f;font-weight:700;' : '' ?>">
      <?= date('d/m/Y', strtotime($os['data_prazo'])) ?><?= $prazoVencido ? ' ⚠ VENCIDO' : '' ?>
    </span>
  </div>
  <?php endif; ?>
  <?php if (!empty($os['tempo_atendimento_segundos'])): ?>
  <div class="linha">
    <span class="campo-label">Tempo total</span>
    <span class="campo-val"><?= formatSegundos((int) $os['tempo_atendimento_segundos']) ?></span>
  </div>
  <?php endif; ?>
</div>

<!-- Tecnicos -->
<?php if (!empty($tecnicosAtribuidos)): ?>
<div class="secao">
  <h3>Tecnicos responsaveis</h3>
  <div class="tecnicos-lista">
    <?php foreach ($tecnicosAtribuidos as $t): ?>
      <div class="tecnico-card">
        <?php if ($t['foto_perfil'] && file_exists(__DIR__ . '/../../' . $t['foto_perfil'])): ?>
          <img class="tecnico-avatar" src="/app-tecnicos/<?= htmlspecialchars($t['foto_perfil']) ?>" alt="">
        <?php else: ?>
          <div class="avatar-init"><?= inicialImpressao($t['nome']) ?></div>
        <?php endif; ?>
        <div>
          <div class="tecnico-nome"><?= htmlspecialchars($t['nome']) ?></div>
          <?php if ($t['telefone']): ?><div style="font-size:11px; color:#6b7789;"><?= htmlspecialchars($t['telefone']) ?></div><?php endif; ?>
          <?php if ($t['responsavel']): ?><div class="tecnico-resp">Responsavel</div><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Descricao / laudo -->
<?php if ($os['observacoes']): ?>
<div class="secao">
  <h3>Descricao / Laudo</h3>
  <p style="margin:0; white-space:pre-wrap; line-height:1.6;"><?= htmlspecialchars($os['observacoes']) ?></p>
</div>
<?php endif; ?>

<!-- Produtos -->
<?php if (!empty($produtos)): ?>
<div class="secao">
  <h3>Produtos utilizados</h3>
  <?php $total = 0; ?>
  <table>
    <thead><tr><th>Produto</th><th>Qtd</th><th>Valor unit.</th><th>Subtotal</th></tr></thead>
    <tbody>
      <?php foreach ($produtos as $p): $sub = (float)$p['valor_venda'] * (float)$p['quantidade']; $total += $sub; ?>
        <tr>
          <td><?= htmlspecialchars($p['nome']) ?></td>
          <td><?= $p['quantidade'] ?></td>
          <td>R$ <?= number_format((float)$p['valor_venda'], 2, ',', '.') ?></td>
          <td>R$ <?= number_format($sub, 2, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="3" style="text-align:right;">Total</td><td>R$ <?= number_format($total, 2, ',', '.') ?></td></tr></tfoot>
  </table>
</div>
<?php endif; ?>

<!-- Fotos -->
<?php if (!empty($fotos)): ?>
<div class="secao">
  <h3>Fotos do atendimento</h3>
  <div class="fotos-grid">
    <?php foreach (array_slice($fotos, 0, 8) as $f): ?>
      <img src="/app-tecnicos/<?= htmlspecialchars($f['caminho_arquivo']) ?>" alt="Foto">
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Historico -->
<?php if (!empty($historico)): ?>
<div class="secao">
  <h3>Historico de acoes</h3>
  <ul class="timeline">
    <?php foreach ($historico as $h): ?>
      <li>
        <div class="acao"><?= htmlspecialchars($rotulosAcao[$h['acao']] ?? $h['acao']) ?><?= $h['usuario_nome'] ? ' &mdash; ' . htmlspecialchars($h['usuario_nome']) : '' ?></div>
        <?php if ($h['detalhe']): ?><div style="font-size:11px; color:#3c4859; margin-top:1px;"><?= htmlspecialchars($h['detalhe']) ?></div><?php endif; ?>
        <div class="quando"><?= date('d/m/Y H:i', strtotime($h['criado_em'])) ?></div>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<!-- Assinatura Digital (se coletada no app) -->
<?php if (!empty($assinaturas)): ?>
<div class="secao">
  <h3>Assinatura Digital do Cliente</h3>
  <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px;">
    <?php foreach ($assinaturas as $a): ?>
      <img src="/app-tecnicos/<?= htmlspecialchars($a['caminho_arquivo']) ?>"
           alt="Assinatura" style="max-height:100px;border:1px solid #ddd;border-radius:6px;background:#f8fafd;">
    <?php endforeach; ?>
  </div>
</div>
<?php else: ?>
<!-- Espaço para assinatura manual (quando não coletada digitalmente) -->
<div class="secao" style="margin-top:28px;">
  <h3>Assinatura e confirmacao</h3>
  <div style="display:flex; gap:40px; margin-top:16px;">
    <div style="flex:1; border-top:1px solid #555; padding-top:6px; text-align:center; font-size:11px; color:#6b7789;">Assinatura do tecnico</div>
    <div style="flex:1; border-top:1px solid #555; padding-top:6px; text-align:center; font-size:11px; color:#6b7789;">Assinatura do cliente</div>
  </div>
</div>
<?php endif; ?>

<div class="rodape">
  <span>SmartVig — Vigilancia Inteligente 24h</span>
  <span>OS #<?= (int) $os['id'] ?> — Impresso em <?= date('d/m/Y H:i') ?></span>
</div>

<div style="text-align:center; margin-top:16px;" class="btn-imprimir">
  <button onclick="window.print()" style="padding:10px 24px; background:#0f4c92; color:#fff; border:none; border-radius:999px; font-size:14px; cursor:pointer; font-weight:600;">Imprimir / Salvar PDF</button>
  <button onclick="window.close()" style="padding:10px 24px; background:#eef1f5; color:#3c4859; border:none; border-radius:999px; font-size:14px; cursor:pointer; font-weight:600; margin-left:10px;">Fechar</button>
</div>

<script>
window.addEventListener('load', function () {
  const params = new URLSearchParams(window.location.search);
  if (params.get('auto') === '1') { setTimeout(() => window.print(), 600); }
});
</script>
</body>
</html>
