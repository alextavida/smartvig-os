import {apiPost} from './client';

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
