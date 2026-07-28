<?php
/**
 * Calendario de OS — FullCalendar v6 com drag-and-drop para reagendar.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor', 'supervisor'];
$tituloPagina = 'Calendário de OS';
$paginaAtiva  = 'calendario';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = obterConexao();
$tecnicos = $pdo->query("SELECT id, nome FROM usuarios WHERE perfil = 'tecnico' AND ativo = 1 ORDER BY nome")->fetchAll();
$isGestor = $usuarioAtual['perfil'] === 'gestor';
?>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<style>
#calendario { background:#fff; border-radius:12px; padding:16px; }
.fc-event { cursor:pointer; font-size:12px; }
.fc-daygrid-event { border-radius:6px; }
.fc .fc-button { background:var(--azul-700,#1d4ed8); border-color:var(--azul-700,#1d4ed8); }
.fc .fc-button:hover { background:var(--azul-800,#1e40af); border-color:var(--azul-800,#1e40af); }
.fc .fc-button-active { background:var(--azul-900,#1e3a8a)!important; }
.legenda { display:flex; gap:12px; flex-wrap:wrap; font-size:12px; }
.legenda-item { display:flex; align-items:center; gap:5px; }
.legenda-dot { width:10px; height:10px; border-radius:2px; }
#modal-evento {
  display:none; position:fixed; inset:0; z-index:999;
  background:rgba(0,0,0,.45); align-items:center; justify-content:center;
}
#modal-evento.aberto { display:flex; }
.modal-caixa {
  background:#fff; border-radius:14px; padding:24px;
  max-width:420px; width:90%; box-shadow:0 16px 48px rgba(0,0,0,.25);
}
</style>

<!-- Filtros -->
<div class="card" style="margin-bottom:14px; padding:12px 16px;">
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <label style="font-size:13px;font-weight:600;color:#475569;">Técnico:</label>
    <select id="filtro-tecnico" class="form-control" style="width:auto;min-width:180px;">
      <option value="">Todos</option>
      <?php foreach ($tecnicos as $t): ?>
        <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option>
      <?php endforeach; ?>
    </select>

    <div class="legenda" style="margin-left:auto;">
      <span class="legenda-item"><span class="legenda-dot" style="background:#64748b;border-radius:3px;"></span> Aberto</span>
      <span class="legenda-item"><span class="legenda-dot" style="background:#1d4ed8;border-radius:3px;"></span> Em andamento</span>
      <span class="legenda-item"><span class="legenda-dot" style="background:#d97706;border-radius:3px;"></span> Pausado</span>
      <span class="legenda-item"><span class="legenda-dot" style="background:#16803c;border-radius:3px;"></span> Concluído</span>
      <span class="legenda-item">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Prazo
      </span>
      <span class="legenda-item">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Atrasado
      </span>
    </div>
  </div>
</div>

<div class="card" style="padding:16px;">
  <div id="calendario"></div>
</div>

<!-- Modal de evento -->
<div id="modal-evento">
  <div class="modal-caixa">
    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:14px;">
      <h3 style="margin:0;font-size:15px;" id="modal-titulo">—</h3>
      <button onclick="fecharModal()" style="background:none;border:none;cursor:pointer;font-size:20px;color:#64748b;">×</button>
    </div>
    <div id="modal-corpo" style="font-size:13px;color:#475569;line-height:1.7;"></div>
    <div style="margin-top:16px;display:flex;gap:8px;">
      <a id="modal-link" href="#" class="btn btn-primario btn-sm">Abrir OS</a>
      <button onclick="fecharModal()" class="btn btn-neutro btn-sm">Fechar</button>
    </div>
  </div>
</div>

<div id="toast-reagendado" style="
  display:none; position:fixed; bottom:24px; right:24px; z-index:9999;
  background:#16803c; color:#fff; border-radius:8px; padding:12px 18px;
  font-size:13px; font-weight:600; box-shadow:0 4px 16px rgba(0,0,0,.2);
  align-items:center; gap:8px;">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
    <polyline points="20 6 9 17 4 12"/>
  </svg>
  OS reagendada com sucesso!
</div>

<script>
(function () {
  const ehGestor = <?= $isGestor ? 'true' : 'false' ?>;
  let calendario;

  function carregarEventos(info, ok, erro) {
    const tecId = document.getElementById('filtro-tecnico').value;
    const url = '/app-tecnicos/api/os/calendario.php'
      + '?inicio=' + info.startStr.slice(0, 10)
      + '&fim='    + info.endStr.slice(0, 10)
      + (tecId ? '&tecnico_id=' + tecId : '');

    fetch(url, {headers: {'Authorization': 'Bearer ' + window.APP_JWT}})
      .then(r => r.json())
      .then(ok)
      .catch(erro);
  }

  function abrirModal(info) {
    const ev = info.event;
    const ep = ev.extendedProps;
    document.getElementById('modal-titulo').textContent = ev.title;
    const icoCal   = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>`;
    const icoPess  = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`;
    const icoClock = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`;
    document.getElementById('modal-corpo').innerHTML = `
      <div style="display:flex;flex-direction:column;gap:6px;font-size:13px;color:#475569;">
        <div style="display:flex;align-items:center;gap:6px;">
          <span class="badge ${ep.status}" style="font-size:11px;">${rotuloStatus(ep.status)}</span>
          ${ep.tipo === 'prazo'
            ? `<span style="display:inline-flex;align-items:center;gap:4px;background:#fef3c7;color:#92400e;border-radius:6px;padding:2px 8px;font-size:11px;font-weight:700;">${icoClock} Prazo/SLA</span>`
            : `<span style="display:inline-flex;align-items:center;gap:4px;background:#f1f5f9;color:#475569;border-radius:6px;padding:2px 8px;font-size:11px;">${icoCal} Agendamento</span>`}
        </div>
        ${ep.tecnico ? `<div style="display:flex;align-items:center;gap:5px;">${icoPess} ${escHtml(ep.tecnico)}</div>` : ''}
        ${ep.prioridade === 'urgente' ? `<span style="display:inline-flex;align-items:center;gap:5px;background:#fef2f2;color:#dc2626;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:700;align-self:start;">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          Urgente</span>` : ''}
      </div>
    `;
    document.getElementById('modal-link').href = '/app-tecnicos/admin/os/detalhe.php?id=' + ep.os_id;
    document.getElementById('modal-evento').classList.add('aberto');
  }

  async function aoArrastar(info) {
    if (!ehGestor) { info.revert(); return; }
    const ep = info.event.extendedProps;
    if (ep.tipo === 'prazo') { info.revert(); return; } // Nao reagenda prazo por drag
    try {
      const r = await fetch('/app-tecnicos/api/os/atualizar.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'Authorization': 'Bearer ' + window.APP_JWT},
        body: JSON.stringify({id: ep.os_id, data_agendamento: info.event.startStr.slice(0, 10)}),
      });
      const dados = await r.json();
      if (!dados.sucesso) throw new Error(dados.erro || 'Erro');
      mostrarToast();
    } catch (e) {
      alert('Falha ao reagendar: ' + e.message);
      info.revert();
    }
  }

  function mostrarToast() {
    const t = document.getElementById('toast-reagendado');
    t.style.display = 'flex';
    setTimeout(() => { t.style.display = 'none'; }, 3000);
  }

  function rotuloStatus(s) {
    return ({aberto:'Aberto',em_andamento:'Em andamento',pausado:'Pausado',
             reagendado:'Reagendado',concluido:'Concluído',cancelado:'Cancelado'})[s] || s;
  }

  function escHtml(s) {
    return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  window.fecharModal = () => document.getElementById('modal-evento').classList.remove('aberto');
  document.getElementById('modal-evento').addEventListener('click', e => {
    if (e.target === e.currentTarget) fecharModal();
  });

  document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('calendario');
    calendario = new FullCalendar.Calendar(el, {
      locale: 'pt-br',
      initialView: 'dayGridMonth',
      headerToolbar: {
        left:   'prev,next today',
        center: 'title',
        right:  'dayGridMonth,timeGridWeek,listWeek',
      },
      editable: ehGestor,
      droppable: false,
      events: carregarEventos,
      eventClick: abrirModal,
      eventDrop: aoArrastar,
      eventTimeFormat: {hour: '2-digit', minute: '2-digit', hour12: false},
      buttonText: {today: 'Hoje', month: 'Mês', week: 'Semana', list: 'Lista'},
      noEventsText: 'Nenhuma OS neste período.',
      height: 'auto',
    });
    calendario.render();

    document.getElementById('filtro-tecnico').addEventListener('change', () => {
      calendario.refetchEvents();
    });
  });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
