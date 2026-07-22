import React, {useState, useEffect, useCallback} from 'react';
import {
  View,
  Text,
  FlatList,
  StyleSheet,
  TouchableOpacity,
  RefreshControl,
  ActivityIndicator,
  ScrollView,
} from 'react-native';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import {listarOs} from '../api/os';
import {OS} from '../types';
import {OsCard} from '../components/OsCard';
import {useAuth} from '../hooks/useAuth';
import {useNotificacoes} from '../hooks/useNotificacoes';
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
  const {usuario, fazerLogout} = useAuth();
  const {naoLidas} = useNotificacoes(true);
  const [abaAtiva, setAbaAtiva] = useState('');
  const [osList, setOsList] = useState<OS[]>([]);
  const [carregando, setCarregando] = useState(true);
  const [atualizando, setAtualizando] = useState(false);
  const [erro, setErro] = useState('');

  const carregar = useCallback(async (status: string) => {
    setErro('');
    try {
      const dados = await listarOs({status: status || undefined});
      setOsList(dados);
    } catch (e: any) {
      setErro(e.message ?? 'Erro ao carregar OS.');
    }
  }, []);

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
    .split(' ')
    .slice(0, 2)
    .map(p => p[0])
    .join('')
    .toUpperCase() ?? 'U';

  return (
    <View style={estilos.container}>
      {/* Header */}
      <View style={estilos.header}>
        <View>
          <Text style={estilos.bemVindo}>Olá, {usuario?.nome.split(' ')[0]}</Text>
          <Text style={estilos.headerSub}>Suas ordens de serviço</Text>
        </View>
        <View style={estilos.headerAcoes}>
          {naoLidas > 0 && (
            <View style={estilos.notifBadge}>
              <Text style={estilos.notifNum}>{naoLidas > 99 ? '99+' : naoLidas}</Text>
            </View>
          )}
          <View style={estilos.avatar}>
            <Text style={estilos.avatarText}>{iniciais}</Text>
          </View>
        </View>
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
            onPress={() => setAbaAtiva(aba.key)}>
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
          data={osList}
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
              onPress={() => navigation.navigate('OsDetail', {osId: item.id})}
            />
          )}
          ListEmptyComponent={
            <View style={estilos.vazio}>
              <Text style={estilos.vazioIcon}>📋</Text>
              <Text style={estilos.vazioText}>Nenhuma OS encontrada.</Text>
              <Text style={estilos.vazioSub}>
                {abaAtiva ? 'Tente mudar o filtro.' : 'Aguarde atribuição pelo gestor.'}
              </Text>
            </View>
          }
        />
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
  headerSub: {color: 'rgba(255,255,255,0.7)', fontSize: 12.5, marginTop: 2},
  headerAcoes: {flexDirection: 'row', alignItems: 'center', gap: 10, position: 'relative'},
  notifBadge: {
    position: 'absolute',
    top: -6,
    right: 30,
    backgroundColor: CORES.vermelho,
    borderRadius: 999,
    minWidth: 18,
    height: 18,
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 1,
    paddingHorizontal: 3,
  },
  notifNum: {color: '#fff', fontSize: 10, fontWeight: '800'},
  avatar: {
    width: 38,
    height: 38,
    borderRadius: 19,
    backgroundColor: CORES.azul600,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: 'rgba(255,255,255,0.3)',
  },
  avatarText: {color: '#fff', fontWeight: '700', fontSize: 14},
  abasScroll: {backgroundColor: CORES.branco, maxHeight: 52},
  abasContainer: {
    paddingHorizontal: 12,
    paddingVertical: 10,
    flexDirection: 'row',
    gap: 8,
  },
  aba: {
    paddingHorizontal: 14,
    paddingVertical: 6,
    borderRadius: 999,
    backgroundColor: CORES.cinza100,
  },
  abaAtiva: {backgroundColor: CORES.azul700},
  abaText: {fontSize: 13, fontWeight: '600', color: CORES.cinza700},
  abaTextAtiva: {color: '#fff'},
  lista: {padding: 16, paddingBottom: 32},
  centro: {flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32},
  carregandoText: {marginTop: 12, color: CORES.cinza500, fontSize: 14},
  erroText: {color: CORES.vermelho, textAlign: 'center', fontSize: 14, marginBottom: 16},
  botaoRetry: {
    backgroundColor: CORES.azul100,
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 999,
  },
  botaoRetryText: {color: CORES.azul700, fontWeight: '600'},
  vazio: {alignItems: 'center', paddingTop: 60},
  vazioIcon: {fontSize: 52, marginBottom: 16},
  vazioText: {fontSize: 16, fontWeight: '700', color: CORES.cinza700, marginBottom: 6},
  vazioSub: {fontSize: 13, color: CORES.cinza500, textAlign: 'center'},
});
