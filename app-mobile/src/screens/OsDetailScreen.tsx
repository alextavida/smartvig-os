import React, {useState, useEffect, useRef, useCallback} from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  Modal,
  Alert,
  Linking,
  Image,
  ActivityIndicator,
  Platform,
} from 'react-native';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import DateTimePicker from '@react-native-community/datetimepicker';
import Geolocation from '@react-native-community/geolocation';
import {launchCamera, launchImageLibrary} from 'react-native-image-picker';
import {visualizarOs, iniciarOs, pausarOs, reagendarOs, encerrarOs, salvarDescricao, adicionarProduto} from '../api/os';
import {atualizarGps} from '../api/gps';
import {uploadMidia} from '../api/midias';
import {OSDetalhe} from '../types';
import {StatusBadge} from '../components/StatusBadge';
import {PriorityBadge} from '../components/PriorityBadge';
import {useAuth} from '../hooks/useAuth';
import {CORES, API_BASE_URL} from '../config';
import {RootStackParamList} from '../navigation';

type Props = NativeStackScreenProps<RootStackParamList, 'OsDetail'>;

const ROTULOS_ACAO: Record<string, string> = {
  os_criada: 'OS criada', os_iniciada: 'OS iniciada', os_pausada: 'OS pausada',
  os_reagendada: 'OS reagendada', os_encerrada: 'OS encerrada',
  os_atualizada: 'OS atualizada', tecnicos_atribuidos: 'Técnicos redefinidos',
  midia_enviada: 'Mídia enviada', produto_adicionado: 'Produto adicionado',
  descricao_atualizada: 'Descrição atualizada',
};

