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
         WHERE ot.tecnico_id = u.id AND o.situacao_local IN ('aberto','em_andamento','pausado','reagendado')) AS os_ativas,
        (SELECT GROUP_CONCAT(ur.role ORDER BY ur.role SEPARATOR ',') FROM usuario_roles ur WHERE ur.usuario_id = u.id) AS roles_compras
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
        <th>Perfil</th>
        <th>Funções Compras</th>
        <th>OS Ativas</th>
        <th>Status</th>
        <th style="width:200px;">Ações</th>
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
          <td>
            <?php
              $rolesArr = $t['roles_compras'] ? explode(',', $t['roles_compras']) : [];
              $rolesLabel = ['solicitante'=>'Solicitante','comprador'=>'Comprador','aprovador'=>'Aprovador'];
              if ($t['perfil'] === 'gestor') {
                  echo '<span style="font-size:11px;color:#1e8e5a;">Acesso total</span>';
              } elseif (empty($rolesArr)) {
                  echo '<span style="font-size:11px;color:#94a3b8;">—</span>';
              } else {
                  foreach ($rolesArr as $r) {
                      echo '<span class="badge em_andamento" style="font-size:10px;padding:2px 8px;margin-right:3px;">' . ($rolesLabel[$r] ?? $r) . '</span>';
                  }
              }
            ?>
          </td>
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
              <button class="btn-icone" title="Funções de Compras" onclick="abrirModalRoles(<?= (int)$t['id'] ?>, <?= htmlspecialchars(json_encode($t['nome']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($t['roles_compras'] ?? ''), ENT_QUOTES) ?>, '<?= $t['perfil'] ?>')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
              </button>
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

<!-- Modal: Funções de Compras -->
<div class="modal-overlay" id="modalRoles">
  <div class="modal-box">
    <button class="modal-fechar" onclick="fecharModais()">✕</button>
    <h3>Funções de Compras</h3>
    <div id="alertaRoles"></div>
    <p id="rolesLabel" style="font-size:13px;color:#6b7789;margin-bottom:14px;"></p>
    <input type="hidden" id="rolesTecnicoId">
    <div id="rolesCheckboxes" style="display:flex;flex-direction:column;gap:12px;margin-bottom:18px;">
      <label style="display:flex;align-items:center;gap:10px;font-size:14px;cursor:pointer;">
        <input type="checkbox" id="roleSolicitante" style="width:16px;height:16px;">
        <span><strong>Solicitante</strong> — pode criar solicitações de compra</span>
      </label>
      <label style="display:flex;align-items:center;gap:10px;font-size:14px;cursor:pointer;">
        <input type="checkbox" id="roleComprador" style="width:16px;height:16px;">
        <span><strong>Comprador</strong> — processa compras aprovadas junto a fornecedores</span>
      </label>
      <label style="display:flex;align-items:center;gap:10px;font-size:14px;cursor:pointer;">
        <input type="checkbox" id="roleAprovador" style="width:16px;height:16px;">
        <span><strong>Aprovador</strong> — aprova ou reprova solicitações</span>
      </label>
    </div>
    <div style="display:flex;gap:10px;margin-top:6px;">
      <button class="btn btn-primario" onclick="salvarRolesTecnico()">Salvar permissões</button>
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

// ---------- Modal Roles ----------
function abrirModalRoles(id, nome, rolesStr, perfil) {
  document.getElementById('rolesTecnicoId').value = id;
  document.getElementById('rolesLabel').textContent = 'Configurar funções de compras para: ' + nome;
  document.getElementById('alertaRoles').innerHTML = '';
  const rolesArr = rolesStr ? rolesStr.split(',') : [];
  const gestor = perfil === 'gestor';
  document.getElementById('roleSolicitante').checked = gestor || rolesArr.includes('solicitante');
  document.getElementById('roleComprador').checked   = gestor || rolesArr.includes('comprador');
  document.getElementById('roleAprovador').checked   = gestor || perfil === 'supervisor' || rolesArr.includes('aprovador');
  document.getElementById('rolesCheckboxes').querySelectorAll('input').forEach(cb => { cb.disabled = gestor || (perfil === 'supervisor' && cb.id === 'roleAprovador'); });
  document.getElementById('modalRoles').classList.add('aberto');
}

async function salvarRolesTecnico() {
  const alertaBox = document.getElementById('alertaRoles');
  const uid = parseInt(document.getElementById('rolesTecnicoId').value, 10);
  const roles = [];
  if (document.getElementById('roleSolicitante').checked) roles.push('solicitante');
  if (document.getElementById('roleComprador').checked)   roles.push('comprador');
  if (document.getElementById('roleAprovador').checked)   roles.push('aprovador');
  try {
    await apiPost('/usuarios/roles.php', { usuario_id: uid, roles });
    alertaBox.innerHTML = '<div class="alerta alerta-sucesso">Permissões salvas!</div>';
    setTimeout(() => window.location.reload(), 900);
  } catch (e) {
    alertaBox.innerHTML = '<div class="alerta alerta-erro">Erro: ' + e.message + '</div>';
  }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
