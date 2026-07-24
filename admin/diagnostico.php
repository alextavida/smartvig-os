<?php
/**
 * Pagina de diagnostico: testa conexao com a API GestaoClick e mostra resposta bruta.
 * Acessivel apenas por gestores logados via sessao PHP.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor'];
$tituloPagina = 'Diagnostico GestaoClick';
$paginaAtiva = '';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/gestaoclick.php';

function testarEndpoint(GestaoClickAPI $gc, string $nome, callable $fn): array
{
    $inicio = microtime(true);
    try {
        $resposta = $fn($gc);
        $tempo = round((microtime(true) - $inicio) * 1000);
        return [
            'ok' => true,
            'tempo' => $tempo,
            'chaves' => is_array($resposta) ? array_keys($resposta) : [],
            'total' => is_array($resposta['data'] ?? null) ? count($resposta['data']) :
                       (is_array($resposta) ? count($resposta) : 0),
            'amostra' => array_slice(is_array($resposta['data'] ?? null) ? $resposta['data'] : (array) $resposta, 0, 1),
            'bruto' => $resposta,
        ];
    } catch (GestaoClickApiException $e) {
        $tempo = round((microtime(true) - $inicio) * 1000);
        return ['ok' => false, 'tempo' => $tempo, 'erro' => $e->getMessage()];
    } catch (Throwable $e) {
        return ['ok' => false, 'tempo' => 0, 'erro' => $e->getMessage()];
    }
}

$pdo = obterConexao();
$gcToken = obterConfiguracao('gc_access_token', '');
$gcSecret = obterConfiguracao('gc_secret_access', '');
$gcBase = obterConfiguracao('gc_base_url', '');

$resultados = [];
if (!empty($gcToken) && !empty($gcSecret)) {
    $gc = new GestaoClickAPI();
    $resultados['clientes'] = testarEndpoint($gc, 'Clientes', fn($g) => $g->listarClientes(''));
    $resultados['os'] = testarEndpoint($gc, 'OS', fn($g) => $g->listarOS(1));
    $resultados['situacoes'] = testarEndpoint($gc, 'Situacoes', fn($g) => $g->listarSituacoes());
}
?>

<style>
.diag-card { background:#fff; border-radius:12px; padding:20px; margin-bottom:16px; box-shadow:0 2px 8px rgba(0,0,0,.07); }
.diag-ok { border-left:4px solid #16803c; }
.diag-erro { border-left:4px solid #dc2626; }
.diag-titulo { font-weight:700; font-size:15px; margin-bottom:10px; display:flex; align-items:center; gap:10px; }
.badge-ok { background:#dcfce7; color:#16803c; border-radius:999px; padding:2px 10px; font-size:12px; }
.badge-erro { background:#fee2e2; color:#dc2626; border-radius:999px; padding:2px 10px; font-size:12px; }
.badge-ms { background:#f1f5f9; color:#64748b; border-radius:999px; padding:2px 10px; font-size:12px; }
pre { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; font-size:12px; overflow-x:auto; max-height:300px; overflow-y:auto; white-space:pre-wrap; word-break:break-all; }
.chave-lista { display:flex; flex-wrap:wrap; gap:6px; margin:8px 0; }
.chave { background:#eff6ff; color:#1d4ed8; border-radius:4px; padding:2px 8px; font-size:12px; font-family:monospace; }
</style>

<div class="diag-card">
  <div class="diag-titulo">Tokens configurados</div>
  <table style="width:auto; border-collapse:collapse;">
    <tr>
      <td style="padding:4px 16px 4px 0; color:var(--cinza-500); font-size:13px;">Base URL</td>
      <td style="font-family:monospace; font-size:13px;"><?= htmlspecialchars($gcBase ?: '(nao configurado)') ?></td>
    </tr>
    <tr>
      <td style="padding:4px 16px 4px 0; color:var(--cinza-500); font-size:13px;">Access Token</td>
      <td style="font-family:monospace; font-size:13px;"><?= $gcToken ? htmlspecialchars(substr($gcToken, 0, 8)) . '...' . substr($gcToken, -4) : '<span style="color:#dc2626;">nao configurado</span>' ?></td>
    </tr>
    <tr>
      <td style="padding:4px 16px 4px 0; color:var(--cinza-500); font-size:13px;">Secret Access</td>
      <td style="font-family:monospace; font-size:13px;"><?= $gcSecret ? htmlspecialchars(substr($gcSecret, 0, 8)) . '...' . substr($gcSecret, -4) : '<span style="color:#dc2626;">nao configurado</span>' ?></td>
    </tr>
  </table>
</div>

<?php if (empty($gcToken) || empty($gcSecret)): ?>
  <div class="alerta alerta-erro">Tokens do GestaoClick nao estao configurados na tabela <code>configuracoes</code>.</div>
<?php else: ?>

<?php foreach (['clientes' => 'Endpoint: clientes/', 'os' => 'Endpoint: ordens_de_servicos/', 'situacoes' => 'Endpoint: situacoes_ordens_servicos/'] as $chave => $label): ?>
  <?php $r = $resultados[$chave]; ?>
  <div class="diag-card <?= $r['ok'] ? 'diag-ok' : 'diag-erro' ?>">
    <div class="diag-titulo">
      <?= $label ?>
      <span class="<?= $r['ok'] ? 'badge-ok' : 'badge-erro' ?>"><?= $r['ok'] ? 'OK' : 'ERRO' ?></span>
      <span class="badge-ms"><?= $r['tempo'] ?>ms</span>
    </div>

    <?php if (!$r['ok']): ?>
      <div style="color:#dc2626; font-size:13px; margin-bottom:8px;">Erro: <?= htmlspecialchars($r['erro']) ?></div>
    <?php else: ?>
      <div style="font-size:13px; margin-bottom:8px;">
        <strong>Chaves no topo da resposta:</strong>
        <div class="chave-lista">
          <?php foreach ($r['chaves'] as $k): ?>
            <span class="chave"><?= htmlspecialchars($k) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if (isset($r['total'])): ?>
        <div style="font-size:13px; margin-bottom:8px;">
          <strong>Registros em <code>data</code>:</strong> <?= $r['total'] ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($r['amostra'])): ?>
        <div style="font-size:13px; margin-bottom:6px;"><strong>Amostra (1o registro):</strong></div>
        <pre><?= htmlspecialchars(json_encode($r['amostra'][0] ?? $r['amostra'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<div class="diag-card" style="background:#fffbeb; border-left:4px solid #f59e0b;">
  <div class="diag-titulo" style="color:#92400e;">Como usar este diagnostico</div>
  <ol style="font-size:13px; color:#78350f; margin:0; padding-left:20px; line-height:1.8;">
    <li>Verifique se os endpoints retornam <strong>OK</strong> (verde). Se retornar ERRO, o problema e de autenticacao ou conectividade.</li>
    <li>Verifique as <strong>chaves no topo da resposta</strong>. Se a chave principal nao for <code>data</code>, precisamos ajustar o parser.</li>
    <li>Veja a <strong>amostra do 1o registro</strong> para entender a estrutura real dos campos (ex: <code>cliente</code> e nested ou plano?).</li>
    <li>Com essa informacao, o codigo de sincronizacao sera atualizado para funcionar com a estrutura real.</li>
  </ol>
</div>

<?php endif; ?>

<div style="margin-top:16px;">
  <a href="/app-tecnicos/admin/" class="btn btn-secundario">&larr; Voltar ao dashboard</a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