export function OsDetailScreen({route, navigation}: Props) {
  const {osId} = route.params;
  const {usuario} = useAuth();
  const isGestor = usuario?.perfil === 'gestor';
  const [os, setOs] = useState<OSDetalhe | null>(null);
  const [carregando, setCarregando] = useState(true);
  const [salvando, setSalvando] = useState(false);
  const [erro, setErro] = useState('');
  const [sucesso, setSucesso] = useState('');

  // Modais
  const [modalPausar, setModalPausar] = useState(false);
  const [modalReagendar, setModalReagendar] = useState(false);
  const [modalEncerrar, setModalEncerrar] = useState(false);
  const [modalProduto, setModalProduto] = useState(false);
  const [modalHistorico, setModalHistorico] = useState(false);

  // Campos dos modais
  const [motivoPausa, setMotivoPausa] = useState('');
  const [novaData, setNovaData] = useState(new Date());
  const [mostrarDatePicker, setMostrarDatePicker] = useState(false);
  const [motivoReagendar, setMotivoReagendar] = useState('');
  const [laudoFinal, setLaudoFinal] = useState('');
  const [descricao, setDescricao] = useState('');
  const [prodNome, setProdNome] = useState('');
  const [prodQtd, setProdQtd] = useState('1');
  const [prodValor, setProdValor] = useState('');

  // GPS
  const gpsTimer = useRef<ReturnType<typeof setInterval> | null>(null);

  const carregar = useCallback(async () => {
    try {
      const dados = await visualizarOs(osId);
      setOs(dados);
      setDescricao(dados.observacoes ?? '');
    } catch (e: any) {
      setErro(e.message ?? 'Erro ao carregar OS.');
    } finally {
      setCarregando(false);
    }
  }, [osId]);

  useEffect(() => {
    carregar();
  }, [carregar]);

  // GPS automático quando em andamento (apenas para técnicos em campo)
  useEffect(() => {
    if (isGestor || os?.situacao_local !== 'em_andamento') {
      if (gpsTimer.current) {clearInterval(gpsTimer.current);}
      return;
    }

    function enviarGps() {
      Geolocation.getCurrentPosition(
        pos => {
          atualizarGps(osId, pos.coords.latitude, pos.coords.longitude).catch(() => {});
        },
        () => {},
        {enableHighAccuracy: true, timeout: 15000},
      );
    }

    enviarGps();
    gpsTimer.current = setInterval(enviarGps, 60_000);
    return () => {
      if (gpsTimer.current) {clearInterval(gpsTimer.current);}
    };
  }, [os?.situacao_local, osId]);

  function mostrarSucesso(msg: string) {
    setSucesso(msg);
    setErro('');
    setTimeout(() => setSucesso(''), 3000);
  }

  function mostrarErro(msg: string) {
    setErro(msg);
    setSucesso('');
  }

  async function acao(fn: () => Promise<void>, successMsg: string) {
    setSalvando(true);
    setErro('');
    try {
      await fn();
      mostrarSucesso(successMsg);
      await carregar();
    } catch (e: any) {
      mostrarErro(e.message ?? 'Erro desconhecido.');
    } finally {
      setSalvando(false);
    }
  }

  function abrirRota() {
    if (!os?.cliente_endereco) {return;}
    const destino = encodeURIComponent(os.cliente_endereco);
    Linking.openURL(`https://www.google.com/maps/dir/?api=1&destination=${destino}`);
  }

  async function enviarMidia(fonte: 'camera' | 'galeria') {
    const fn = fonte === 'camera' ? launchCamera : launchImageLibrary;
    fn(
      {mediaType: 'mixed', quality: 0.8, includeBase64: false},
      async response => {
        if (response.didCancel || !response.assets?.[0]) {return;}
        const asset = response.assets[0];
        const tipo = asset.type?.startsWith('video') ? 'video' : 'foto';
        setSalvando(true);
        try {
          await uploadMidia(osId, tipo, {
            uri: asset.uri!,
            name: asset.fileName ?? `midia.${tipo === 'foto' ? 'jpg' : 'mp4'}`,
            type: asset.type ?? 'image/jpeg',
          });
          mostrarSucesso('Mídia enviada com sucesso!');
          await carregar();
        } catch (e: any) {
          mostrarErro(e.message ?? 'Erro ao enviar mídia.');
        } finally {
          setSalvando(false);
        }
      },
    );
  }

  function escolherMidia() {
    Alert.alert('Enviar mídia', 'Como deseja adicionar?', [
      {text: 'Câmera', onPress: () => enviarMidia('camera')},
      {text: 'Galeria', onPress: () => enviarMidia('galeria')},
      {text: 'Cancelar', style: 'cancel'},
    ]);
  }

  if (carregando) {
    return (
      <View style={estilos.centro}>
        <ActivityIndicator size="large" color={CORES.azul600} />
        <Text style={{color: CORES.cinza500, marginTop: 12}}>Carregando OS...</Text>
      </View>
    );
  }

  if (!os) {
    return (
      <View style={estilos.centro}>
        <Text style={{color: CORES.vermelho}}>{erro || 'OS não encontrada.'}</Text>
      </View>
    );
  }

  const situacao = os.situacao_local;
  const produtos = os.produtos ?? [];
  const totalProdutos = produtos.reduce(
    (acc, p) => acc + p.valor_venda * p.quantidade, 0,
  );

  const dataFormatada = os.data_agendamento
    ? new Date(os.data_agendamento + 'T12:00:00').toLocaleDateString('pt-BR')
    : 'Sem data';

  return (
    <>
      <ScrollView style={estilos.container} contentContainerStyle={estilos.scroll}>

        {/* Alertas */}
        {sucesso ? (
          <View style={estilos.alertaSucesso}>
            <Text style={estilos.alertaSucessoText}>✓ {sucesso}</Text>
          </View>
        ) : null}
        {erro ? (
          <View style={estilos.alertaErro}>
            <Text style={estilos.alertaErroText}>✕ {erro}</Text>
          </View>
        ) : null}

        {/* Cabeçalho da OS */}
        <View style={estilos.card}>
          <View style={estilos.osHeader}>
            <Text style={estilos.osNum}>OS #{os.id}</Text>
            <View style={{gap: 6}}>
              <StatusBadge status={situacao} />
              <PriorityBadge prioridade={os.prioridade} />
            </View>
          </View>

          <Text style={estilos.clienteNome}>{os.cliente_nome ?? '-'}</Text>

          {os.cliente_endereco ? (
            <TouchableOpacity style={estilos.infoRow} onPress={abrirRota}>
              <Text style={estilos.infoIcon}>📍</Text>
              <Text style={[estilos.infoText, {color: CORES.azul700}]}>
                {os.cliente_endereco}
              </Text>
            </TouchableOpacity>
          ) : null}

          {os.cliente_telefone ? (
            <TouchableOpacity
              style={estilos.infoRow}
              onPress={() => Linking.openURL(`tel:${os.cliente_telefone}`)}>
              <Text style={estilos.infoIcon}>📞</Text>
              <Text style={[estilos.infoText, {color: CORES.azul700}]}>
                {os.cliente_telefone}
              </Text>
            </TouchableOpacity>
          ) : null}

          <View style={estilos.infoRow}>
            <Text style={estilos.infoIcon}>📅</Text>
            <Text style={estilos.infoText}>{dataFormatada}</Text>
          </View>

          {/* Botão Rota */}
          {os.cliente_endereco ? (
            <TouchableOpacity style={estilos.botaoRota} onPress={abrirRota}>
              <Text style={estilos.botaoRotaText}>🗺 Abrir rota no Google Maps</Text>
            </TouchableOpacity>
          ) : null}
        </View>

        {/* Botões de ação */}
        <View style={estilos.card}>
          <Text style={estilos.secaoTitulo}>Ações</Text>
          <View style={estilos.acoesGrid}>

            {['aberto', 'reagendado', 'pausado'].includes(situacao) && (
              <TouchableOpacity
                style={[estilos.botaoAcao, estilos.botaoPrimario]}
                disabled={salvando}
                onPress={() => acao(() => iniciarOs(osId), 'OS iniciada!')}>
                <Text style={estilos.botaoAcaoText}>▶ Iniciar</Text>
              </TouchableOpacity>
            )}

            {situacao === 'em_andamento' && (
              <>
                <TouchableOpacity
                  style={[estilos.botaoAcao, estilos.botaoNeutro]}
                  onPress={() => setModalPausar(true)}>
                  <Text style={[estilos.botaoAcaoText, {color: CORES.cinza700}]}>⏸ Pausar</Text>
                </TouchableOpacity>

                <TouchableOpacity
                  style={[estilos.botaoAcao, estilos.botaoNeutro]}
                  onPress={() => setModalReagendar(true)}>
                  <Text style={[estilos.botaoAcaoText, {color: CORES.cinza700}]}>📅 Reagendar</Text>
                </TouchableOpacity>

                <TouchableOpacity
                  style={[estilos.botaoAcao, estilos.botaoSucesso]}
                  onPress={() => setModalEncerrar(true)}>
                  <Text style={[estilos.botaoAcaoText, {color: CORES.verde}]}>✓ Encerrar</Text>
                </TouchableOpacity>
              </>
            )}

            {['aberto', 'pausado'].includes(situacao) && (
              <TouchableOpacity
                style={[estilos.botaoAcao, estilos.botaoNeutro]}
                onPress={() => setModalReagendar(true)}>
                <Text style={[estilos.botaoAcaoText, {color: CORES.cinza700}]}>📅 Reagendar</Text>
              </TouchableOpacity>
            )}

          </View>

          {situacao === 'em_andamento' && (
            <View style={estilos.gpsIndicador}>
              <View style={estilos.gpsDot} />
              <Text style={estilos.gpsText}>GPS ativo — enviando posição a cada 60s</Text>
            </View>
          )}
        </View>

        {/* Descrição / Laudo */}
        <View style={estilos.card}>
          <Text style={estilos.secaoTitulo}>Descrição / Laudo</Text>
          <TextInput
            style={estilos.textarea}
            value={descricao}
            onChangeText={setDescricao}
            multiline
            numberOfLines={4}
            placeholder="Descreva o serviço, observações..."
            placeholderTextColor={CORES.cinza300}
          />
          <TouchableOpacity
            style={[estilos.botaoPequeno, {backgroundColor: CORES.azul100}]}
            disabled={salvando}
            onPress={() => acao(() => salvarDescricao(osId, descricao), 'Descrição salva.')}>
            <Text style={{color: CORES.azul700, fontWeight: '600'}}>Salvar descrição</Text>
          </TouchableOpacity>
        </View>

        {/* Produtos */}
        <View style={estilos.card}>
          <View style={estilos.secaoHeader}>
            <Text style={estilos.secaoTitulo}>Produtos ({produtos.length})</Text>
            <TouchableOpacity onPress={() => setModalProduto(true)}>
              <Text style={{color: CORES.azul700, fontWeight: '600', fontSize: 13}}>+ Adicionar</Text>
            </TouchableOpacity>
          </View>

          {produtos.length > 0 ? (
            <>
              {produtos.map((p, i) => (
                <View key={i} style={estilos.produtoRow}>
                  <View style={{flex: 1}}>
                    <Text style={estilos.produtoNome}>{p.nome}</Text>
                    <Text style={estilos.produtoDetalhe}>
                      {p.quantidade}x R$ {Number(p.valor_venda).toFixed(2).replace('.', ',')}
                    </Text>
                  </View>
                  <Text style={estilos.produtoSubtotal}>
                    R$ {(p.quantidade * p.valor_venda).toFixed(2).replace('.', ',')}
                  </Text>
                </View>
              ))}
              <View style={estilos.totalRow}>
                <Text style={estilos.totalLabel}>Total</Text>
                <Text style={estilos.totalValor}>
                  R$ {totalProdutos.toFixed(2).replace('.', ',')}
                </Text>
              </View>
            </>
          ) : (
            <Text style={estilos.vazioInline}>Nenhum produto adicionado.</Text>
          )}
        </View>

        {/* Mídias */}
        <View style={estilos.card}>
          <View style={estilos.secaoHeader}>
            <Text style={estilos.secaoTitulo}>Fotos e Vídeos ({os.midias.length})</Text>
            <TouchableOpacity onPress={escolherMidia}>
              <Text style={{color: CORES.azul700, fontWeight: '600', fontSize: 13}}>+ Enviar</Text>
            </TouchableOpacity>
          </View>

          {os.midias.length > 0 ? (
            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{marginTop: 8}}>
              {os.midias.map((m, i) => {
                const uri = `${API_BASE_URL.replace('/api', '')}/${m.caminho_arquivo}`;
                return m.tipo === 'foto' ? (
                  <Image
                    key={i}
                    source={{uri}}
                    style={estilos.miniaturaFoto}
                    resizeMode="cover"
                  />
                ) : (
                  <View key={i} style={[estilos.miniaturaFoto, estilos.miniaturaVideo]}>
                    <Text style={{color: '#fff', fontSize: 28}}>▶</Text>
                  </View>
                );
              })}
            </ScrollView>
          ) : (
            <Text style={estilos.vazioInline}>Nenhuma mídia enviada.</Text>
          )}
        </View>

        {/* Técnicos */}
        {os.tecnicos.length > 0 && (
          <View style={estilos.card}>
            <Text style={estilos.secaoTitulo}>Técnicos atribuídos</Text>
            {os.tecnicos.map(t => (
              <View key={t.id} style={estilos.tecnicoRow}>
                <View style={estilos.tecnicoAvatar}>
                  <Text style={estilos.tecnicoIniciais}>
                    {t.nome.split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase()}
                  </Text>
                </View>
                <View>
                  <Text style={estilos.tecnicoNome}>{t.nome}</Text>
                  {t.responsavel && (
                    <Text style={estilos.tecnicoResp}>Responsável</Text>
                  )}
                </View>
              </View>
            ))}
          </View>
        )}

        {/* Histórico */}
        {os.historico.length > 0 && (
          <View style={estilos.card}>
            <View style={estilos.secaoHeader}>
              <Text style={estilos.secaoTitulo}>Histórico ({os.historico.length})</Text>
              <TouchableOpacity onPress={() => setModalHistorico(true)}>
                <Text style={{color: CORES.azul700, fontSize: 13, fontWeight: '600'}}>Ver tudo</Text>
              </TouchableOpacity>
            </View>
            {os.historico.slice(0, 3).map((h, i) => (
              <View key={i} style={estilos.histRow}>
                <Text style={estilos.histAcao}>{ROTULOS_ACAO[h.acao] ?? h.acao}</Text>
                {h.detalhe && <Text style={estilos.histDetalhe} numberOfLines={1}>{h.detalhe}</Text>}
                <Text style={estilos.histData}>
                  {new Date(h.criado_em).toLocaleString('pt-BR')}
                  {h.usuario_nome ? ` · ${h.usuario_nome}` : ''}
                </Text>
              </View>
            ))}
          </View>
        )}

        <View style={{height: 32}} />
      </ScrollView>

      {/* =================== MODAIS =================== */}

      {/* Modal Pausar */}
      <Modal visible={modalPausar} transparent animationType="slide">
        <View style={estilos.modalOverlay}>
          <View style={estilos.modalBox}>
            <Text style={estilos.modalTitulo}>Pausar OS</Text>
            <Text style={estilos.modalLabel}>Motivo da pausa *</Text>
            <TextInput
              style={[estilos.textarea, {marginBottom: 16}]}
              value={motivoPausa}
              onChangeText={setMotivoPausa}
              multiline
              numberOfLines={3}
              placeholder="Ex: cliente ausente, falta de material..."
              placeholderTextColor={CORES.cinza300}
            />
            <View style={estilos.modalBotoes}>
              <TouchableOpacity
                style={[estilos.botaoModal, {backgroundColor: CORES.cinza100}]}
                onPress={() => {setModalPausar(false); setMotivoPausa('');}}>
                <Text style={{color: CORES.cinza700, fontWeight: '600'}}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[estilos.botaoModal, {backgroundColor: CORES.azul700}]}
                disabled={salvando}
                onPress={() => {
                  if (!motivoPausa.trim()) {Alert.alert('Informe o motivo da pausa.'); return;}
                  acao(() => pausarOs(osId, motivoPausa), 'OS pausada.').then(() => {
                    setModalPausar(false);
                    setMotivoPausa('');
                  });
                }}>
                <Text style={{color: '#fff', fontWeight: '600'}}>
                  {salvando ? '...' : '⏸ Confirmar'}
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal Reagendar */}
      <Modal visible={modalReagendar} transparent animationType="slide">
        <View style={estilos.modalOverlay}>
          <View style={estilos.modalBox}>
            <Text style={estilos.modalTitulo}>Reagendar OS</Text>
            <Text style={estilos.modalLabel}>Nova data *</Text>
            <TouchableOpacity
              style={estilos.dateInput}
              onPress={() => setMostrarDatePicker(true)}>
              <Text style={{color: CORES.cinza900}}>
                {novaData.toLocaleDateString('pt-BR')}
              </Text>
            </TouchableOpacity>

            {mostrarDatePicker && (
              <DateTimePicker
                value={novaData}
                mode="date"
                minimumDate={new Date()}
                onChange={(_, d) => {setMostrarDatePicker(false); if (d) {setNovaData(d);}}}
              />
            )}

            <Text style={estilos.modalLabel}>Motivo (opcional)</Text>
            <TextInput
              style={[estilos.input, {marginBottom: 16}]}
              value={motivoReagendar}
              onChangeText={setMotivoReagendar}
              placeholder="Motivo do reagendamento..."
              placeholderTextColor={CORES.cinza300}
            />
            <View style={estilos.modalBotoes}>
              <TouchableOpacity
                style={[estilos.botaoModal, {backgroundColor: CORES.cinza100}]}
                onPress={() => setModalReagendar(false)}>
                <Text style={{color: CORES.cinza700, fontWeight: '600'}}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[estilos.botaoModal, {backgroundColor: CORES.azul700}]}
                disabled={salvando}
                onPress={() => {
                  const dataStr = novaData.toISOString().split('T')[0];
                  acao(
                    () => reagendarOs(osId, dataStr, motivoReagendar),
                    'OS reagendada.',
                  ).then(() => {setModalReagendar(false); setMotivoReagendar('');});
                }}>
                <Text style={{color: '#fff', fontWeight: '600'}}>
                  {salvando ? '...' : '📅 Confirmar'}
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal Encerrar */}
      <Modal visible={modalEncerrar} transparent animationType="slide">
        <View style={estilos.modalOverlay}>
          <View style={estilos.modalBox}>
            <Text style={estilos.modalTitulo}>Encerrar OS</Text>
            <Text style={estilos.modalLabel}>Laudo final *</Text>
            <TextInput
              style={[estilos.textarea, {marginBottom: 16}]}
              value={laudoFinal}
              onChangeText={setLaudoFinal}
              multiline
              numberOfLines={4}
              placeholder="Descreva detalhadamente o serviço realizado..."
              placeholderTextColor={CORES.cinza300}
            />
            <View style={estilos.modalBotoes}>
              <TouchableOpacity
                style={[estilos.botaoModal, {backgroundColor: CORES.cinza100}]}
                onPress={() => setModalEncerrar(false)}>
                <Text style={{color: CORES.cinza700, fontWeight: '600'}}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[estilos.botaoModal, {backgroundColor: CORES.verde}]}
                disabled={salvando}
                onPress={() => {
                  if (!laudoFinal.trim()) {Alert.alert('Informe o laudo final.'); return;}
                  acao(
                    () => encerrarOs(osId, laudoFinal),
                    'OS encerrada com sucesso!',
                  ).then(() => {setModalEncerrar(false); setLaudoFinal('');});
                }}>
                <Text style={{color: '#fff', fontWeight: '600'}}>
                  {salvando ? '...' : '✓ Encerrar'}
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal Produto */}
      <Modal visible={modalProduto} transparent animationType="slide">
        <View style={estilos.modalOverlay}>
          <View style={estilos.modalBox}>
            <Text style={estilos.modalTitulo}>Adicionar produto</Text>
            <Text style={estilos.modalLabel}>Nome do produto *</Text>
            <TextInput
              style={[estilos.input, {marginBottom: 12}]}
              value={prodNome}
              onChangeText={setProdNome}
              placeholder="Nome do produto"
              placeholderTextColor={CORES.cinza300}
            />
            <View style={{flexDirection: 'row', gap: 10}}>
              <View style={{flex: 1}}>
                <Text style={estilos.modalLabel}>Quantidade *</Text>
                <TextInput
                  style={[estilos.input, {marginBottom: 12}]}
                  value={prodQtd}
                  onChangeText={setProdQtd}
                  keyboardType="decimal-pad"
                  placeholder="1"
                  placeholderTextColor={CORES.cinza300}
                />
              </View>
              <View style={{flex: 1}}>
                <Text style={estilos.modalLabel}>Valor unit. *</Text>
                <TextInput
                  style={[estilos.input, {marginBottom: 12}]}
                  value={prodValor}
                  onChangeText={setProdValor}
                  keyboardType="decimal-pad"
                  placeholder="0,00"
                  placeholderTextColor={CORES.cinza300}
                />
              </View>
            </View>
            <View style={estilos.modalBotoes}>
              <TouchableOpacity
                style={[estilos.botaoModal, {backgroundColor: CORES.cinza100}]}
                onPress={() => {setModalProduto(false); setProdNome(''); setProdQtd('1'); setProdValor('');}}>
                <Text style={{color: CORES.cinza700, fontWeight: '600'}}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[estilos.botaoModal, {backgroundColor: CORES.azul700}]}
                disabled={salvando}
                onPress={() => {
                  const nome = prodNome.trim();
                  const quantidade = parseFloat(prodQtd.replace(',', '.'));
                  const valor_venda = parseFloat(prodValor.replace(',', '.'));
                  if (!nome || isNaN(quantidade) || isNaN(valor_venda)) {
                    Alert.alert('Preencha nome, quantidade e valor.');
                    return;
                  }
                  acao(
                    () => adicionarProduto(osId, {nome, quantidade, valor_venda}),
                    'Produto adicionado!',
                  ).then(() => {
                    setModalProduto(false);
                    setProdNome(''); setProdQtd('1'); setProdValor('');
                  });
                }}>
                <Text style={{color: '#fff', fontWeight: '600'}}>
                  {salvando ? '...' : '+ Adicionar'}
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal Histórico completo */}
      <Modal visible={modalHistorico} transparent animationType="slide">
        <View style={estilos.modalOverlay}>
          <View style={[estilos.modalBox, {maxHeight: '80%'}]}>
            <Text style={estilos.modalTitulo}>Histórico completo</Text>
            <ScrollView style={{maxHeight: 400}}>
              {os.historico.map((h, i) => (
                <View key={i} style={[estilos.histRow, {borderBottomWidth: 1, borderColor: CORES.cinza100, paddingBottom: 8, marginBottom: 8}]}>
                  <Text style={estilos.histAcao}>{ROTULOS_ACAO[h.acao] ?? h.acao}</Text>
                  {h.detalhe && <Text style={estilos.histDetalhe}>{h.detalhe}</Text>}
                  <Text style={estilos.histData}>
                    {new Date(h.criado_em).toLocaleString('pt-BR')}
                    {h.usuario_nome ? ` · ${h.usuario_nome}` : ''}
                  </Text>
                </View>
              ))}
            </ScrollView>
            <TouchableOpacity
              style={[estilos.botaoModal, {backgroundColor: CORES.cinza100, marginTop: 12, alignSelf: 'center'}]}
              onPress={() => setModalHistorico(false)}>
              <Text style={{color: CORES.cinza700, fontWeight: '600'}}>Fechar</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </>
  );
}

