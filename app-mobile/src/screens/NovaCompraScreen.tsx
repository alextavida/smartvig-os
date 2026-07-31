import React, {useState, useRef} from 'react';
import {
  View, Text, TextInput, TouchableOpacity, ScrollView,
  StyleSheet, ActivityIndicator, Alert, KeyboardAvoidingView, Platform,
} from 'react-native';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import {RootStackParamList} from '../navigation';
import {criarCompra, ItemCompra} from '../api/compras';
import {CORES} from '../config';
import Icon from 'react-native-vector-icons/MaterialIcons';

type Props = NativeStackScreenProps<RootStackParamList, 'NovaCompra'>;

const PRIORIDADES = [
  {value: 'baixa',   label: 'Baixa',   cor: '#94a3b8'},
  {value: 'media',   label: 'Média',   cor: '#2563eb'},
  {value: 'alta',    label: 'Alta',    cor: '#d97706'},
  {value: 'urgente', label: 'Urgente', cor: '#dc2626'},
] as const;

const DESTINOS = [
  {value: 'estoque',    label: 'Estoque'},
  {value: 'cliente',    label: 'Cliente'},
  {value: 'manutencao', label: 'Manutenção'},
  {value: 'obra',       label: 'Obra'},
  {value: 'condominio', label: 'Condomínio'},
  {value: 'veiculo',    label: 'Veículo'},
  {value: 'outro',      label: 'Outro'},
];

interface ItemForm {
  key: string;
  nome: string;
  quantidade: string;
  valor: string;
}

function novoItem(): ItemForm {
  return {key: String(Date.now()), nome: '', quantidade: '1', valor: ''};
}

