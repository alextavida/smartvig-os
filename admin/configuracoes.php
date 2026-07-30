<?php
/**
 * Configurações do sistema: GestãoClick.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor'];
$tituloPagina = 'Configurações';
$paginaAtiva = 'configuracoes';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$pdo = obterConexao();
$mensagem = null;
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chaves = ['gc_base_url', 'gc_access_token', 'gc_secret_access'];
    foreach ($chaves as $chave) {
        $valor = trim($_POST[$chave] ?? '');
        if ($valor !== '') {
            definirConfiguracao($chave, $valor);
        }
    }
    $mensagem = 'Configurações salvas.';
}

$gcBaseUrl     = obterConfiguracao('gc_base_url', 'https://api.gestaoclick.com/') ?? '';
$gcAccessToken = obterConfiguracao('gc_access_token', '') ?? '';
$gcSecretAccess= obterConfiguracao('gc_secret_access', '') ?? '';
?>

<style>
.config-card { background:#fff; border-radius:12px; padding:24px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,.07); }
.config-card h3 { margin:0 0 16px; font-size:1rem; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.badge-ok  { background:#dcfce7; color:#16803c; border-radius:999px; padding:2px 10px; font-size:12px; font-weight:700; }
.badge-err { background:#fee2e2; color:#dc2626; border-radius:999px; padding:2px 10px; font-size:12px; font-weight:700; }
.form-label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:4px; margin-top:12px; }
.form-input { width:100%; box-sizing:border-box; border:1px solid #d1d5db; border-radius:8px; padding:8px 12px; font-size:14px; font-family:monospace; }
.form-hint  { font-size:12px; color:#6b7280; margin-top:4px; }
</style>

<?php if ($mensagem): ?>
  <div class="alerta alerta-sucesso" style="margin-bottom:16px;"><?= htmlspecialchars($mensagem) ?></div>
<?php endif; ?>

<div class="config-card">
  <h3>
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
    Integração GestãoClick
    <?php if ($gcAccessToken && $gcSecretAccess): ?>
      <span class="badge-ok">Configurado</span>
    <?php else: ?>
      <span class="badge-err">Não configurado</span>
    <?php endif; ?>
  </h3>
  <form method="post">
    <label class="form-label">URL Base da API</label>
    <input type="text" name="gc_base_url" class="form-input" value="<?= htmlspecialchars($gcBaseUrl) ?>" placeholder="https://api.gestaoclick.com/">
    <label class="form-label">Access Token</label>
    <input type="text" name="gc_access_token" class="form-input" value="<?= htmlspecialchars($gcAccessToken) ?>" placeholder="Token de 40 caracteres">
    <label class="form-label">Secret Access Token</label>
    <input type="text" name="gc_secret_access" class="form-input" value="<?= htmlspecialchars($gcSecretAccess) ?>" placeholder="Token de 40 caracteres">
    <p class="form-hint">Encontre em GestãoClick → Configurações → Integrações → API.</p>
    <div style="margin-top:16px; display:flex; gap:8px; flex-wrap:wrap;">
      <button type="submit" class="btn btn-primario">Salvar</button>
      <a href="/app-tecnicos/admin/diagnostico.php" class="btn btn-secundario">Testar conexão</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
