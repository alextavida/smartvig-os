import {useState, useEffect, useRef, useCallback} from 'react';
import {listarNotificacoes} from '../api/notificacoes';

const INTERVALO_MS = 30_000; // 30 segundos

export function useNotificacoes(ativo: boolean) {
  const [naoLidas, setNaoLidas] = useState(0);
  const timer = useRef<ReturnType<typeof setInterval> | null>(null);

  const verificar = useCallback(async () => {
    try {
      const dados = await listarNotificacoes(true);
      setNaoLidas(dados.total_nao_lidas ?? 0);
    } catch {
      // silencioso — sem conexão não quebra o app
    }
  }, []);

  useEffect(() => {
    if (!ativo) {return;}

    verificar();
    timer.current = setInterval(verificar, INTERVALO_MS);

    return () => {
      if (timer.current) {clearInterval(timer.current);}
    };
  }, [ativo, verificar]);

  return {naoLidas, verificar};
}
