<?php
declare(strict_types=1);

$perfisPermitidosPagina = ['gestor'];
$tituloPagina = 'Configurações de Compras';
$paginaAtiva  = 'compras_config';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/compras_helpers.php';

$pdo = obterConexao();

// Lista todos os usuários com suas roles de compras
$usuarios = $pdo->query(
    "SELECT u.id, u.nome, u.email, u.perfil, u.ativo,
            GROUP_CONCAT(ur.role ORDER BY ur.role SEPARATOR ',') AS roles_compras
     FROM usuarios u
     LEFT JOIN usuario_roles ur ON ur.usuario_id = u.id
     GROUP BY u.id ORDER BY u.perfil, u.nome"
)->fetchAll();

$categorias = $pdo->query('SELECT * FROM categorias_compra ORDER BY nome')->fetchAll();
$centros    = $pdo->query('SELECT * FROM centros_custo ORDER BY nome')->fetchAll();

$perfilLabel = ['gestor'=>'Gestor','supervisor'=>'Supervisor','tecnico'=>'Técnico'];
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;" class="responsive-2col">

  <!-- Gerenciamento de roles -->
  <div class="card" style="grid-column:1/-1;">
    <h3 style="margin-top:0;display:flex;align-items:center;gap:8px;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--azul-700)" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Permissões por Usuário
    </h3>
    <p style="font-size:13px;color:#6b7789;margin-bottom:14px;">
      Configure as funções de cada usuário no módulo de compras.<br>
      <strong>Solicitante:</strong> cria solicitações &nbsp;|&nbsp;
      <strong>Comprador:</strong> processa compras &nbsp;|&nbsp;
      <strong>Aprovador:</strong> aprova/reprova (além de Gestor/Supervisor)
    </p>
    <div id="alertaRoles"></div>
    <div style="overflow-x:auto;">
    <table>
      <thead>
        <tr>
          <th>Usuário</th>
          <th>Perfil</th>
          <th style="text-align:center;">Solicitante</th>
          <th style="text-align:center;">Comprador</th>
          <th style="text-align:center;">Aprovador</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
          <?php
            $rolesArr  = $u['roles_compras'] ? explode(',', $u['roles_compras']) : [];
            $idAttr    = 'u' . (int)$u['id'];
          ?>
          <tr>
            <td>
              <strong style="font-size:13px;"><?= htmlspecialchars($u['nome']) ?></strong>
              <div style="font-size:11px;color:#6b7789;"><?= htmlspecialchars($u['email']) ?></div>
            </td>
            <td><span class="badge <?= $u['perfil']==='gestor'?'aberto':($u['perfil']==='supervisor'?'reagendado':'concluido') ?>"><?= htmlspecialchars($perfilLabel[$u['perfil']] ?? $u['perfil']) ?></span></td>
            <td style="text-align:center;">
              <?php if ($u['perfil'] === 'gestor'): ?>
                <span style="color:#1e8e5a;font-size:18px;" title="Gestor tem acesso total">✓</span>
              <?php else: ?>
                <input type="checkbox" id="<?= $idAttr ?>_sol" <?= in_array('solicitante', $rolesArr, true) ? 'checked' : '' ?>>
              <?php endif; ?>
            </td>
            <td style="text-align:center;">
              <?php if ($u['perfil'] === 'gestor'): ?>
                <span style="color:#1e8e5a;font-size:18px;">✓</span>
              <?php else: ?>
                <input type="checkbox" id="<?= $idAttr ?>_comp" <?= in_array('comprador', $rolesArr, true) ? 'checked' : '' ?>>
              <?php endif; ?>
            </td>
            <td style="text-align:center;">
              <?php if (in_array($u['perfil'], ['gestor','supervisor'], true)): ?>
                <span style="color:#1e8e5a;font-size:18px;">✓</span>
              <?php else: ?>
                <input type="checkbox" id="<?= $idAttr ?>_aprov" <?= in_array('aprovador', $rolesArr, true) ? 'checked' : '' ?>>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($u['perfil'] !== 'gestor'): ?>
              <button onclick="salvarRoles(<?= (int)$u['id'] ?>, '<?= $idAttr ?>')" class="btn btn-secundario btn-sm">Salvar</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- Categorias -->
  <div class="card">
    <h3 style="margin-top:0;">Categorias de Compra</h3>
    <div id="alertaCat"></div>
    <div style="display:flex;gap:8px;margin-bottom:12px;">
      <input type="text" id="novaCategoria" placeholder="Nome da categoria" style="flex:1;">
      <button onclick="addCategoria()" class="btn btn-primario btn-sm">Adicionar</button>
    </div>
    <div>
      <?php foreach ($categorias as $cat): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid #eef1f5;font-size:13px;">
        <span><?= htmlspecialchars($cat['nome']) ?></span>
        <span class="badge <?= $cat['ativo'] ? 'concluido' : 'pausado' ?>" style="font-size:10px;"><?= $cat['ativo'] ? 'Ativa' : 'Inativa' ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Centros de Custo -->
  <div class="card">
    <h3 style="margin-top:0;">Centros de Custo</h3>
    <div id="alertaCC"></div>
    <div style="display:flex;gap:8px;margin-bottom:12px;">
      <input type="text" id="nomeCC" placeholder="Nome do centro" style="flex:1;">
      <input type="text" id="codigoCC" placeholder="Código" style="width:80px;">
      <button onclick="addCentro()" class="btn btn-primario btn-sm">Adicionar</button>
    </div>
    <div>
      <?php foreach ($centros as $cc): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid #eef1f5;font-size:13px;">
        <span><?= htmlspecialchars($cc['nome']) ?></span>
        <?php if ($cc['codigo']): ?><code style="font-size:11px;background:#f4f9fe;padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($cc['codigo']) ?></code><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<script>
