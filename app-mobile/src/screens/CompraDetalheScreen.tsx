import React, {useState, useEffect, useCallback} from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  TextInput,
  Modal,
} from 'react-native';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import {
  visualizarCompra,
  aprovarCompra,
  reprovarCompra,
  devolverCompra,
  cancelarCompra,
  enviarParaAprovacao,
} from '../api/compras';
import {SolicitacaoCompraDetalhe} from '../types';
import {useAuth} from '../hooks/useAuth';
import {temRoleCompras} from '../types';
import {CORES} from '../config';
import {RootStackParamList} from '../navigation';

type Props = NativeStackScreenProps<RootStackParamList, 'CompraDetalhe'>;

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
  aguardando_aprovacao: 'Aguardando Aprovação',
  aprovado: 'Aprovado',
  reprovado: 'Reprovado',
  devolvido: 'Devolvido',
  em_compra: 'Em Compra',
  recebido: 'Recebido',
  concluido: 'Concluído',
  cancelado: 'Cancelado',
};

function formatarMoeda(v: number | null | undefined): string {
  if (v == null) return '—';
  return 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function formatarData(s: string | null | undefined): string {
  if (!s) return '—';
  const d = new Date(s);
  return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
}

export function CompraDetalheScreen({navigation, route}: Props) {
  const {id} = route.params;
  const {usuario} = useAuth();
  const [compra, setCompra] = useState<SolicitacaoCompraDetalhe | null>(null);
  const [carregando, setCarregando] = useState(true);
  const [processando, setProcessando] = useState(false);
  const [modalMotivo, setModalMotivo] = useState<'reprovar' | 'devolver' | null>(null);
  const [motivo, setMotivo] = useState('');

  const carregar = useCallback(async () => {
    setCarregando(true);
    try {
      const d = await visualizarCompra(id);
      setCompra(d);
    } catch (e: any) {
      Alert.alert('Erro', e.message ?? 'Erro ao carregar.');
      navigation.goBack();
    } finally {
      setCarregando(false);
    }
  }, [id]);

  useEffect(() => {
    navigation.setOptions({title: `SC #${id}`});
    carregar();
  }, [carregar]);

  const executar = async (acao: () => Promise<void>, msg: string) => {
    setProcessando(true);
    try {
      await acao();
      Alert.alert('Sucesso', msg, [{text: 'OK', onPress: () => carregar()}]);
    } catch (e: any) {
      Alert.alert('Erro', e.message ?? 'Erro.');
    } finally {
      setProcessando(false);
    }
  };

  const confirmarAprovar = () => {
    Alert.alert('Aprovar solicitação', 'Confirma a aprovação?', [
      {text: 'Cancelar', style: 'cancel'},
      {text: 'Aprovar', onPress: () => executar(() => aprovarCompra(id), 'Solicitação aprovada!')},
    ]);
  };

  const confirmarCancelar = () => {
    Alert.alert('Cancelar solicitação', 'Deseja cancelar esta solicitação?', [
      {text: 'Não', style: 'cancel'},
      {text: 'Cancelar SC', style: 'destructive', onPress: () => executar(() => cancelarCompra(id), 'Solicitação cancelada.')},
    ]);
  };

  const enviar = () => {
    Alert.alert('Enviar para aprovação', 'Enviar esta solicitação para aprovação?', [
      {text: 'Não', style: 'cancel'},
      {text: 'Enviar', onPress: () => executar(() => enviarParaAprovacao(id), 'Enviado para aprovação!')},
    ]);
  };

  const submitMotivo = async () => {
    if (!motivo.trim()) { Alert.alert('Informe o motivo.'); return; }
    const acao = modalMotivo === 'reprovar'
      ? () => reprovarCompra(id, motivo)
      : () => devolverCompra(id, motivo);
    const msg = modalMotivo === 'reprovar' ? 'Reprovado.' : 'Devolvido para ajuste.';
    setModalMotivo(null);
    setMotivo('');
    await executar(acao, msg);
  };

  if (carregando || !compra) {
    return (
      <View style={{flex: 1, justifyContent: 'center', alignItems: 'center'}}>
        <ActivityIndicator size="large" color={CORES.azul700} />
      </View>
    );
  }

  const isAprovador = usuario ? temRoleCompras(usuario, 'aprovador') : false;
  const isMeuRascunho = compra.status === 'rascunho' && compra.solicitante_nome === usuario?.nome;

  return (
    <ScrollView style={estilos.container} contentContainerStyle={{padding: 14}}>
      {/* Header */}
      <View style={estilos.card}>
        <View style={estilos.row}>
          <Text style={estilos.numero}>{compra.numero}</Text>
          <View style={[estilos.badge, {backgroundColor: STATUS_COR[compra.status] + '22'}]}>
            <Text style={[estilos.badgeText, {color: STATUS_COR[compra.status]}]}>
              {STATUS_LABEL[compra.status] ?? compra.status}
            </Text>
          </View>
        </View>
        <Text style={estilos.label}>Solicitante</Text>
        <Text style={estilos.value}>{compra.solicitante_nome}</Text>
        <Text style={estilos.label}>Justificativa</Text>
        <Text style={estilos.value}>{compra.justificativa}</Text>
        {compra.observacoes && <>
          <Text style={estilos.label}>Observações</Text>
          <Text style={estilos.value}>{compra.observacoes}</Text>
        </>}
        <View style={estilos.row2}>
          <View style={{flex: 1}}>
            <Text style={estilos.label}>Valor estimado</Text>
            <Text style={estilos.value}>{formatarMoeda(compra.valor_estimado)}</Text>
          </View>
          {compra.valor_final != null && <View style={{flex: 1}}>
            <Text style={estilos.label}>Valor final</Text>
            <Text style={[estilos.value, {color: '#1e8e5a', fontWeight: '700'}]}>{formatarMoeda(compra.valor_final)}</Text>
          </View>}
        </View>
        <Text style={estilos.label}>Criado em</Text>
        <Text style={estilos.value}>{formatarData(compra.criado_em)}</Text>
      </View>

      {/* Itens */}
      {compra.itens?.length > 0 && (
        <View style={estilos.card}>
          <Text style={estilos.secTitle}>Itens ({compra.itens.length})</Text>
          {compra.itens.map(item => (
            <View key={item.id} style={estilos.itemRow}>
              <Text style={estilos.itemDesc}>{item.descricao}</Text>
              <View style={estilos.row}>
                <Text style={estilos.itemQtd}>{item.quantidade} {item.unidade ?? 'un'}</Text>
                <Text style={estilos.itemValor}>{formatarMoeda(item.valor_unitario_final ?? item.valor_unitario_estimado)}</Text>
              </View>
            </View>
          ))}
        </View>
      )}

      {/* Compra info */}
      {compra.fornecedor_nome && (
        <View style={estilos.card}>
          <Text style={estilos.secTitle}>Informações de Compra</Text>
          <Text style={estilos.label}>Fornecedor</Text>
          <Text style={estilos.value}>{compra.fornecedor_nome}</Text>
          {compra.valor_negociado != null && <>
            <Text style={estilos.label}>Valor negociado</Text>
            <Text style={estilos.value}>{formatarMoeda(compra.valor_negociado)}</Text>
          </>}
          {compra.prazo_entrega && <>
            <Text style={estilos.label}>Prazo de entrega</Text>
            <Text style={estilos.value}>{compra.prazo_entrega}</Text>
          </>}
          {compra.numero_pedido && <>
            <Text style={estilos.label}>Nº do pedido</Text>
            <Text style={estilos.value}>{compra.numero_pedido}</Text>
          </>}
        </View>
      )}

      {/* Aprovação info */}
      {compra.aprovador_nome && (
        <View style={estilos.card}>
          <Text style={estilos.secTitle}>Aprovação</Text>
          <Text style={estilos.label}>Aprovador</Text>
          <Text style={estilos.value}>{compra.aprovador_nome}</Text>
          {compra.aprovado_em && <>
            <Text style={estilos.label}>Data</Text>
            <Text style={estilos.value}>{formatarData(compra.aprovado_em)}</Text>
          </>}
          {compra.motivo_reprovacao && <>
            <Text style={estilos.label}>Motivo</Text>
            <Text style={[estilos.value, {color: '#dc2626'}]}>{compra.motivo_reprovacao}</Text>
          </>}
        </View>
      )}

      {/* Ações */}
      {!processando && (
        <View style={estilos.card}>
          <Text style={estilos.secTitle}>Ações</Text>
          {isMeuRascunho && (
            <TouchableOpacity style={[estilos.btn, {backgroundColor: CORES.azul700}]} onPress={enviar}>
              <Text style={estilos.btnText}>Enviar para Aprovação</Text>
            </TouchableOpacity>
          )}
          {isAprovador && compra.status === 'aguardando_aprovacao' && (<>
            <TouchableOpacity style={[estilos.btn, {backgroundColor: '#059669'}]} onPress={confirmarAprovar}>
              <Text style={estilos.btnText}>Aprovar</Text>
            </TouchableOpacity>
            <TouchableOpacity style={[estilos.btn, {backgroundColor: '#d97706'}]} onPress={() => { setMotivo(''); setModalMotivo('devolver'); }}>
              <Text style={estilos.btnText}>Devolver para Ajuste</Text>
            </TouchableOpacity>
            <TouchableOpacity style={[estilos.btn, {backgroundColor: '#dc2626'}]} onPress={() => { setMotivo(''); setModalMotivo('reprovar'); }}>
              <Text style={estilos.btnText}>Reprovar</Text>
            </TouchableOpacity>
          </>)}
          {['rascunho', 'devolvido'].includes(compra.status) && (
            <TouchableOpacity style={[estilos.btn, {backgroundColor: '#64748b'}]} onPress={confirmarCancelar}>
              <Text style={estilos.btnText}>Cancelar Solicitação</Text>
            </TouchableOpacity>
          )}
        </View>
      )}
      {processando && <ActivityIndicator color={CORES.azul700} style={{marginVertical: 16}} />}

      {/* Histórico */}
      {compra.historico?.length > 0 && (
        <View style={estilos.card}>
          <Text style={estilos.secTitle}>Histórico</Text>
          {compra.historico.map(h => (
            <View key={h.id} style={estilos.histItem}>
              <Text style={estilos.histAcao}>{h.acao}</Text>
              {h.detalhe && <Text style={estilos.histDetalhe}>{h.detalhe}</Text>}
              <Text style={estilos.histMeta}>{h.usuario_nome ?? 'Sistema'} · {formatarData(h.criado_em)}</Text>
            </View>
          ))}
        </View>
      )}

      {/* Modal motivo */}
      <Modal visible={!!modalMotivo} transparent animationType="slide">
        <View style={estilos.modalOverlay}>
          <View style={estilos.modalBox}>
            <Text style={estilos.modalTitle}>
              {modalMotivo === 'reprovar' ? 'Motivo da Reprovação' : 'Motivo da Devolução'}
            </Text>
            <TextInput
              style={estilos.textarea}
              multiline
              numberOfLines={4}
              placeholder="Descreva o motivo..."
              value={motivo}
              onChangeText={setMotivo}
            />
            <View style={estilos.row}>
              <TouchableOpacity style={[estilos.btn, {flex: 1, backgroundColor: '#64748b'}]} onPress={() => setModalMotivo(null)}>
                <Text style={estilos.btnText}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity style={[estilos.btn, {flex: 1, backgroundColor: modalMotivo === 'reprovar' ? '#dc2626' : '#d97706'}]} onPress={submitMotivo}>
                <Text style={estilos.btnText}>Confirmar</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </ScrollView>
  );
}

const estilos = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#f4f8fd'},
  card: {backgroundColor: '#fff', borderRadius: 12, padding: 14, marginBottom: 10, shadowColor: '#000', shadowOpacity: 0.06, shadowRadius: 6, elevation: 2},
  row: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6},
  row2: {flexDirection: 'row', marginTop: 4},
  numero: {fontSize: 15, fontWeight: '700', color: '#1e3a5f'},
  badge: {paddingHorizontal: 10, paddingVertical: 3, borderRadius: 12},
  badgeText: {fontSize: 11, fontWeight: '700'},
  label: {fontSize: 11, color: '#94a3b8', marginTop: 8, marginBottom: 2, fontWeight: '600', textTransform: 'uppercase', letterSpacing: 0.5},
  value: {fontSize: 13, color: '#374151'},
  secTitle: {fontSize: 13, fontWeight: '700', color: '#1e3a5f', marginBottom: 10},
  itemRow: {paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: '#f0f4f8'},
  itemDesc: {fontSize: 13, color: '#374151', marginBottom: 4},
  itemQtd: {fontSize: 12, color: '#6b7789'},
  itemValor: {fontSize: 12, fontWeight: '700', color: '#1e3a5f'},
  btn: {borderRadius: 8, padding: 12, alignItems: 'center', marginBottom: 8},
  btnText: {color: '#fff', fontWeight: '700', fontSize: 13},
  histItem: {paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: '#f0f4f8'},
  histAcao: {fontSize: 13, fontWeight: '600', color: '#374151'},
  histDetalhe: {fontSize: 12, color: '#6b7789', marginTop: 2},
  histMeta: {fontSize: 11, color: '#94a3b8', marginTop: 4},
  modalOverlay: {flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'flex-end'},
  modalBox: {backgroundColor: '#fff', borderTopLeftRadius: 20, borderTopRightRadius: 20, padding: 20},
  modalTitle: {fontSize: 15, fontWeight: '700', color: '#1e3a5f', marginBottom: 12},
  textarea: {borderWidth: 1, borderColor: '#e2e8f0', borderRadius: 8, padding: 10, minHeight: 100, textAlignVertical: 'top', marginBottom: 12, fontSize: 13},
});
