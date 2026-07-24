<?php
/**
 * Pagina de diagnostico: testa conexao com a API GestaoClick.
 * Mostra resposta bruta (incluindo corpo de erros) para identificar
 * problemas de autenticacao ou endpoint incorreto.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor'];
$tituloPagina = 'Diagnostico GestaoClick';
$paginaAtiva = '';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$pdo = obterConexao();
$gcToken  = obterConfiguracao('gc_access_token', '') ?? '';
$gcSecret = obterConfiguracao('gc_secret_access', '') ?? '';
$gcBase   = rtrim(obterConfiguracao('gc_base_url', 'https://api.gestaoclick.com/') ?? '', '/') . '/';

// Chama um endpoint GC via cURL e retorna tudo (código, headers, corpo)
function chamadaRawGC(string $base, string $token, string $secret, string $endpoint, array $params = []): array
{
    // Tokens enviados como query params (headers com underscore são bloqueados por nginx)
    $auth = ['access_token' => $token, 'secret_access' => $secret];
    $url = $base . ltrim($endpoint, '/') . '?' . http_build_query(array_merge($auth, $params));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HEADER         => true,   // inclui headers na saida
    ]);

    $inicio = microtime(true);
    $resposta = curl_exec($ch);
    $tempo    = round((microtime(true) - $inicio) * 1000);
    $codigo   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroC    = curl_error($ch);
    $tamanhoHeader = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $corpo = $resposta !== false ? substr($resposta, $tamanhoHeader) : '';
    $json  = json_decode($corpo, true);

    return [
        'url'    => $url,
        'tempo'  => $tempo,
        'codigo' => $codigo,
        'curl_erro' => $erroC,
        'corpo'  => $corpo,
        'json'   => $json,
        'ok'     => $codigo >= 200 && $codigo < 300,
    ];
}

$resultados = [];
if (!empty($gcToken) && !empty($gcSecret)) {
    $endpoints = [
        'clientes'         => ['clientes/', ['pagina' => 1]],
        'ordens_servicos'  => ['ordens_servicos/', ['pagina' => 1]],
        'situacoes'        => ['situacoes_ordens_servicos/', []],
    ];
    foreach ($endpoints as $chave => [$ep, $params]) {
        $resultados[$chave] = chamadaRawGC($gcBase, $gcToken, $gcSecret, $ep, $params);
    }
}
?>

<style>
.dc { background:#fff; border-radius:12px; padding:20px; margin-bottom:16px; box-shadow:0 2px 8px rgba(0,0,0,.07); }
.dc-ok  { border-left:4px solid #16803c; }
.dc-err { border-left:4px solid #dc2626; }
.badge  { border-radius:999px; padding:2px 10px; font-size:12px; font-weight:700; display:inline-block; }
.bOk    { background:#dcfce7; color:#16803c; }
.bErr   { background:#fee2e2; color:#dc2626; }
.bMs    { background:#f1f5f9; color:#64748b; }
.bCode  { background:#eff6ff; color:#1d4ed8; }
pre { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; font-size:12px; overflow-x:auto; max-height:280px; overflow-y:auto; white-space:pre-wrap; word-break:break-all; margin:8px 0 0; }
</style>

<!-- Tokens -->
<div class="dc">
  <strong style="font-size:14px;">Tokens lidos da tabela <code>configuracoes</code></strong>
  <table style="margin-top:10px; border-collapse:collapse; font-size:13px;">
    <tr><td style="padding:3px 16px 3px 0; color:#64748b;">Base URL</td><td style="font-family:monospace;"><?= htmlspecialchars($gcBase) ?></td></tr>
    <tr><td style="padding:3px 16px 3px 0; color:#64748b;">access_token</td><td style="font-family:monospace;"><?= $gcToken ? htmlspecialchars(substr($gcToken,0,8).'...'.substr($gcToken,-6)) . ' <span style="color:#64748b;">(<?= strlen($gcToken) ?> chars)</span>' : '<span style="color:#dc2626;">não configurado</span>' ?></td></tr>
    <tr><td style="padding:3px 16px 3px 0; color:#64748b;">secret_access</td><td style="font-family:monospace;"><?= $gcSecret ? htmlspecialchars(substr($gcSecret,0,8).'...'.substr($gcSecret,-6)) . ' <span style="color:#64748b;">(<?= strlen($gcSecret) ?> chars)</span>' : '<span style="color:#dc2626;">não configurado</span>' ?></td></tr>
  </table>
</div>

<?php if (empty($gcToken) || empty($gcSecret)): ?>
<div class="alerta alerta-erro">Tokens não configurados na tabela <code>configuracoes</code>.</div>
<?php else: ?>

<?php
$labelsEp = [
    'clientes'        => 'clientes/',
    'ordens_servicos' => 'ordens_servicos/ <small>(antes era ordens_de_servicos/)</small>',
    'situacoes'       => 'situacoes_ordens_servicos/',
];
foreach ($resultados as $chave => $r):
?>
<div class="dc <?= $r['ok'] ? 'dc-ok' : 'dc-err' ?>">
  <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
    <span style="font-weight:700; font-size:14px;">Endpoint: <?= $labelsEp[$chave] ?></span>
    <span class="badge <?= $r['ok'] ? 'bOk' : 'bErr' ?>"><?= $r['ok'] ? 'OK' : 'ERRO' ?></span>
    <span class="badge bCode">HTTP <?= $r['codigo'] ?></span>
    <span class="badge bMs"><?= $r['tempo'] ?>ms</span>
  </div>

  <div style="font-size:12px; color:#64748b; margin-bottom:6px;">URL chamada: <code><?= htmlspecialchars($r['url']) ?></code></div>

  <?php if ($r['curl_erro']): ?>
    <div style="color:#dc2626; font-size:13px; margin-bottom:6px;">Erro cURL: <?= htmlspecialchars($r['curl_erro']) ?></div>
  <?php endif; ?>

  <?php if ($r['ok'] && $r['json']): ?>
    <div style="font-size:13px; margin-bottom:4px;"><strong>Chaves no topo da resposta:</strong>
      <?php foreach (array_keys($r['json']) as $k): ?>
        <span style="background:#eff6ff; color:#1d4ed8; border-radius:4px; padding:1px 7px; font-size:12px; font-family:monospace; margin-left:4px;"><?= htmlspecialchars($k) ?></span>
      <?php endforeach; ?>
    </div>
    <?php $itens = $r['json']['data'] ?? $r['json']; ?>
    <?php if (is_array($itens)): ?>
      <div style="font-size:13px; margin-bottom:6px;"><strong>Registros:</strong> <?= is_array($r['json']['data'] ?? null) ? count($r['json']['data']) : count($itens) ?></div>
      <?php $primeiro = is_array($r['json']['data'] ?? null) ? ($r['json']['data'][0] ?? null) : ($itens[0] ?? null); ?>
      <?php if ($primeiro): ?>
        <div style="font-size:13px; margin-bottom:4px;"><strong>1º registro (campos disponíveis):</strong></div>
        <pre><?= htmlspecialchars(json_encode($primeiro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
      <?php endif; ?>
    <?php endif; ?>
  <?php else: ?>
    <div style="font-size:13px; margin-bottom:4px;"><strong>Resposta bruta do GestaoClick:</strong></div>
    <pre><?= htmlspecialchars($r['corpo'] ?: '(resposta vazia)') ?></pre>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<div class="dc" style="background:#fffbeb; border-left:4px solid #f59e0b;">
  <strong style="color:#92400e; font-size:14px;">O que fazer com este resultado</strong>
  <ul style="font-size:13px; color:#78350f; margin:10px 0 0; padding-left:20px; line-height:2;">
    <li>Se continuar <strong>401</strong>: os tokens estão errados ou expirados. Acesse o painel do GestaoClick → Integrações → API e copie os tokens novamente.</li>
    <li>Se aparecer <strong>OK</strong> (verde): olhe os campos do 1º registro e me informe para ajustar o parser.</li>
    <li>Para atualizar os tokens: acesse o phpMyAdmin → tabela <code>configuracoes</code> e edite <code>gc_access_token</code> e <code>gc_secret_access</code>.</li>
  </ul>
</div>
<?php endif; ?>

<div style="margin-top:16px;">
  <a href="javascript:location.reload()" class="btn btn-primario" style="margin-right:8px;">&#8635; Recarregar teste</a>
  <a href="/app-tecnicos/admin/" class="btn btn-secundario">&larr; Dashboard</a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
