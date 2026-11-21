"""Verifikasi akhir berkas PDF v1.1 terhadap daftar perubahan revisi."""
import re

import pymupdf

HASIL = '/home/user/dokumen/hasil/'
SUMBER = '/home/user/dokumen/sumber/'

CEK_SKPL = [
    ('sampul versi 1.1', 'SKPL-1.1'),
    ('kode dokumen sampul', 'LELANGIKAN.ID-SKPL-1.1'),
    ('FR-21 daya beli', 'daya beli'),
    ('FR-21 limit kredit + berat aktual', 'berat aktual lot'),
    ('FR-64 bergabung selagi berjalan', 'selama sesi masih berjalan'),
    ('FR-64 OTP pemesanan', 'kode OTP pemesanan'),
    ('rute resmi sesi', '/api/sesi/{id}/penawaran'),
    ('rute laporan umum', '/api/laporan/{jenis}'),
    ('rute ekspor audit', '/api/audit?format=csv'),
    ('peristiwa settlement', 'SETTLEMENT_DIBAYAR'),
    ('peristiwa pantauan', 'LOT_DIPANTAU_DIBUKA'),
    ('kanal privat', 'nelayan.{id_pemilik}'),
    ('cadangan polling', 'cadangan polling 1 detik'),
    ('tawaran pertama = harga pembukaan', 'harga minimum yang sah sama dengan harga pembukaan'),
    ('entitas 27', 'dua puluh tujuh entitas'),
    ('diagram use case 8 aktor/13 UC', '8 aktor, 13 use case)'),
    ('judul ERD 27 entitas', 'Entity Relationship Diagram konseptual LELANGIKAN.ID (27 entitas)'),
    ('judul diagram kelas 37 kelas', 'Diagram kelas keseluruhan (37 kelas, 3 interface, 20 enumerasi)'),
    ('narasi kelas 37', 'Diagram memuat 37 kelas'),
    ('narasi enumerasi 20', '20 kelas enumerasi'),
    ('IV.6 catatan alias laporan', 'dilayani oleh satu rute "/api/laporan/{jenis}"'),
    ('FR-05 di luar cakupan', 'Pada versi 1.1 antrean luring belum diimplementasikan'),
    ('FR-06 di luar cakupan', 'Pada versi 1.1 status sinkronisasi belum diimplementasikan'),
    ('FR-07 timbangan simulasi', 'pembacaan berat berasal dari timbangan simulasi'),
]

CEK_DPPL = [
    ('kode dokumen 1.1', 'LELANGIKAN.ID-DPPL-1.1'),
    ('kamus: kode_otp', 'kode_otp'),
    ('kamus: pencairan_nelayan', 'pencairan_nelayan'),
    ('kamus: kode_tpi', 'kode_tpi'),
    ('kamus: preferensi', 'preferensi'),
    ('kamus: jejak pembatalan', 'alasan_batal'),
    ('kamus: tanggal pengambilan', 'tanggal_pengambilan'),
    ('enumerasi TujuanOtp', 'TujuanOtp'),
    ('enumerasi StatusLot diperluas', 'DIPERPANJANG'),
    ('enumerasi StatusBayarNelayan', 'DIBAYAR_SEBAGIAN, LUNAS, DITAHAN'),
    ('enumerasi JenisMutasi', 'PENYESUAIAN, PEMBAYARAN, PENGEMBALIAN'),
    ('ALG-01 tawaran pertama', 'tawaran pertama pada lelang naik'),
    ('rekap kamus 27 entitas', '27 entitas dan 204 atribut'),
    ('lingkup 27 entitas', 'dua puluh tujuh entitas data'),
    ('struktur 27 tabel', 'dua puluh tujuh tabel'),
    ('Tabel 2.4 kode_otp', 'daftar_pantauan, kode_otp'),
    ('judul ERD 27 tabel', 'Entity Relationship Diagram fisik LELANGIKAN.ID (27 tabel)'),
    ('judul diagram kelas 37 kelas', 'Diagram kelas keseluruhan LELANGIKAN.ID (37 kelas, 3 interface, 20 enumerasi)'),
    ('narasi enumerasi 20', '37 kelas, 3 interface, dan 20 enumerasi'),
]


def teks_pdf(path):
    d = pymupdf.open(path)
    return '\n'.join(d[i].get_text() for i in range(d.page_count)), d.page_count


def cek_daftar_isi(path):
    """Setiap baris 'judul<TAB/...>nomor' pada halaman daftar isi harus cocok dengan halaman nyata."""
    d = pymupdf.open(path)
    halaman_daftar = [i for i in range(d.page_count)
                      if 'DAFTAR ISI' in d[i].get_text()]
    if not halaman_daftar:
        return 0, 0, []
    nyata = {}
    for i in range(d.page_count):
        t = d[i].get_text()
        if 'DAFTAR ISI' in t or 'DAFTAR TABEL' in t or 'DAFTAR GAMBAR' in t:
            continue
        for baris in t.splitlines():
            nyata.setdefault(re.sub(r'[\s\t]+', ' ', baris).strip().lower(), i + 1)
    cocok = beda = 0
    contoh = []
    for i in range(d.page_count):
        t = d[i].get_text()
        if 'DAFTAR ISI' not in t:
            continue
        for baris in t.splitlines():
            m = re.match(r'^(.*?)[.\u2026]{2,}\s*(\d+)$', baris.strip())
            if not m:
                continue
            judul, no = re.sub(r'[\s\t]+', ' ', m.group(1)).strip().lower(), int(m.group(2))
            hasil = nyata.get(judul)
            if hasil is None:
                for k, v in nyata.items():
                    if k.startswith(judul.rstrip('.… ')):
                        hasil = v
                        break
            if hasil == no:
                cocok += 1
            else:
                beda += 1
                if len(contoh) < 5:
                    contoh.append((judul[:50], no, hasil))
    return cocok, beda, contoh


def jalankan():
    for nama, cek in (('SKPL-LelangIkan-v1.1', CEK_SKPL), ('DPPL-LelangIkan-v1.1', CEK_DPPL)):
        teks, hal = teks_pdf(HASIL + nama + '.pdf')
        print(f'== {nama}: {hal} halaman')
        gagal = 0
        for label, pola in cek:
            # nama panjang dapat terpotong baris pada kolom sempit, jadi bandingkan tanpa spasi
            ok = (re.sub(r'\s+', ' ', pola) in re.sub(r'\s+', ' ', teks)
                  or re.sub(r'\s+', '', pola) in re.sub(r'\s+', '', teks))
            if not ok:
                gagal += 1
            print(f'   {"OK  " if ok else "HILANG"} {label}')
        # halaman RIWAYAT REVISI DOKUMEN dihapus atas permintaan -> harus tidak ada
        ada_riwayat = 'RIWAYAT REVISI' in teks.upper()
        print(f'   {"HILANG" if ada_riwayat else "OK  "} halaman riwayat revisi sudah dihapus')
        gagal += 1 if ada_riwayat else 0
        cocok, beda, contoh = cek_daftar_isi(HASIL + nama + '.pdf')
        print(f'   daftar isi: {cocok} nomor cocok, {beda} berbeda')
        for c in contoh:
            print('      BEDA', c)
        total = len(cek) + 1
        print(f'   -> {total - gagal}/{total} butir revisi terverifikasi\n')


if __name__ == '__main__':
    jalankan()
