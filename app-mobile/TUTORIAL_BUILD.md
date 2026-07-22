# Tutorial: Compilar e Instalar o SmartVig OS (Android)

Este guia cobre tudo desde a instalação das ferramentas até o APK instalado no celular.

---

## 1. Pré-requisitos

### 1.1 Node.js (LTS)
Acesse https://nodejs.org e baixe a versão LTS (20.x ou superior).  
Verifique no terminal:
```
node -v
npm -v
```

### 1.2 Java Development Kit (JDK 17)
Baixe o JDK 17 em: https://adoptium.net/temurin/releases/?version=17  
Instale e configure a variável de ambiente:
- `JAVA_HOME` = caminho do JDK (ex: `C:\Program Files\Eclipse Adoptium\jdk-17.0.x`)
- Adicione `%JAVA_HOME%\bin` ao PATH

Verifique:
```
java -version
```

### 1.3 Android Studio
Baixe em: https://developer.android.com/studio

Durante a instalação, garanta que estas opções estão marcadas:
- Android SDK
- Android SDK Platform
- Android Virtual Device (AVD)

Após instalar, abra o Android Studio:
1. SDK Manager → instale **Android 14 (API 34)**
2. SDK Manager → SDK Tools → marque:
   - Android SDK Build-Tools 34
   - Android Emulator
   - Android SDK Platform-Tools
   - NDK (Side by side) — versão 25.1.8937393

Configure as variáveis de ambiente:
- `ANDROID_HOME` = `C:\Users\SEU_USUARIO\AppData\Local\Android\Sdk`
- Adicione ao PATH:
  - `%ANDROID_HOME%\platform-tools`
  - `%ANDROID_HOME%\emulator`

Verifique:
```
adb version
```

---

## 2. Instalar dependências do projeto

Abra o PowerShell na pasta `app-mobile`:
```
cd C:\xampp\htdocs\app-tecnicos\app-mobile
npm install
```

---

## 3. Configurar a URL da API

Abra o arquivo `src/config.ts` e ajuste `API_BASE_URL`:

**Para emulador Android (padrão):**
```typescript
export const API_BASE_URL = 'https://10.0.2.2:4443/app-tecnicos/api';
```

**Para celular físico na mesma rede Wi-Fi:**
```typescript
export const API_BASE_URL = 'http://192.168.1.X/app-tecnicos/api';
// Substitua 192.168.1.X pelo IP do seu PC na rede local
// Para descobrir o IP: execute `ipconfig` no CMD, procure "Adaptador Ethernet" ou "Wi-Fi"
```

> **Nota:** Com celular físico e HTTP (sem SSL), troque `https://` por `http://` e certifique-se  
> de que o `android:usesCleartextTraffic="true"` está no AndroidManifest.xml.

---

## 4. Certificado SSL do XAMPP (para emulador)

O XAMPP usa um certificado autoassinado. O arquivo `network_security_config.xml` já está  
configurado para aceitar certificados de usuário no emulador (`10.0.2.2`).

