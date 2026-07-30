// Polyfill URLSearchParams.set para Hermes (React Native / Android)
// Necessario para @react-native-voice/voice que usa .set() internamente
if (typeof URLSearchParams !== 'undefined' && !URLSearchParams.prototype.set) {
  URLSearchParams.prototype.set = function (name, value) {
    var existing = this.getAll(name);
    if (existing.length > 0) { this.delete(name); }
    this.append(name, String(value));
  };
}

import notifee from '@notifee/react-native';
import {AppRegistry} from 'react-native';
import App from './App';
import {name as appName} from './app.json';

// Obrigatório pelo @notifee — handler de eventos quando o app está em background
notifee.onBackgroundEvent(async () => {});

AppRegistry.registerComponent(appName, () => App);
