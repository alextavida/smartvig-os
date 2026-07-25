<?php
/**
 * Portal público — visualização e aprovação/recusa de orçamento pelo cliente.
 * URL: /app-tecnicos/portal/orcamento.php?token=XXXXX
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$token = trim($_GET['token'] ?? '');
$orcamento = null;
$itens = [];
$erro = '';

if ($token) {
    $pdo = obterConexao();
    $stmt = $pdo->prepare('SELECT * FROM orcamentos WHERE token = :t');
    $stmt->execute([':t' => $token]);
    $orcamento = $stmt->fetch();

    if ($orcamento) {
        $si = $pdo->prepare('SELECT * FROM orcamento_itens WHERE orcamento_id = :id ORDER BY id');
        $si->execute([':id' => $orcamento['id']]);
        $itens = $si->fetchAll();
    } else {
        $erro = 'Orçamento não encontrado.';
    }
} else {
    $erro = 'Link inválido.';
}

$total = array_sum(array_map(fn($i) => (float)$i['quantidade'] * (float)$i['valor_unitario'], $itens));
$vencimento = $orcamento ? date('d/m/Y', strtotime($orcamento['criado_em'] . ' +' . $orcamento['validade_dias'] . ' days')) : '';
$venceu = $orcamento && strtotime($orcamento['criado_em'] . ' +' . $orcamento['validade_dias'] . ' days') < time();
$respondido = $orcamento && in_array($orcamento['status'], ['aprovado','recusado','convertido'], true);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Orçamento <?= htmlspecialchars($orcamento['codigo'] ?? '') ?> — SmartVig OS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#f1f5f9;min-height:100vh;padding:24px 12px;}
.card{background:#fff;border-radius:14px;padding:24px;max-width:640px;margin:0 auto;box-shadow:0 4px 24px rgba(0,0,0,.1);}
.logo{text-align:center;margin-bottom:24px;}
.logo img{height:40px;}
.logo-txt{font-size:20px;font-weight:800;color:#1e3a8a;}
h1{font-size:18px;font-weight:800;color:#1c2430;margin-bottom:4px;}
.sub{font-size:13px;color:#64748b;margin-bottom:20px;}
table{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px;}
th{text-align:left;color:#64748b;font-weight:600;padding:8px 6px;border-bottom:2px solid #e2e8f0;}
td{padding:10px 6px;border-bottom:1px solid #f1f5f9;color:#1c2430;}
.total-row td{font-weight:800;font-size:15px;color:#1d4ed8;border-top:2px solid #e2e8f0;border-bottom:none;}
.badge{display:inline-block;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:700;}
.badge-rascunho,.badge-enviado{background:#f1f5f9;color:#64748b;}
.badge-aprovado,.badge-convertido{background:#dcfce7;color:#16803c;}
.badge-recusado{background:#fee2e2;color:#dc2626;}
.btn{display:inline-block;padding:12px 24px;border-radius:999px;font-size:14px;font-weight:700;cursor:pointer;border:none;text-align:center;}
.btn-apr{background:#16803c;color:#fff;width:100%;margin-bottom:10px;}
.btn-rec{background:#fff;color:#dc2626;border:2px solid #dc2626;width:100%;}
.btn:disabled{opacity:.5;cursor:not-allowed;}
.obs{background:#f8fafc;border-radius:8px;padding:12px;font-size:13px;color:#475569;margin-bottom:16px;white-space:pre-wrap;}
.aviso{border-radius:8px;padding:12px;font-size:13px;font-weight:600;text-align:center;margin-bottom:16px;}
.aviso-vencido{background:#fef2f2;color:#dc2626;}
.aviso-respondido-apr{background:#dcfce7;color:#16803c;}
.aviso-respondido-rec{background:#fef2f2;color:#dc2626;}
#msg-ok{display:none;text-align:center;padding:20px;}
</style>
</head>
<body>
<?php if ($erro): ?>
  <div class="card">
    <div class="logo"><div class="logo-txt">SmartVig OS</div></div>
    <div style="text-align:center;padding:32px;color:#dc2626;font-weight:700;"><?= htmlspecialchars($erro) ?></div>
  </div>
<?php elseif ($orcamento): ?>
  <div class="card">
    <div class="logo"><div class="logo-txt">SmartVig OS</div></div>
    <h1>Orçamento <?= htmlspecialchars($orcamento['codigo']) ?></h1>
    <div class="sub">
      Cliente: <strong><?= htmlspecialchars($orcamento['cliente_nome']) ?></strong> &nbsp;|&nbsp;
      Validade: <?= $vencimento ?>
      <span class="badge badge-<?= $orcamento['status'] ?>" style="margin-left:8px;"><?= ucfirst($orcamento['status']) ?></span>
    </div>

    <?php if ($venceu && !$respondido): ?>
      <div class="aviso aviso-vencido">⏰ Este orçamento venceu em <?= $vencimento ?>.</div>
    <?php elseif ($respondido && $orcamento['status'] === 'aprovado'): ?>
      <div class="aviso aviso-respondido-apr">✅ Você aprovou este orçamento. Nossa equipe entrará em contato em breve!</div>
    <?php elseif ($respondido && in_array($orcamento['status'], ['recusado'])): ?>
      <div class="aviso aviso-respondido-rec">❌ Você recusou este orçamento.</div>
    <?php elseif ($respondido): ?>
      <div class="aviso aviso-respondido-apr">✅ Este orçamento foi aprovado e convertido em ordem de serviço.</div>
    <?php endif; ?>

    <table>
      <thead>
        <tr>
          <th>Descrição</th>
          <th>Tipo</th>
          <th style="text-align:right;">Qtd</th>
          <th style="text-align:right;">Unit.</th>
          <th style="text-align:right;">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($itens as $item): ?>
        <tr>
          <td><?= htmlspecialchars($item['descricao']) ?></td>
          <td style="color:#64748b;"><?= $item['tipo'] === 'peca' ? 'Peça' : 'Serviço' ?></td>
          <td style="text-align:right;"><?= number_format((float)$item['quantidade'], 2, ',', '.') ?></td>
          <td style="text-align:right;">R$ <?= number_format((float)$item['valor_unitario'], 2, ',', '.') ?></td>
          <td style="text-align:right;font-weight:600;">R$ <?= number_format((float)$item['quantidade'] * (float)$item['valor_unitario'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
          <td colspan="4">Total</td>
          <td style="text-align:right;">R$ <?= number_format($total, 2, ',', '.') ?></td>
        </tr>
      </tbody>
    </table>

    <?php if ($orcamento['observacoes']): ?>
      <div class="obs"><?= htmlspecialchars($orcamento['observacoes']) ?></div>
    <?php endif; ?>

    <div id="msg-ok"></div>

    <?php if (!$respondido && !$venceu): ?>
      <button class="btn btn-apr" onclick="responder('aprovado')" id="btn-apr">✅ Aprovar Orçamento</button>
      <button class="btn btn-rec" onclick="responder('recusado')" id="btn-rec">❌ Recusar</button>
    <?php endif; ?>
  </div>

<script>
async function responder(decisao) {
  document.getElementById('btn-apr').disabled = true;
  document.getElementById('btn-rec').disabled = true;
  try {
    const r = await fetch('/app-tecnicos/api/portal/orcamento.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({token: '<?= htmlspecialchars($token, ENT_QUOTES) ?>', decisao}),
    });
    const d = await r.json();
    if (d.sucesso) {
      const msg = document.getElementById('msg-ok');
      msg.style.display = 'block';
      msg.innerHTML = decisao === 'aprovado'
        ? '<div style="font-size:32px;margin-bottom:8px;">✅</div><div style="font-size:16px;font-weight:700;color:#16803c;">Orçamento aprovado!</div><div style="font-size:13px;color:#64748b;margin-top:4px;">Nossa equipe entrará em contato em breve.</div>'
        : '<div style="font-size:32px;margin-bottom:8px;">❌</div><div style="font-size:16px;font-weight:700;color:#dc2626;">Orçamento recusado.</div><div style="font-size:13px;color:#64748b;margin-top:4px;">Se mudar de ideia, entre em contato conosco.</div>';
      document.getElementById('btn-apr').style.display = 'none';
      document.getElementById('btn-rec').style.display = 'none';
    } else {
      alert(d.erro || 'Erro ao processar.');
      document.getElementById('btn-apr').disabled = false;
      document.getElementById('btn-rec').disabled = false;
    }
  } catch (e) {
    alert('Erro de conexão. Tente novamente.');
    document.getElementById('btn-apr').disabled = false;
    document.getElementById('btn-rec').disabled = false;
  }
}
</script>
<?php endif; ?>
</body>
</html>
