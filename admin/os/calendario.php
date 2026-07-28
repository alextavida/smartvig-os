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
      <span class="legenda-item"><span class="legenda-dot" style="background:#64748b;"></span> Aberto</span>
      <span class="legenda-item"><span class="legenda-dot" style="background:#1d4ed8;"></span> Em andamento</span>
      <span class="legenda-item"><span class="legenda-dot" style="background:#d97706;"></span> Pausado</span>
      <span class="legenda-item"><span class="legenda-dot" style="background:#16803c;"></span> Concluído</span>
      <span class="legenda-item"><span class="legenda-dot" style="background:#f59e0b;"></span> Prazo</span>
      <span class="legenda-item"><span class="legenda-dot" style="background:#dc2626;"></span> Atrasado</span>
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
  font-size:13px; font-weight:600; box-shadow:0 4px 16px rgba(0,0,0,.2);">
  ✓ OS reagendada com sucesso!
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
    document.getElementById('modal-corpo').innerHTML = `
      <div><b>Status:</b> ${rotuloStatus(ep.status)}</div>
      <div><b>Tipo:</b> ${ep.tipo === 'prazo' ? 'Prazo/SLA' : 'Agendamento'}</div>
      ${ep.tecnico ? `<div><b>Técnico:</b> ${escHtml(ep.tecnico)}</div>` : ''}
      ${ep.prioridade === 'urgente' ? '<div style="color:#dc2626;font-weight:700;">URGENTE</div>' : ''}
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
    t.style.display = 'block';
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
