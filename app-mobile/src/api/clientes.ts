import {apiGet} from './client';
import {ClienteGC} from '../types';

export async function buscarClientes(busca: string): Promise<ClienteGC[]> {
  if (busca.length < 2) {return [];}
  const resultado = await apiGet<{clientes: ClienteGC[]}>(
    `/clientes/buscar.php?busca=${encodeURIComponent(busca)}`,
  );
  return resultado.clientes ?? [];
}
