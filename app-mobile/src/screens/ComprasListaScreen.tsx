import React, {useState, useEffect, useCallback} from 'react';
import {
  View, Text, FlatList, StyleSheet, TouchableOpacity,
  RefreshControl, ActivityIndicator, ScrollView,
} from 'react-native';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import {listarCompras} from '../api/compras';
import {SolicitacaoCompra} from '../types';
import {CORES} from '../config';
import {RootStackParamList} from '../navigation';
import Icon from 'react-native-vector-icons/MaterialIcons';

type Props = NativeStackScreenProps<RootStackParamList, 'ComprasLista'>;

const ABAS = [
  {key: '',                     label: 'Todas'},
  {key: 'aguardando_aprovacao', label: 'Aguardando'},
  {key: 'aprovado',             label: 'Aprovadas'},
  {key: 'em_compra',            label: 'Em Compra'},
  {key: 'recebido',             label: 'Recebidas'},
  {key: 'concluido',            label: 'Concluídas'},
];

const STATUS_COR: Record<string, string> = {
  rascunho:             '#94a3b8',
  aguardando_aprovacao: '#d97706',
  aprovado:             '#2563eb',
  reprovado:            '#dc2626',
  devolvido:            '#7c3aed',
  em_compra:            '#0891b2',
  recebido:             '#059669',
  concluido:            '#1e8e5a',
  cancelado:            '#64748b',
};

const STATUS_LABEL: Record<string, string> = {
  rascunho:             'Rascunho',
  aguardando_aprovacao: 'Aguardando',
  aprovado:             'Aprovado',
  reprovado:            'Reprovado',
  devolvido:            'Devolvido',
  em_compra:            'Em Compra',
  recebido:             'Recebido',
  concluido:            'Concluído',
  cancelado:            'Cancelado',
};

const PRIORIDADE_COR: Record<string, string> = {
  baixa:   '#94a3b8',
  media:   '#2563eb',
  alta:    '#d97706',
  urgente: '#dc2626',
};

