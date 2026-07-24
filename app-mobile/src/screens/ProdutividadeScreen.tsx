import React, {useState, useEffect, useCallback} from 'react';
import {
  View, Text, ScrollView, StyleSheet,
  ActivityIndicator, TouchableOpacity, RefreshControl,
} from 'react-native';
import {apiGet} from '../api/client';
import {CORES} from '../config';

interface Produtividade {
  total_os: number;
  concluidas_semana: number;
  concluidas_mes: number;
  tempo_medio_segundos: number | null;
  tempo_max_segundos: number | null;
  total_com_tempo: number;
  distribuicao: Record<string, number>;
  recentes_concluidas: Array<{id: number; cliente_nome: string; data_conclusao: string; tempo_atendimento_segundos: number | null}>;
  os_ativa: {id: number; cliente_nome: string; situacao_local: string} | null;
}

function formatarTempo(seg: number): string {
  const h = Math.floor(seg / 3600);
  const m = Math.floor((seg % 3600) / 60);
  return h > 0 ? `${h}h ${m}min` : `${m}min`;
}

const STATUS_ROTULOS: Record<string, string> = {
  aberto: 'Abertas', em_andamento: 'Em andamento', pausado: 'Pausadas',
  reagendado: 'Reagendadas', concluido: 'Concluídas', cancelado: 'Canceladas',
};
const STATUS_CORES: Record<string, string> = {
  aberto: CORES.azul600, em_andamento: CORES.amarelo, pausado: '#5a6472',
  reagendado: CORES.laranja, concluido: CORES.verde, cancelado: CORES.vermelho,
};

export function ProdutividadeScreen() {
  const [dados, setDados]       = useState<Produtividade | null>(null);
  const [carregando, setCarregando] = useState(true);
  const [atualizando, setAtualizando] = useState(false);
  const [erro, setErro]         = useState('');

  const carregar = useCallback(async (silencioso = false) => {
    if (!silencioso) { setCarregando(true); }
    setErro('');
    try {
      const result = await apiGet<Produtividade>('/tecnicos/produtividade.php');
      setDados(result);
    } catch (e: any) {
      setErro(e.message ?? 'Erro ao carregar dados.');
    } finally {
      setCarregando(false);
      setAtualizando(false);
    }
  }, []);

  useEffect(() => { carregar(); }, [carregar]);

  if (carregando) {
    return (
      <View style={s.centro}>
        <ActivityIndicator size="large" color={CORES.azul600} />
        <Text style={{color: CORES.cinza500, marginTop: 12}}>Carregando produtividade...</Text>
      </View>
    );
  }

  if (erro) {
    return (
      <View style={s.centro}>
        <Text style={{color: CORES.vermelho, textAlign: 'center', marginBottom: 16}}>{erro}</Text>
        <TouchableOpacity style={s.btnRetry} onPress={() => carregar()}>
          <Text style={{color: CORES.azul700, fontWeight: '700'}}>Tentar novamente</Text>
        </TouchableOpacity>
      </View>
    );
  }

  if (!dados) { return null; }

  const taxaConclusao = dados.total_os > 0
    ? Math.round(((dados.distribuicao.concluido ?? 0) / dados.total_os) * 100)
    : 0;

  return (
    <ScrollView
      style={s.container}
      contentContainerStyle={s.scroll}
      refreshControl={
        <RefreshControl
          refreshing={atualizando}
          onRefresh={() => { setAtualizando(true); carregar(true); }}
          colors={[CORES.azul600]}
        />
      }>

      {/* OS ativa agora */}
      {dados.os_ativa && (
        <View style={[s.card, {borderLeftWidth: 3, borderLeftColor: CORES.amarelo}]}>
          <Text style={[s.secaoTitulo, {color: CORES.amarelo}]}>⚡ Em andamento agora</Text>
          <Text style={s.clienteAtivo}>{dados.os_ativa.cliente_nome}</Text>
          <Text style={{color: CORES.cinza500, fontSize: 12}}>OS #{dados.os_ativa.id}</Text>
        </View>
      )}

      {/* Cards de destaque */}
      <View style={s.gridCards}>
        <View style={[s.statCard, {borderTopColor: CORES.azul600}]}>
          <Text style={[s.statNum, {color: CORES.azul600}]}>{dados.total_os}</Text>
          <Text style={s.statLabel}>Total de OS</Text>
        </View>
        <View style={[s.statCard, {borderTopColor: CORES.verde}]}>
          <Text style={[s.statNum, {color: CORES.verde}]}>{dados.concluidas_semana}</Text>
          <Text style={s.statLabel}>Esta semana</Text>
        </View>
        <View style={[s.statCard, {borderTopColor: CORES.azul700}]}>
          <Text style={[s.statNum, {color: CORES.azul700}]}>{dados.concluidas_mes}</Text>
          <Text style={s.statLabel}>Este mês</Text>
        </View>
        <View style={[s.statCard, {borderTopColor: '#7c3aed'}]}>
          <Text style={[s.statNum, {color: '#7c3aed'}]}>{taxaConclusao}%</Text>
          <Text style={s.statLabel}>Taxa conclusão</Text>
        </View>
      </View>

      {/* Tempo médio */}
      {dados.tempo_medio_segundos !== null && (
        <View style={s.card}>
          <Text style={s.secaoTitulo}>⏱ Tempo de atendimento</Text>
          <View style={{flexDirection: 'row', justifyContent: 'space-between', marginTop: 8}}>
            <View style={{alignItems: 'center', flex: 1}}>
              <Text style={s.tempoNum}>{formatarTempo(dados.tempo_medio_segundos)}</Text>
              <Text style={s.tempoLabel}>Tempo médio</Text>
            </View>
            {dados.tempo_max_segundos && (
              <View style={{alignItems: 'center', flex: 1}}>
                <Text style={[s.tempoNum, {color: CORES.laranja}]}>{formatarTempo(dados.tempo_max_segundos)}</Text>
                <Text style={s.tempoLabel}>Tempo máximo</Text>
              </View>
            )}
            <View style={{alignItems: 'center', flex: 1}}>
              <Text style={[s.tempoNum, {color: CORES.cinza700}]}>{dados.total_com_tempo}</Text>
              <Text style={s.tempoLabel}>OS com tempo</Text>
            </View>
          </View>
        </View>
      )}

      {/* Distribuição por status */}
      {Object.keys(dados.distribuicao).length > 0 && (
        <View style={s.card}>
          <Text style={s.secaoTitulo}>Distribuição por situação</Text>
          {Object.entries(dados.distribuicao).map(([status, qtd]) => {
            const pct = dados.total_os > 0 ? (qtd / dados.total_os) * 100 : 0;
            const cor = STATUS_CORES[status] ?? CORES.cinza500;
            return (
              <View key={status} style={{marginTop: 10}}>
                <View style={{flexDirection: 'row', justifyContent: 'space-between', marginBottom: 4}}>
                  <Text style={{fontSize: 13, color: CORES.cinza700, fontWeight: '600'}}>
                    {STATUS_ROTULOS[status] ?? status}
                  </Text>
                  <Text style={{fontSize: 13, color: CORES.cinza900, fontWeight: '700'}}>
                    {qtd} ({pct.toFixed(0)}%)
                  </Text>
                </View>
                <View style={{height: 8, backgroundColor: CORES.cinza100, borderRadius: 4}}>
                  <View style={{height: 8, width: `${Math.min(100, pct)}%` as any, backgroundColor: cor, borderRadius: 4}} />
                </View>
              </View>
            );
          })}
        </View>
      )}

      {/* Últimas concluídas */}
      {dados.recentes_concluidas.length > 0 && (
        <View style={s.card}>
          <Text style={s.secaoTitulo}>Últimas concluídas</Text>
          {dados.recentes_concluidas.map((os, i) => (
            <View key={os.id} style={[s.recenteRow, i > 0 && {borderTopWidth: 1, borderTopColor: CORES.cinza100, marginTop: 8, paddingTop: 8}]}>
              <View style={{flex: 1}}>
                <Text style={{fontWeight: '700', fontSize: 13.5, color: CORES.cinza900}}>{os.cliente_nome}</Text>
                <Text style={{fontSize: 11, color: CORES.cinza500, marginTop: 2}}>
                  {new Date(os.data_conclusao).toLocaleDateString('pt-BR')}
                </Text>
              </View>
              {os.tempo_atendimento_segundos ? (
                <Text style={{color: CORES.cinza700, fontSize: 12, fontWeight: '700'}}>
                  ⏱ {formatarTempo(os.tempo_atendimento_segundos)}
                </Text>
              ) : null}
            </View>
          ))}
        </View>
      )}

      <View style={{height: 30}} />
    </ScrollView>
  );
}

