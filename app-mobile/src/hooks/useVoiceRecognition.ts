import {useState, useEffect, useCallback} from 'react';
import Voice, {
  SpeechResultsEvent,
  SpeechErrorEvent,
} from '@react-native-voice/voice';
import {Alert, Platform} from 'react-native';

export interface VoiceRecognitionResult {
  ouvindo: boolean;
  textoReconhecido: string;
  erro: string;
  iniciarDitado: () => Promise<void>;
  pararDitado: () => Promise<void>;
  limpar: () => void;
}

export function useVoiceRecognition(): VoiceRecognitionResult {
  const [ouvindo, setOuvindo]               = useState(false);
  const [textoReconhecido, setTexto]         = useState('');
  const [erro, setErro]                      = useState('');

  useEffect(() => {
    Voice.onSpeechStart  = () => { setOuvindo(true); setErro(''); };
    Voice.onSpeechEnd    = () => setOuvindo(false);
    Voice.onSpeechCancel = () => setOuvindo(false);

    Voice.onSpeechResults = (e: SpeechResultsEvent) => {
      const melhor = e.value?.[0] ?? '';
      if (melhor) { setTexto(melhor); }
    };

    Voice.onSpeechError = (e: SpeechErrorEvent) => {
      setOuvindo(false);
      const msg = e.error?.message ?? '';
      // Código 7 = "Sem correspondência" — ignora silenciosamente
      if (!msg.includes('7')) {
        setErro('Não foi possível reconhecer. Tente novamente.');
      }
    };

    return () => {
      Voice.destroy().then(Voice.removeAllListeners).catch(() => {});
    };
  }, []);

  const iniciarDitado = useCallback(async () => {
    try {
      const disponivel = await Voice.isAvailable();
      if (!disponivel) {
        Alert.alert('Reconhecimento de voz não disponível neste dispositivo.');
        return;
      }
      setTexto('');
      setErro('');
      await Voice.start('pt-BR');
    } catch (e: any) {
      setErro(e.message ?? 'Erro ao iniciar reconhecimento de voz.');
      setOuvindo(false);
    }
  }, []);

  const pararDitado = useCallback(async () => {
    try {
      await Voice.stop();
    } catch {
      // ignora
    } finally {
      setOuvindo(false);
    }
  }, []);

  const limpar = useCallback(() => {
    setTexto('');
    setErro('');
  }, []);

  return {ouvindo, textoReconhecido, erro, iniciarDitado, pararDitado, limpar};
}
