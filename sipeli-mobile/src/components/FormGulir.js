import { createContext, useContext, useEffect, useRef } from 'react';
import { Dimensions, Keyboard, KeyboardAvoidingView, ScrollView, TextInput, View } from 'react-native';

/**
 * Pembungkus formulir panjang yang aman dari keyboard.
 * - KeyboardAvoidingView "padding": memberi ruang setinggi keyboard (Android edge-to-edge)
 * - Saat keyboard muncul / kolom disentuh: kolom yang aktif digulir agar terlihat
 */
const KonteksGulir = createContext(() => {});
export const useGulirKeKolom = () => useContext(KonteksGulir);

const JARAK = 24; // jarak aman di atas keyboard (piksel)

export default function FormGulir({ children, gayaIsi, refreshControl }) {
  const wadahRef = useRef(null);
  const gulirRef = useRef(null);
  const posisiGulir = useRef(0);
  const atasKeyboard = useRef(null); // koordinat Y tepi atas keyboard di layar

  function gulirKeKolomAktif(tunda = 60) {
    setTimeout(() => {
      const kolom = TextInput.State.currentlyFocusedInput();
      if (!kolom || !wadahRef.current || !gulirRef.current) return;
      wadahRef.current.measureInWindow((wx, wy, ww, wh) => {
        kolom.measureInWindow((kx, ky, kw, kh) => {
          const batasBawah = Math.min(
            wy + wh,
            atasKeyboard.current ?? Dimensions.get('window').height
          ) - JARAK;
          const batasAtas = wy + JARAK;
          let geser = 0;
          if (ky + kh > batasBawah) geser = ky + kh - batasBawah;
          else if (ky < batasAtas) geser = ky - batasAtas;
          if (geser !== 0) {
            gulirRef.current.scrollTo({ y: Math.max(0, posisiGulir.current + geser), animated: true });
          }
        });
      });
    }, tunda);
  }

  useEffect(() => {
    const tampil = Keyboard.addListener('keyboardDidShow', (e) => {
      atasKeyboard.current = e.endCoordinates?.screenY ?? null;
      gulirKeKolomAktif(150); // tunggu tata letak menyesuaikan keyboard
    });
    const sembunyi = Keyboard.addListener('keyboardDidHide', () => {
      atasKeyboard.current = null;
    });
    return () => {
      tampil.remove();
      sembunyi.remove();
    };
  }, []);

  return (
    <KonteksGulir.Provider value={gulirKeKolomAktif}>
      <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
        <View ref={wadahRef} style={{ flex: 1 }} collapsable={false}>
          <ScrollView
            ref={gulirRef}
            contentContainerStyle={gayaIsi}
            keyboardShouldPersistTaps="handled"
            onScroll={(e) => (posisiGulir.current = e.nativeEvent.contentOffset.y)}
            scrollEventThrottle={16}
            refreshControl={refreshControl}
          >
            {children}
          </ScrollView>
        </View>
      </KeyboardAvoidingView>
    </KonteksGulir.Provider>
  );
}
