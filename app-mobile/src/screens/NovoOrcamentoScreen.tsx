import React, {useState, useRef, useCallback} from 'react';
import {
  View, Text, TextInput, TouchableOpacity, ScrollView,
  StyleSheet, ActivityIndicator, Alert, KeyboardAvoidingView,
  Platform, FlatList,
} from 'react-native';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import {RootStackParamList} from '../navigation';
import {criarOrcamento, OrcamentoItemPayload} from '../api/orcamentos';
import {buscarClientes} from '../api/clientes';
import {ClienteGC} from '../types';
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
  return {key: String(Date.now() + Math.random()), tipo: 'servico', descricao: '', quantidade: '1', valor: ''};
}

export function NovoOrcamentoScreen({navigation}: Props) {
  // Cliente GC
  const [gcBusca, setGcBusca]                         = useState('');
  const [gcResultados, setGcResultados]               = useState<ClienteGC[]>([]);
  const [gcCarregando, setGcCarregando]               = useState(false);
  const [gcSelecionado, setGcSelecionado]             = useState<ClienteGC | null>(null);
  const timerGc                                        = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Campos do cliente (preenchidos via GC ou manualmente)
  const [clienteNome, setClienteNome]     = useState('');
  const [clienteTel, setClienteTel]       = useState('');
  const [clienteEmail, setClienteEmail]   = useState('');

  // Formulário
  const [validade, setValidade]           = useState(7);
  const [observacoes, setObservacoes]     = useState('');
  const [itens, setItens]                 = useState<ItemForm[]>([novoItem()]);
  const [salvando, setSalvando]           = useState(false);
  const scrollRef = useRef<ScrollView>(null);

  const buscarGC = useCallback((texto: string) => {
    setGcBusca(texto);
    if (timerGc.current) { clearTimeout(timerGc.current); }
    if (texto.length < 2) { setGcResultados([]); return; }
    timerGc.current = setTimeout(async () => {
      setGcCarregando(true);
      try {
        const res = await buscarClientes(texto);
        setGcResultados(res);
      } catch {
        setGcResultados([]);
      } finally {
        setGcCarregando(false);
      }
    }, 350);
  }, []);

  function selecionarCliente(c: ClienteGC) {
    setGcSelecionado(c);
    setClienteNome(c.nome);
    setClienteTel(c.telefone);
    setClienteEmail(c.email);
    setGcBusca('');
    setGcResultados([]);
  }

  function limparCliente() {
    setGcSelecionado(null);
    setClienteNome('');
    setClienteTel('');
    setClienteEmail('');
  }

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
    const nome = clienteNome.trim();
    if (!nome) {
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
        cliente_nome:     nome,
        cliente_telefone: clienteTel.trim() || undefined,
        cliente_email:    clienteEmail.trim() || undefined,
        gc_cliente_id:    (gcSelecionado?.id ?? null) ? (gcSelecionado!.id as number) : undefined,
        validade_dias:    validade,
        observacoes:      observacoes.trim() || undefined,
        itens:            payload,
      });

      const msg = gcSelecionado
        ? `${resp.codigo} criado e enviado ao GestãoClick.`
        : `${resp.codigo} criado localmente.\n\nDica: selecione um cliente do GestãoClick para sincronizar automaticamente.`;

      Alert.alert('Orçamento criado', msg, [
        {text: 'Ver orçamento', onPress: () => navigation.replace('OrcamentoDetalhe', {id: resp.id})},
      ]);
    } catch (ex: any) {
      Alert.alert('Erro', ex.message ?? 'Não foi possível criar o orçamento.');
    } finally {
      setSalvando(false);
    }
  }

  return (
    <KeyboardAvoidingView style={{flex: 1}} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView
        ref={scrollRef}
        style={e.container}
        contentContainerStyle={{padding: 16, paddingBottom: 110}}
        keyboardShouldPersistTaps="handled"
      >

        {/* ── Busca de cliente GestãoClick ── */}
        <Text style={e.secTitulo}>Cliente</Text>

        {gcSelecionado ? (
          /* Cliente GC selecionado — chip */
          <View style={e.gcChip}>
            <View style={{flex: 1}}>
              <Text style={e.gcChipNome}>{gcSelecionado.nome}</Text>
              {gcSelecionado.telefone ? <Text style={e.gcChipSub}>{gcSelecionado.telefone}</Text> : null}
              <View style={e.gcBadge}>
                <Icon name="check-circle" size={11} color="#16803c" />
                <Text style={e.gcBadgeText}>Vinculado ao GestãoClick</Text>
              </View>
            </View>
            <TouchableOpacity onPress={limparCliente} style={{padding: 4}}>
              <Icon name="close" size={20} color="#64748b" />
            </TouchableOpacity>
          </View>
        ) : (
          /* Campo de busca GC */
          <View>
            <View style={e.gcSearchWrap}>
              <Icon name="search" size={18} color="#94a3b8" style={{marginRight: 8}} />
              <TextInput
                style={e.gcSearchInput}
                placeholder="Buscar cliente no GestãoClick..."
                placeholderTextColor="#94a3b8"
                value={gcBusca}
                onChangeText={buscarGC}
                autoCapitalize="words"
              />
              {gcCarregando && <ActivityIndicator size="small" color={CORES.azul700} />}
            </View>
            {gcResultados.length > 0 && (
              <View style={e.gcDropdown}>
                {gcResultados.map(c => (
                  <TouchableOpacity
                    key={String(c.id)}
                    style={e.gcItem}
                    onPress={() => selecionarCliente(c)}
                  >
                    <Text style={e.gcItemNome}>{c.nome}</Text>
                    {(c.telefone || c.endereco) ? (
                      <Text style={e.gcItemSub} numberOfLines={1}>
                        {[c.telefone, c.endereco].filter(Boolean).join(' · ')}
                      </Text>
                    ) : null}
                  </TouchableOpacity>
                ))}
              </View>
            )}
            {gcBusca.length >= 2 && !gcCarregando && gcResultados.length === 0 && (
              <Text style={e.gcVazio}>Nenhum cliente encontrado. Preencha manualmente abaixo.</Text>
            )}
          </View>
        )}

        {/* Campos de cliente (auto-preenchidos ou manuais) */}
        <Text style={e.label}>
          Nome <Text style={e.req}>*</Text>
          {!gcSelecionado && <Text style={e.labelHint}> (preenchimento manual)</Text>}
        </Text>
        <TextInput
          style={[e.input, gcSelecionado && e.inputReadOnly]}
          placeholder="Nome do cliente"
          value={clienteNome}
          onChangeText={gcSelecionado ? undefined : setClienteNome}
          editable={!gcSelecionado}
          placeholderTextColor="#94a3b8"
        />

        <Text style={e.label}>Telefone</Text>
        <TextInput
          style={[e.input, gcSelecionado && e.inputReadOnly]}
          placeholder="(00) 00000-0000"
          keyboardType="phone-pad"
          value={clienteTel}
          onChangeText={gcSelecionado ? undefined : setClienteTel}
          editable={!gcSelecionado}
          placeholderTextColor="#94a3b8"
        />

        <Text style={e.label}>E-mail</Text>
        <TextInput
          style={[e.input, gcSelecionado && e.inputReadOnly]}
          placeholder="cliente@email.com"
          keyboardType="email-address"
          autoCapitalize="none"
          value={clienteEmail}
          onChangeText={gcSelecionado ? undefined : setClienteEmail}
          editable={!gcSelecionado}
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
        {!gcSelecionado && (
          <Text style={e.avisoGC}>
            Selecione um cliente GC para sincronizar com o GestãoClick
          </Text>
        )}
        <TouchableOpacity style={e.btnCriar} onPress={criar} disabled={salvando}>
          {salvando
            ? <ActivityIndicator color="#fff" size="small" />
            : <Text style={e.btnCriarText}>
                {gcSelecionado ? 'Criar e Enviar ao GestãoClick' : 'Criar Orçamento'}
              </Text>
          }
        </TouchableOpacity>
      </View>
    </KeyboardAvoidingView>
  );
}

