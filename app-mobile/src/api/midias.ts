import {apiUpload, apiPost} from './client';
import {API_BASE_URL} from '../config';

export async function uploadMidia(
  osId: number,
  tipo: 'foto' | 'video',
  arquivo: {uri: string; name: string; type: string},
): Promise<void> {
  const fd = new FormData();
  fd.append('os_id', String(osId));
  fd.append('tipo', tipo);
  fd.append('arquivo', arquivo as any);
  await apiUpload('/midias/upload.php', fd);
}

export async function uploadFotoPerfil(
  arquivo: {uri: string; name: string; type: string},
): Promise<string> {
  const fd = new FormData();
  fd.append('arquivo', arquivo as any);
  const dados = await apiUpload<{url: string}>('/tecnicos/upload_foto.php', fd);
  return dados.url;
}

/** Envia imagem em base64 (ex: assinatura digital) como mídia da OS */
export async function uploadBase64Midia(
  osId: number,
  tipo: 'foto' | 'video',
  base64: string,
  nomeArquivo: string = 'assinatura.png',
): Promise<void> {
  await apiPost('/midias/upload_base64.php', {os_id: osId, tipo, base64, nome_arquivo: nomeArquivo});
}

/** Constrói URL pública de mídia a partir do caminho relativo */
export function urlMidia(caminhoRelativo: string): string {
  // Remove a parte /api da URL base para chegar na raiz do app
  const base = API_BASE_URL.replace('/api', '');
  return `${base}/${caminhoRelativo}`;
}
