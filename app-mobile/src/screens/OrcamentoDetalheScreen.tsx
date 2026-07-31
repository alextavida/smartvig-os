import React, {useState, useEffect} from 'react';
import {
  View, Text, ScrollView, StyleSheet, ActivityIndicator, TouchableOpacity,
} from 'react-native';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import {visualizarOrcamento} from '../api/orcamentos';
import {OrcamentoDetalhe} from '../types';
import {CORES} from '../config';
import {RootStackParamList} from '../navigation';

type Props = NativeStackScreenProps<RootStackParamList, 'OrcamentoDetalhe'>;

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
  convertido: 'Convertido em OS',
};

function formatarMoeda(v: number): string {
  return 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function formatarData(s: string): string {
  return new Date(s).toLocaleString('pt-BR', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'});
}

export function OrcamentoDetalheScreen({route}: Props) {
  const {id} = route.params;
  const [orc, setOrc]           = useState<OrcamentoDetalhe | null>(null);
  const [carregando, setCarregando] = useState(true);
  const [erro, setErro]         = useState('');

  useEffect(() => {
    (async () => {
      try {
        setOrc(await visualizarOrcamento(id));
      } catch (ex: any) {
        setErro(ex.message ?? 'Erro ao carregar.');
      } finally {
        setCarregando(false);
      }
    })();
  }, [id]);

  if (carregando) {
    return <ActivityIndicator size="large" color={CORES.azul700} style={{flex: 1, marginTop: 60}} />;
  }

  if (erro || !orc) {
    return (
      <View style={s.centro}>
        <Text style={{color: '#dc2626'}}>{erro || 'Orçamento não encontrado.'}</Text>
      </View>
    );
  }

  const cor = STATUS_COR[orc.status] ?? '#94a3b8';

  return (
    <ScrollView style={s.container} contentContainerStyle={{padding: 16, paddingBottom: 40}}>
      {/* Cabeçalho */}
      <View style={s.card}>
        <View style={s.row}>
          <Text style={s.codigo}>{orc.codigo}</Text>
          <View style={[s.badge, {backgroundColor: cor + '22'}]}>
            <Text style={[s.badgeText, {color: cor}]}>{STATUS_LABEL[orc.status] ?? orc.status}</Text>
          </View>
        </View>
        <Text style={s.secLabel}>Criado por</Text>
        <Text style={s.valor}>{orc.criado_por_nome ?? '—'}</Text>
        <Text style={s.secLabel}>Data</Text>
        <Text style={s.valor}>{formatarData(orc.criado_em)}</Text>
        <Text style={s.secLabel}>Validade</Text>
        <Text style={s.valor}>{orc.validade_dias} dias</Text>
        {orc.os_id_gerada ? (
          <>
            <Text style={s.secLabel}>OS gerada</Text>
            <Text style={s.valor}>#{orc.os_id_gerada}</Text>
          </>
        ) : null}
      </View>

      {/* Cliente */}
      <View style={s.card}>
        <Text style={s.secTitulo}>Cliente</Text>
        <Text style={s.clienteNome}>{orc.cliente_nome}</Text>
        {orc.cliente_telefone ? <Text style={s.campo}>{orc.cliente_telefone}</Text> : null}
        {orc.cliente_email    ? <Text style={s.campo}>{orc.cliente_email}</Text>    : null}
      </View>

      {/* Observações */}
      {orc.observacoes ? (
        <View style={s.card}>
          <Text style={s.secTitulo}>Observações</Text>
          <Text style={s.campo}>{orc.observacoes}</Text>
        </View>
      ) : null}

      {/* Itens */}
      <View style={s.card}>
        <Text style={s.secTitulo}>Itens</Text>
        {orc.itens.map((item, idx) => (
          <View key={item.id} style={[s.itemRow, idx < orc.itens.length - 1 && s.itemBorder]}>
            <View style={s.itemLeft}>
              <Text style={s.itemDesc}>{item.descricao}</Text>
              <View style={[s.tipoBadge, {backgroundColor: item.tipo === 'servico' ? '#e8f1fc' : '#fdf3d9'}]}>
                <Text style={[s.tipoText, {color: item.tipo === 'servico' ? CORES.azul700 : '#d97706'}]}>
                  {item.tipo === 'servico' ? 'Serviço' : 'Peça'}
                </Text>
              </View>
              <Text style={s.itemQtd}>{item.quantidade}x</Text>
            </View>
            <View style={s.itemRight}>
              <Text style={s.itemUni}>{formatarMoeda(item.valor_unitario)}</Text>
              <Text style={s.itemTotal}>{formatarMoeda(item.quantidade * item.valor_unitario)}</Text>
            </View>
          </View>
        ))}
        <View style={s.totalRow}>
          <Text style={s.totalLabel}>Total</Text>
          <Text style={s.totalValor}>{formatarMoeda(orc.total)}</Text>
        </View>
      </View>
    </ScrollView>
  );
}

const s = StyleSheet.create({
  container:  {flex: 1, backgroundColor: '#f4f8fd'},
  centro:     {flex: 1, justifyContent: 'center', alignItems: 'center'},
  card:       {backgroundColor: '#fff', borderRadius: 14, padding: 16, marginBottom: 12, elevation: 1},
  row:        {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12},
  codigo:     {fontSize: 18, fontWeight: '800', color: '#1e3a5f'},
  badge:      {paddingHorizontal: 10, paddingVertical: 3, borderRadius: 12},
  badgeText:  {fontSize: 12, fontWeight: '700'},
  secLabel:   {fontSize: 11, color: '#94a3b8', marginTop: 10, marginBottom: 2, textTransform: 'uppercase', letterSpacing: 0.5},
  valor:      {fontSize: 14, color: '#374151', fontWeight: '500'},
  secTitulo:  {fontSize: 14, fontWeight: '700', color: '#1e3a5f', marginBottom: 12},
  clienteNome:{fontSize: 16, fontWeight: '700', color: '#1e3a5f', marginBottom: 4},
  campo:      {fontSize: 14, color: '#374151', marginBottom: 2},
  itemRow:    {flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 10},
  itemBorder: {borderBottomWidth: 1, borderBottomColor: '#eef1f5'},
  itemLeft:   {flex: 1, marginRight: 12},
  itemDesc:   {fontSize: 14, color: '#374151', fontWeight: '500', marginBottom: 4},
  tipoBadge:  {alignSelf: 'flex-start', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 6, marginBottom: 2},
  tipoText:   {fontSize: 10, fontWeight: '700'},
  itemQtd:    {fontSize: 12, color: '#6b7789'},
  itemRight:  {alignItems: 'flex-end'},
  itemUni:    {fontSize: 12, color: '#6b7789'},
  itemTotal:  {fontSize: 14, fontWeight: '700', color: '#1e3a5f'},
  totalRow:   {flexDirection: 'row', justifyContent: 'space-between', borderTopWidth: 2, borderTopColor: CORES.azul700, paddingTop: 12, marginTop: 8},
  totalLabel: {fontSize: 15, fontWeight: '700', color: '#1e3a5f'},
  totalValor: {fontSize: 17, fontWeight: '800', color: CORES.azul700},
});