export function NovaCompraScreen({navigation}: Props) {
  const [justificativa, setJustificativa] = useState('');
  const [prioridade, setPrioridade] = useState<'baixa' | 'media' | 'alta' | 'urgente'>('media');
  const [destino, setDestino] = useState('estoque');
  const [destinoRef, setDestinoRef] = useState('');
  const [observacoes, setObservacoes] = useState('');
  const [itens, setItens] = useState<ItemForm[]>([novoItem()]);
  const [salvando, setSalvando] = useState(false);
  const scrollRef = useRef<ScrollView>(null);

  function atualizarItem(key: string, campo: keyof ItemForm, valor: string) {
    setItens(prev => prev.map(i => i.key === key ? {...i, [campo]: valor} : i));
  }

  function removerItem(key: string) {
    if (itens.length === 1) { return; }
    setItens(prev => prev.filter(i => i.key !== key));
  }

  function adicionarItem() {
    setItens(prev => [...prev, novoItem()]);
    setTimeout(() => scrollRef.current?.scrollToEnd({animated: true}), 100);
  }

  async function salvar(enviar: boolean) {
    if (!justificativa.trim()) {
      Alert.alert('Campo obrigatório', 'Informe a justificativa da solicitação.');
      return;
    }
    const itensFiltrados = itens.filter(i => i.nome.trim());
    if (itensFiltrados.length === 0) {
      Alert.alert('Itens obrigatórios', 'Adicione ao menos um item com descrição.');
      return;
    }

    setSalvando(true);
    try {
      const payload: ItemCompra[] = itensFiltrados.map(i => ({
        produto_nome: i.nome.trim(),
        quantidade: parseFloat(i.quantidade) || 1,
        valor_estimado: i.valor ? parseFloat(i.valor) : undefined,
      }));

      const resp = await criarCompra({
        justificativa: justificativa.trim(),
        prioridade,
        destino,
        destino_referencia: destinoRef.trim() || undefined,
        observacoes: observacoes.trim() || undefined,
        itens: payload,
        enviar,
      });

      Alert.alert(
        'Sucesso',
        enviar ? `Solicitação ${resp.numero} enviada para aprovação.` : `Rascunho ${resp.numero} salvo.`,
        [{text: 'OK', onPress: () => navigation.navigate('CompraDetalhe', {id: resp.id})}],
      );
    } catch (e: any) {
      Alert.alert('Erro', e.message ?? 'Não foi possível criar a solicitação.');
    } finally {
      setSalvando(false);
    }
  }

  return (
    <KeyboardAvoidingView style={{flex: 1}} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView ref={scrollRef} style={e.container} contentContainerStyle={{padding: 16, paddingBottom: 120}}>

        {/* Justificativa */}
        <Text style={e.label}>Justificativa <Text style={e.req}>*</Text></Text>
        <TextInput
          style={[e.input, e.inputMulti]}
          multiline
          numberOfLines={3}
          placeholder="Descreva o motivo da solicitação..."
          value={justificativa}
          onChangeText={setJustificativa}
          placeholderTextColor="#94a3b8"
        />

        {/* Prioridade */}
        <Text style={e.label}>Prioridade</Text>
        <View style={e.pills}>
          {PRIORIDADES.map(p => (
            <TouchableOpacity
              key={p.value}
              style={[e.pill, prioridade === p.value && {backgroundColor: p.cor, borderColor: p.cor}]}
              onPress={() => setPrioridade(p.value)}
            >
              <Text style={[e.pillText, prioridade === p.value && {color: '#fff', fontWeight: '700'}]}>
                {p.label}
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        {/* Destino */}
        <Text style={e.label}>Destino</Text>
        <View style={e.pills}>
          {DESTINOS.map(d => (
            <TouchableOpacity
              key={d.value}
              style={[e.pill, destino === d.value && {backgroundColor: CORES.azul700, borderColor: CORES.azul700}]}
              onPress={() => setDestino(d.value)}
            >
              <Text style={[e.pillText, destino === d.value && {color: '#fff', fontWeight: '700'}]}>
                {d.label}
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        {/* Referência */}
        <Text style={e.label}>Referência do destino</Text>
        <TextInput
          style={e.input}
          placeholder="Ex: OS #123, Cliente Fulano..."
          value={destinoRef}
          onChangeText={setDestinoRef}
          placeholderTextColor="#94a3b8"
        />

        {/* Observações */}
        <Text style={e.label}>Observações</Text>
        <TextInput
          style={[e.input, e.inputMulti]}
          multiline
          numberOfLines={2}
          placeholder="Informações adicionais..."
          value={observacoes}
          onChangeText={setObservacoes}
          placeholderTextColor="#94a3b8"
        />

        {/* Itens */}
        <View style={e.secHeader}>
          <Text style={e.secTitulo}>Itens <Text style={e.req}>*</Text></Text>
          <TouchableOpacity onPress={adicionarItem} style={e.btnAddItem}>
            <Icon name="add" size={16} color={CORES.azul700} />
            <Text style={e.btnAddItemText}>Adicionar</Text>
          </TouchableOpacity>
        </View>

        {itens.map((item, idx) => (
          <View key={item.key} style={e.itemCard}>
            <View style={e.itemHeader}>
              <Text style={e.itemNum}>Item {idx + 1}</Text>
              {itens.length > 1 && (
                <TouchableOpacity onPress={() => removerItem(item.key)}>
                  <Icon name="delete-outline" size={20} color="#dc2626" />
                </TouchableOpacity>
              )}
            </View>
            <TextInput
              style={e.input}
              placeholder="Descrição do produto/serviço *"
              value={item.nome}
              onChangeText={v => atualizarItem(item.key, 'nome', v)}
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
                <Text style={e.labelSm}>Valor estimado (R$)</Text>
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
        <TouchableOpacity
          style={[e.btn, e.btnSecundario]}
          onPress={() => salvar(false)}
          disabled={salvando}
        >
          {salvando ? <ActivityIndicator color={CORES.azul700} size="small" /> : <Text style={e.btnSecText}>Salvar Rascunho</Text>}
        </TouchableOpacity>
        <TouchableOpacity
          style={[e.btn, e.btnPrimario]}
          onPress={() => salvar(true)}
          disabled={salvando}
        >
          <Text style={e.btnPrimText}>Enviar para Aprovação</Text>
        </TouchableOpacity>
      </View>
    </KeyboardAvoidingView>
  );
}

const e = StyleSheet.create({
  container:    {flex: 1, backgroundColor: '#f4f8fd'},
  label:        {fontSize: 13, fontWeight: '600', color: '#374151', marginBottom: 6, marginTop: 14},
  labelSm:      {fontSize: 12, color: '#6b7789', marginBottom: 4},
  req:          {color: '#dc2626'},
  input:        {backgroundColor: '#fff', borderWidth: 1, borderColor: '#d1d5db', borderRadius: 10, padding: 10, fontSize: 14, color: '#1c2430'},
  inputMulti:   {minHeight: 72, textAlignVertical: 'top'},
  pills:        {flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 4},
  pill:         {paddingHorizontal: 12, paddingVertical: 6, borderRadius: 20, borderWidth: 1, borderColor: '#d1d5db', backgroundColor: '#f8fafc'},
  pillText:     {fontSize: 12, color: '#6b7789'},
  secHeader:    {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 18, marginBottom: 8},
  secTitulo:    {fontSize: 15, fontWeight: '700', color: '#1e3a5f'},
  btnAddItem:   {flexDirection: 'row', alignItems: 'center', gap: 4},
  btnAddItemText:{fontSize: 13, color: CORES.azul700, fontWeight: '600'},
  itemCard:     {backgroundColor: '#fff', borderRadius: 12, padding: 12, marginBottom: 10, borderWidth: 1, borderColor: '#e5eaf2'},
  itemHeader:   {flexDirection: 'row', justifyContent: 'space-between', marginBottom: 8},
  itemNum:      {fontSize: 12, fontWeight: '700', color: '#6b7789'},
  itemRow:      {flexDirection: 'row', marginTop: 8},
  bottomBar:    {position: 'absolute', bottom: 0, left: 0, right: 0, backgroundColor: '#fff', borderTopWidth: 1, borderTopColor: '#eef1f5', padding: 12, flexDirection: 'row', gap: 10},
  btn:          {flex: 1, paddingVertical: 13, borderRadius: 10, alignItems: 'center'},
  btnPrimario:  {backgroundColor: CORES.azul700},
  btnSecundario:{backgroundColor: '#fff', borderWidth: 1, borderColor: CORES.azul700},
  btnPrimText:  {color: '#fff', fontWeight: '700', fontSize: 13},
  btnSecText:   {color: CORES.azul700, fontWeight: '700', fontSize: 13},
});
