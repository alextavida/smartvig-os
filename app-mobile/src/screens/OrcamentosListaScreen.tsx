import React, {useState, useEffect, useCallback} from 'react';
import {
  View, Text, FlatList, TouchableOpacity, StyleSheet,
  RefreshControl, ActivityIndicator,
} from 'react-native';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import {listarOrcamentos} from '../api/orcamentos';
import {Orcamento} from '../types';
import {CORES} from '../config';
import {RootStackParamList} from '../navigation';
import Icon from 'react-native-vector-icons/MaterialIcons';

type Props = NativeStackScreenProps<RootStackParamList, 'OrcamentosLista'>;

const ABAS = [
  {key: '',           label: 'Todos'},
  {key: 'rascunho',   label: 'Rascunho'},
  {key: 'enviado',    label: 'Enviado'},
  {key: 'aprovado',   label: 'Aprovado'},
  {key: 'recusado',   label: 'Recusado'},
  {key: 'convertido', label: 'Convertido'},
];

const STATUS_COR: Record<string, string> = {
  rascunho:   '#94a3b8',
  enviado:    '#d97706',
  aprovado:   '#16803c',
  recusado:   '#dc2626',
  convertido: '#2563eb',
};

const STATUS_LABEL: Record<string, string> = {
  rascunho:   'Rascunho',
  enviado:    'Enviado',
  aprovado:   'Aprovado',
  recusado:   'Recusado',
  convertido: 'Convertido',
};

function formatarMoeda(v: number): string {
  return 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function formatarData(s: string): string {
  return new Date(s).toLocaleDateString('pt-BR');
}

export function OrcamentosListaScreen({navigation}: Props) {
  const [abaAtiva, setAbaAtiva]   = useState('');
  const [lista, setLista]         = useState<Orcamento[]>([]);
  const [carregando, setCarregando] = useState(true);
  const [atualizando, setAtualizando] = useState(false);
  const [erro, setErro]           = useState('');

  const carregar = useCallback(async (refresh = false) => {
    if (refresh) { setAtualizando(true); } else { setCarregando(true); }
    setErro('');
    try {
      const resp = await listarOrcamentos(abaAtiva || undefined);
      setLista(resp.orcamentos ?? []);
    } catch (ex: any) {
      setErro(ex.message ?? 'Erro ao carregar.');
    } finally {
      setCarregando(false);
      setAtualizando(false);
    }
  }, [abaAtiva]);

  useEffect(() => { carregar(); }, [carregar]);

  const renderItem = ({item}: {item: Orcamento}) => (
    <TouchableOpacity
      style={s.card}
      onPress={() => navigation.navigate('OrcamentoDetalhe', {id: item.id})}
      activeOpacity={0.85}
    >
      <View style={s.cardHeader}>
        <Text style={s.codigo}>{item.codigo}</Text>
        <View style={[s.badge, {backgroundColor: (STATUS_COR[item.status] ?? '#94a3b8') + '22'}]}>
          <Text style={[s.badgeText, {color: STATUS_COR[item.status] ?? '#94a3b8'}]}>
            {STATUS_LABEL[item.status] ?? item.status}
          </Text>
        </View>
      </View>
      <Text style={s.cliente}>{item.cliente_nome}</Text>
      {item.cliente_telefone ? <Text style={s.tel}>{item.cliente_telefone}</Text> : null}
      <View style={s.cardFooter}>
        <Text style={s.itens}>{item.total_itens} {item.total_itens === 1 ? 'item' : 'itens'}</Text>
        <Text style={s.total}>{formatarMoeda(item.total)}</Text>
        <Text style={s.data}>{formatarData(item.criado_em)}</Text>
      </View>
    </TouchableOpacity>
  );

  return (
    <View style={s.container}>
      {/* Abas */}
      <FlatList
        horizontal
        data={ABAS}
        keyExtractor={i => i.key}
        showsHorizontalScrollIndicator={false}
        style={s.abasContainer}
        contentContainerStyle={{paddingHorizontal: 12, paddingVertical: 8}}
        renderItem={({item}) => (
          <TouchableOpacity
            onPress={() => setAbaAtiva(item.key)}
            style={[s.aba, abaAtiva === item.key && s.abaAtiva]}
          >
            <Text style={[s.abaText, abaAtiva === item.key && s.abaTextAtiva]}>
              {item.label}
            </Text>
          </TouchableOpacity>
        )}
      />

      {carregando ? (
        <ActivityIndicator size="large" color={CORES.azul700} style={{marginTop: 40}} />
      ) : erro ? (
        <View style={s.centro}>
          <Text style={{color: '#dc2626', marginBottom: 12}}>{erro}</Text>
          <TouchableOpacity onPress={() => carregar()}>
            <Text style={{color: CORES.azul700}}>Tentar novamente</Text>
          </TouchableOpacity>
        </View>
      ) : lista.length === 0 ? (
        <View style={s.centro}>
          <Text style={{color: '#94a3b8', fontSize: 14}}>Nenhum orçamento encontrado.</Text>
        </View>
      ) : (
        <FlatList
          data={lista}
          keyExtractor={i => String(i.id)}
          renderItem={renderItem}
          contentContainerStyle={{padding: 12}}
          refreshControl={
            <RefreshControl refreshing={atualizando} onRefresh={() => carregar(true)} colors={[CORES.azul700]} />
          }
        />
      )}

      {/* FAB */}
      <TouchableOpacity
        style={s.fab}
        onPress={() => navigation.navigate('NovoOrcamento')}
        activeOpacity={0.85}
      >
        <Icon name="add" size={26} color="#fff" />
      </TouchableOpacity>
    </View>
  );
}

const s = StyleSheet.create({
  container:    {flex: 1, backgroundColor: '#f4f8fd'},
  abasContainer:{flexGrow: 0, backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#eef1f5'},
  aba:          {paddingHorizontal: 14, paddingVertical: 6, borderRadius: 20, marginRight: 6, backgroundColor: '#f0f4f8'},
  abaAtiva:     {backgroundColor: CORES.azul700},
  abaText:      {fontSize: 12, color: '#6b7789'},
  abaTextAtiva: {color: '#fff', fontWeight: '700'},
  card:         {backgroundColor: '#fff', borderRadius: 12, padding: 14, marginBottom: 10, elevation: 2},
  cardHeader:   {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4},
  codigo:       {fontSize: 13, fontWeight: '800', color: '#1e3a5f'},
  badge:        {paddingHorizontal: 8, paddingVertical: 2, borderRadius: 10},
  badgeText:    {fontSize: 11, fontWeight: '700'},
  cliente:      {fontSize: 14, fontWeight: '600', color: '#374151', marginBottom: 2},
  tel:          {fontSize: 12, color: '#6b7789', marginBottom: 6},
  cardFooter:   {flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 8},
  itens:        {fontSize: 12, color: '#6b7789'},
  total:        {fontSize: 13, fontWeight: '700', color: '#1e3a5f', marginLeft: 'auto'},
  data:         {fontSize: 11, color: '#94a3b8'},
  centro:       {flex: 1, justifyContent: 'center', alignItems: 'center'},
  fab:          {position: 'absolute', bottom: 20, right: 20, width: 56, height: 56, borderRadius: 28, backgroundColor: CORES.azul700, alignItems: 'center', justifyContent: 'center', elevation: 6},
});
