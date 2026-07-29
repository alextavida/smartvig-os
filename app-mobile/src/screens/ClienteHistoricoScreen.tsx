import React, {useState, useEffect, useCallback} from 'react';
import {
  View, Text, ScrollView, StyleSheet, TouchableOpacity,
  ActivityIndicator, RefreshControl,
} from 'react-native';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import {apiGet} from '../api/client';
import {CORES} from '../config';
import {StatusBadge} from '../components/StatusBadge';
import Icon from 'react-native-vector-icons/MaterialIcons';
import {RootStackParamList} from '../navigation';

type Props = NativeStackScreenProps<RootStackParamList, 'ClienteHistorico'>;

interface OsResumo {
  id: number;
  gc_os_id: number | null;
  situacao_local: string;
  data_agendamento: string | null;
  data_conclusao: string | null;
  prioridade: string;
  observacoes: string | null;
  tecnico_nome: string | null;
  criado_em: string;
}

interface ClienteInfo {
  nome: string;
  email: string;
  telefone: string;
  endereco: string;
}

interface Historico {
  cliente: ClienteInfo | null;
  ordens: OsResumo[];
  total: number;
}

const SITUACAO_CORES: Record<string, string> = {
  aberto: '#1462b0', em_andamento: '#b8860b', pausado: '#6b7789',
  reagendado: '#c8641a', concluido: '#1e8e5a', cancelado: '#c62f2f',
};

export function ClienteHistoricoScreen({route, navigation}: Props) {
  const {gcClienteId, clienteNome} = route.params;
  const [dados, setDados] = useState<Historico | null>(null);
  const [carregando, setCarregando] = useState(true);
  const [atualizando, setAtualizando] = useState(false);
  const [erro, setErro] = useState('');

  const carregar = useCallback(async (silencioso = false) => {
    if (!silencioso) setErro('');
    try {
      const res = await apiGet<Historico>(
        `/clientes/historico.php?gc_cliente_id=${gcClienteId}`,
      );
      setDados(res);
    } catch (e: any) {
      if (!silencioso) setErro(e.message ?? 'Erro ao carregar histórico.');
    }
  }, [gcClienteId]);

  useEffect(() => {
    carregar(false).finally(() => setCarregando(false));
  }, [carregar]);

  const atualizar = async () => {
    setAtualizando(true);
    await carregar(true);
    setAtualizando(false);
  };

  function fmtData(iso: string | null) {
    if (!iso) return '—';
    return new Date(iso + (iso.includes('T') ? '' : 'T12:00:00')).toLocaleDateString('pt-BR');
  }

  if (carregando) {
    return (
      <View style={est.centro}>
        <ActivityIndicator size="large" color={CORES.azul600} />
      </View>
    );
  }

  const cliente = dados?.cliente;
  const ordens = dados?.ordens ?? [];
  const concluidas = ordens.filter(o => o.situacao_local === 'concluido').length;
  const emAberto = ordens.filter(o => !['concluido', 'cancelado'].includes(o.situacao_local)).length;

  return (
    <ScrollView
      style={est.container}
      contentContainerStyle={est.scroll}
      refreshControl={
        <RefreshControl refreshing={atualizando} onRefresh={atualizar}
          colors={[CORES.azul600]} tintColor={CORES.azul600} />
      }>

      {/* Cabeçalho do cliente */}
      <View style={est.header}>
        <View style={est.avatar}>
          <Text style={est.avatarText}>
            {(cliente?.nome ?? clienteNome).split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase()}
          </Text>
        </View>
        <View style={{flex: 1}}>
          <Text style={est.nomeCliente}>{cliente?.nome ?? clienteNome}</Text>
          {cliente?.telefone ? (
            <Text style={est.subInfo}>{cliente.telefone}</Text>
          ) : null}
          {cliente?.email ? (
            <Text style={est.subInfo}>{cliente.email}</Text>
          ) : null}
          {cliente?.endereco ? (
            <Text style={est.subInfo} numberOfLines={1}>{cliente.endereco}</Text>
          ) : null}
        </View>
      </View>

      {erro ? (
        <View style={est.erroBox}><Text style={est.erroText}>{erro}</Text></View>
      ) : null}

      {/* Stats rápidos */}
      <View style={est.statsRow}>
        <View style={est.statBox}>
          <Text style={est.statNum}>{ordens.length}</Text>
          <Text style={est.statLabel}>Total de OS</Text>
        </View>
        <View style={est.statBox}>
          <Text style={[est.statNum, {color: CORES.verde}]}>{concluidas}</Text>
          <Text style={est.statLabel}>Concluídas</Text>
        </View>
        <View style={est.statBox}>
          <Text style={[est.statNum, {color: CORES.azul700}]}>{emAberto}</Text>
          <Text style={est.statLabel}>Em aberto</Text>
        </View>
      </View>

      {/* Lista de OS */}
      <Text style={est.secaoTitulo}>Histórico de atendimentos</Text>

      {ordens.length === 0 ? (
        <View style={est.vazio}>
          <Icon name="history" size={40} color={CORES.cinza300} />
          <Text style={est.vazioText}>Nenhuma OS encontrada para este cliente.</Text>
        </View>
      ) : (
        ordens.map(os => (
          <TouchableOpacity
            key={os.id}
            style={est.osCard}
            activeOpacity={0.8}
            onPress={() => navigation.navigate('OsDetail', {osId: os.id})}>

            <View style={est.osCardHeader}>
              <Text style={est.osNum}>OS #{os.id}</Text>
              <StatusBadge status={os.situacao_local} />
            </View>

            <View style={est.osCardBody}>
              {os.data_agendamento ? (
                <View style={est.infoRow}>
                  <Icon name="calendar-today" size={13} color={CORES.cinza500} />
                  <Text style={est.infoText}>Agendada: {fmtData(os.data_agendamento)}</Text>
                </View>
              ) : null}
              {os.data_conclusao ? (
                <View style={est.infoRow}>
                  <Icon name="check-circle" size={13} color={CORES.verde} />
                  <Text style={est.infoText}>Concluída: {fmtData(os.data_conclusao)}</Text>
                </View>
              ) : null}
              {os.tecnico_nome ? (
                <View style={est.infoRow}>
                  <Icon name="person" size={13} color={CORES.cinza500} />
                  <Text style={est.infoText}>{os.tecnico_nome}</Text>
                </View>
              ) : null}
              {os.observacoes ? (
                <Text style={est.laudo} numberOfLines={2}>{os.observacoes}</Text>
              ) : null}
            </View>

            <View style={[est.barraLateral, {backgroundColor: SITUACAO_CORES[os.situacao_local] ?? CORES.cinza300}]} />
          </TouchableOpacity>
        ))
      )}

      <View style={{height: 32}} />
    </ScrollView>
  );
}

