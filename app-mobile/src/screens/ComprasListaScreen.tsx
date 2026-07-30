import React, {useState, useEffect, useCallback} from 'react';
import {
  View,
  Text,
  FlatList,
  StyleSheet,
  TouchableOpacity,
  RefreshControl,
  ActivityIndicator,
} from 'react-native';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import {listarCompras} from '../api/compras';
import {SolicitacaoCompra} from '../types';
import {useAuth} from '../hooks/useAuth';
import {CORES} from '../config';
import {RootStackParamList} from '../navigation';

type Props = NativeStackScreenProps<RootStackParamList, 'ComprasLista'>;

const STATUS_ABAS = [
  {key: '', label: 'Todas'},
  {key: 'aguardando_aprovacao', label: 'Aguardando'},
  {key: 'aprovado', label: 'Aprovadas'},
  {key: 'em_compra', label: 'Em Compra'},
  {key: 'recebido', label: 'Recebidas'},
  {key: 'concluido', label: 'Concluídas'},
];

const STATUS_COR: Record<string, string> = {
  rascunho: '#94a3b8',
  aguardando_aprovacao: '#d97706',
  aprovado: '#2563eb',
  reprovado: '#dc2626',
  devolvido: '#7c3aed',
  em_compra: '#0891b2',
  recebido: '#059669',
  concluido: '#1e8e5a',
  cancelado: '#64748b',
};

const STATUS_LABEL: Record<string, string> = {
  rascunho: 'Rascunho',
  aguardando_aprovacao: 'Aguardando',
  aprovado: 'Aprovado',
  reprovado: 'Reprovado',
  devolvido: 'Devolvido',
  em_compra: 'Em Compra',
  recebido: 'Recebido',
  concluido: 'Concluído',
  cancelado: 'Cancelado',
};

const PRIORIDADE_COR: Record<string, string> = {
  baixa: '#94a3b8',
  media: '#2563eb',
  alta: '#d97706',
  urgente: '#dc2626',
};

