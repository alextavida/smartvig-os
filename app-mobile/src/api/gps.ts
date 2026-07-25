import {apiGet, apiPost} from './client';
import {GpsTecnico} from '../types';

export async function atualizarGps(
  latitude: number,
  longitude: number,
  osId?: number,
): Promise<void> {
  await apiPost('/gps/atualizar.php', {
    latitude,
    longitude,
    ...(osId != null ? {os_id: osId} : {}),
  });
}

export async function listarGps(): Promise<GpsTecnico[]> {
  const dados = await apiGet('/gps/listar.php');
  return (dados?.posicoes ?? []) as GpsTecnico[];
}