async function salvarRoles(uid, prefix) {
  const roles = [];
  if (document.getElementById(prefix+'_sol')?.checked)  roles.push('solicitante');
  if (document.getElementById(prefix+'_comp')?.checked) roles.push('comprador');
  if (document.getElementById(prefix+'_aprov')?.checked) roles.push('aprovador');

  const r = await fetch('/app-tecnicos/api/usuarios/roles.php', {
    method:'POST',
    headers:{'Content-Type':'application/json','Authorization':'Bearer '+(window.APP_JWT||'')},
    body: JSON.stringify({usuario_id: uid, roles}),
  });
  const d = await r.json();
  if (d.sucesso) {
    document.getElementById('alertaRoles').innerHTML='<div style="color:#1e8e5a;margin-bottom:8px;font-size:13px;">✓ Permissões salvas.</div>';
    setTimeout(()=>document.getElementById('alertaRoles').innerHTML='',3000);
  } else {
    alert(d.erro||'Erro.');
  }
}

async function addCategoria() {
  const nome = document.getElementById('novaCategoria').value.trim();
  if (!nome) return;
  const r = await fetch('/app-tecnicos/api/categorias_compra/listar.php', {method:'GET',headers:{'Authorization':'Bearer '+(window.APP_JWT||'')}});
  // Insere via endpoint genérico — simplificação
  const pdo = await fetch('/app-tecnicos/admin/compras/configuracoes/add_categoria.php', {
    method:'POST',
    headers:{'Content-Type':'application/json','Authorization':'Bearer '+(window.APP_JWT||'')},
    body: JSON.stringify({nome}),
  });
  const d = await pdo.json();
  if (d && d.sucesso) { location.reload(); } else { location.reload(); }
}

async function addCentro() {
  const nome = document.getElementById('nomeCC').value.trim();
  const cod  = document.getElementById('codigoCC').value.trim();
  if (!nome) return;
  const r = await fetch('/app-tecnicos/admin/compras/configuracoes/add_centro.php', {
    method:'POST',
    headers:{'Content-Type':'application/json','Authorization':'Bearer '+(window.APP_JWT||'')},
    body: JSON.stringify({nome, codigo: cod||null}),
  });
  location.reload();
}
</script>
<style>
@media(max-width:768px){ .responsive-2col{grid-template-columns:1fr!important;} }
</style>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