function formatarMoeda(v: number | null): string {
  if (v == null) return '—';
  return 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function formatarData(s: string): string {
  const d = new Date(s);
  return d.toLocaleDateString('pt-BR');
}

export function ComprasListaScreen({navigation}: Props) {
  const {usuario} = useAuth();
  const [abaAtiva, setAbaAtiva] = useState('');
  const [lista, setLista] = useState<SolicitacaoCompra[]>([]);
  const [carregando, setCarregando] = useState(true);
  const [atualizando, setAtualizando] = useState(false);
  const [erro, setErro] = useState('');

  const carregar = useCallback(async (refresh = false) => {
    if (refresh) { setAtualizando(true); } else { setCarregando(true); }
    setErro('');
    try {
      const resp = await listarCompras({status: abaAtiva || undefined});
      setLista(resp.solicitacoes ?? []);
    } catch (e: any) {
      setErro(e.message ?? 'Erro ao carregar.');
    } finally {
      setCarregando(false);
      setAtualizando(false);
    }
  }, [abaAtiva]);

  useEffect(() => { carregar(); }, [carregar]);

  const renderItem = ({item}: {item: SolicitacaoCompra}) => (
    <TouchableOpacity
      style={estilos.card}
      onPress={() => navigation.navigate('CompraDetalhe', {id: item.id})}
      activeOpacity={0.85}
    >
      <View style={estilos.cardHeader}>
        <Text style={estilos.numero}>{item.numero}</Text>
        <View style={[estilos.badge, {backgroundColor: STATUS_COR[item.status] + '22'}]}>
          <Text style={[estilos.badgeText, {color: STATUS_COR[item.status]}]}>
            {STATUS_LABEL[item.status] ?? item.status}
          </Text>
        </View>
      </View>
      <Text style={estilos.solicitante}>{item.solicitante_nome}</Text>
      {item.categoria_nome && <Text style={estilos.categoria}>{item.categoria_nome}</Text>}
      <Text style={estilos.justificativa} numberOfLines={2}>{item.justificativa}</Text>
      <View style={estilos.cardFooter}>
        <View style={[estilos.priorBadge, {backgroundColor: PRIORIDADE_COR[item.prioridade] + '22'}]}>
          <Text style={[estilos.priorText, {color: PRIORIDADE_COR[item.prioridade]}]}>
            {item.prioridade.toUpperCase()}
          </Text>
        </View>
        <Text style={estilos.valor}>{formatarMoeda(item.valor_final ?? item.valor_negociado ?? item.valor_estimado)}</Text>
        <Text style={estilos.data}>{formatarData(item.criado_em)}</Text>
      </View>
    </TouchableOpacity>
  );

  return (
    <View style={estilos.container}>
      {/* Abas de status */}
      <FlatList
        horizontal
        data={STATUS_ABAS}
        keyExtractor={i => i.key}
        showsHorizontalScrollIndicator={false}
        style={estilos.abasContainer}
        contentContainerStyle={{paddingHorizontal: 12, paddingVertical: 8}}
        renderItem={({item}) => (
          <TouchableOpacity
            onPress={() => setAbaAtiva(item.key)}
            style={[estilos.aba, abaAtiva === item.key && estilos.abaAtiva]}
          >
            <Text style={[estilos.abaText, abaAtiva === item.key && estilos.abaTextAtiva]}>
              {item.label}
            </Text>
          </TouchableOpacity>
        )}
      />

      {carregando ? (
        <ActivityIndicator size="large" color={CORES.azul} style={{marginTop: 40}} />
      ) : erro ? (
        <View style={estilos.erroBox}>
          <Text style={estilos.erroText}>{erro}</Text>
          <TouchableOpacity onPress={() => carregar()} style={estilos.btnRetry}>
            <Text style={{color: CORES.azul}}>Tentar novamente</Text>
          </TouchableOpacity>
        </View>
      ) : lista.length === 0 ? (
        <View style={estilos.vazio}>
          <Text style={estilos.vazioText}>Nenhuma solicitação encontrada.</Text>
        </View>
      ) : (
        <FlatList
          data={lista}
          keyExtractor={i => String(i.id)}
          renderItem={renderItem}
          contentContainerStyle={{padding: 12}}
          refreshControl={
            <RefreshControl refreshing={atualizando} onRefresh={() => carregar(true)} colors={[CORES.azul]} />
          }
        />
      )}
    </View>
  );
}

const estilos = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#f4f8fd'},
  abasContainer: {flexGrow: 0, backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#eef1f5'},
  aba: {paddingHorizontal: 14, paddingVertical: 6, borderRadius: 20, marginRight: 6, backgroundColor: '#f0f4f8'},
  abaAtiva: {backgroundColor: CORES.azul},
  abaText: {fontSize: 12, color: '#6b7789'},
  abaTextAtiva: {color: '#fff', fontWeight: '700'},
  card: {backgroundColor: '#fff', borderRadius: 12, padding: 14, marginBottom: 10, shadowColor: '#000', shadowOpacity: 0.06, shadowRadius: 6, elevation: 2},
  cardHeader: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4},
  numero: {fontSize: 13, fontWeight: '700', color: '#1e3a5f'},
  badge: {paddingHorizontal: 8, paddingVertical: 2, borderRadius: 10},
  badgeText: {fontSize: 11, fontWeight: '700'},
  solicitante: {fontSize: 13, color: '#374151', marginBottom: 2},
  categoria: {fontSize: 11, color: '#6b7789', marginBottom: 4},
  justificativa: {fontSize: 12, color: '#6b7789', marginBottom: 8},
  cardFooter: {flexDirection: 'row', alignItems: 'center', gap: 8},
  priorBadge: {paddingHorizontal: 6, paddingVertical: 1, borderRadius: 6},
  priorText: {fontSize: 9, fontWeight: '700'},
  valor: {fontSize: 12, fontWeight: '700', color: '#1e3a5f', marginLeft: 'auto'},
  data: {fontSize: 11, color: '#94a3b8'},
  erroBox: {flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24},
  erroText: {color: '#dc2626', textAlign: 'center', marginBottom: 12},
  btnRetry: {padding: 10},
  vazio: {flex: 1, justifyContent: 'center', alignItems: 'center'},
  vazioText: {color: '#94a3b8', fontSize: 14},
});
