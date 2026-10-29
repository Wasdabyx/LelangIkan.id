import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

// Token & data pengguna disimpan terenkripsi di HP (SecureStore).
// Pada mode web (uji di browser) memakai localStorage.
const KUNCI = 'sipeli_sesi';

export async function simpanSesi(token, pengguna) {
  const nilai = JSON.stringify({ token, pengguna });
  if (Platform.OS === 'web') localStorage.setItem(KUNCI, nilai);
  else await SecureStore.setItemAsync(KUNCI, nilai);
}

export async function ambilSesi() {
  try {
    const nilai =
      Platform.OS === 'web'
        ? localStorage.getItem(KUNCI)
        : await SecureStore.getItemAsync(KUNCI);
    return nilai ? JSON.parse(nilai) : null;
  } catch (e) {
    return null;
  }
}

export async function hapusSesi() {
  if (Platform.OS === 'web') localStorage.removeItem(KUNCI);
  else await SecureStore.deleteItemAsync(KUNCI);
}
