import React, {useState, useEffect, useCallback, useRef, useMemo} from 'react';
import {
  View,
  Text,
  FlatList,
  StyleSheet,
  TouchableOpacity,
  RefreshControl,
  ActivityIndicator,
  ScrollView,
  TextInput,
} from 'react-native';
import Geolocation from '@react-native-community/geolocation';
import {useNavigation} from '@react-navigation/native';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import {listarOs} from '../api/os';
import {atualizarGps} from '../api/gps';
import {OS} from '../types';
import {OsCard} from '../components/OsCard';
import {useAuth} from '../hooks/useAuth';
import {useNotificacoes} from '../hooks/useNotificacoes';
import {useOfflineQueue} from '../hooks/useOfflineQueue';
import {CORES} from '../config';
import {RootStackParamList} from '../navigation';

type Props = NativeStackScreenProps<RootStackParamList, 'Home'>;

const ABAS = [
  {key: '', label: 'Todas'},
  {key: 'em_andamento', label: 'Em andamento'},
  {key: 'aberto', label: 'Abertas'},
  {key: 'pausado', label: 'Pausadas'},
  {key: 'reagendado', label: 'Reagendadas'},
  {key: 'concluido', label: 'Concluídas'},
];

export function HomeScreen({navigation}: Props) {
  const {usuario} = useAuth();
  const {naoLidas} = useNotificacoes(true);
  const {pendentes} = useOfflineQueue();
  const nav = useNavigation<any>();

  const [abaAtiva, setAbaAtiva] = useState('');
  const [osList, setOsList] = useState<OS[]>([]);
  const [carregando, setCarregando] = useState(true);
  const [atualizando, setAtualizando] = useState(false);
  const [erro, setErro] = useState('');
  const [busca, setBusca] = useState('');

  const isGestor = usuario?.perfil === 'gestor';
  const gpsTimer = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    if (isGestor) {return;}
    function enviarGps() {
      Geolocation.getCurrentPosition(
        pos => {
          atualizarGps(pos.coords.latitude, pos.coords.longitude).catch(() => {});
        },
        () => {},
        {enableHighAccuracy: false, timeout: 15000},
      );
    }
    enviarGps();
    gpsTimer.current = setInterval(enviarGps, 120_000);
    return () => {
      if (gpsTimer.current) {clearInterval(gpsTimer.current);}
    };
  }, [isGestor]);

  const carregar = useCallback(async (status: string) => {
    setErro('');
    try {
      const dados = await listarOs({status: status || undefined});
      setOsList(dados);
    } catch (e: any) {
      setErro(e.message ?? 'Erro ao carregar OS.');
    }
  }, []);

  const osListFiltrada = useMemo(() => {
    if (!busca.trim()) { return osList; }
    const termo = busca.toLowerCase().trim();
    return osList.filter(o =>
      (o.cliente_nome?.toLowerCase().includes(termo)) ||
      (o.codigo?.toLowerCase().includes(termo)) ||
      String(o.id).includes(termo),
    );
  }, [osList, busca]);

  useEffect(() => {
    setCarregando(true);
    carregar(abaAtiva).finally(() => setCarregando(false));
  }, [abaAtiva, carregar]);

  const atualizar = async () => {
    setAtualizando(true);
    await carregar(abaAtiva);
    setAtualizando(false);
  };

  const iniciais = usuario?.nome
    ?.split(' ')
    .slice(0, 2)
    .map(p => p?.[0] ?? '')
    .join('')
    .toUpperCase() || 'U';

  // OS em andamento pelo técnico (exibida no topo como card de destaque)
  const osEmAndamento = !isGestor
    ? osList.find(o => o.situacao_local === 'em_andamento')
    : undefined;

  return (
    <View style={estilos.container}>
      {/* Header */}
      <View style={estilos.header}>
        <View style={{flex: 1}}>
          <Text style={estilos.bemVindo}>
            Olá, {usuario?.nome?.split(' ')?.[0] ?? 'Usuário'}
          </Text>
          <View style={estilos.perfilRow}>
            <View style={[estilos.perfilBadge, isGestor && estilos.perfilBadgeGestor]}>
              <Text style={estilos.perfilBadgeText}>
                {isGestor ? 'Gestor' : 'Técnico'}
              </Text>
            </View>
            <Text style={estilos.headerSub}>
              {isGestor ? 'Todas as ordens de serviço' : 'Suas ordens de serviço'}
            </Text>
          </View>
        </View>
        <View style={estilos.headerAcoes}>
          {naoLidas > 0 && (
            <View style={estilos.notifBadge}>
              <Text style={estilos.notifNum}>{naoLidas > 99 ? '99+' : naoLidas}</Text>
            </View>
          )}
          <View style={[estilos.avatar, isGestor && estilos.avatarGestor]}>
            <Text style={estilos.avatarText}>{iniciais}</Text>
          </View>
        </View>
      </View>

      {/* Banner offline (técnico com ações pendentes) */}
      {!isGestor && pendentes.length > 0 && (
        <View style={estilos.bannerOffline}>
          <Text style={estilos.bannerOfflineIcon}>📡</Text>
          <Text style={estilos.bannerOfflineText}>
            {pendentes.length} ação{pendentes.length > 1 ? 'ões' : ''} aguardando sincronização
          </Text>
        </View>
      )}

      {/* Card OS em andamento (técnico) */}
      {!isGestor && osEmAndamento && (
        <TouchableOpacity
          style={estilos.cardAtiva}
          onPress={() => navigation.navigate('OsDetail', {osId: osEmAndamento.id})}
          activeOpacity={0.85}>
          <View style={estilos.cardAtivaRow}>
            <View style={estilos.cardAtivaIndicador} />
            <View style={{flex: 1}}>
              <Text style={estilos.cardAtivaLabel}>OS em andamento</Text>
              <Text style={estilos.cardAtivaNome} numberOfLines={1}>
                {osEmAndamento.cliente_nome ?? `OS #${osEmAndamento.id}`}
              </Text>
              {osEmAndamento.codigo ? (
                <Text style={estilos.cardAtivaCodigo}>{osEmAndamento.codigo}</Text>
              ) : null}
            </View>
            <Text style={estilos.cardAtivaChevron}>›</Text>
          </View>
        </TouchableOpacity>
      )}

      {/* Resumo rápido (gestor) */}
      {isGestor && osList.length > 0 && (
        <View style={estilos.resumoRow}>
          {[
            {label: 'Total', valor: osList.length, cor: CORES.azul700},
            {label: 'Em campo', valor: osList.filter(o => o.situacao_local === 'em_andamento').length, cor: CORES.amarelo},
            {label: 'Abertas', valor: osList.filter(o => o.situacao_local === 'aberto').length, cor: CORES.cinza500},
          ].map(item => (
            <View key={item.label} style={estilos.resumoItem}>
              <Text style={[estilos.resumoValor, {color: item.cor}]}>{item.valor}</Text>
              <Text style={estilos.resumoLabel}>{item.label}</Text>
            </View>
          ))}
        </View>
      )}

      {/* Acesso rápido para técnico: Produtividade */}
      {!isGestor && !carregando && (
        <TouchableOpacity
          style={estilos.acessoProdutividade}
          onPress={() => nav.navigate('Produtividade')}
          activeOpacity={0.8}>
          <Text style={estilos.acessoProdutividadeIcon}>📊</Text>
          <Text style={estilos.acessoProdutividadeText}>Ver minha produtividade</Text>
          <Text style={estilos.acessoProdutividadeChevron}>›</Text>
        </TouchableOpacity>
      )}

      {/* Busca */}
      <View style={estilos.buscaContainer}>
        <Text style={estilos.buscaIcone}>🔍</Text>
        <TextInput
          style={estilos.buscaInput}
          value={busca}
          onChangeText={setBusca}
          placeholder="Buscar por cliente, código ou nº..."
          placeholderTextColor={CORES.cinza300}
          returnKeyType="search"
          clearButtonMode="while-editing"
        />
        {busca.length > 0 && (
          <TouchableOpacity onPress={() => setBusca('')}>
            <Text style={{color: CORES.cinza500, fontSize: 18, paddingHorizontal: 8}}>×</Text>
          </TouchableOpacity>
        )}
      </View>

      {/* Abas de filtro */}
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        style={estilos.abasScroll}
        contentContainerStyle={estilos.abasContainer}>
        {ABAS.map(aba => (
          <TouchableOpacity
            key={aba.key}
            style={[estilos.aba, abaAtiva === aba.key && estilos.abaAtiva]}
            onPress={() => { setAbaAtiva(aba.key); setBusca(''); }}>
            <Text style={[estilos.abaText, abaAtiva === aba.key && estilos.abaTextAtiva]}>
              {aba.label}
            </Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

      {/* Lista */}
      {carregando ? (
        <View style={estilos.centro}>
          <ActivityIndicator size="large" color={CORES.azul600} />
          <Text style={estilos.carregandoText}>Carregando...</Text>
        </View>
      ) : erro ? (
        <View style={estilos.centro}>
          <Text style={estilos.erroText}>{erro}</Text>
          <TouchableOpacity style={estilos.botaoRetry} onPress={atualizar}>
            <Text style={estilos.botaoRetryText}>Tentar novamente</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <FlatList
          data={osListFiltrada}
          keyExtractor={item => String(item.id)}
          contentContainerStyle={estilos.lista}
          refreshControl={
            <RefreshControl
              refreshing={atualizando}
              onRefresh={atualizar}
              colors={[CORES.azul600]}
              tintColor={CORES.azul600}
            />
          }
          renderItem={({item}) => (
            <OsCard
              os={item}
              mostrarTecnico={isGestor}
              onPress={() => navigation.navigate('OsDetail', {osId: item.id})}
            />
          )}
          ListEmptyComponent={
            <View style={estilos.vazio}>
              <Text style={estilos.vazioIcon}>📋</Text>
              <Text style={estilos.vazioText}>Nenhuma OS encontrada.</Text>
              <Text style={estilos.vazioSub}>
                {abaAtiva
                  ? 'Tente mudar o filtro.'
                  : isGestor
                  ? 'Crie a primeira OS com o botão abaixo.'
                  : 'Aguarde atribuição pelo gestor.'}
              </Text>
            </View>
          }
        />
      )}

      {/* FAB: Nova OS (somente gestor) */}
      {isGestor && (
        <TouchableOpacity
          style={estilos.fab}
          onPress={() => navigation.navigate('CreateOs')}
          activeOpacity={0.85}>
          <Text style={estilos.fabText}>+</Text>
        </TouchableOpacity>
      )}
    </View>
  );
}

const estilos = StyleSheet.create({
  container: {flex: 1, backgroundColor: CORES.azul50},
  header: {
    backgroundColor: CORES.azul800,
    paddingTop: 52,
    paddingBottom: 16,
    paddingHorizontal: 20,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  bemVindo: {color: '#fff', fontSize: 18, fontWeight: '700'},
  perfilRow: {flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 4},
  perfilBadge: {
    backgroundColor: 'rgba(255,255,255,0.15)',
    borderRadius: 999, paddingHorizontal: 8, paddingVertical: 2,
  },
  perfilBadgeGestor: {backgroundColor: 'rgba(255,200,0,0.25)'},
  perfilBadgeText: {color: '#fff', fontSize: 10, fontWeight: '700'},
  headerSub: {color: 'rgba(255,255,255,0.65)', fontSize: 12},
  headerAcoes: {flexDirection: 'row', alignItems: 'center', gap: 10, position: 'relative'},
  notifBadge: {
    position: 'absolute', top: -6, right: 30,
    backgroundColor: CORES.vermelho, borderRadius: 999,
    minWidth: 18, height: 18,
    alignItems: 'center', justifyContent: 'center', zIndex: 1, paddingHorizontal: 3,
  },
  notifNum: {color: '#fff', fontSize: 10, fontWeight: '800'},
  avatar: {
    width: 38, height: 38, borderRadius: 19,
    backgroundColor: CORES.azul600, alignItems: 'center', justifyContent: 'center',
    borderWidth: 2, borderColor: 'rgba(255,255,255,0.3)',
  },
  avatarGestor: {backgroundColor: '#b8860b'},
  avatarText: {color: '#fff', fontWeight: '700', fontSize: 14},

  // Banner offline
  bannerOffline: {
    backgroundColor: '#fff3cd',
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 10,
    gap: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#ffc107',
  },
  bannerOfflineIcon: {fontSize: 16},
  bannerOfflineText: {flex: 1, fontSize: 13, fontWeight: '600', color: '#856404'},

  // Card OS ativa
  cardAtiva: {
    backgroundColor: CORES.branco,
    marginHorizontal: 12,
    marginTop: 10,
    marginBottom: 2,
    borderRadius: 12,
    borderLeftWidth: 4,
    borderLeftColor: CORES.amarelo,
    shadowColor: '#10284A',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.08,
    shadowRadius: 6,
    elevation: 3,
  },
  cardAtivaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 12,
    gap: 10,
  },
  cardAtivaIndicador: {
    width: 10, height: 10, borderRadius: 5,
    backgroundColor: CORES.amarelo,
  },
  cardAtivaLabel: {fontSize: 10, fontWeight: '700', color: CORES.cinza500, textTransform: 'uppercase', letterSpacing: 0.5},
  cardAtivaNome: {fontSize: 15, fontWeight: '700', color: CORES.cinza900, marginTop: 2},
  cardAtivaCodigo: {fontSize: 12, color: CORES.cinza500, marginTop: 1},
  cardAtivaChevron: {fontSize: 22, color: CORES.cinza300, fontWeight: '300'},

  // Acesso rápido produtividade
  acessoProdutividade: {
    backgroundColor: CORES.azul100,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 10,
    gap: 8,
    borderBottomWidth: 1,
    borderBottomColor: CORES.cinza100,
  },
  acessoProdutividadeIcon: {fontSize: 16},
  acessoProdutividadeText: {flex: 1, fontSize: 13, fontWeight: '600', color: CORES.azul700},
  acessoProdutividadeChevron: {fontSize: 20, color: CORES.azul700, fontWeight: '300'},

  resumoRow: {
    backgroundColor: CORES.branco, flexDirection: 'row',
    paddingVertical: 12, paddingHorizontal: 20,
    borderBottomWidth: 1, borderBottomColor: CORES.cinza100, gap: 24,
  },
  resumoItem: {alignItems: 'center'},
  resumoValor: {fontSize: 20, fontWeight: '800'},
  resumoLabel: {fontSize: 11, color: CORES.cinza500, fontWeight: '600', marginTop: 1},
  buscaContainer: {
    flexDirection: 'row', alignItems: 'center',
    backgroundColor: CORES.branco,
    paddingHorizontal: 14, paddingVertical: 8,
    borderBottomWidth: 1, borderBottomColor: CORES.cinza100,
    gap: 6,
  },
  buscaIcone: {fontSize: 15, color: CORES.cinza500},
  buscaInput: {
    flex: 1, fontSize: 14, color: CORES.cinza900,
    paddingVertical: 6,
  },
  abasScroll: {backgroundColor: CORES.branco, maxHeight: 52},
  abasContainer: {paddingHorizontal: 12, paddingVertical: 10, flexDirection: 'row', gap: 8},
  aba: {paddingHorizontal: 14, paddingVertical: 6, borderRadius: 999, backgroundColor: CORES.cinza100},
  abaAtiva: {backgroundColor: CORES.azul700},
  abaText: {fontSize: 13, fontWeight: '600', color: CORES.cinza700},
  abaTextAtiva: {color: '#fff'},
  lista: {padding: 16, paddingBottom: 100},
  centro: {flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32},
  carregandoText: {marginTop: 12, color: CORES.cinza500, fontSize: 14},
  erroText: {color: CORES.vermelho, textAlign: 'center', fontSize: 14, marginBottom: 16},
  botaoRetry: {backgroundColor: CORES.azul100, paddingHorizontal: 20, paddingVertical: 10, borderRadius: 999},
  botaoRetryText: {color: CORES.azul700, fontWeight: '600'},
  vazio: {alignItems: 'center', paddingTop: 60},
  vazioIcon: {fontSize: 52, marginBottom: 16},
  vazioText: {fontSize: 16, fontWeight: '700', color: CORES.cinza700, marginBottom: 6},
  vazioSub: {fontSize: 13, color: CORES.cinza500, textAlign: 'center'},
  fab: {
    position: 'absolute',
    bottom: 24,
    right: 20,
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: CORES.azul700,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: CORES.azul900,
    shadowOffset: {width: 0, height: 4},
    shadowOpacity: 0.4,
    shadowRadius: 8,
    elevation: 8,
  },
  fabText: {color: '#fff', fontSize: 28, fontWeight: '300', lineHeight: 32},
});
