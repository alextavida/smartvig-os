/**
 * Cliente HTTP base para a API SmartVig.
 * Adiciona automaticamente o header Authorization: Bearer {jwt}.
 * Aceita certificados auto-assinados (necessário para XAMPP local).
 */

import {obterJwt} from '../storage';
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

async function requisitar<T>(
  caminho: string,
  opcoes: RequestInit = {},
): Promise<T> {
  const jwt = await obterJwt();

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(opcoes.headers as Record<string, string>),
  };

  if (jwt) {
    headers['Authorization'] = `Bearer ${jwt}`;
  }

  const url = `${API_BASE_URL}${caminho}`;

  const resposta = await fetch(url, {
    ...opcoes,
    headers,
  });

  let dados: any;
  try {
    dados = await resposta.json();
  } catch {
    throw new ApiError('Resposta inválida do servidor.', resposta.status);
  }

  if (!resposta.ok || dados?.sucesso === false) {
    throw new ApiError(
      dados?.mensagem || `Erro HTTP ${resposta.status}`,
      resposta.status,
    );
  }

  return (dados?.dados ?? dados) as T;
}

export async function apiGet<T>(caminho: string): Promise<T> {
  return requisitar<T>(caminho, {method: 'GET'});
}

export async function apiPost<T>(caminho: string, corpo: object): Promise<T> {
  return requisitar<T>(caminho, {
    method: 'POST',
    body: JSON.stringify(corpo),
  });
}

export async function apiUpload<T>(
  caminho: string,
  formData: FormData,
): Promise<T> {
  const jwt = await obterJwt();
  const url = `${API_BASE_URL}${caminho}`;

  const headers: Record<string, string> = {};
  if (jwt) {
    headers['Authorization'] = `Bearer ${jwt}`;
  }
  // Não definir Content-Type — o fetch define automaticamente com boundary para multipart

  const resposta = await fetch(url, {
    method: 'POST',
    headers,
    body: formData,
  });

  let dados: any;
  try {
    dados = await resposta.json();
  } catch {
    throw new ApiError('Resposta inválida do servidor.', resposta.status);
  }

  if (!resposta.ok || dados?.sucesso === false) {
    throw new ApiError(
      dados?.mensagem || `Erro HTTP ${resposta.status}`,
      resposta.status,
    );
  }

  return (dados?.dados ?? dados) as T;
}