const s = StyleSheet.create({
  container: {flex: 1, backgroundColor: CORES.azul50},
  scroll: {padding: 14, paddingTop: 10},
  centro: {flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32},
  card: {
    backgroundColor: CORES.branco, borderRadius: 14, padding: 16, marginBottom: 12,
    shadowColor: '#10284A', shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.07, shadowRadius: 6, elevation: 2,
  },
  secaoTitulo: {fontSize: 14, fontWeight: '800', color: CORES.cinza900, marginBottom: 2, letterSpacing: 0.2},
  clienteAtivo: {fontSize: 17, fontWeight: '800', color: CORES.cinza900, marginTop: 4},
  gridCards: {flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginBottom: 12},
  statCard: {
    backgroundColor: CORES.branco, borderRadius: 12, padding: 16,
    flex: 1, minWidth: '44%', alignItems: 'center',
    borderTopWidth: 3,
    shadowColor: '#10284A', shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.05, shadowRadius: 4, elevation: 1,
  },
  statNum: {fontSize: 28, fontWeight: '900', letterSpacing: -1},
  statLabel: {fontSize: 11, color: CORES.cinza500, fontWeight: '600', marginTop: 2, textAlign: 'center'},
  tempoNum: {fontSize: 20, fontWeight: '800', color: CORES.azul600},
  tempoLabel: {fontSize: 11, color: CORES.cinza500, marginTop: 2, textAlign: 'center'},
  recenteRow: {flexDirection: 'row', alignItems: 'center'},
  btnRetry: {paddingHorizontal: 20, paddingVertical: 10, backgroundColor: CORES.azul100, borderRadius: 999},
});
