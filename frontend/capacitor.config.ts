import type { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'com.salonmanager.app', // change to your brand before the first Play Store upload; it cannot change later
  appName: 'Salon Manager',
  webDir: 'dist',
  server: { androidScheme: 'https' },
  plugins: { PushNotifications: { presentationOptions: ['badge', 'sound', 'alert'] } },
}

export default config
