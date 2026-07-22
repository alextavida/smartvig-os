import {apiPost} from './client';

export async function atualizarGps(
  osId: number,
  latitude: number,
  longitude: number,
): Promise<void> {
  await apiPost('/gps/atualizar.php', {
    os_id: osId,
    latitude,
    longitude,
  });
}
