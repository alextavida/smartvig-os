import {apiGet, apiPost} from './client';
import {SolicitacaoCompra, SolicitacaoCompraDetalhe} from '../types';

export interface ListarComprasParams {
  status?: string;
  pagina?: number;
  minha?: boolean;
}

interface ListarComprasResposta {
  solicitacoes: SolicitacaoCompra[];
  paginacao: {pagina: number; total: number; total_paginas: number};
}

export async function listarCompras(params: ListarComprasParams = {}): Promise<ListarComprasResposta> {
  const partes: string[] = [];
  if (params.status) { partes.push(`status=${encodeURIComponent(params.status)}`); }
  if (params.pagina) { partes.push(`pagina=${params.pagina}`); }
  if (params.minha)  { partes.push('minha=1'); }
  const qs = partes.length ? '?' + partes.join('&') : '';
  return apiGet<ListarComprasResposta>(`/compras/listar.php${qs}`);
}

export async function visualizarCompra(id: number): Promise<SolicitacaoCompraDetalhe> {
  return apiGet<SolicitacaoCompraDetalhe>(`/compras/visualizar.php?id=${id}`);
}

export async function aprovarCompra(id: number, observacao?: string): Promise<void> {
  await apiPost('/compras/aprovar.php', {id, observacao: observacao ?? null});
}

export async function reprovarCompra(id: number, motivo: string): Promise<void> {
  await apiPost('/compras/reprovar.php', {id, motivo});
}

export async function devolverCompra(id: number, motivo: string): Promise<void> {
  await apiPost('/compras/devolver.php', {id, motivo});
}

export async function cancelarCompra(id: number, motivo?: string): Promise<void> {
  await apiPost('/compras/cancelar.php', {id, motivo: motivo ?? null});
}

export async function enviarParaAprovacao(id: number): Promise<void> {
  await apiPost('/compras/enviar_aprovacao.php', {id});
}

export interface ResumoCompras {
  total: number;
  aguardando_aprovacao: number;
  aprovadas: number;
  em_compra: number;
  valor_estimado_total: number | null;
}

export async function resumoCompras(): Promise<ResumoCompras> {
  return apiGet<ResumoCompras>('/compras/resumo.php');
}
