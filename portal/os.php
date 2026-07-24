<?php
/**
 * Portal público do cliente — acesso sem login via token único.
 * URL: /app-tecnicos/portal/os.php?token=<portal_token>
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$token = trim($_GET['token'] ?? '');

// Busca dados diretamente (sem usar a API interna para simplificar)
$pdo = obterConexao();
$stmt = $pdo->prepare(
    "SELECT os.id, os.cliente_nome, os.situacao_local, os.data_agendamento, os.data_conclusao,
            u.nome AS tecnico_nome, u.foto_perfil AS tecnico_foto
     FROM ordens_servico os
     LEFT JOIN usuarios u ON u.id = os.tecnico_id
     WHERE os.portal_token = :token LIMIT 1"
);
$stmt->execute(['token' => $token]);
$os = $stmt->fetch();

$encontrou = $os && $token !== '';

if ($encontrou) {
    $acoesVisiveis = ['os_criada','os_iniciada','os_pausada','os_reagendada','os_encerrada'];
    $rotulosAcoes  = [
        'os_criada'    => 'OS aberta',
        'os_iniciada'  => 'Técnico em atendimento',
        'os_pausada'   => 'Atendimento pausado',
        'os_reagendada'=> 'OS reagendada',
        'os_encerrada' => 'Atendimento concluído',
    ];
    $ph = implode(',', array_fill(0, count($acoesVisiveis), '?'));
    $stmtH = $pdo->prepare("SELECT acao, criado_em FROM historico_os WHERE os_id = ? AND acao IN ({$ph}) ORDER BY criado_em ASC");
    $stmtH->execute([$os['id'], ...$acoesVisiveis]);
    $historico = $stmtH->fetchAll();

    $progressoMapa = ['aberto'=>0,'reagendado'=>0,'em_andamento'=>1,'pausado'=>1,'concluido'=>2,'cancelado'=>-1];
    $progresso = $progressoMapa[$os['situacao_local']] ?? 0;

    $nomesCurtos = ['aberto'=>'Aberta','em_andamento'=>'Em andamento','pausado'=>'Pausada',
                    'reagendado'=>'Reagendada','concluido'=>'Concluída','cancelado'=>'Cancelada'];
    $situacaoNome = $nomesCurtos[$os['situacao_local']] ?? ucfirst($os['situacao_local']);
    $corSit = ['aberto'=>'#1462b0','em_andamento'=>'#b8860b','pausado'=>'#5a6472',
               'reagendado'=>'#c8641a','concluido'=>'#1e8e5a','cancelado'=>'#c62f2f'];
    $cor = $corSit[$os['situacao_local']] ?? '#1c2430';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SmartVig — Acompanhe seu atendimento</title>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  body { margin: 0; font-family: -apple-system, "Segoe UI", Arial, sans-serif; background: #f4f9fe; color: #1c2430; min-height: 100vh; }
  .header { background: linear-gradient(135deg, #0b3a6f 0%, #1462b0 100%); color: #fff; padding: 24px 20px 20px; text-align: center; }
  .header img { width: 48px; height: 48px; object-fit: contain; margin-bottom: 8px; }
  .header h1 { margin: 0; font-size: 1.2rem; font-weight: 800; letter-spacing: 0.5px; }
  .header p { margin: 4px 0 0; font-size: 12px; opacity: 0.7; }
  .container { max-width: 480px; margin: 0 auto; padding: 20px 16px; }
  .card { background: #fff; border-radius: 16px; padding: 20px; margin-bottom: 14px; box-shadow: 0 2px 12px rgba(10,28,60,.08); }
  .status-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 16px; border-radius: 999px; font-weight: 700; font-size: 13px; }
  .cliente-nome { font-size: 22px; font-weight: 800; margin: 6px 0; }
  .info-row { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 8px; font-size: 14px; }
  .info-icon { font-size: 16px; margin-top: 1px; flex-shrink: 0; }
  .progresso { display: flex; align-items: center; gap: 0; margin: 16px 0 8px; }
  .etapa { flex: 1; text-align: center; }
  .etapa-circulo { width: 32px; height: 32px; border-radius: 50%; margin: 0 auto 6px; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; border: 2px solid #cbd3dd; background: #fff; color: #6b7789; transition: all .3s; }
  .etapa-circulo.feito { background: #1e8e5a; border-color: #1e8e5a; color: #fff; }
  .etapa-circulo.ativo { background: #1462b0; border-color: #1462b0; color: #fff; box-shadow: 0 0 0 4px rgba(20,98,176,.15); }
  .etapa-nome { font-size: 10px; color: #6b7789; font-weight: 600; }
  .etapa-nome.ativo { color: #1462b0; font-weight: 700; }
  .linha-etapa { flex: 1; height: 2px; background: #cbd3dd; margin-top: -20px; }
  .linha-etapa.feita { background: #1e8e5a; }
  .timeline { list-style: none; margin: 0; padding: 0; }
  .timeline li { display: flex; gap: 12px; padding-bottom: 16px; position: relative; }
  .timeline li:not(:last-child)::before { content: ""; position: absolute; left: 6px; top: 14px; bottom: 0; width: 2px; background: #eef1f5; }
  .tl-dot { width: 14px; height: 14px; border-radius: 50%; background: #1462b0; flex-shrink: 0; margin-top: 2px; }
  .tl-desc { font-size: 13.5px; font-weight: 600; color: #1c2430; }
  .tl-data { font-size: 11px; color: #6b7789; margin-top: 2px; }
  .tecnico-card { display: flex; align-items: center; gap: 14px; }
  .tecnico-avatar { width: 48px; height: 48px; border-radius: 50%; background: #1462b0; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 17px; flex-shrink: 0; overflow: hidden; }
  .tecnico-avatar img { width: 100%; height: 100%; object-fit: cover; }
  .rodape { text-align: center; font-size: 11px; color: #94a3b8; padding: 20px 0 30px; }
  .nao-encontrado { text-align: center; padding: 60px 20px; }
  .nao-encontrado .icone { font-size: 56px; margin-bottom: 16px; }
  .atualizar-btn { display: block; margin: 16px auto 0; background: none; border: 1.5px solid #1462b0; color: #1462b0; padding: 10px 24px; border-radius: 999px; font-size: 14px; font-weight: 700; cursor: pointer; }
</style>
</head>
<body>

<div class="header">
  <img src="/app-tecnicos/imgs/logo.png" alt="SmartVig" onerror="this.style.display='none'">
  <h1>SmartVig OS</h1>
  <p>Acompanhamento de atendimento</p>
</div>

<div class="container">
<?php if (!$encontrou): ?>
  <div class="nao-encontrado">
    <div class="icone">🔍</div>
    <h2 style="font-size:1.2rem;margin:0 0 8px;">Link inválido</h2>
    <p style="color:#6b7789;font-size:14px;">Este link de acompanhamento não existe ou expirou.</p>
  </div>

<?php else: ?>

  <!-- Card principal -->
  <div class="card">
    <span class="status-chip" style="background:<?= $cor ?>20; color:<?= $cor ?>; margin-bottom:8px;">
      <?= $situacaoNome === 'Concluída' ? '✓' : '●' ?> <?= htmlspecialchars($situacaoNome) ?>
    </span>
    <div class="cliente-nome">Olá, <?= htmlspecialchars(explode(' ', trim($os['cliente_nome']))[0]) ?>!</div>

    <?php if ($os['data_agendamento']): ?>
    <div class="info-row">
      <span class="info-icon">📅</span>
      <span>Agendado para <?= date('d/m/Y', strtotime($os['data_agendamento'])) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($os['data_conclusao']): ?>
    <div class="info-row">
      <span class="info-icon">✅</span>
      <span>Concluído em <?= date('d/m/Y \à\s H:i', strtotime($os['data_conclusao'])) ?></span>
    </div>
    <?php endif; ?>

    <!-- Barra de progresso -->
    <?php if ($progresso >= 0): ?>
    <div class="progresso">
      <div class="etapa">
        <div class="etapa-circulo <?= $progresso >= 0 ? 'feito' : '' ?>">1</div>
        <div class="etapa-nome <?= $progresso === 0 ? 'ativo' : '' ?>">Aberta</div>
      </div>
      <div class="linha-etapa <?= $progresso >= 1 ? 'feita' : '' ?>"></div>
      <div class="etapa">
        <div class="etapa-circulo <?= $progresso === 1 ? 'ativo' : ($progresso > 1 ? 'feito' : '') ?>">2</div>
        <div class="etapa-nome <?= $progresso === 1 ? 'ativo' : '' ?>">Atendimento</div>
      </div>
      <div class="linha-etapa <?= $progresso >= 2 ? 'feita' : '' ?>"></div>
      <div class="etapa">
        <div class="etapa-circulo <?= $progresso >= 2 ? 'feito' : '' ?>">✓</div>
        <div class="etapa-nome <?= $progresso === 2 ? 'ativo' : '' ?>">Concluída</div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Técnico responsável -->
  <?php if ($os['tecnico_nome']): ?>
  <div class="card">
    <div style="font-size:11px;color:#6b7789;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">Técnico responsável</div>
    <div class="tecnico-card">
      <div class="tecnico-avatar">
        <?php if ($os['tecnico_foto'] && file_exists(__DIR__ . '/../' . $os['tecnico_foto'])): ?>
          <img src="/app-tecnicos/<?= htmlspecialchars($os['tecnico_foto']) ?>" alt="">
        <?php else: ?>
          <?= mb_strtoupper(mb_substr($os['tecnico_nome'], 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div>
        <div style="font-weight:700;font-size:15px;"><?= htmlspecialchars($os['tecnico_nome']) ?></div>
        <div style="font-size:12px;color:#6b7789;margin-top:2px;">Técnico SmartVig</div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Linha do tempo -->
  <?php if (!empty($historico)): ?>
  <div class="card">
    <div style="font-size:11px;color:#6b7789;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;">Histórico</div>
    <ul class="timeline">
      <?php foreach ($historico as $h): ?>
        <li>
          <div class="tl-dot"></div>
          <div>
            <div class="tl-desc"><?= htmlspecialchars($rotulosAcoes[$h['acao']] ?? $h['acao']) ?></div>
            <div class="tl-data"><?= date('d/m/Y \à\s H:i', strtotime($h['criado_em'])) ?></div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <button class="atualizar-btn" onclick="location.reload()">🔄 Atualizar status</button>

<?php endif; ?>

  <div class="rodape">SmartVig OS &mdash; Vigilância Inteligente 24h</div>
</div>

</body>
</html>
