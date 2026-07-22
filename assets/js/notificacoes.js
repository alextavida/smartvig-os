/**
 * Substitui o push (FCM): busca notificacoes nao lidas via polling
 * a cada 20 segundos e atualiza o sininho no topo do painel.
 */

(function () {
  const sino = document.getElementById('sinoNotificacoes');
  const contador = document.getElementById('contadorNotificacoes');
  if (!sino || !contador) return;

  async function atualizar() {
    try {
      const dados = await apiGet('/notificacoes/listar.php?somente_nao_lidas=1');
      if (dados.nao_lidas > 0) {
        contador.style.display = 'flex';
        contador.textContent = dados.nao_lidas > 99 ? '99+' : dados.nao_lidas;
      } else {
        contador.style.display = 'none';
      }
    } catch (e) {
      // Silencioso: falha de rede pontual nao deve incomodar o usuario.
    }
  }

  sino.addEventListener('click', async () => {
    try {
      const dados = await apiGet('/notificacoes/listar.php');
      if (!dados.notificacoes.length) {
        alert('Nenhuma notificacao ainda.');
        return;
      }
      const linhas = dados.notificacoes
        .slice(0, 10)
        .map((n) => `${n.lida ? '' : '🔵 '}${n.titulo}: ${n.mensagem}`)
        .join('\n');
      alert(linhas);
      await apiPost('/notificacoes/marcar_lida.php', { todas: true });
      atualizar();
    } catch (e) {
      alert('Erro ao carregar notificacoes: ' + e.message);
    }
  });

  atualizar();
  setInterval(atualizar, 20000);
})();