function fmt(v: number | null | undefined): string {
  if (v == null) { return '—'; }
  return 'R$ ' + Number(v).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function fmtData(s: string | null | undefined): string {
  if (!s) { return ''; }
  try { return new Date(s).toLocaleDateString('pt-BR'); } catch { return ''; }
}

export function ComprasListaScreen({navigation}: Props) {
  const [abaAtiva, setAbaAtiva]       = useState('');
  const [lista, setLista]             = useState<SolicitacaoCompra[]>([]);
  const [carregando, setCarregando]   = useState(true);
  const [atualizando, setAtualizando] = useState(false);
  const [erro, setErro]               = useState('');

  const carregar = useCallback(async (refresh = false) => {
    try {
      if (refresh) { setAtualizando(true); } else { setCarregando(true); }
      setErro('');
      const resp = await listarCompras({status: abaAtiva || undefined});
      setLista(Array.isArray(resp.solicitacoes) ? resp.solicitacoes : []);
    } catch (ex: any) {
      setErro(ex?.message ?? 'Erro ao carregar.');
    } finally {
      setCarregando(false);
      setAtualizando(false);
    }
  }, [abaAtiva]);

  useEffect(() => { carregar(); }, [carregar]);

  function corStatus(status: string) {
    return STATUS_COR[status] ?? '#94a3b8';
  }

  const renderItem = ({item}: {item: SolicitacaoCompra}) => {
    const cor        = corStatus(item.status);
    const priorCor   = PRIORIDADE_COR[item.prioridade] ?? '#94a3b8';
    const valor      = item.valor_final ?? item.valor_negociado ?? item.valor_estimado;
    return (
      <TouchableOpacity
        style={s.card}
        onPress={() => navigation.navigate('CompraDetalhe', {id: item.id})}
        activeOpacity={0.85}
      >
        <View style={s.cardTopo}>
          <Text style={s.numero}>{item.numero ?? ''}</Text>
          <View style={[s.badge, {backgroundColor: cor + '22'}]}>
            <Text style={[s.badgeText, {color: cor}]}>
              {STATUS_LABEL[item.status] ?? item.status}
            </Text>
          </View>
        </View>

        <Text style={s.solicitante}>{item.solicitante_nome ?? ''}</Text>
        {item.categoria_nome ? <Text style={s.categoria}>{item.categoria_nome}</Text> : null}
        <Text style={s.justificativa} numberOfLines={2}>{item.justificativa ?? ''}</Text>

        <View style={s.rodape}>
          <View style={[s.priorBadge, {backgroundColor: priorCor + '22'}]}>
            <Text style={[s.priorText, {color: priorCor}]}>
              {item.prioridade ? item.prioridade.toUpperCase() : '—'}
            </Text>
          </View>
          <Text style={s.dataText}>{fmtData(item.criado_em)}</Text>
          <View style={{flex: 1}} />
          <Text style={s.valorText}>{fmt(valor)}</Text>
        </View>
      </TouchableOpacity>
    );
  };

  return (
    <View style={s.tela}>

      {/* Abas de status — ScrollView horizontal simples */}
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        style={s.abasScroll}
        contentContainerStyle={s.abasContent}
      >
        {ABAS.map(a => (
          <TouchableOpacity
            key={a.key}
            onPress={() => setAbaAtiva(a.key)}
            style={[s.aba, abaAtiva === a.key && s.abaAtiva]}
          >
            <Text style={[s.abaText, abaAtiva === a.key && s.abaTextAtiva]}>
              {a.label}
            </Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

      {/* Conteúdo */}
      {carregando ? (
        <View style={s.centro}>
          <ActivityIndicator size="large" color={CORES.azul700} />
        </View>
      ) : erro ? (
        <View style={s.centro}>
          <Text style={s.erroText}>{erro}</Text>
          <TouchableOpacity onPress={() => carregar()} style={s.btnRetry}>
            <Text style={{color: CORES.azul700, fontWeight: '600'}}>Tentar novamente</Text>
          </TouchableOpacity>
        </View>
      ) : lista.length === 0 ? (
        <View style={s.centro}>
          <Text style={s.vazioText}>Nenhuma solicitação encontrada.</Text>
        </View>
      ) : (
        <FlatList
          data={lista}
          keyExtractor={i => String(i.id)}
          renderItem={renderItem}
          contentContainerStyle={{padding: 12, paddingBottom: 90}}
          refreshControl={
            <RefreshControl
              refreshing={atualizando}
              onRefresh={() => carregar(true)}
              colors={[CORES.azul700]}
            />
          }
        />
      )}

      {/* FAB nova solicitação */}
      <TouchableOpacity
        style={s.fab}
        onPress={() => navigation.navigate('NovaCompra')}
        activeOpacity={0.85}
      >
        <Icon name="add" size={26} color="#fff" />
      </TouchableOpacity>
    </View>
  );
}

const s = StyleSheet.create({
  tela:         {flex: 1, backgroundColor: '#f4f8fd'},
  abasScroll:   {flexGrow: 0, backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#eef1f5'},
  abasContent:  {paddingHorizontal: 12, paddingVertical: 8, flexDirection: 'row'},
  aba:          {paddingHorizontal: 14, paddingVertical: 6, borderRadius: 20, marginRight: 6, backgroundColor: '#f0f4f8'},
  abaAtiva:     {backgroundColor: CORES.azul700},
  abaText:      {fontSize: 12, color: '#6b7789'},
  abaTextAtiva: {color: '#fff', fontWeight: '700'},
  centro:       {flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24},
  erroText:     {color: '#dc2626', textAlign: 'center', marginBottom: 12},
  btnRetry:     {padding: 10},
  vazioText:    {color: '#94a3b8', fontSize: 14},
  card:         {backgroundColor: '#fff', borderRadius: 12, padding: 14, marginBottom: 10, elevation: 2},
  cardTopo:     {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4},
  numero:       {fontSize: 13, fontWeight: '700', color: '#1e3a5f'},
  badge:        {paddingHorizontal: 8, paddingVertical: 2, borderRadius: 10},
  badgeText:    {fontSize: 11, fontWeight: '700'},
  solicitante:  {fontSize: 13, color: '#374151', marginBottom: 2},
  categoria:    {fontSize: 11, color: '#6b7789', marginBottom: 4},
  justificativa:{fontSize: 12, color: '#6b7789', marginBottom: 8},
  rodape:       {flexDirection: 'row', alignItems: 'center'},
  priorBadge:   {paddingHorizontal: 6, paddingVertical: 1, borderRadius: 6},
  priorText:    {fontSize: 9, fontWeight: '700'},
  dataText:     {fontSize: 11, color: '#94a3b8', marginLeft: 8},
  valorText:    {fontSize: 12, fontWeight: '700', color: '#1e3a5f'},
  fab:          {position: 'absolute', bottom: 20, right: 20, width: 56, height: 56, borderRadius: 28, backgroundColor: CORES.azul700, alignItems: 'center', justifyContent: 'center', elevation: 6},
});
