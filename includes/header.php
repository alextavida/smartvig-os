<?php
/**
 * Cabecalho comum a todas as paginas do painel (admin e tecnico).
 * Espera (opcionalmente) definidas antes do include:
 *   $perfisPermitidosPagina = ['gestor']  (ou ['tecnico'], ou ambos)
 *   $tituloPagina = 'Dashboard'
 *   $paginaAtiva  = 'dashboard' (usado para destacar o menu)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/guard.php';
require_once __DIR__ . '/../includes/icons.php';

$usuarioAtual = exigirLoginWeb($perfisPermitidosPagina ?? []);
$tituloPagina = $tituloPagina ?? 'SmartVig';
$paginaAtiva = $paginaAtiva ?? '';

$iniciais = '';
foreach (explode(' ', trim($usuarioAtual['nome'])) as $parte) {
    if ($parte !== '') { $iniciais .= mb_strtoupper(mb_substr($parte, 0, 1)); }
    if (mb_strlen($iniciais) >= 2) { break; }
}

// Foto de perfil do usuario logado (lida da sessao ou banco)
$fotoPerfil = $usuarioAtual['foto_perfil'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($tituloPagina) ?> - SmartVig OS</title>
<link rel="icon" href="/app-tecnicos/imgs/logo.png">
<link rel="stylesheet" href="/app-tecnicos/assets/css/app.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="marca">
      <img src="/app-tecnicos/imgs/logo.png" alt="SmartVig">
      <div>
        <div class="titulo">SmartVig</div>
        <div class="subtitulo">Gestao de OS</div>
      </div>
    </div>
    <nav>
      <?php if (in_array($usuarioAtual['perfil'], ['gestor', 'supervisor'], true)): ?>
        <?php $isSupervisor = $usuarioAtual['perfil'] === 'supervisor'; ?>
        <a href="/app-tecnicos/admin/" class="<?= $paginaAtiva === 'dashboard' ? 'ativo' : '' ?>">
          <?= ic('dashboard') ?> Dashboard
        </a>
        <a href="/app-tecnicos/admin/os/lista.php" class="<?= $paginaAtiva === 'os_lista' ? 'ativo' : '' ?>">
          <?= ic('os_lista') ?> Ordens de Servico
        </a>
        <?php if (!$isSupervisor): ?>
        <a href="/app-tecnicos/admin/os/criar.php" class="<?= $paginaAtiva === 'os_criar' ? 'ativo' : '' ?>">
          <?= ic('os_nova') ?> Nova OS
        </a>
        <?php endif; ?>
        <a href="/app-tecnicos/admin/os/mapa.php" class="<?= $paginaAtiva === 'mapa' ? 'ativo' : '' ?>">
          <?= ic('mapa') ?> Mapa de Tecnicos
        </a>
        <a href="/app-tecnicos/admin/os/calendario.php" class="<?= $paginaAtiva === 'calendario' ? 'ativo' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          Calendário
        </a>
        <a href="/app-tecnicos/admin/os/sla.php" class="<?= $paginaAtiva === 'sla' ? 'ativo' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Painel SLA
        </a>
        <a href="/app-tecnicos/admin/orcamentos/lista.php" class="<?= $paginaAtiva === 'orcamentos' ? 'ativo' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          Orçamentos
        </a>
        <?php if (!$isSupervisor): ?>
        <a href="/app-tecnicos/admin/tecnicos/lista.php" class="<?= $paginaAtiva === 'tecnicos' ? 'ativo' : '' ?>">
          <?= ic('tecnicos') ?> Tecnicos
        </a>
        <?php endif; ?>
        <a href="/app-tecnicos/admin/clientes/historico.php" class="<?= $paginaAtiva === 'clientes' ? 'ativo' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Histórico Clientes
        </a>
        <a href="/app-tecnicos/admin/relatorio.php" class="<?= $paginaAtiva === 'relatorio' ? 'ativo' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          Relatório
        </a>
        <?php if (!$isSupervisor): ?>
        <a href="/app-tecnicos/admin/diagnostico.php" class="<?= $paginaAtiva === 'diagnostico' ? 'ativo' : '' ?>" style="opacity:.7; font-size:12px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          Diagnostico GC
        </a>
        <?php endif; ?>
      <?php else: ?>
        <a href="/app-tecnicos/tecnico/" class="<?= $paginaAtiva === 'dashboard' ? 'ativo' : '' ?>">
          <?= ic('os_lista') ?> Minhas OS
        </a>
        <a href="/app-tecnicos/tecnico/perfil.php" class="<?= $paginaAtiva === 'perfil' ? 'ativo' : '' ?>">
          <?= ic('perfil') ?> Meu Perfil
        </a>
      <?php endif; ?>
    </nav>
    <div class="rodape-sidebar">
      <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
        <?php if ($fotoPerfil && file_exists(__DIR__ . '/../' . $fotoPerfil)): ?>
          <img src="/app-tecnicos/<?= htmlspecialchars($fotoPerfil) ?>" class="avatar-foto" alt="Foto">
        <?php else: ?>
          <div class="avatar-placeholder" style="width:32px;height:32px;font-size:13px;"><?= htmlspecialchars($iniciais) ?></div>
        <?php endif; ?>
        <div>
          <div style="font-size:12px; font-weight:600;"><?= htmlspecialchars($usuarioAtual['nome']) ?></div>
          <div style="font-size:10px; opacity:.7;"><?= $usuarioAtual['perfil'] === 'gestor' ? 'Gestor' : 'Tecnico' ?></div>
        </div>
      </div>
      <a href="/app-tecnicos/logout.php" style="color:rgba(255,255,255,.8); font-size:12px; display:flex; align-items:center; gap:6px;">
        <?= ic('sair', 14) ?> Sair do sistema
      </a>
    </div>
  </aside>
  <main class="conteudo">
    <div class="topbar">
      <h1><?= htmlspecialchars($tituloPagina) ?></h1>
      <div class="acoes">
        <div class="sino-notif" id="sinoNotificacoes" title="Notificacoes">
          <?= ic('notificacao') ?>
          <span class="contador" id="contadorNotificacoes" style="display:none;">0</span>
        </div>
        <?php if ($fotoPerfil && file_exists(__DIR__ . '/../' . $fotoPerfil)): ?>
          <a href="<?= $usuarioAtual['perfil'] === 'tecnico' ? '/app-tecnicos/tecnico/perfil.php' : '#' ?>" style="display:flex; align-items:center; gap:8px; text-decoration:none; background:var(--branco); border:1px solid var(--cinza-100); border-radius:999px; padding:4px 14px 4px 4px; box-shadow:var(--sombra); font-size:13px; color:var(--cinza-900);">
            <img src="/app-tecnicos/<?= htmlspecialchars($fotoPerfil) ?>" class="avatar-foto" style="width:26px;height:26px;" alt="">
            <span><?= htmlspecialchars($usuarioAtual['nome']) ?></span>
          </a>
        <?php else: ?>
          <div class="usuario-chip">
            <span class="avatar"><?= htmlspecialchars($iniciais) ?></span>
            <span><?= htmlspecialchars($usuarioAtual['nome']) ?></span>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div id="painelNotificacoes" style="display:none; position:fixed; top:70px; right:24px; width:340px; background:var(--branco); border-radius:var(--raio); box-shadow:0 8px 32px rgba(0,0,0,.2); z-index:500; max-height:400px; overflow-y:auto;">
      <div style="padding:12px 16px; border-bottom:1px solid var(--cinza-100); font-weight:700; font-size:13.5px;">Notificacoes</div>
      <div id="listaNotificacoes" style="padding:8px;"></div>
    </div>
    <script>window.APP_JWT = <?= json_encode($usuarioAtual['jwt']) ?>;</script>