Para **certificado físico de produção**, substitua o certificado autoassinado por um  
certificado válido (ex: Let's Encrypt com domínio real).

---

## 5. Criar e iniciar um emulador

No Android Studio → Device Manager → Create Virtual Device:
- Escolha: Pixel 6 (ou similar)
- System Image: Android 14 (API 34) x86_64
- RAM: mínimo 2 GB

Inicie o emulador pelo Android Studio ou pelo terminal:
```
emulator -avd NOME_DO_AVD
```

Verifique se o emulador está visível:
```
adb devices
```

---

## 6. Executar em modo de desenvolvimento

Com o emulador rodando ou celular conectado via USB (com depuração USB ativada):
```
cd C:\xampp\htdocs\app-tecnicos\app-mobile
npx react-native run-android
```

O Metro bundler abrirá automaticamente. O app será instalado e iniciado no dispositivo.

> Se aparecer "Unable to load script", pressione `R` no emulador para recarregar, ou  
> execute `adb reverse tcp:8081 tcp:8081` para celular físico.

---

## 7. Gerar APK de Release

### 7.1 Gerar o keystore (primeira vez)
```
cd C:\xampp\htdocs\app-tecnicos\app-mobile\android\app
keytool -genkey -v -keystore release.keystore -alias smartvig -keyalg RSA -keysize 2048 -validity 10000
```
Responda as perguntas (nome, organização, cidade, etc.) e guarde a senha em local seguro.

### 7.2 Configurar as credenciais do keystore

Edite `android/gradle.properties` e adicione:
```
RELEASE_STORE_FILE=release.keystore
RELEASE_STORE_PASSWORD=SUA_SENHA
RELEASE_KEY_ALIAS=smartvig
RELEASE_KEY_PASSWORD=SUA_SENHA
```

Edite `android/app/build.gradle`, na seção `signingConfigs.release`:
```gradle
release {
    storeFile file(RELEASE_STORE_FILE)
    storePassword RELEASE_STORE_PASSWORD
    keyAlias RELEASE_KEY_ALIAS
    keyPassword RELEASE_KEY_PASSWORD
}
```

### 7.3 Compilar o APK
```
cd C:\xampp\htdocs\app-tecnicos\app-mobile\android
.\gradlew.bat assembleRelease
```

O APK gerado estará em:
```
android\app\build\outputs\apk\release\app-release.apk
```

### 7.4 Instalar no celular

Via adb (celular conectado por USB):
```
adb install android\app\build\outputs\apk\release\app-release.apk
```

Ou copie o arquivo `app-release.apk` para o celular e abra-o no gerenciador de arquivos  
(certifique-se de que "Instalar apps desconhecidos" está habilitado nas configurações do Android).

---

## 8. Gerar AAB para Google Play (opcional)

Se quiser publicar na Play Store:
```
cd android
.\gradlew.bat bundleRelease
```

O arquivo gerado estará em:
```
android\app\build\outputs\bundle\release\app-release.aab
```

---

## 9. Problemas comuns

| Problema | Solução |
|---|---|
| `SDK location not found` | Configure `ANDROID_HOME` ou crie `android/local.properties` com `sdk.dir=C:\\Users\\SEU_USER\\AppData\\Local\\Android\\Sdk` |
| `JAVA_HOME is not set` | Configure a variável de ambiente JAVA_HOME apontando para o JDK 17 |
| `Emulator not found` | Inicie o emulador pelo Android Studio antes de rodar `run-android` |
| `Network request failed` | Verifique se o XAMPP está rodando e a URL em `src/config.ts` está correta |
| `SSL error` | Certifique-se de que o `network_security_config.xml` está referenciado no AndroidManifest |
| `Duplicate class` | Execute `cd android && .\gradlew.bat clean` e tente novamente |
| Metro não inicia | Execute `npx react-native start --reset-cache` |

---

## 10. Estrutura do projeto

```
app-mobile/
├── src/
│   ├── api/          # Clientes HTTP (os, auth, gps, midias, notificacoes)
│   ├── components/   # StatusBadge, PriorityBadge, OsCard
│   ├── config.ts     # URL base e paleta de cores
│   ├── hooks/        # useAuth, useNotificacoes
│   ├── navigation/   # Stack + Tab navigator
│   ├── screens/      # LoginScreen, HomeScreen, OsDetailScreen, ProfileScreen
│   ├── storage/      # AsyncStorage wrappers (JWT, sessão)
│   └── types/        # Interfaces TypeScript
├── android/          # Projeto Android nativo
├── App.tsx           # Root com AuthProvider
├── index.js          # Entry point React Native
└── package.json
```

---

## 11. Funcionalidades implementadas

- **Login** — JWT armazenado em AsyncStorage, exclusivo para perfil `tecnico`
- **Lista de OS** — abas de filtro, pull-to-refresh, polling de notificações a cada 30s
- **Detalhe da OS** — iniciar, pausar (motivo), reagendar (date picker), encerrar (laudo)
- **GPS automático** — envia posição a cada 60s quando OS está `em_andamento`
- **Fotos e vídeos** — câmera ou galeria via `react-native-image-picker`
- **Produtos** — lista com subtotais, adicionar via modal
- **Perfil** — alterar foto, alterar senha
- **Rota** — abre Google Maps com endereço do cliente
- **Ligar** — toca o número do cliente ao clicar
