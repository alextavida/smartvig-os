/**
 * Fila offline — armazena ações do técnico quando sem conexão e as processa
 * automaticamente ao voltar online.
 */
import {useState, useEffect, useCallback, useRef} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import {iniciarOs, pausarOs, encerrarOs, salvarDescricao, adicionarProduto} from '../api/os';

const QUEUE_KEY = 'smartvig_offline_queue';

export interface AcaoPendente {
  id: string;
  tipo: 'iniciar' | 'pausar' | 'encerrar' | 'salvar_descricao' | 'adicionar_produto';
  os_id: number;
  params: Record<string, any>;
  tentativas: number;
  criadoEm: number;
}

async function lerFila(): Promise<AcaoPendente[]> {
  const raw = await AsyncStorage.getItem(QUEUE_KEY);
  return raw ? (JSON.parse(raw) as AcaoPendente[]) : [];
}

async function salvarFila(fila: AcaoPendente[]): Promise<void> {
  await AsyncStorage.setItem(QUEUE_KEY, JSON.stringify(fila));
}

async function executarAcao(acao: AcaoPendente): Promise<void> {
  const {tipo, os_id, params} = acao;
  switch (tipo) {
    case 'iniciar':          return iniciarOs(os_id);
    case 'pausar':           return pausarOs(os_id, params.motivo ?? '');
    case 'encerrar':         return encerrarOs(os_id, params.laudo_final ?? '');
    case 'salvar_descricao': return salvarDescricao(os_id, params.observacoes ?? '');
    case 'adicionar_produto':
      return adicionarProduto(os_id, {
        nome: params.nome,
        quantidade: params.quantidade,
        valor_venda: params.valor_venda,
      });
  }
}

export function useOfflineQueue() {
  const [pendentes, setPendentes] = useState<AcaoPendente[]>([]);
  const processando = useRef(false);

  // Carrega fila inicial
  useEffect(() => {
    lerFila().then(setPendentes);
  }, []);

  // Processa fila quando voltar online
  useEffect(() => {
    const unsub = NetInfo.addEventListener(async state => {
      if (!state.isConnected || processando.current) { return; }
      const fila = await lerFila();
      if (fila.length === 0) { return; }

      processando.current = true;
      let filaAtual = [...fila];

      for (const acao of fila) {
        try {
          await executarAcao(acao);
          filaAtual = filaAtual.filter(a => a.id !== acao.id);
          await salvarFila(filaAtual);
          setPendentes([...filaAtual]);
        } catch {
          // Incrementa tentativas; remove se > 3
          filaAtual = filaAtual.map(a =>
            a.id === acao.id ? {...a, tentativas: a.tentativas + 1} : a,
          ).filter(a => a.tentativas <= 3);
          await salvarFila(filaAtual);
        }
      }

      processando.current = false;
    });

    return () => unsub();
  }, []);

  const enfileirar = useCallback(async (acao: Omit<AcaoPendente, 'id' | 'tentativas' | 'criadoEm'>) => {
    const nova: AcaoPendente = {
      ...acao,
      id: `${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
      tentativas: 0,
      criadoEm: Date.now(),
    };
    const fila = await lerFila();
    const novaFila = [...fila, nova];
    await salvarFila(novaFila);
    setPendentes(novaFila);
    return nova.id;
  }, []);

  const removerDaFila = useCallback(async (id: string) => {
    const fila = await lerFila();
    const nova = fila.filter(a => a.id !== id);
    await salvarFila(nova);
    setPendentes(nova);
  }, []);

  return {pendentes, enfileirar, removerDaFila};
}
