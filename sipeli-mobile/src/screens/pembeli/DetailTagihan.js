import { useCallback, useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { lelang } from '../../api';
import { DialogKonfirmasi, KotakPesan, Tombol } from '../../components/Dasar';
import FormGulir from '../../components/FormGulir';
import KodeQR from '../../components/KodeQR';
import RincianTagihan from '../../components/RincianTagihan';
import { Bagian } from '../../components/Tata';
import { bagikanNotaPdf } from '../../cetakNota';
import { formatRupiah } from '../../format';
import { usePolling } from '../../polling';
import { warna } from '../../tema';

function tanggalRelatif(hari) {
  const tanggal = new Date();
  tanggal.setDate(tanggal.getDate() + hari);
  return `${tanggal.getFullYear()}-${String(tanggal.getMonth() + 1).padStart(2, '0')}-${String(tanggal.getDate()).padStart(2, '0')}`;
}

function labelTanggal(tanggal) {
  const [tahun, bulan, hari] = tanggal.split('-').map(Number);
  return new Date(tahun, bulan - 1, hari).toLocaleDateString('id-ID', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
  });
}

export default function DetailTagihan({ idTransaksi, onKembali }) {
  const [t, setT] = useState(null);
  const [galat, setGalat] = useState(null);
  const [pesan, setPesan] = useState(null);
  const [membagikan, setMembagikan] = useState(false);
  const [memprosesSaldo, setMemprosesSaldo] = useState(false);
  const [menjadwalkan, setMenjadwalkan] = useState(false);
  const [tanyaBayarSaldo, setTanyaBayarSaldo] = useState(false);

  const muat = useCallback(async () => {
    try {
      const hasil = await lelang.detailTagihan(idTransaksi);
      setT(hasil.data);
      setGalat(null);
    } catch (e) {
      if (e.status !== 401) setGalat(e.message);
    }
  }, [idTransaksi]);
  usePolling(muat, 3000, !t || !['LUNAS', 'DIBATALKAN'].includes(t.status));

  async function bayarSaldo() {
    setTanyaBayarSaldo(false);
    setMemprosesSaldo(true);
    setGalat(null);
    setPesan(null);
    try {
      const hasil = await lelang.bayarSaldo(idTransaksi);
      setT(hasil.data);
      setPesan(hasil.pesan);
    } catch (e) {
      setGalat(e.message);
    } finally {
      setMemprosesSaldo(false);
    }
  }

  async function pilihPengambilan(tanggal) {
    setMenjadwalkan(true);
    setGalat(null);
    setPesan(null);
    try {
      const hasil = await lelang.jadwalkanPengambilan(idTransaksi, tanggal);
      setT(hasil.data);
      setPesan(hasil.pesan);
    } catch (e) {
      setGalat(e.message);
    } finally {
      setMenjadwalkan(false);
    }
  }

  async function bagikan() {
    setMembagikan(true);
    try {
      await bagikanNotaPdf(t);
    } catch (e) {
      setGalat(e.message);
    } finally {
      setMembagikan(false);
    }
  }

  if (!t) {
    return (
      <View style={s.tengah}>
        {galat ? <KotakPesan teks={galat} /> : <ActivityIndicator size="large" color={warna.utama} />}
        <Tombol judul="Kembali" jenis="teks" onPress={onKembali} gaya={{ marginTop: 12 }} />
      </View>
    );
  }

  const lunas = t.status === 'LUNAS';
  const kedaluwarsa = t.status === 'DIBATALKAN';
  const sisa = Number(t.sisa_tagihan);
  const saldoCukup = Number(t.saldo_tersedia) >= sisa;
  const hariIni = tanggalRelatif(0);
  const besok = tanggalRelatif(1);

  return (
    <View style={{ flex: 1, backgroundColor: warna.latar }}>
      <FormGulir gayaIsi={s.isi}>
        <Bagian>
          <RincianTagihan t={t} />
          <View style={s.saldo}>
            <Text style={s.labelSaldo}>{lunas ? 'Sisa saldo aplikasi' : 'Saldo tersedia'}</Text>
            <Text style={s.nilaiSaldo}>{formatRupiah(t.saldo_tersedia)}</Text>
          </View>
          {galat ? <KotakPesan teks={galat} gaya={{ marginTop: 12 }} /> : null}
          {pesan ? <KotakPesan jenis="sukses" teks={pesan} gaya={{ marginTop: 12 }} /> : null}
          {kedaluwarsa ? (
            <KotakPesan jenis="galat" teks="Batas pembayaran telah lewat. Lot dapat diproses kembali oleh TPI." gaya={{ marginTop: 14 }} />
          ) : !lunas ? (
            <>
              <KotakPesan
                jenis="info"
                teks={`Lunasi sebelum ${t.batas_bayar?.slice(0, 16).replace('T', ' ') || 'batas waktu'}. Pembayaran dapat melalui saldo aplikasi atau ke kasir dengan tunai, transfer, atau QRIS.`}
                gaya={{ marginTop: 14 }}
              />
              <Tombol
                judul={memprosesSaldo ? 'Memproses pembayaran...' : `Bayar lunas dari saldo (${formatRupiah(sisa)})`}
                onPress={() => setTanyaBayarSaldo(true)}
                memuat={memprosesSaldo}
                nonaktif={!saldoCukup || memprosesSaldo}
                gaya={{ marginTop: 12 }}
              />
              {!saldoCukup ? <Text style={s.catatan}>Saldo belum cukup. Silakan bayar melalui kasir atau isi saldo terlebih dahulu.</Text> : null}
            </>
          ) : (
            <>
              <View style={s.qr}>
                <KodeQR nilai={t.qr_nota} ukuran={170} />
                <Text style={s.teksQr}>Tunjukkan QR nota saat mengambil ikan.</Text>
              </View>
              <Text style={s.judulAmbil}>Jadwal pengambilan</Text>
              {t.tanggal_pengambilan ? (
                <Text style={s.jadwal}>Terjadwal: {labelTanggal(t.tanggal_pengambilan)}</Text>
              ) : (
                <Text style={s.catatan}>Pilih pengambilan hari ini atau besok.</Text>
              )}
              <Text style={s.catatan}>Batas pengambilan: {labelTanggal(besok)}, pada jam operasional TPI.</Text>
              <View style={s.tombolTanggal}>
                <Tombol
                  judul="Hari ini"
                  onPress={() => pilihPengambilan(hariIni)}
                  memuat={menjadwalkan}
                  nonaktif={menjadwalkan}
                  gaya={{ flex: 1 }}
                />
                <Tombol
                  judul="Besok"
                  jenis="garis"
                  onPress={() => pilihPengambilan(besok)}
                  memuat={menjadwalkan}
                  nonaktif={menjadwalkan}
                  gaya={{ flex: 1 }}
                />
              </View>
              <Tombol judul="Simpan / Bagikan Nota PDF" ikon="bagikan" onPress={bagikan} memuat={membagikan} gaya={{ marginTop: 12 }} />
            </>
          )}
        </Bagian>
        <View style={{ paddingHorizontal: 16 }}>
          <Tombol judul="Kembali" jenis="garis" onPress={onKembali} />
        </View>
      </FormGulir>
      <DialogKonfirmasi
        tampil={tanyaBayarSaldo}
        judul="Bayar dengan saldo?"
        pesan={`Saldo Anda akan dipotong sebesar ${formatRupiah(sisa)} untuk melunasi ${t.kode_transaksi}. Pembayaran tidak dapat dibatalkan.`}
        teksYa="Bayar sekarang"
        onYa={bayarSaldo}
        onTidak={() => setTanyaBayarSaldo(false)}
      />
    </View>
  );
}

const s = StyleSheet.create({
  tengah: { flex: 1, backgroundColor: warna.latar, alignItems: 'center', justifyContent: 'center', padding: 16 },
  isi: { paddingBottom: 32 },
  qr: { alignItems: 'center', marginTop: 16, paddingTop: 16, gap: 8, borderTopWidth: 1, borderTopColor: warna.garisHalus },
  teksQr: { fontSize: 12, color: warna.teksRedup, textAlign: 'center' },
  saldo: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 14 },
  labelSaldo: { fontSize: 13, color: warna.teksRedup },
  nilaiSaldo: { fontSize: 15, fontWeight: '700', color: warna.teks },
  catatan: { fontSize: 12, color: warna.teksRedup, marginTop: 8, lineHeight: 17 },
  judulAmbil: { fontSize: 15, fontWeight: '700', color: warna.teks, marginTop: 18 },
  jadwal: { fontSize: 13, color: warna.sukses, marginTop: 5 },
  tombolTanggal: { flexDirection: 'row', gap: 10, marginTop: 12 },
});
