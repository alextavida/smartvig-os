/**
 * Configuração central do app.
 * IMPORTANTE: antes de gerar o APK, altere API_BASE_URL para o IP da sua máquina
 * na rede local (ex: https://192.168.1.100:4443/app-tecnicos/api).
 * O emulador usa 10.0.2.2 para acessar o localhost da máquina host.
 */

// IP do emulador → localhost da máquina
// Para dispositivo físico → use o IP LAN da sua máquina
export const API_BASE_URL = 'https://186.208.217.187:4443/app-tecnicos/api';

// Cores da marca SmartVig (espelho do app.css)
export const CORES = {
  azul900: '#0b3a6f',
  azul800: '#0f4c92',
  azul700: '#1462b0',
  azul600: '#1a73c7',
  azul500: '#2e8bdb',
  azul100: '#e8f1fc',
  azul50:  '#f4f9fe',
  cinza900: '#1c2430',
  cinza700: '#3c4859',
  cinza500: '#6b7789',
  cinza300: '#cbd3dd',
  cinza200: '#dde3eb',
  cinza100: '#eef1f5',
  branco: '#ffffff',
  verde: '#1e8e5a',
  verdeBg: '#e5f5ec',
  amarelo: '#b8860b',
  amareloBg: '#fdf3d9',
  laranja: '#c8641a',
  laranjaBg: '#fbe7d6',
  vermelho: '#c62f2f',
  vermelhoBg: '#fbe4e4',
};

export const STATUS_CORES: Record<string, {bg: string; text: string}> = {
  aberto:       {bg: '#e8f1fc', text: '#1462b0'},
  em_andamento: {bg: '#fdf3d9', text: '#b8860b'},
  pausado:      {bg: '#eceff2', text: '#5a6472'},
  reagendado:   {bg: '#fbe7d6', text: '#c8641a'},
  concluido:    {bg: '#e5f5ec', text: '#1e8e5a'},
  cancelado:    {bg: '#fbe4e4', text: '#c62f2f'},
};

export const PRIORIDADE_CORES: Record<string, {bg: string; text: string}> = {
  baixo:         {bg: '#e8f5e9', text: '#2e7d32'},
  intermediario: {bg: '#fff8e1', text: '#e65100'},
  urgente:       {bg: '#ffebee', text: '#b71c1c'},
};

export const STATUS_LABELS: Record<string, string> = {
  aberto: 'Aberto',
  em_andamento: 'Em andamento',
  pausado: 'Pausado',
  reagendado: 'Reagendado',
  concluido: 'Concluído',
  cancelado: 'Cancelado',
};
