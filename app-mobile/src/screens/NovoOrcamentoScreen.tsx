import React, {useState, useRef} from 'react';
import {
  View, Text, TextInput, TouchableOpacity, ScrollView,
  StyleSheet, ActivityIndicator, Alert, KeyboardAvoidingView, Platform,
} from 'react-native';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import {RootStackParamList} from '../navigation';
import {criarOrcamento, OrcamentoItemPayload} from '../api/orcamentos';
import {CORES} from '../config';
import Icon from 'react-native-vector-icons/MaterialIcons';

type Props = NativeStackScreenProps<RootStackParamList, 'NovoOrcamento'>;

const VALIDADES = [
  {value: 7,  label: '7 dias'},
  {value: 15, label: '15 dias'},
  {value: 30, label: '30 dias'},
  {value: 60, label: '60 dias'},
];

interface ItemForm {
  key: string;
  tipo: 'servico' | 'peca';
  descricao: string;
  quantidade: string;
  valor: string;
}

function novoItem(): ItemForm {
  return {key: String(Date.now()), tipo: 'servico', descricao: '', quantidade: '1', valor: ''};
}

export function NovoOrcamentoScreen({navigation}: Props) {
  const [clienteNome, setClienteNome]   = useState('');
  const [clienteTel, setClienteTel]     = useState('');
  const [clienteEmail, setClienteEmail] = useState('');
  const [validade, setValidade]         = useState(7);
  const [observacoes, setObservacoes]   = useState('');
  const [itens, setItens]               = useState<ItemForm[]>([novoItem()]);
  const [salvando, setSalvando]         = useState(false);
  const scrollRef = useRef<ScrollView>(null);

  function atualizarItem(key: string, campo: keyof ItemForm, valor: string) {
    setItens(prev => prev.map(i => i.key === key ? {...i, [campo]: valor} : i));
  }

  function toggleTipo(key: string) {
    setItens(prev => prev.map(i =>
      i.key === key ? {...i, tipo: i.tipo === 'servico' ? 'peca' : 'servico'} : i,
    ));
  }

  function removerItem(key: string) {
    if (itens.length === 1) { return; }
    setItens(prev => prev.filter(i => i.key !== key));
  }

  function adicionarItem() {
    setItens(prev => [...prev, novoItem()]);
    setTimeout(() => scrollRef.current?.scrollToEnd({animated: true}), 100);
  }

  async function criar() {
    if (!clienteNome.trim()) {
      Alert.alert('Campo obrigatório', 'Informe o nome do cliente.');
      return;
    }
    const itensFiltrados = itens.filter(i => i.descricao.trim());
    if (itensFiltrados.length === 0) {
      Alert.alert('Itens obrigatórios', 'Adicione ao menos um item com descrição.');
      return;
    }

    setSalvando(true);
    try {
      const payload: OrcamentoItemPayload[] = itensFiltrados.map(i => ({
        tipo: i.tipo,
        descricao: i.descricao.trim(),
        quantidade: parseFloat(i.quantidade) || 1,
        valor_unitario: parseFloat(i.valor) || 0,
      }));

      const resp = await criarOrcamento({
        cliente_nome: clienteNome.trim(),
        cliente_telefone: clienteTel.trim() || undefined,
        cliente_email: clienteEmail.trim() || undefined,
        validade_dias: validade,
        observacoes: observacoes.trim() || undefined,
        itens: payload,
      });

      Alert.alert(
        'Orçamento criado',
        `${resp.codigo} criado com sucesso.`,
        [{text: 'Ver orçamento', onPress: () => navigation.replace('OrcamentoDetalhe', {id: resp.id})}],
      );
    } catch (ex: any) {
      Alert.alert('Erro', ex.message ?? 'Não foi possível criar o orçamento.');
    } finally {
      setSalvando(false);
    }
  }

  return (
    <KeyboardAvoidingView style={{flex: 1}} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView ref={scrollRef} style={e.container} contentContainerStyle={{padding: 16, paddingBottom: 110}}>

        {/* Cliente */}
        <Text style={e.secTitulo}>Dados do cliente</Text>
        <Text style={e.label}>Nome <Text style={e.req}>*</Text></Text>
        <TextInput
          style={e.input}
          placeholder="Nome do cliente"
          value={clienteNome}
          onChangeText={setClienteNome}
          placeholderTextColor="#94a3b8"
        />
        <Text style={e.label}>Telefone</Text>
        <TextInput
          style={e.input}
          placeholder="(00) 00000-0000"
          keyboardType="phone-pad"
          value={clienteTel}
          onChangeText={setClienteTel}
          placeholderTextColor="#94a3b8"
        />
        <Text style={e.label}>E-mail</Text>
        <TextInput
          style={e.input}
          placeholder="cliente@email.com"
          keyboardType="email-address"
          autoCapitalize="none"
          value={clienteEmail}
          onChangeText={setClienteEmail}
          placeholderTextColor="#94a3b8"
        />

        {/* Validade */}
        <Text style={e.label}>Validade</Text>
        <View style={e.pills}>
          {VALIDADES.map(v => (
            <TouchableOpacity
              key={v.value}
              style={[e.pill, validade === v.value && {backgroundColor: CORES.azul700, borderColor: CORES.azul700}]}
              onPress={() => setValidade(v.value)}
            >
              <Text style={[e.pillText, validade === v.value && {color: '#fff', fontWeight: '700'}]}>
                {v.label}
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        {/* Observações */}
        <Text style={e.label}>Observações</Text>
        <TextInput
          style={[e.input, e.inputMulti]}
          multiline
          numberOfLines={2}
          placeholder="Condições, prazo de entrega, etc."
          value={observacoes}
          onChangeText={setObservacoes}
          placeholderTextColor="#94a3b8"
        />

        {/* Itens */}
        <View style={e.secHeader}>
          <Text style={e.secTitulo}>Itens <Text style={e.req}>*</Text></Text>
          <TouchableOpacity onPress={adicionarItem} style={e.btnAdd}>
            <Icon name="add" size={16} color={CORES.azul700} />
            <Text style={e.btnAddText}>Adicionar</Text>
          </TouchableOpacity>
        </View>

        {itens.map((item, idx) => (
          <View key={item.key} style={e.itemCard}>
            <View style={e.itemHeader}>
              <Text style={e.itemNum}>Item {idx + 1}</Text>
              <View style={e.itemHeaderRight}>
                {/* Toggle tipo */}
                <TouchableOpacity
                  style={[e.tipoBtn, {backgroundColor: item.tipo === 'servico' ? '#e8f1fc' : '#fdf3d9'}]}
                  onPress={() => toggleTipo(item.key)}
                >
                  <Text style={[e.tipoText, {color: item.tipo === 'servico' ? CORES.azul700 : '#d97706'}]}>
                    {item.tipo === 'servico' ? 'Serviço' : 'Peça'}
                  </Text>
                </TouchableOpacity>
                {itens.length > 1 && (
                  <TouchableOpacity onPress={() => removerItem(item.key)} style={{marginLeft: 8}}>
                    <Icon name="delete-outline" size={20} color="#dc2626" />
                  </TouchableOpacity>
                )}
              </View>
            </View>
            <TextInput
              style={e.input}
              placeholder="Descrição *"
              value={item.descricao}
              onChangeText={v => atualizarItem(item.key, 'descricao', v)}
              placeholderTextColor="#94a3b8"
            />
            <View style={e.itemRow}>
              <View style={{flex: 1, marginRight: 8}}>
                <Text style={e.labelSm}>Quantidade</Text>
                <TextInput
                  style={e.input}
                  keyboardType="numeric"
                  value={item.quantidade}
                  onChangeText={v => atualizarItem(item.key, 'quantidade', v)}
                  placeholderTextColor="#94a3b8"
                />
              </View>
              <View style={{flex: 1}}>
                <Text style={e.labelSm}>Valor unit. (R$)</Text>
                <TextInput
                  style={e.input}
                  keyboardType="numeric"
                  placeholder="0,00"
                  value={item.valor}
                  onChangeText={v => atualizarItem(item.key, 'valor', v)}
                  placeholderTextColor="#94a3b8"
                />
              </View>
            </View>
          </View>
        ))}
      </ScrollView>

      {/* Barra inferior */}
      <View style={e.bottomBar}>
        <TouchableOpacity style={e.btnCriar} onPress={criar} disabled={salvando}>
          {salvando
            ? <ActivityIndicator color="#fff" size="small" />
            : <Text style={e.btnCriarText}>Criar Orçamento</Text>
          }
        </TouchableOpacity>
      </View>
    </KeyboardAvoidingView>
  );
}

const e = StyleSheet.create({
  container:    {flex: 1, backgroundColor: '#f4f8fd'},
  secTitulo:    {fontSize: 15, fontWeight: '700', color: '#1e3a5f', marginTop: 6, marginBottom: 4},
  secHeader:    {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 18, marginBottom: 8},
  label:        {fontSize: 13, fontWeight: '600', color: '#374151', marginBottom: 6, marginTop: 12},
  labelSm:      {fontSize: 12, color: '#6b7789', marginBottom: 4},
  req:          {color: '#dc2626'},
  input:        {backgroundColor: '#fff', borderWidth: 1, borderColor: '#d1d5db', borderRadius: 10, padding: 10, fontSize: 14, color: '#1c2430'},
  inputMulti:   {minHeight: 64, textAlignVertical: 'top'},
  pills:        {flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 4},
  pill:         {paddingHorizontal: 14, paddingVertical: 6, borderRadius: 20, borderWidth: 1, borderColor: '#d1d5db', backgroundColor: '#f8fafc'},
  pillText:     {fontSize: 12, color: '#6b7789'},
  btnAdd:       {flexDirection: 'row', alignItems: 'center', gap: 4},
  btnAddText:   {fontSize: 13, color: CORES.azul700, fontWeight: '600'},
  itemCard:     {backgroundColor: '#fff', borderRadius: 12, padding: 12, marginBottom: 10, borderWidth: 1, borderColor: '#e5eaf2'},
  itemHeader:   {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8},
  itemHeaderRight: {flexDirection: 'row', alignItems: 'center'},
  itemNum:      {fontSize: 12, fontWeight: '700', color: '#6b7789'},
  tipoBtn:      {paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6},
  tipoText:     {fontSize: 11, fontWeight: '700'},
  itemRow:      {flexDirection: 'row', marginTop: 8},
  bottomBar:    {position: 'absolute', bottom: 0, left: 0, right: 0, backgroundColor: '#fff', borderTopWidth: 1, borderTopColor: '#eef1f5', padding: 12},
  btnCriar:     {backgroundColor: CORES.azul700, paddingVertical: 14, borderRadius: 12, alignItems: 'center'},
  btnCriarText: {color: '#fff', fontWeight: '700', fontSize: 15},
});