const e = StyleSheet.create({
  container:      {flex: 1, backgroundColor: '#f4f8fd'},
  secTitulo:      {fontSize: 15, fontWeight: '700', color: '#1e3a5f', marginTop: 6, marginBottom: 8},
  secHeader:      {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 18, marginBottom: 8},
  label:          {fontSize: 13, fontWeight: '600', color: '#374151', marginBottom: 6, marginTop: 12},
  labelHint:      {fontSize: 11, fontWeight: '400', color: '#94a3b8'},
  labelSm:        {fontSize: 12, color: '#6b7789', marginBottom: 4},
  req:            {color: '#dc2626'},
  input:          {backgroundColor: '#fff', borderWidth: 1, borderColor: '#d1d5db', borderRadius: 10, padding: 10, fontSize: 14, color: '#1c2430'},
  inputReadOnly:  {backgroundColor: '#f0f4f8', color: '#6b7789'},
  inputMulti:     {minHeight: 64, textAlignVertical: 'top'},
  pills:          {flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 4},
  pill:           {paddingHorizontal: 14, paddingVertical: 6, borderRadius: 20, borderWidth: 1, borderColor: '#d1d5db', backgroundColor: '#f8fafc'},
  pillText:       {fontSize: 12, color: '#6b7789'},
  btnAdd:         {flexDirection: 'row', alignItems: 'center', gap: 4},
  btnAddText:     {fontSize: 13, color: CORES.azul700, fontWeight: '600'},
  itemCard:       {backgroundColor: '#fff', borderRadius: 12, padding: 12, marginBottom: 10, borderWidth: 1, borderColor: '#e5eaf2'},
  itemHeader:     {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8},
  itemHeaderRight:{flexDirection: 'row', alignItems: 'center'},
  itemNum:        {fontSize: 12, fontWeight: '700', color: '#6b7789'},
  tipoBtn:        {paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6},
  tipoText:       {fontSize: 11, fontWeight: '700'},
  itemRow:        {flexDirection: 'row', marginTop: 8},
  // GC search
  gcSearchWrap:   {flexDirection: 'row', alignItems: 'center', backgroundColor: '#fff', borderWidth: 1.5, borderColor: CORES.azul700, borderRadius: 10, paddingHorizontal: 10, paddingVertical: 8, marginBottom: 4},
  gcSearchInput:  {flex: 1, fontSize: 14, color: '#1c2430', padding: 0},
  gcDropdown:     {backgroundColor: '#fff', borderWidth: 1, borderColor: '#d1d5db', borderRadius: 10, marginBottom: 8, overflow: 'hidden', elevation: 4},
  gcItem:         {padding: 12, borderBottomWidth: 1, borderBottomColor: '#f1f5f9'},
  gcItemNome:     {fontSize: 13, fontWeight: '700', color: '#1e3a5f'},
  gcItemSub:      {fontSize: 11, color: '#64748b', marginTop: 2},
  gcVazio:        {fontSize: 12, color: '#94a3b8', marginBottom: 8, fontStyle: 'italic'},
  gcChip:         {flexDirection: 'row', alignItems: 'center', backgroundColor: '#f0fdf4', borderWidth: 1.5, borderColor: '#16803c', borderRadius: 12, padding: 12, marginBottom: 8},
  gcChipNome:     {fontSize: 14, fontWeight: '700', color: '#1e3a5f'},
  gcChipSub:      {fontSize: 12, color: '#64748b', marginTop: 2},
  gcBadge:        {flexDirection: 'row', alignItems: 'center', gap: 4, marginTop: 4},
  gcBadgeText:    {fontSize: 11, color: '#16803c', fontWeight: '600'},
  // Bottom bar
  bottomBar:      {position: 'absolute', bottom: 0, left: 0, right: 0, backgroundColor: '#fff', borderTopWidth: 1, borderTopColor: '#eef1f5', padding: 12, paddingBottom: 16},
  avisoGC:        {fontSize: 11, color: '#94a3b8', textAlign: 'center', marginBottom: 8},
  btnCriar:       {backgroundColor: CORES.azul700, paddingVertical: 14, borderRadius: 12, alignItems: 'center'},
  btnCriarText:   {color: '#fff', fontWeight: '700', fontSize: 15},
});