const est = StyleSheet.create({
  container: {flex: 1, backgroundColor: CORES.azul50},
  scroll: {padding: 14},
  centro: {flex: 1, alignItems: 'center', justifyContent: 'center'},
  header: {
    backgroundColor: CORES.branco, borderRadius: 14, padding: 16,
    flexDirection: 'row', alignItems: 'center', gap: 14, marginBottom: 12,
    shadowColor: '#10284A', shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.07, shadowRadius: 6, elevation: 2,
  },
  avatar: {
    width: 52, height: 52, borderRadius: 26,
    backgroundColor: CORES.azul700, alignItems: 'center', justifyContent: 'center',
    flexShrink: 0,
  },
  avatarText: {color: '#fff', fontWeight: '800', fontSize: 18},
  nomeCliente: {fontSize: 16, fontWeight: '800', color: CORES.cinza900},
  subInfo: {fontSize: 12, color: CORES.cinza500, marginTop: 2},
  erroBox: {backgroundColor: CORES.vermelhoBg, borderRadius: 8, padding: 12, marginBottom: 10},
  erroText: {color: CORES.vermelho, fontSize: 13, fontWeight: '600'},
  statsRow: {
    flexDirection: 'row', gap: 10, marginBottom: 18,
  },
  statBox: {
    flex: 1, backgroundColor: CORES.branco, borderRadius: 12, padding: 14,
    alignItems: 'center',
    shadowColor: '#10284A', shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.05, shadowRadius: 3, elevation: 1,
  },
  statNum: {fontSize: 24, fontWeight: '800', color: CORES.cinza900},
  statLabel: {fontSize: 11, color: CORES.cinza500, fontWeight: '600', marginTop: 2},
  secaoTitulo: {
    fontSize: 11, fontWeight: '700', color: CORES.cinza500,
    textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 10,
  },
  osCard: {
    backgroundColor: CORES.branco, borderRadius: 12, marginBottom: 10,
    overflow: 'hidden', flexDirection: 'row',
    shadowColor: '#10284A', shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.06, shadowRadius: 4, elevation: 2,
  },
  barraLateral: {width: 4, flexShrink: 0},
  osCardHeader: {
    flexDirection: 'row', justifyContent: 'space-between',
    alignItems: 'center', marginBottom: 8,
  },
  osCardBody: {padding: 14, flex: 1},
  osNum: {fontSize: 12, fontWeight: '700', color: CORES.cinza500},
  infoRow: {flexDirection: 'row', alignItems: 'center', gap: 5, marginBottom: 3},
  infoText: {fontSize: 12.5, color: CORES.cinza700},
  laudo: {fontSize: 12, color: CORES.cinza500, marginTop: 6, fontStyle: 'italic'},
  vazio: {alignItems: 'center', paddingVertical: 40, gap: 12},
  vazioText: {color: CORES.cinza500, fontSize: 14, textAlign: 'center'},
});
