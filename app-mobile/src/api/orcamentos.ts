import {apiGet, apiPost} from './client';
import {Orcamento, OrcamentoDetalhe} from '../types';

export interface OrcamentoItemPayload {
  tipo: 'servico' | 'peca';
  descricao: string;
  quantidade: number;
  valor_unitario: number;
}

export interface NovoOrcamentoPayload {
  cliente_nome: string;
  cliente_email?: string;
  cliente_telefone?: string;
  gc_cliente_id?: number;
  observacoes?: string;
  validade_dias?: number;
  itens: OrcamentoItemPayload[];
}

interface ListarOrcamentosResposta {
  orcamentos: Orcamento[];
  total: number;
}

export async function listarOrcamentos(
  status?: string,
): Promise<ListarOrcamentosResposta> {
  const qs = status ? `?status=${encodeURIComponent(status)}` : '';
  return apiGet<ListarOrcamentosResposta>(`/orcamentos/listar.php${qs}`);
}

export async function visualizarOrcamento(id: number): Promise<OrcamentoDetalhe> {
  const resp = await apiGet<{orcamento: OrcamentoDetalhe}>(
    `/orcamentos/visualizar.php?id=${id}`,
  );
  return resp.orcamento;
}

export async function criarOrcamento(
  payload: NovoOrcamentoPayload,
): Promise<{id: number; codigo: string}> {
  return apiPost('/orcamentos/criar.php', payload);
}
