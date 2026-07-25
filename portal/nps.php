<?php
/**
 * Portal público — NPS de atendimento.
 * URL: /app-tecnicos/portal/nps.php?token=XXXXX
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$token = trim($_GET['token'] ?? '');
$os = null;
$erro = '';

if ($token) {
    $pdo  = obterConexao();
    $stmt = $pdo->prepare(
        'SELECT id, codigo, cliente_nome, situacao_local, nps_respondido, nps_nota
         FROM ordens_servico WHERE nps_token = :t'
    );
    $stmt->execute([':t' => $token]);
    $os = $stmt->fetch();
    if (!$os) { $erro = 'Link de avaliação inválido.'; }
    elseif ($os['situacao_local'] !== 'concluido') { $erro = 'Esta OS ainda não foi concluída.'; }
} else {
    $erro = 'Link inválido.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Avalie o atendimento — SmartVig OS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 12px;}
.card{background:#fff;border-radius:16px;padding:32px 28px;max-width:480px;width:100%;box-shadow:0 4px 24px rgba(0,0,0,.1);text-align:center;}
.logo-txt{font-size:22px;font-weight:800;color:#1e3a8a;margin-bottom:6px;}
h1{font-size:18px;font-weight:700;color:#1c2430;margin:20px 0 8px;}
.sub{font-size:13px;color:#64748b;margin-bottom:28px;}
.estrelas{display:flex;justify-content:center;gap:8px;margin-bottom:24px;}
.estrela{font-size:40px;cursor:pointer;transition:transform .1s;filter:grayscale(1);}
.estrela:hover,.estrela.sel{filter:none;transform:scale(1.15);}
.rotulos{display:flex;justify-content:space-between;font-size:11px;color:#94a3b8;margin:-16px 0 20px;}
textarea{width:100%;border:1px solid #e2e8f0;border-radius:8px;padding:12px;font-size:13px;font-family:inherit;resize:vertical;min-height:80px;margin-bottom:16px;color:#1c2430;}
.btn{display:block;width:100%;padding:14px;background:#1d4ed8;color:#fff;border:none;border-radius:999px;font-size:15px;font-weight:700;cursor:pointer;}
.btn:disabled{opacity:.5;cursor:not-allowed;}
#msg-ok{display:none;}
.erro-txt{color:#dc2626;font-weight:700;padding:24px 0;}
</style>
</head>
<body>
<div class="card">
  <div class="logo-txt">SmartVig OS</div>

  <?php if ($erro): ?>
    <div class="erro-txt"><?= htmlspecialchars($erro) ?></div>

  <?php elseif ($os['nps_respondido']): ?>
    <div style="font-size:40px;margin:16px 0;">⭐</div>
    <h1>Avaliação já registrada</h1>
    <p class="sub">Você já avaliou este atendimento com <?= (int)$os['nps_nota'] ?> estrela<?= $os['nps_nota'] > 1 ? 's' : '' ?>. Obrigado!</p>

  <?php else: ?>
    <h1>Como foi o nosso atendimento?</h1>
    <p class="sub">OS: <?= htmlspecialchars($os['codigo'] ?: '#'.$os['id']) ?> — <?= htmlspecialchars($os['cliente_nome']) ?></p>

    <div id="form-nps">
      <div class="estrelas" id="estrelas">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <span class="estrela" data-nota="<?= $i ?>" onclick="selecionarNota(<?= $i ?>)">⭐</span>
        <?php endfor; ?>
      </div>
      <div class="rotulos">
        <span>Muito ruim</span>
        <span>Excelente</span>
      </div>
      <textarea id="comentario" placeholder="Deixe um comentário (opcional)..."></textarea>
      <button class="btn" id="btn-enviar" onclick="enviar()" disabled>Enviar avaliação</button>
    </div>

    <div id="msg-ok">
      <div style="font-size:48px;margin:12px 0;">🙏</div>
      <h1>Obrigado pela avaliação!</h1>
      <p class="sub">Seu feedback é muito importante para nós.</p>
    </div>
  <?php endif; ?>
</div>

<?php if (!$erro && !$os['nps_respondido']): ?>
<script>
let notaSel = 0;

function selecionarNota(n) {
  notaSel = n;
  document.querySelectorAll('.estrela').forEach((el, i) => {
    el.classList.toggle('sel', i < n);
  });
  document.getElementById('btn-enviar').disabled = false;
}

async function enviar() {
  if (!notaSel) return;
  document.getElementById('btn-enviar').disabled = true;
  const comentario = document.getElementById('comentario').value.trim();
  try {
    const r = await fetch('/app-tecnicos/api/portal/nps.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        token: '<?= htmlspecialchars($token, ENT_QUOTES) ?>',
        nota: notaSel,
        comentario,
      }),
    });
    const d = await r.json();
    if (d.sucesso) {
      document.getElementById('form-nps').style.display = 'none';
      document.getElementById('msg-ok').style.display = 'block';
    } else {
      alert(d.erro || 'Erro.');
      document.getElementById('btn-enviar').disabled = false;
    }
  } catch {
    alert('Erro de conexão.');
    document.getElementById('btn-enviar').disabled = false;
  }
}
</script>
<?php endif; ?>
</body>
</html>
