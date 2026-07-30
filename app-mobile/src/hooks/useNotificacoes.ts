import {useState, useEffect, useRef, useCallback} from 'react';
import {Vibration} from 'react-native';
import {listarNotificacoes} from '../api/notificacoes';
import {exibirNotificacaoLocal} from '../services/pushNotifications';
import {Notificacao} from '../types';

const INTERVALO_MS = 30_000; // 30 segundos

function tipoParaDados(n: Notificacao): Record<string, string> | undefined {
  if (n.os_id) {
    return {tipo: 'os', os_id: String(n.os_id)};
  }
  return undefined;
}

export function useNotificacoes(ativo: boolean) {
  const [naoLidas, setNaoLidas]     = useState(0);
  const [novas, setNovas]           = useState<Notificacao[]>([]);
  const anteriorRef                 = useRef<number>(-1);
  const primeiraVerificacao         = useRef(true);
  const timer = useRef<ReturnType<typeof setInterval> | null>(null);

  const verificar = useCallback(async () => {
    try {
      const dados = await listarNotificacoes(true);
      const totalAtual = dados.total_nao_lidas ?? 0;
      const listaAtual = dados.notificacoes ?? [];

      // Notifica se chegaram novas (ignora primeira verificação para não spammar no login)
      if (!primeiraVerificacao.current && totalAtual > anteriorRef.current && anteriorRef.current >= 0) {
        const novasChegadas = listaAtual.filter(n => !n.lida);
        if (novasChegadas.length > 0) {
          const primeira = novasChegadas[0];
          Vibration.vibrate([0, 200, 100, 200]);
          exibirNotificacaoLocal(
            primeira.titulo ?? 'Nova notificação',
            primeira.mensagem ?? '',
            tipoParaDados(primeira),
          );
        }
        setNovas(novasChegadas);
      }

      anteriorRef.current         = totalAtual;
      primeiraVerificacao.current = false;
      setNaoLidas(totalAtual);
    } catch {
      // sem conexão não quebra o app
    }
  }, []);

  useEffect(() => {
    if (!ativo) { return; }

    verificar();
    timer.current = setInterval(verificar, INTERVALO_MS);

    return () => {
      if (timer.current) { clearInterval(timer.current); }
    };
  }, [ativo, verificar]);

  return {naoLidas, novas, verificar};
}