const estilos = StyleSheet.create({
  container: {flex: 1, backgroundColor: CORES.azul50},
  scroll: {padding: 14, paddingTop: 10},
  centro: {flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32},
  card: {
    backgroundColor: CORES.branco, borderRadius: 14,
    padding: 16, marginBottom: 12,
    shadowColor: '#10284A', shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.07, shadowRadius: 6, elevation: 2,
  },
  alertaSucesso: {backgroundColor: CORES.verdeBg, borderRadius: 8, padding: 12, marginBottom: 10},
  alertaSucessoText: {color: CORES.verde, fontWeight: '600', fontSize: 13.5},
  alertaErro: {backgroundColor: CORES.vermelhoBg, borderRadius: 8, padding: 12, marginBottom: 10},
  alertaErroText: {color: CORES.vermelho, fontWeight: '600', fontSize: 13.5},
  osHeader: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 10},
  osNum: {fontSize: 13, color: CORES.cinza500, fontWeight: '700'},
  clienteNome: {fontSize: 18, fontWeight: '800', color: CORES.cinza900, marginBottom: 10},
  infoRow: {flexDirection: 'row', alignItems: 'flex-start', marginBottom: 6, gap: 6},
  infoIcon: {fontSize: 14, marginTop: 1},
  infoText: {fontSize: 13.5, color: CORES.cinza700, flex: 1},
  botaoRota: {
    backgroundColor: CORES.azul100, borderRadius: 999,
    paddingVertical: 10, paddingHorizontal: 16,
    alignItems: 'center', marginTop: 10,
  },
  botaoRotaText: {color: CORES.azul700, fontWeight: '600', fontSize: 13.5},
  secaoTitulo: {fontSize: 14, fontWeight: '800', color: CORES.cinza900, marginBottom: 12, letterSpacing: 0.2},
  secaoHeader: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10},
  acoesGrid: {flexDirection: 'row', flexWrap: 'wrap', gap: 10},
  botaoAcao: {
    paddingHorizontal: 18, paddingVertical: 11,
    borderRadius: 999, minWidth: 110, alignItems: 'center',
  },
  botaoPrimario: {
    backgroundColor: CORES.azul700,
    shadowColor: CORES.azul800, shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.3, shadowRadius: 4, elevation: 3,
  },
  botaoNeutro: {backgroundColor: CORES.cinza100},
  botaoSucesso: {backgroundColor: CORES.verdeBg},
  botaoAcaoText: {fontWeight: '700', fontSize: 13.5, color: '#fff'},
  gpsIndicador: {
    flexDirection: 'row', alignItems: 'center', gap: 8,
    backgroundColor: '#e5f5ec', borderRadius: 8,
    padding: 8, marginTop: 12,
  },
  gpsDot: {width: 8, height: 8, borderRadius: 4, backgroundColor: CORES.verde},
  gpsText: {color: CORES.verde, fontSize: 12, fontWeight: '600'},
  textarea: {
    borderWidth: 1, borderColor: CORES.cinza300, borderRadius: 8,
    padding: 12, fontSize: 14, color: CORES.cinza900,
    minHeight: 90, textAlignVertical: 'top',
  },
  input: {
    borderWidth: 1, borderColor: CORES.cinza300, borderRadius: 8,
    paddingHorizontal: 12, paddingVertical: 10,
    fontSize: 14, color: CORES.cinza900,
  },
  botaoPequeno: {
    paddingHorizontal: 16, paddingVertical: 9,
    borderRadius: 999, alignSelf: 'flex-start', marginTop: 10,
  },
  produtoRow: {
    flexDirection: 'row', alignItems: 'center',
    paddingVertical: 8, borderBottomWidth: 1, borderColor: CORES.cinza100,
  },
  produtoNome: {fontWeight: '600', fontSize: 13.5, color: CORES.cinza900},
  produtoDetalhe: {fontSize: 12, color: CORES.cinza500, marginTop: 2},
  produtoSubtotal: {fontWeight: '700', fontSize: 13, color: CORES.cinza900},
  totalRow: {
    flexDirection: 'row', justifyContent: 'space-between',
    paddingTop: 10, marginTop: 4,
  },
  totalLabel: {fontWeight: '700', color: CORES.cinza700},
  totalValor: {fontWeight: '800', color: CORES.azul900, fontSize: 15},
  vazioInline: {color: CORES.cinza500, fontSize: 13, marginTop: 4},
  miniaturaFoto: {
    width: 90, height: 90, borderRadius: 8,
    marginRight: 8, backgroundColor: '#000',
  },
  miniaturaVideo: {
    alignItems: 'center', justifyContent: 'center',
    backgroundColor: '#1c2430',
  },
  tecnicoRow: {
    flexDirection: 'row', alignItems: 'center', gap: 12,
    paddingVertical: 8, borderBottomWidth: 1, borderColor: CORES.cinza100,
  },
  tecnicoAvatar: {
    width: 36, height: 36, borderRadius: 18,
    backgroundColor: CORES.azul600,
    alignItems: 'center', justifyContent: 'center',
  },
  tecnicoIniciais: {color: '#fff', fontWeight: '700', fontSize: 13},
  tecnicoNome: {fontWeight: '600', fontSize: 13.5, color: CORES.cinza900},
  tecnicoResp: {fontSize: 11, color: CORES.azul700, fontWeight: '700'},
  histRow: {marginBottom: 8},
  histAcao: {fontWeight: '700', fontSize: 13, color: CORES.cinza900},
  histDetalhe: {fontSize: 12.5, color: CORES.cinza700, marginTop: 1},
  histData: {fontSize: 11, color: CORES.cinza500, marginTop: 2},
  // Modal
  modalOverlay: {
    flex: 1, backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'flex-end',
  },
  modalBox: {
    backgroundColor: CORES.branco,
    borderTopLeftRadius: 20, borderTopRightRadius: 20,
    padding: 24, paddingBottom: 32,
  },
  modalTitulo: {fontSize: 17, fontWeight: '800', color: CORES.azul900, marginBottom: 16},
  modalLabel: {fontSize: 13, fontWeight: '600', color: CORES.cinza700, marginBottom: 6},
  modalBotoes: {flexDirection: 'row', gap: 10, marginTop: 4},
  botaoModal: {
    flex: 1, paddingVertical: 13,
    borderRadius: 999, alignItems: 'center',
  },
  dateInput: {
    borderWidth: 1, borderColor: CORES.cinza300, borderRadius: 8,
    paddingHorizontal: 12, paddingVertical: 10,
    marginBottom: 12,
  },
});
