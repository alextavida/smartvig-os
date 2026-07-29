<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>SmartVig – Acompanhe sua OS</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; color: #1a202c; min-height: 100vh; }
  .topo { background: linear-gradient(135deg, #1462b0 0%, #0c447c 100%); color: #fff; padding: 24px 20px 32px; text-align: center; }
  .topo-logo { font-size: 22px; font-weight: 800; letter-spacing: 0.5px; }
  .topo-sub  { font-size: 13px; opacity: 0.75; margin-top: 4px; }
  .conteudo  { max-width: 520px; margin: -20px auto 0; padding: 0 16px 40px; }
  .card      { background: #fff; border-radius: 14px; padding: 20px; margin-bottom: 14px; box-shadow: 0 2px 10px rgba(16,40,74,.08); }
  .secao     { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7789; margin-bottom: 12px; }
  .cliente-nome { font-size: 20px; font-weight: 800; color: #1a202c; }
  .os-id     { font-size: 13px; color: #6b7789; margin-top: 4px; }
  .status    { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; font-size: 13px; font-weight: 700; margin-top: 12px; }
  .s-aberto       { background: #e8f1fc; color: #1462b0; }
  .s-em_andamento { background: #fdf3d9; color: #8a5e00; }
  .s-pausado      { background: #f0f2f5; color: #4a5568; }
  .s-reagendado   { background: #fbe7d6; color: #c8641a; }
  .s-concluido    { background: #e5f5ec; color: #1e8e5a; }
  .s-cancelado    { background: #fbe4e4; color: #c62f2f; }
  /* Progresso */
  .progresso  { display: flex; align-items: center; gap: 0; margin: 16px 0 0; }
  .prog-item  { display: flex; flex-direction: column; align-items: center; flex: 1; }
  .prog-circulo { width: 28px; height: 28px; border-radius: 50%; border: 2px solid #d0d8e4; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #d0d8e4; flex-shrink: 0; }
  .prog-circulo.ativo   { background: #1462b0; border-color: #1462b0; color: #fff; }
  .prog-circulo.feito   { background: #1e8e5a; border-color: #1e8e5a; color: #fff; }
  .prog-circulo.cancel  { background: #c62f2f; border-color: #c62f2f; color: #fff; }
  .prog-linha { flex: 1; height: 2px; background: #d0d8e4; margin: 0 -1px; position: relative; top: -14px; }
  .prog-linha.feita { background: #1e8e5a; }
  .prog-label { font-size: 10px; color: #6b7789; margin-top: 4px; text-align: center; }
  .prog-label.ativa { color: #1462b0; font-weight: 700; }
  /* Timeline */
  .timeline  { list-style: none; padding-left: 16px; border-left: 2px solid #e2e8f0; }
  .tl-item   { position: relative; padding: 0 0 16px 20px; }
  .tl-item::before { content: ''; position: absolute; left: -7px; top: 4px; width: 12px; height: 12px; border-radius: 50%; background: #1462b0; border: 2px solid #fff; box-shadow: 0 0 0 2px #1462b0; }
  .tl-acao   { font-weight: 600; font-size: 14px; color: #1a202c; }
  .tl-data   { font-size: 12px; color: #8b9ab0; margin-top: 2px; }
  /* NPS */
  .nps-box   { text-align: center; }
  .nps-pergunta { font-size: 15px; font-weight: 600; color: #1a202c; margin-bottom: 16px; }
  .nps-notas { display: flex; gap: 6px; justify-content: center; flex-wrap: wrap; }
  .nps-nota  { width: 38px; height: 38px; border-radius: 8px; border: 1px solid #d0d8e4; background: #f7f8fa; cursor: pointer; font-size: 14px; font-weight: 700; color: #4a5568; transition: all .15s; }
  .nps-nota:hover { background: #1462b0; color: #fff; border-color: #1462b0; }
  .nps-nota.selecionada { background: #1462b0; color: #fff; border-color: #1462b0; }
  .nps-legendas { display: flex; justify-content: space-between; font-size: 11px; color: #8b9ab0; margin-top: 8px; }
  .nps-comentario { width: 100%; border: 1px solid #d0d8e4; border-radius: 8px; padding: 10px 12px; font-size: 14px; margin-top: 12px; min-height: 80px; resize: none; }
  .btn-enviar { width: 100%; background: #1462b0; color: #fff; border: none; border-radius: 999px; padding: 13px; font-size: 15px; font-weight: 700; cursor: pointer; margin-top: 12px; }
  .btn-enviar:hover { background: #0c447c; }
  .nps-agradecimento { text-align: center; padding: 16px 0; }
  .nps-agradecimento h3 { font-size: 18px; color: #1e8e5a; }
  .nps-agradecimento p  { color: #6b7789; margin-top: 8px; font-size: 14px; }
  .erro-box  { text-align: center; padding: 40px 20px; }
  .erro-box h2 { color: #c62f2f; font-size: 18px; }
  .erro-box p  { color: #6b7789; margin-top: 8px; }
  .carregando { text-align: center; padding: 60px 20px; color: #6b7789; }
  .spinner   { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #1462b0; border-radius: 50%; animation: spin .8s linear infinite; margin: 0 auto 16px; }
  @keyframes spin { to { transform: rotate(360deg); } }
  @media (max-width: 400px) { .nps-nota { width: 32px; height: 32px; font-size: 12px; } }
</style>
</head>
<body>

<div class="topo">
  <div class="topo-logo">SmartVig</div>
  <div class="topo-sub">Acompanhamento de Ordem de Serviço</div>
</div>

<div class="conteudo" id="app">
  <div class="carregando"><div class="spinner"></div>Carregando...</div>
</div>

<script>
(function () {
  const token = new URLSearchParams(location.search).get('token') || '';
  const npsToken = new URLSearchParams(location.search).get('nps') || '';
  const app = document.getElementById('app');

  const API = '<?php echo rtrim(dirname(dirname($_SERVER['REQUEST_URI'])), '/'); ?>/api';

  const LABELS = {
    aberto: 'Aguardando', em_andamento: 'Em atendimento',
    pausado: 'Pausado', reagendado: 'Reagendado',
    concluido: 'Concluído', cancelado: 'Cancelado',
  };

  function fmt(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleString('pt-BR', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
  }

  function renderErro(msg) {
    app.innerHTML = `<div class="erro-box"><h2>Ops!</h2><p>${msg}</p></div>`;
  }

  async function carregarStatus() {
    if (!token) { renderErro('Link inválido. Verifique o link recebido.'); return; }
    try {
      const r = await fetch(`${API}/portal/status.php?token=${encodeURIComponent(token)}`);
      const j = await r.json();
      if (!j.sucesso) { renderErro(j.erro || 'Link inválido ou expirado.'); return; }
      renderStatus(j.dados);
    } catch { renderErro('Não foi possível carregar. Tente novamente.'); }
  }

  function renderStatus(d) {
    const situacao = d.situacao || 'aberto';
    const prog = d.progresso ?? 0;
    const hist = d.historico || [];
    const npsUrl = npsToken ? `?token=${encodeURIComponent(token)}&nps=${encodeURIComponent(npsToken)}` : '';

    const etapas = [
      { label: 'Aberta', prog: 0 },
      { label: 'Em atendimento', prog: 1 },
      { label: 'Concluída', prog: 2 },
    ];

    const barras = etapas.map((e, i) => {
      const cirClasse = situacao === 'cancelado' ? 'cancel'
        : prog > e.prog ? 'feita'
        : prog === e.prog ? 'ativo' : '';
      const lnClasse = prog > e.prog ? 'feita' : '';
      const lbClasse = prog === e.prog && situacao !== 'cancelado' ? 'ativa' : '';
      const icon = cirClasse === 'feita' ? '✓' : cirClasse === 'cancel' && i === 0 ? '✗' : (i + 1);
      const linha = i < etapas.length - 1
        ? `<div class="prog-linha ${lnClasse}"></div>` : '';
      return `
        <div class="prog-item">
          <div class="prog-circulo ${cirClasse}">${icon}</div>
          <div class="prog-label ${lbClasse}">${e.label}</div>
        </div>
        ${linha}`;
    }).join('');

    const linhasHist = hist.map(h =>
      `<li class="tl-item"><div class="tl-acao">${h.descricao}</div><div class="tl-data">${fmt(h.quando)}</div></li>`
    ).join('');

    const npsSection = npsToken ? `
      <div class="card" id="nps-card">
        <div class="secao">Avalie o atendimento</div>
        <div class="nps-box">
          <p class="nps-pergunta">De 0 a 10, qual a chance de recomendar nosso serviço?</p>
          <div class="nps-notas" id="notas">
            ${Array.from({length: 11}, (_, i) => `<button class="nps-nota" data-v="${i}">${i}</button>`).join('')}
          </div>
          <div class="nps-legendas"><span>Muito improvável</span><span>Muito provável</span></div>
          <textarea class="nps-comentario" id="comentario" placeholder="Comentário opcional..."></textarea>
          <button class="btn-enviar" id="btn-nps">Enviar avaliação</button>
        </div>
      </div>` : '';

    const data = d.data_agendamento
      ? `<div style="font-size:13px;color:#6b7789;margin-top:6px;">Data: ${new Date(d.data_agendamento+'T12:00:00').toLocaleDateString('pt-BR')}</div>` : '';
    const tecnico = d.tecnico_nome
      ? `<div style="font-size:13px;color:#6b7789;margin-top:4px;">Técnico: ${d.tecnico_nome}</div>` : '';

    app.innerHTML = `
      <div class="card">
        <div class="cliente-nome">${d.cliente_nome_curto || 'Cliente'}</div>
        <div class="os-id">Ordem de Serviço</div>
        <div class="status s-${situacao}">${LABELS[situacao] || situacao}</div>
        ${data}${tecnico}
        <div class="progresso">${barras}</div>
      </div>

      ${hist.length ? `
      <div class="card">
        <div class="secao">Histórico do atendimento</div>
        <ul class="timeline">${linhasHist}</ul>
      </div>` : ''}

      ${npsSection}
    `;

    // NPS interatividade
    if (npsToken) {
      let notaSel = null;
      document.querySelectorAll('.nps-nota').forEach(btn => {
        btn.addEventListener('click', () => {
          notaSel = parseInt(btn.dataset.v);
          document.querySelectorAll('.nps-nota').forEach(b => b.classList.remove('selecionada'));
          btn.classList.add('selecionada');
        });
      });
      document.getElementById('btn-nps').addEventListener('click', async () => {
        if (notaSel === null) { alert('Selecione uma nota de 0 a 10.'); return; }
        const comentario = document.getElementById('comentario').value;
        try {
          const r = await fetch(`${API}/portal/nps.php`, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({token: npsToken, nota: notaSel, comentario}),
          });
          const j = await r.json();
          if (j.sucesso) {
            document.getElementById('nps-card').innerHTML = `
              <div class="nps-agradecimento">
                <h3>Obrigado pela avaliação!</h3>
                <p>Sua opinião é muito importante para melhorarmos nosso serviço.</p>
              </div>`;
          } else { alert(j.erro || 'Erro ao enviar avaliação.'); }
        } catch { alert('Erro ao enviar. Tente novamente.'); }
      });
    }
  }

  carregarStatus();
})();
</script>
</body>
</html>
