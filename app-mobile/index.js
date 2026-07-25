// Polyfill URLSearchParams.set para Hermes (React Native / Android)
// Necessario para @react-native-voice/voice que usa .set() internamente
if (typeof URLSearchParams !== 'undefined' && !URLSearchParams.prototype.set) {
  URLSearchParams.prototype.set = function (name, value) {
    var existing = this.getAll(name);
    if (existing.length > 0) { this.delete(name); }
    this.append(name, String(value));
  };
}

import {AppRegistry} from 'react-native';
import App from './App';
import {name as appName} from './app.json';

AppRegistry.registerComponent(appName, () => App);
