<?php
/**
 * Pagina de diagnostico: testa 4 metodos de autenticacao contra a API GestaoClick.
 * Mostra qual metodo funciona e a estrutura real da resposta.
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

function testeAuth(string $base, string $token, string $secret, string $metodo, string $endpoint = 'clientes/'): array
{
    $url = $base . $endpoint;
    $headers = ['Content-Type: application/json', 'Accept: application/json'];

    switch ($metodo) {
        case 'header_correto':
            // "Secret Access Token" (3 palavras) → snake_case = secret_access_token
            $url .= '?pagina=1';
            $headers[] = 'access_token: ' . $token;
            $headers[] = 'secret_access_token: ' . $secret;
            $desc = 'CORRETO: access_token / secret_access_token';
            break;
        case 'header_underscore':
            $url .= '?pagina=1';
            $headers[] = 'access_token: ' . $token;
            $headers[] = 'secret_access: ' . $secret;
            $desc = 'Antigo: access_token / secret_access';
            break;
        case 'header_hyphen':
            $url .= '?pagina=1';
            $headers[] = 'access-token: ' . $token;
            $headers[] = 'secret-access-token: ' . $secret;
            $desc = 'Hífen: access-token / secret-access-token';
            break;
        case 'query_param':
            $url .= '?access_token=' . urlencode($token) . '&secret_access_token=' . urlencode($secret) . '&pagina=1';
            $desc = 'Query params: ?access_token=...&secret_access_token=...';
            break;
        case 'hex_check':
            // Não faz requisição HTTP — apenas verifica encoding dos tokens
            return [
                'desc'         => 'Verificação hex dos tokens (sem requisição HTTP)',
                'url'          => '(sem requisição)',
                'tempo'        => 0,
                'codigo'       => 0,
                'ok'           => false,
                'erroC'        => '',
                'corpo'        => 'access_token hex: ' . bin2hex($token) . "\nsecret_access hex: " . bin2hex($secret),
                'json'         => null,
                'mensagemErro' => '',
            ];
        default:
            $desc = 'Desconhecido';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $inicio  = microtime(true);
    $corpo   = curl_exec($ch);
    $tempo   = round((microtime(true) - $inicio) * 1000);
    $codigo  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroC   = curl_error($ch);
    curl_close($ch);

    $json = $corpo !== false ? json_decode($corpo, true) : null;
    $ok   = $codigo >= 200 && $codigo < 300;

    $mensagemErro = '';
    if (!$ok && is_array($json)) {
        $mensagemErro = $json['data']['mensagem'] ?? $json['message'] ?? $json['erro'] ?? (string) $corpo;
    }

    return compact('desc', 'url', 'tempo', 'codigo', 'ok', 'erroC', 'corpo', 'json', 'mensagemErro');
}

$resultados = [];
if (!empty($gcToken) && !empty($gcSecret)) {
    foreach (['header_correto', 'header_underscore', 'header_hyphen', 'query_param', 'hex_check'] as $m) {
        $resultados[$m] = testeAuth($gcBase, $gcToken, $gcSecret, $m);
        // Para quando encontrar o metodo que funciona (evitar rate limit)
        if ($resultados[$m]['ok']) { break; }
    }
}

$metodoOk = null;
foreach ($resultados as $k => $r) {
    if ($r['ok']) { $metodoOk = $k; break; }
}
?>

<style>
.dc { background:#fff; border-radius:12px; padding:18px; margin-bottom:12px; box-shadow:0 2px 8px rgba(0,0,0,.07); }
.dc-ok  { border-left:4px solid #16803c; }
.dc-err { border-left:4px solid #dc2626; }
.badge  { border-radius:999px; padding:2px 9px; font-size:12px; font-weight:700; display:inline-block; }
.bOk  { background:#dcfce7; color:#16803c; }
.bErr { background:#fee2e2; color:#dc2626; }
.bMs  { background:#f1f5f9; color:#64748b; }
pre { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; font-size:12px; overflow-x:auto; max-height:200px; overflow-y:auto; white-space:pre-wrap; word-break:break-all; margin-top:8px; }
</style>

<!-- Tokens -->
<div class="dc">
  <strong>Tokens na tabela <code>configuracoes</code></strong>
  <table style="margin-top:8px; font-size:13px; border-collapse:collapse;">
    <tr><td style="padding:3px 16px 3px 0; color:#64748b;">Base URL</td><td style="font-family:monospace;"><?= htmlspecialchars($gcBase) ?></td></tr>
    <tr><td style="padding:3px 16px 3px 0; color:#64748b;">access_token</td><td style="font-family:monospace;"><?= $gcToken ? htmlspecialchars(substr($gcToken,0,10)) . '...' . substr($gcToken,-6) . ' (' . strlen($gcToken) . ' chars)' : '<span style="color:#dc2626;">não configurado</span>' ?></td></tr>
    <tr><td style="padding:3px 16px 3px 0; color:#64748b;">secret_access</td><td style="font-family:monospace;"><?= $gcSecret ? htmlspecialchars(substr($gcSecret,0,10)) . '...' . substr($gcSecret,-6) . ' (' . strlen($gcSecret) . ' chars)' : '<span style="color:#dc2626;">não configurado</span>' ?></td></tr>
  </table>
</div>

<?php if (empty($gcToken) || empty($gcSecret)): ?>
<div class="alerta alerta-erro">Tokens não configurados.</div>
<?php elseif ($metodoOk): ?>
<div class="dc dc-ok">
  <strong style="font-size:15px; color:#16803c;">✓ Autenticação OK — método encontrado: <?= htmlspecialchars($resultados[$metodoOk]['desc']) ?></strong>
  <div style="margin-top:8px; font-size:13px;">
    <strong>Estrutura da resposta (1º registro de clientes):</strong>
    <?php $itens = $resultados[$metodoOk]['json']['data'] ?? []; ?>
    <div>Registros: <?= count($itens) ?> | Chaves: <?= implode(', ', array_keys($itens[0] ?? [])) ?></div>
    <?php if (!empty($itens[0])): ?>
      <pre><?= htmlspecialchars(json_encode($itens[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
<div class="dc" style="border-left:4px solid #f59e0b; background:#fffbeb;">
  <strong style="color:#92400e;">Nenhum método de autenticação funcionou. Veja o resultado de cada tentativa:</strong>
</div>
<?php endif; ?>

<?php foreach ($resultados as $chave => $r): ?>
<div class="dc <?= $r['ok'] ? 'dc-ok' : 'dc-err' ?>">
  <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <span class="badge <?= $r['ok'] ? 'bOk' : 'bErr' ?>"><?= $r['ok'] ? 'OK' : 'ERRO' ?></span>
    <span class="badge" style="background:#eff6ff; color:#1d4ed8;">HTTP <?= $r['codigo'] ?></span>
    <span class="badge bMs"><?= $r['tempo'] ?>ms</span>
    <span style="font-size:13px; font-weight:600;"><?= htmlspecialchars($r['desc']) ?></span>
  </div>
  <?php if ($chave === 'hex_check'): ?>
    <pre style="color:#1e40af;"><?= htmlspecialchars($r['corpo']) ?></pre>
  <?php elseif ($r['erroC']): ?>
    <div style="color:#dc2626; font-size:13px;">Erro cURL: <?= htmlspecialchars($r['erroC']) ?></div>
  <?php elseif (!$r['ok']): ?>
    <div style="font-size:13px; color:#dc2626;">Motivo: <?= htmlspecialchars($r['mensagemErro']) ?></div>
  <?php endif; ?>
  <?php if ($r['ok'] && $r['json']): ?>
    <pre><?= htmlspecialchars(json_encode($r['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (!$metodoOk): ?>
<div class="dc" style="border-left:4px solid #dc2626;">
  <strong style="font-size:14px; color:#dc2626;">Todos os métodos falharam — ação necessária:</strong>
  <ol style="font-size:13px; margin:10px 0 0; padding-left:20px; line-height:2;">
    <li>Acesse <strong>GestãoClick → Configurações → Integrações → API</strong></li>
    <li>Regenere os tokens ou confirme se os tokens atuais são exatamente estes:<br>
      <code style="font-size:11px;"><?= htmlspecialchars($gcToken) ?></code><br>
      <code style="font-size:11px;"><?= htmlspecialchars($gcSecret) ?></code></li>
    <li>Se os tokens diferem, atualize no phpMyAdmin → tabela <code>configuracoes</code></li>
    <li>Verifique se o plano GestãoClick inclui acesso à API (planos básicos podem não ter)</li>
  </ol>
</div>
<?php endif; ?>

<?php if ($metodoOk):
    // Pega estrutura real de 1 OS para depuracao
    try {
        $gcInst = new GestaoClickAPI();
        $osResp = $gcInst->listarOS(1);
        $sampleOs = $osResp['data'][0] ?? $osResp['dados'][0] ?? null;
        $osMeta = $osResp['meta'] ?? null;
    } catch (GestaoClickApiException $e) {
        $sampleOs = null;
        $osMeta = null;
    }
?>
<div class="dc" style="border-left:4px solid #1d4ed8;">
  <strong style="font-size:14px; color:#1d4ed8;">Estrutura real de 1 OS do GestãoClick (para depuração do sync)</strong>
  <?php if ($osMeta): ?>
    <div style="font-size:12px;color:#64748b;margin-top:6px;">
      Total OS: <?= (int)($osMeta['total_registros'] ?? 0) ?> |
      Páginas: <?= (int)($osMeta['total_paginas'] ?? 0) ?> |
      Por página: <?= (int)($osMeta['limite_por_pagina'] ?? 0) ?>
    </div>
  <?php endif; ?>
  <?php if ($sampleOs): ?>
    <div style="font-size:12px;color:#64748b;margin-top:4px;">
      <strong>Chaves disponíveis:</strong> <?= htmlspecialchars(implode(', ', array_keys($sampleOs))) ?>
    </div>
    <pre><?= htmlspecialchars(json_encode($sampleOs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
  <?php else: ?>
    <div style="color:#c0392b;font-size:13px;margin-top:8px;">Nenhuma OS encontrada no GestãoClick.</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div style="margin-top:16px; display:flex; gap:8px;">
  <a href="javascript:location.reload()" class="btn btn-primario">&#8635; Testar novamente</a>
  <a href="/app-tecnicos/admin/" class="btn btn-secundario">&larr; Dashboard</a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
