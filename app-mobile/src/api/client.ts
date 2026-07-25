/**
 * Cliente HTTP base para a API SmartVig.
 * — Authorization: Bearer {jwt} em todas as chamadas autenticadas.
 * — Em qualquer 401 limpa a sessao e emite 'smartvig:sessao_expirada'
 *   para o AuthProvider redirecionar ao login automaticamente.
 */

import {DeviceEventEmitter} from 'react-native';
import {obterJwt, limparSessao} from '../storage';
import {API_BASE_URL} from '../config';

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode: number,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

async function tratar401(): Promise<void> {
  await limparSessao();
  DeviceEventEmitter.emit('smartvig:sessao_expirada');
}

async function requisitar<T>(
  caminho: string,
  opcoes: RequestInit = {},
): Promise<T> {
  const jwt = await obterJwt();

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(opcoes.headers as Record<string, string>),
  };
  if (jwt) { headers['Authorization'] = `Bearer ${jwt}`; }

  const url = `${API_BASE_URL}${caminho}`;
  const resposta = await fetch(url, {...opcoes, headers});

  let dados: any;
  try { dados = await resposta.json(); } catch {
    throw new ApiError('Resposta inválida do servidor.', resposta.status);
  }

  if (resposta.status === 401) {
    await tratar401();
    throw new ApiError(dados?.erro || 'Sessão expirada. Faça login novamente.', 401);
  }

  if (!resposta.ok || dados?.sucesso === false) {
    throw new ApiError(
      dados?.erro || dados?.mensagem || `Erro HTTP ${resposta.status}`,
      resposta.status,
    );
  }

  return (dados?.dados ?? dados) as T;
}

export async function apiGet<T>(caminho: string): Promise<T> {
  return requisitar<T>(caminho, {method: 'GET'});
}

export async function apiPost<T>(caminho: string, corpo: object): Promise<T> {
  return requisitar<T>(caminho, {method: 'POST', body: JSON.stringify(corpo)});
}

export async function apiUpload<T>(caminho: string, formData: FormData): Promise<T> {
  const jwt = await obterJwt();
  const url = `${API_BASE_URL}${caminho}`;

  const headers: Record<string, string> = {};
  if (jwt) { headers['Authorization'] = `Bearer ${jwt}`; }

  const resposta = await fetch(url, {method: 'POST', headers, body: formData});

  let dados: any;
  try { dados = await resposta.json(); } catch {
    throw new ApiError('Resposta inválida do servidor.', resposta.status);
  }

  if (resposta.status === 401) {
    await tratar401();
    throw new ApiError(dados?.erro || 'Sessão expirada. Faça login novamente.', 401);
  }

  if (!resposta.ok || dados?.sucesso === false) {
    throw new ApiError(
      dados?.erro || dados?.mensagem || `Erro HTTP ${resposta.status}`,
      resposta.status,
    );
  }

  return (dados?.dados ?? dados) as T;
}
