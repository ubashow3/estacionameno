import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.estacionamento.app',
  appName: 'estacionamento',
  webDir: 'build',
  server: {
    androidScheme: 'https'
  }
};

export default config;