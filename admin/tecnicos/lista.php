<?php
/**
 * Lista de tecnicos: cadastro, edicao inline, upload de foto, redefinicao de senha.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor'];
$tituloPagina = 'Tecnicos';
$paginaAtiva = 'tecnicos';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/icons.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = obterConexao();
$usuarioAtualId = (int) ($usuarioAtual['id'] ?? 0);
$tecnicos = $pdo->query(
    "SELECT u.id, u.nome, u.email, u.telefone, u.ativo, u.foto_perfil, u.perfil,
        (SELECT COUNT(*) FROM os_tecnicos ot INNER JOIN ordens_servico o ON o.id = ot.os_id
         WHERE ot.tecnico_id = u.id AND o.situacao_local IN ('aberto','em_andamento','pausado','reagendado')) AS os_ativas
     FROM usuarios u ORDER BY FIELD(u.perfil,'gestor','supervisor','tecnico'), u.nome"
)->fetchAll();

$perfilLabel = ['gestor' => 'Administrador', 'supervisor' => 'Supervisor', 'tecnico' => 'Técnico'];
$perfilBadge = ['gestor' => 'aberto', 'supervisor' => 'reagendado', 'tecnico' => 'concluido'];

function inicialTec(string $nome): string {
    $i = ''; foreach (explode(' ', trim($nome)) as $p) { if ($p !== '') { $i .= mb_strtoupper(mb_substr($p, 0, 1)); } if (mb_strlen($i) >= 2) { break; } } return $i;
}
?>

<!-- Cadastrar novo tecnico -->
<div class="card">
  <h3 style="margin-top:0; display:flex; align-items:center; gap:8px;"><?= ic('os_nova') ?> Cadastrar novo tecnico</h3>
  <div id="alertaTecnico"></div>
  <form id="formNovoTecnico">
    <div class="linha-form">
      <div class="campo"><label>Nome *</label><input type="text" name="nome" required></div>
      <div class="campo"><label>E-mail *</label><input type="email" name="email" required></div>
    </div>
    <div class="linha-form">
      <div class="campo"><label>Telefone</label><input type="tel" name="telefone" placeholder="(00) 00000-0000"></div>
      <div class="campo"><label>Senha inicial *</label><input type="password" name="senha" minlength="6" required></div>
    </div>
    <button type="submit" class="btn btn-primario"><?= ic('check', 15) ?> Cadastrar tecnico</button>
  </form>
</div>

<!-- Lista de tecnicos -->
<div class="card">
  <h3 style="margin-top:0; display:flex; align-items:center; gap:8px;"><?= ic('tecnicos') ?> Tecnicos cadastrados</h3>
  <div id="alertaAcao"></div>
  <?php if (empty($tecnicos)): ?>
    <div class="vazio"><?= ic('tecnico_vazio') ?><br>Nenhum tecnico cadastrado ainda.</div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Usuário</th>
        <th>E-mail / Telefone</th>
        <th>Função</th>
        <th>OS Ativas</th>
        <th>Status</th>
        <th style="width:180px;">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($tecnicos as $t): ?>
        <tr id="row-<?= (int) $t['id'] ?>">
          <td>
            <div style="display:flex; align-items:center; gap:10px;">
              <?php if ($t['foto_perfil'] && file_exists(__DIR__ . '/../../' . $t['foto_perfil'])): ?>
                <img src="/app-tecnicos/<?= htmlspecialchars($t['foto_perfil']) ?>" class="avatar-foto" alt="">
              <?php else: ?>
                <div class="avatar-placeholder" style="width:36px;height:36px;font-size:13px;"><?= inicialTec($t['nome']) ?></div>
              <?php endif; ?>
              <strong><?= htmlspecialchars($t['nome']) ?></strong>
            </div>
          </td>
          <td>
            <div><?= htmlspecialchars($t['email']) ?></div>
            <div style="font-size:12px; color:var(--cinza-500);"><?= htmlspecialchars($t['telefone'] ?? '-') ?></div>
          </td>
          <td><span class="badge <?= $perfilBadge[$t['perfil']] ?? 'cancelado' ?>"><?= $perfilLabel[$t['perfil']] ?? $t['perfil'] ?></span></td>
          <td><?= (int) $t['os_ativas'] ?></td>
          <td>
            <?php if ($t['ativo']): ?>
              <span class="badge concluido">Ativo</span>
            <?php else: ?>
              <span class="badge cancelado">Inativo</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="acoes-tabela">
              <button class="btn-icone" title="Editar" onclick="abrirModalEditar(<?= htmlspecialchars(json_encode(['id'=>(int)$t['id'],'nome'=>$t['nome'],'email'=>$t['email'],'telefone'=>$t['telefone']??'','ativo'=>(bool)$t['ativo'],'perfil'=>$t['perfil'],'ehEuMesmo'=>((int)$t['id']===$usuarioAtualId)])) ?>)"><?= ic('editar', 15) ?></button>
              <button class="btn-icone" title="Foto de perfil" onclick="abrirModalFoto(<?= (int) $t['id'] ?>, <?= htmlspecialchars(json_encode($t['nome']), ENT_QUOTES) ?>)"><?= ic('foto', 15) ?></button>
              <button class="btn-icone" title="Redefinir senha" onclick="abrirModalSenha(<?= (int) $t['id'] ?>, <?= htmlspecialchars(json_encode($t['nome']), ENT_QUOTES) ?>)"><?= ic('cadeado', 15) ?></button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Modal: Editar tecnico -->
<div class="modal-overlay" id="modalEditar">
  <div class="modal-box">
    <button class="modal-fechar" onclick="fecharModais()"><?= ic('voltar', 16) ?></button>
    <h3><?= ic('editar') ?> Editar tecnico</h3>
    <div id="alertaEditar"></div>
    <input type="hidden" id="editId">
    <div class="campo"><label>Nome *</label><input type="text" id="editNome" required></div>
    <div class="campo"><label>E-mail *</label><input type="email" id="editEmail" required></div>
    <div class="campo"><label>Telefone</label><input type="tel" id="editTelefone"></div>
    <div class="campo">
      <label>Função</label>
      <select id="editPerfil">
        <option value="gestor">Administrador</option>
        <option value="supervisor">Supervisor</option>
        <option value="tecnico">Técnico</option>
      </select>
      <small id="editPerfilAviso" style="display:none;color:#c8641a;font-size:12px;margin-top:4px;">
        Você está editando seu próprio perfil — a função não pode ser alterada.
      </small>
    </div>
    <div class="campo">
      <label>Status</label>
      <select id="editAtivo">
        <option value="1">Ativo</option>
        <option value="0">Inativo</option>
      </select>
    </div>
    <div style="display:flex; gap:10px; margin-top:6px;">
      <button class="btn btn-primario" onclick="salvarEdicao()"><?= ic('check', 15) ?> Salvar</button>
      <button class="btn btn-neutro" onclick="fecharModais()">Cancelar</button>
    </div>
  </div>
</div>

<!-- Modal: Foto de perfil -->
<div class="modal-overlay" id="modalFoto">
  <div class="modal-box">
    <button class="modal-fechar" onclick="fecharModais()"><?= ic('voltar', 16) ?></button>
    <h3><?= ic('foto') ?> Foto de perfil</h3>
    <div id="alertaFoto"></div>
    <div id="fotoPreviewContainer" style="text-align:center; margin-bottom:16px;"></div>
    <input type="hidden" id="fotoTecnicoId">
    <div class="campo"><label>Selecionar imagem (JPG, PNG ou WEBP, max 3MB)</label>
      <input type="file" id="fotoArquivo" accept="image/jpeg,image/png,image/webp"></div>
    <div style="display:flex; gap:10px; margin-top:6px;">
      <button class="btn btn-primario" onclick="enviarFoto()"><?= ic('foto', 15) ?> Enviar foto</button>
      <button class="btn btn-neutro" onclick="fecharModais()">Cancelar</button>
    </div>
  </div>
</div>

<!-- Modal: Redefinir senha -->
<div class="modal-overlay" id="modalSenha">
  <div class="modal-box">
    <button class="modal-fechar" onclick="fecharModais()"><?= ic('voltar', 16) ?></button>
    <h3><?= ic('cadeado') ?> Redefinir senha</h3>
    <div id="alertaSenha"></div>
    <div id="senhaLabel" style="margin-bottom:14px; font-size:13.5px; color:var(--cinza-700);"></div>
    <input type="hidden" id="senhaTecnicoId">
    <div class="campo"><label>Nova senha *</label><input type="password" id="novaSenha" minlength="6" placeholder="Minimo 6 caracteres"></div>
    <div class="campo"><label>Confirmar nova senha *</label><input type="password" id="confirmarSenha" minlength="6"></div>
    <div style="display:flex; gap:10px; margin-top:6px;">
      <button class="btn btn-primario" onclick="redefinirSenha()"><?= ic('check', 15) ?> Redefinir</button>
      <button class="btn btn-neutro" onclick="fecharModais()">Cancelar</button>
    </div>
  </div>
</div>

<script>
// ---------- Cadastrar novo tecnico ----------
document.getElementById('formNovoTecnico').addEventListener('submit', async function (ev) {
  ev.preventDefault();
  const alertaBox = document.getElementById('alertaTecnico');
  const fd = new FormData(ev.target);
  const payload = {};
  for (const [k, v] of fd.entries()) payload[k] = v;
  try {
    await apiPost('/tecnicos/criar.php', payload);
    alertaBox.innerHTML = '<div class="alerta alerta-sucesso">Tecnico cadastrado com sucesso!</div>';
    setTimeout(() => window.location.reload(), 900);
  } catch (e) {
    alertaBox.innerHTML = '<div class="alerta alerta-erro">Erro: ' + e.message + '</div>';
  }
});

// ---------- Utilitarios de modal ----------
function fecharModais() {
  document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('aberto'));
}
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) fecharModais(); });
});

// ---------- Modal Editar ----------
function abrirModalEditar(t) {
  document.getElementById('editId').value = t.id;
  document.getElementById('editNome').value = t.nome;
  document.getElementById('editEmail').value = t.email;
  document.getElementById('editTelefone').value = t.telefone || '';
  document.getElementById('editAtivo').value = t.ativo ? '1' : '0';
  document.getElementById('editPerfil').value = t.perfil || 'tecnico';
  const aviso = document.getElementById('editPerfilAviso');
  const selectPerfil = document.getElementById('editPerfil');
  if (t.ehEuMesmo) {
    selectPerfil.disabled = true;
    aviso.style.display = 'block';
  } else {
    selectPerfil.disabled = false;
    aviso.style.display = 'none';
  }
  document.getElementById('alertaEditar').innerHTML = '';
  document.getElementById('modalEditar').classList.add('aberto');
}

async function salvarEdicao() {
  const alertaBox = document.getElementById('alertaEditar');
  try {
    const perfilSelect = document.getElementById('editPerfil');
    const payload = {
      tecnico_id: parseInt(document.getElementById('editId').value, 10),
      nome: document.getElementById('editNome').value,
      email: document.getElementById('editEmail').value,
      telefone: document.getElementById('editTelefone').value,
      ativo: document.getElementById('editAtivo').value === '1',
    };
    if (!perfilSelect.disabled) { payload.perfil = perfilSelect.value; }
    await apiPost('/tecnicos/editar.php', payload);
    alertaBox.innerHTML = '<div class="alerta alerta-sucesso">Dados atualizados!</div>';
    setTimeout(() => window.location.reload(), 900);
  } catch (e) {
    alertaBox.innerHTML = '<div class="alerta alerta-erro">Erro: ' + e.message + '</div>';
  }
}

// ---------- Modal Foto ----------
function abrirModalFoto(id, nome) {
  document.getElementById('fotoTecnicoId').value = id;
  document.getElementById('alertaFoto').innerHTML = '';
  document.getElementById('fotoArquivo').value = '';
  document.getElementById('fotoPreviewContainer').innerHTML = '<p style="color:var(--cinza-500);font-size:13px;">Tecnico: <strong>' + nome + '</strong></p>';
  document.getElementById('modalFoto').classList.add('aberto');
}

document.getElementById('fotoArquivo').addEventListener('change', function () {
  const f = this.files[0];
  if (!f) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('fotoPreviewContainer').innerHTML =
      '<img src="' + e.target.result + '" style="width:88px;height:88px;border-radius:50%;object-fit:cover;border:3px solid var(--azul-100);">';
  };
  reader.readAsDataURL(f);
});

async function enviarFoto() {
  const alertaBox = document.getElementById('alertaFoto');
  const arquivo = document.getElementById('fotoArquivo').files[0];
  if (!arquivo) { alertaBox.innerHTML = '<div class="alerta alerta-erro">Selecione uma imagem.</div>'; return; }
  const fd = new FormData();
  fd.append('tecnico_id', document.getElementById('fotoTecnicoId').value);
  fd.append('arquivo', arquivo);
  try {
    await apiUpload('/tecnicos/upload_foto.php', fd);
    alertaBox.innerHTML = '<div class="alerta alerta-sucesso">Foto atualizada!</div>';
    setTimeout(() => window.location.reload(), 900);
  } catch (e) {
    alertaBox.innerHTML = '<div class="alerta alerta-erro">Erro: ' + e.message + '</div>';
  }
}

// ---------- Modal Senha ----------
function abrirModalSenha(id, nome) {
  document.getElementById('senhaTecnicoId').value = id;
  document.getElementById('senhaLabel').innerHTML = 'Redefinir senha de <strong>' + nome + '</strong>';
  document.getElementById('novaSenha').value = '';
  document.getElementById('confirmarSenha').value = '';
  document.getElementById('alertaSenha').innerHTML = '';
  document.getElementById('modalSenha').classList.add('aberto');
}

async function redefinirSenha() {
  const alertaBox = document.getElementById('alertaSenha');
  const nova = document.getElementById('novaSenha').value;
  const conf = document.getElementById('confirmarSenha').value;
  if (nova.length < 6) { alertaBox.innerHTML = '<div class="alerta alerta-erro">A senha deve ter ao menos 6 caracteres.</div>'; return; }
  if (nova !== conf) { alertaBox.innerHTML = '<div class="alerta alerta-erro">As senhas nao coincidem.</div>'; return; }
  try {
    await apiPost('/auth/alterar_senha.php', {
      tecnico_id: parseInt(document.getElementById('senhaTecnicoId').value, 10),
      nova_senha: nova,
    });
    alertaBox.innerHTML = '<div class="alerta alerta-sucesso">Senha redefinida com sucesso!</div>';
    setTimeout(fecharModais, 1200);
  } catch (e) {
    alertaBox.innerHTML = '<div class="alerta alerta-erro">Erro: ' + e.message + '</div>';
  }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
