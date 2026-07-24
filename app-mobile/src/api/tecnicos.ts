import {apiGet} from './client';
import {TecnicoLista} from '../types';

export async function listarTecnicos(): Promise<TecnicoLista[]> {
  const resultado = await apiGet<{tecnicos: TecnicoLista[]}>('/tecnicos/listar.php');
  return resultado.tecnicos ?? [];
}
