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
      <?php if ($usuarioAtual['perfil'] === 'gestor'): ?>
        <a href="/app-tecnicos/admin/" class="<?= $paginaAtiva === 'dashboard' ? 'ativo' : '' ?>">
          <?= ic('dashboard') ?> Dashboard
        </a>
        <a href="/app-tecnicos/admin/os/lista.php" class="<?= $paginaAtiva === 'os_lista' ? 'ativo' : '' ?>">
          <?= ic('os_lista') ?> Ordens de Servico
        </a>
        <a href="/app-tecnicos/admin/os/criar.php" class="<?= $paginaAtiva === 'os_criar' ? 'ativo' : '' ?>">
          <?= ic('os_nova') ?> Nova OS
        </a>
        <a href="/app-tecnicos/admin/os/mapa.php" class="<?= $paginaAtiva === 'mapa' ? 'ativo' : '' ?>">
          <?= ic('mapa') ?> Mapa de Tecnicos
        </a>
        <a href="/app-tecnicos/admin/tecnicos/lista.php" class="<?= $paginaAtiva === 'tecnicos' ? 'ativo' : '' ?>">
          <?= ic('tecnicos') ?> Tecnicos
        </a>
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
