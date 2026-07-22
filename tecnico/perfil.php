<?php
/**
 * Perfil do tecnico: foto de perfil, dados pessoais, alterar senha.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['tecnico'];
$tituloPagina = 'Meu Perfil';
$paginaAtiva = 'perfil';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../config/database.php';

$pdo = obterConexao();
$userId = $usuarioAtual['usuario_id'];

$stmt = $pdo->prepare('SELECT id, nome, email, telefone, foto_perfil, criado_em FROM usuarios WHERE id = :id');
$stmt->execute(['id' => $userId]);
$usuario = $stmt->fetch();

$totalOs = (int) $pdo->prepare(
    'SELECT COUNT(*) FROM os_tecnicos ot INNER JOIN ordens_servico o ON o.id = ot.os_id WHERE ot.tecnico_id = :id'
)->execute(['id' => $userId]) ? $pdo->query("SELECT COUNT(*) FROM os_tecnicos ot INNER JOIN ordens_servico o ON o.id = ot.os_id WHERE ot.tecnico_id = {$userId}")->fetchColumn() : 0;

$osAtivas = (int) $pdo->query("SELECT COUNT(*) FROM os_tecnicos ot INNER JOIN ordens_servico o ON o.id = ot.os_id WHERE ot.tecnico_id = {$userId} AND o.situacao_local IN ('aberto','em_andamento','pausado','reagendado')")->fetchColumn();
$osConcluidas = (int) $pdo->query("SELECT COUNT(*) FROM os_tecnicos ot INNER JOIN ordens_servico o ON o.id = ot.os_id WHERE ot.tecnico_id = {$userId} AND o.situacao_local = 'concluido'")->fetchColumn();

function inicialPerfil(string $nome): string {
    $i = ''; foreach (explode(' ', trim($nome)) as $p) { if ($p !== '') { $i .= mb_strtoupper(mb_substr($p, 0, 1)); } if (mb_strlen($i) >= 2) { break; } } return $i;
}
?>

<div id="alertaPerfil"></div>

<!-- Cabecalho do perfil -->
<div class="card">
  <div class="perfil-header">
    <div id="avatarContainer">
      <?php if ($usuario['foto_perfil'] && file_exists(__DIR__ . '/../' . $usuario['foto_perfil'])): ?>
        <img src="/app-tecnicos/<?= htmlspecialchars($usuario['foto_perfil']) ?>" class="avatar-grande" id="avatarImg" alt="Foto">
      <?php else: ?>
        <div class="avatar-placeholder grande" id="avatarImg"><?= inicialPerfil($usuario['nome']) ?></div>
      <?php endif; ?>
    </div>
    <div>
      <h2 style="margin:0 0 4px 0; font-size:22px;"><?= htmlspecialchars($usuario['nome']) ?></h2>
      <div style="font-size:13.5px; color:var(--cinza-500);"><?= htmlspecialchars($usuario['email']) ?></div>
      <?php if ($usuario['telefone']): ?>
        <div style="font-size:13.5px; color:var(--cinza-500);"><?= htmlspecialchars($usuario['telefone']) ?></div>
      <?php endif; ?>
      <div style="font-size:12px; color:var(--cinza-500); margin-top:6px;">Cadastrado em <?= date('d/m/Y', strtotime($usuario['criado_em'])) ?></div>
    </div>
    <div style="margin-left:auto; display:flex; gap:16px; text-align:center; flex-wrap:wrap;">
      <div><div style="font-size:26px; font-weight:800; color:var(--azul-900);"><?= $osAtivas ?></div><div style="font-size:12px; color:var(--cinza-500);">OS ativas</div></div>
      <div><div style="font-size:26px; font-weight:800; color:var(--verde);"><?= $osConcluidas ?></div><div style="font-size:12px; color:var(--cinza-500);">Concluidas</div></div>
    </div>
  </div>

  <!-- Upload de foto -->
  <div style="border-top:1px solid var(--cinza-100); padding-top:14px; margin-top:4px;">
    <label style="font-size:13px; font-weight:600; display:block; margin-bottom:8px;"><?= ic('foto') ?> Atualizar foto de perfil</label>
    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
      <input type="file" id="fotoArquivo" accept="image/jpeg,image/png,image/webp" style="flex:1; min-width:200px;">
      <button class="btn btn-secundario" onclick="enviarFoto()"><?= ic('foto', 15) ?> Enviar foto</button>
    </div>
    <div style="font-size:12px; color:var(--cinza-500); margin-top:4px;">JPG, PNG ou WEBP — maximo 3 MB</div>
  </div>
</div>

<!-- Alterar senha -->
<div class="card">
  <h3 style="margin-top:0; display:flex; align-items:center; gap:8px;"><?= ic('cadeado') ?> Alterar senha</h3>
  <div id="alertaSenha"></div>
  <form id="formAlterarSenha" autocomplete="off">
    <div class="linha-form">
      <div class="campo">
        <label for="senhaAtual">Senha atual *</label>
        <input type="password" id="senhaAtual" required>
      </div>
    </div>
    <div class="linha-form">
      <div class="campo">
        <label for="novaSenha">Nova senha *</label>
        <input type="password" id="novaSenha" required minlength="6" placeholder="Minimo 6 caracteres">
      </div>
      <div class="campo">
        <label for="confirmarSenha">Confirmar nova senha *</label>
        <input type="password" id="confirmarSenha" required minlength="6">
      </div>
    </div>
    <button type="submit" class="btn btn-primario"><?= ic('check', 15) ?> Alterar senha</button>
  </form>
</div>

<script>
// Upload de foto
document.getElementById('fotoArquivo').addEventListener('change', function () {
  const f = this.files[0];
  if (!f) return;
  const reader = new FileReader();
  reader.onload = e => {
    const img = document.getElementById('avatarImg');
    const novaImg = '<img src="' + e.target.result + '" class="avatar-grande" id="avatarImg" alt="Foto">';
    img.outerHTML = novaImg;
  };
  reader.readAsDataURL(f);
});

async function enviarFoto() {
  const alertaBox = document.getElementById('alertaPerfil');
  const arquivo = document.getElementById('fotoArquivo').files[0];
  if (!arquivo) { alertaBox.innerHTML = '<div class="alerta alerta-erro">Selecione uma imagem.</div>'; return; }
  const fd = new FormData();
  fd.append('arquivo', arquivo);
  try {
    const dados = await apiUpload('/tecnicos/upload_foto.php', fd);
    alertaBox.innerHTML = '<div class="alerta alerta-sucesso">Foto de perfil atualizada com sucesso!</div>';
    // Atualiza avatar na pagina sem reload
    const avatarEl = document.getElementById('avatarImg');
    if (avatarEl) {
      const url = dados.url + '?t=' + Date.now();
      avatarEl.outerHTML = '<img src="' + url + '" class="avatar-grande" id="avatarImg" alt="Foto">';
    }
  } catch (e) {
    alertaBox.innerHTML = '<div class="alerta alerta-erro">Erro: ' + e.message + '</div>';
  }
}

// Alterar senha
document.getElementById('formAlterarSenha').addEventListener('submit', async function (ev) {
  ev.preventDefault();
  const alertaBox = document.getElementById('alertaSenha');
  const nova = document.getElementById('novaSenha').value;
  const conf = document.getElementById('confirmarSenha').value;

  if (nova.length < 6) { alertaBox.innerHTML = '<div class="alerta alerta-erro">A nova senha deve ter ao menos 6 caracteres.</div>'; return; }
  if (nova !== conf) { alertaBox.innerHTML = '<div class="alerta alerta-erro">As senhas nao coincidem.</div>'; return; }

  try {
    await apiPost('/auth/alterar_senha.php', {
      senha_atual: document.getElementById('senhaAtual').value,
      nova_senha: nova,
    });
    alertaBox.innerHTML = '<div class="alerta alerta-sucesso">Senha alterada com sucesso! Suas proximas sessoes usarao a nova senha.</div>';
    this.reset();
  } catch (e) {
    alertaBox.innerHTML = '<div class="alerta alerta-erro">Erro: ' + e.message + '</div>';
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
