"""Sunting DPPL LelangIkan.id (DOCX) sesuai spesifikasi revisi v1.1.

Tata letak asli dipertahankan: hanya teks sel/paragraf yang berubah serta penambahan
baris pada Tabel 2.4, kamus data, dan enumerasi. Gambar tidak disentuh.
"""
import copy
import os
import re

from docx import Document

from ganti_gambar import ganti_gambar
from docx.shared import Cm
from docx.table import Table, _Row
from docx.text.paragraph import Paragraph

MASUK = '/tmp/sumber/DPPL-LelangIkan-v1.0-docx.docx'
KELUAR = '/home/user/dokumen/hasil/DPPL-LelangIkan-v1.1.docx'

catatan = []


def bersih(t):
    return (t or '').replace('\u200b', '').replace('\u00ad', '')


def log(p):
    catatan.append(p)
    print('  ' + p)


def ganti_paragraf(p, lama, baru):
    if lama in p.text:
        for run in p.runs:
            if lama in run.text:
                run.text = run.text.replace(lama, baru)
                return True
    teks = bersih(p.text)
    if lama in teks:
        if p.runs:
            p.runs[0].text = teks.replace(lama, baru, 1)
            for run in p.runs[1:]:
                run.text = ''
            return True
    return False


def sel_tulis(sel, teks):
    par = sel.paragraphs[0]
    if par.runs:
        par.runs[0].text = teks
        for run in par.runs[1:]:
            run.text = ''
    else:
        par.add_run(teks)
    for ekstra in sel.paragraphs[1:]:
        ekstra._p.getparent().remove(ekstra._p)


def baris_baru(tabel, sebelum=-1):
    acuan = tabel.rows[sebelum]._tr
    baru = copy.deepcopy(acuan)
    acuan.addnext(baru)
    return _Row(baru, tabel)


def tabel_berlabel(dok, label):
    """Tabel pertama sesudah paragraf berlabel 'Tabel x.y ...'."""
    anak = list(dok.element.body)
    for i, el in enumerate(anak):
        if el.tag.endswith('}p') and bersih(Paragraph(el, dok).text).startswith(label):
            for e in anak[i + 1:]:
                if e.tag.endswith('}tbl'):
                    return Table(e, dok)
                if e.tag.endswith('}p') and bersih(Paragraph(e, dok).text).strip():
                    break
    return None


# ------------------------------------------------------------------ 1. versi & lingkup

def ubah_versi(dok):
    n = 0
    for p in dok.paragraphs:
        if 'LELANGIKAN.ID-DPPL-1.0' in bersih(p.text):
            ganti_paragraf(p, 'LELANGIKAN.ID-DPPL-1.0', 'LELANGIKAN.ID-DPPL-1.1')
            n += 1
    for bagian in dok.sections:
        for p in bagian.footer.paragraphs + bagian.header.paragraphs:
            if 'DPPL-1.0' in bersih(p.text):
                ganti_paragraf(p, 'DPPL-1.0', 'DPPL-1.1')
                n += 1
    log(f'Versi dokumen: {n} tempat diubah menjadi DPPL-1.1')


def ubah_lingkup(dok):
    for p in dok.paragraphs:
        t = bersih(p.text)
        if 'dua puluh lima entitas data' in t:
            ganti_paragraf(p, 'dua puluh lima entitas data', 'dua puluh tujuh entitas data')
            log('Lingkup 1.2: 25 -> 27 entitas data')
        if 'dua puluh lima tabel' in t:
            ganti_paragraf(p, 'dua puluh lima tabel', 'dua puluh tujuh tabel')
            log('Struktur basis data 2.2.5: 25 -> 27 tabel')
        if re.search(r'Gambar 2\.2.*\(25 tabel\)', t):
            ganti_paragraf(p, '(25 tabel)', '(27 tabel)')
            log('Judul Gambar 2.2: jumlah tabel 25 -> 27')


# ------------------------------------------------------------------ 2. Tabel 2.4 kelompok tabel

KELOMPOK_BARU = [
    ('Kelembagaan dan pengguna', 'pengguna, peran, tpi, konfigurasi',
     'Identitas pengguna, hak akses, unit TPI, parameter sistem'),
    ('Kedatangan dan tangkapan', 'pemilik_kapal, kapal, kedatangan_kapal, jenis_ikan, grade_mutu, tangkapan',
     'Data pendaratan kapal dan rincian hasil tangkapan yang menjadi dasar lot'),
    ('Lelang', 'lot_lelang, foto_lot, sesi_lelang, penawaran, daftar_pantauan, kode_otp',
     'Satuan lelang beserta fotonya, jalannya sesi lelang naik atau turun, seluruh penawaran, '
     'daftar pantauan pembeli, dan kode OTP pemesanan lot'),
    ('Keuangan', 'transaksi_penjualan, pembayaran, saldo_pembeli, mutasi_saldo, tarif_retribusi, '
                 'pembayaran_nelayan, pencairan_nelayan',
     'Tagihan, pembayaran, saldo, retribusi, pembayaran kepada nelayan, dan pencairan bertahapnya'),
    ('Pendukung', 'notifikasi, audit_log, tautan_akses_nelayan, kartu_nelayan',
     'Pemberitahuan, jejak audit hanya-tambah, tautan akses portal nelayan, dan kartu nelayan ber-QR'),
]


def ubah_kelompok_tabel(dok):
    tabel = tabel_berlabel(dok, 'Tabel 2.4')
    if tabel is None:
        log('Tabel 2.4: tidak ditemukan')
        return
    for baris, (kelompok, isi, fungsi) in zip(tabel.rows[1:], KELOMPOK_BARU):
        sel_tulis(baris.cells[0], kelompok)
        sel_tulis(baris.cells[1], isi)
        sel_tulis(baris.cells[2], fungsi)
    log('Tabel 2.4: daftar tabel per kelompok diselaraskan (kode_otp, pencairan_nelayan ditambahkan)')


# ------------------------------------------------------------------ 3. kamus data

ATRIBUT_TAMBAHAN = {
    'tpi': [('kode_tpi', 'VARCHAR(10) UQ')],
    'pengguna': [('preferensi', 'JSON, NULL')],
    'transaksi_penjualan': [('id_pembatal', 'BIGINT UNSIGNED, NULL, FK pengguna.id_pengguna'),
                            ('alasan_batal', 'VARCHAR(255), NULL'),
                            ('waktu_batal', 'DATETIME, NULL'),
                            ('tanggal_pengambilan', 'DATE, NULL')],
}

ENTITAS_BARU = [
    ('kode_otp',
     '- id_otp (BIGINT UNSIGNED PK); - id_pengguna (BIGINT UNSIGNED, FK pengguna.id_pengguna); '
     '- tujuan (ENUM(AKTIVASI, PEMESANAN)); - id_lot (BIGINT UNSIGNED, NULL, FK lot_lelang.id_lot); '
     '- kode_hash (VARCHAR(255)); - kedaluwarsa_pada (DATETIME); - jumlah_percobaan (TINYINT UNSIGNED); '
     '- terpakai_pada (DATETIME, NULL); - waktu_dibuat (DATETIME)',
     'terbitkan(pengguna, tujuan, idLot, masaBerlaku) -> kode; verifikasi(kode, tujuan, idLot) -> boolean; '
     'hanguskan()'),
    ('pencairan_nelayan',
     '- id_pencairan (BIGINT UNSIGNED PK); - id_bayar_nelayan (BIGINT UNSIGNED, FK pembayaran_nelayan.id_bayar_nelayan); '
     '- nominal (DECIMAL(12,2)); - cara_pencairan (ENUM(TUNAI, TRANSFER_REKENING, KARTU_NELAYAN)); '
     '- nomor_referensi (VARCHAR(40), NULL); - waktu (DATETIME); - id_kasir (BIGINT UNSIGNED, FK pengguna.id_pengguna); '
     '- keterangan (VARCHAR(200), NULL)',
     'catat(idBayarNelayan, nominal, caraPencairan, nomorReferensi) -> PencairanNelayan; '
     'totalTercairkan(idBayarNelayan) -> nominal; getSisaPencairan()'),
]


def ubah_kamus(dok):
    tabel = None
    for t in dok.tables:
        kepala = [bersih(c.text).strip().lower() for c in t.rows[0].cells]
        if 'entitas' in kepala and any('atribut' in k for k in kepala) and 'metode' in kepala:
            tabel = t
            break
    if tabel is None:
        log('Kamus data: tidak ditemukan')
        return

    # (a) atribut yang belum tercatat pada entitas yang sudah ada
    for baris in tabel.rows[1:]:
        nama = bersih(baris.cells[0].text).strip()
        tambahan = ATRIBUT_TAMBAHAN.get(nama)
        if not tambahan:
            continue
        sel = baris.cells[1]
        teks = bersih(sel.text).rstrip().rstrip(';')
        ada = teks
        sisip = [f'- {a} ({b})' for a, b in tambahan if f'- {a} ' not in ada]
        if sisip:
            sel_tulis(sel, teks + '; ' + '; '.join(sisip))
            log(f'Kamus data {nama}: atribut {" ".join(a for a, _ in tambahan)} ditambahkan')

    # (b) entitas yang belum memiliki baris
    sudah = {bersih(r.cells[0].text).strip() for r in tabel.rows[1:]}
    for nama, atribut, metode in ENTITAS_BARU:
        if nama in sudah:
            continue
        baris = baris_baru(tabel)
        sel_tulis(baris.cells[0], nama)
        sel_tulis(baris.cells[1], atribut)
        if len(baris.cells) > 2:
            sel_tulis(baris.cells[2], metode)
        log(f'Kamus data: entitas {nama} ditambahkan')

    # (c) rekap di bawah tabel diselaraskan dengan isi tabel (hanya angka yang dapat diperiksa)
    jumlah_entitas = len(tabel.rows) - 1
    jumlah_atribut = sum(len(re.findall(r'-\s*[a-zA-Z0-9_]+\s*\(', bersih(r.cells[1].text)))
                         for r in tabel.rows[1:])
    for p in dok.paragraphs:
        t = bersih(p.text)
        if t.startswith('Jumlah keseluruhan:'):
            m = re.match(r'Jumlah keseluruhan:.*?di luar get/set\.', t)
            if m and p.runs:
                p.runs[0].text = t.replace(
                    m.group(0),
                    f'Jumlah keseluruhan: {jumlah_entitas} entitas dan {jumlah_atribut} atribut; '
                    'setiap entitas dilengkapi metode get/set dan metode domain pada kolom Metode.')
                for run in p.runs[1:]:
                    run.text = ''
                log(f'Kamus data: rekap diperbarui menjadi {jumlah_entitas} entitas dan {jumlah_atribut} atribut')
            break


# ------------------------------------------------------------------ 4. enumerasi

ENUM_TAMBAHAN = [
    ('TujuanOtp', 'AKTIVASI, PEMESANAN', 'kode_otp.tujuan; Pengguna.verifikasiOtp()'),
]

# enumerasi yang nilainya pada DPPL belum lengkap dibanding skema basis data terpasang
ENUM_UBAH = {
    'JenisMutasi': ('SETOR, PENARIKAN, PEMBAYARAN, PENGEMBALI',
                    'SETOR, PENARIKAN, PENYESUAIAN, PEMBAYARAN, PENGEMBALIAN',
                    'SaldoPembeli, DepositService, PembayaranService'),
    'StatusBayarNelayan': ('BELUM_DIBAYAR, DIBAYAR, DITAHAN',
                           'BELUM_DIBAYAR, DIBAYAR_SEBAGIAN, LUNAS, DITAHAN',
                           'pembayaran_nelayan.status; SettlementService'),
    'StatusLot': ('DRAFT_HARGA, MENUNGGU_LELANG, DILELANG, TERJUAL, TIDAK_TERJUAL, DIBATALKAN',
                  'DRAFT_HARGA, MENUNGGU_LELANG, DILELANG, DIPERPANJANG, TERJUAL, TIDAK_TERJUAL, '
                  'DIBATALKAN, SELESAI',
                  'lot_lelang.status; LotLelang, SesiLelang'),
}

def ubah_enumerasi(dok):
    tabel = None
    for t in dok.tables:
        kepala = [bersih(c.text).strip().lower() for c in t.rows[0].cells]
        if 'enumerasi' in kepala:
            tabel = t
            break
    if tabel is None:
        log('Tabel enumerasi: tidak ditemukan')
        return
    for baris in tabel.rows[1:]:
        nama = bersih(baris.cells[0].text).strip()
        if nama in ENUM_UBAH:
            lama, baru, dipakai = ENUM_UBAH[nama]
            if lama in bersih(baris.cells[1].text):
                sel_tulis(baris.cells[1], baru)
                if len(baris.cells) > 2:
                    sel_tulis(baris.cells[2], dipakai)
                log(f'Enumerasi {nama}: nilai diselaraskan dengan skema basis data')
    sudah = {bersih(r.cells[0].text).strip() for r in tabel.rows[1:]}
    for nama, nilai, dipakai in ENUM_TAMBAHAN:
        if nama in sudah:
            continue
        baris = baris_baru(tabel)
        for i, isi in enumerate((nama, nilai, dipakai)):
            sel_tulis(baris.cells[i], isi)
        log(f'Enumerasi: {nama} ditambahkan')


# ------------------------------------------------------------------ 5. ALG-01 catatan tawaran pertama

def ubah_alg01(dok):
    for i, p in enumerate(dok.paragraphs):
        t = bersih(p.text)
        if t.startswith('6.') and 'harga_pembukaan' in t and 'saldo/limit' in t:
            lanjut = bersih(dok.paragraphs[i + 1].text) if i + 1 < len(dok.paragraphs) else ''
            if 'tawaran pertama' in lanjut:
                log('ALG-01: catatan tawaran pertama sudah ada')
                return
            baru = copy.deepcopy(p._p)
            p._p.addnext(baru)
            par = Paragraph(baru, p._parent)
            for run in list(par.runs)[1:]:
                run._r.getparent().remove(run._r)
            teks = ('6a.\tKhusus tawaran pertama pada lelang naik, harga minimum yang sah sama dengan '
                    'harga_pembukaan lot (kenaikan minimum belum berlaku); pada lelang turun, Ambil sah '
                    'pada harga yang sedang berjalan dan ditolak bila harga tersebut kurang dari '
                    'harga batas bawah.')
            if par.runs:
                par.runs[0].text = teks
            else:
                par.add_run(teks)
            log('ALG-01: langkah 6a tentang tawaran pertama/Ambil ditambahkan')
            return
    log('ALG-01: langkah 6 tidak ditemukan (dilewati)')


# ------------------------------------------------------------------ 6. diagram terbaru

DIAGRAM = '/tmp/diagram'
GAMBAR_DPPL = [
    (r'Gambar 2\.2', f'{DIAGRAM}/erd-final.png', 24.4, 15.0),
    (r'Gambar 3\.27', f'{DIAGRAM}/class-final.png', 24.4, 16.2),
]


def ubah_diagram(dok):
    """Gambar hasil render diagrams/*.drawio menggantikan gambar v1.0."""
    ganti_gambar(dok, GAMBAR_DPPL, log)


def ubah_kalimat_kelas(dok):
    """Diagram kelas baru memuat 37 kelas dan 20 enumerasi."""
    n = 0
    for p in dok.paragraphs:
        if '35 kelas' in bersih(p.text):
            ganti_paragraf(p, '35 kelas', '37 kelas')
            n += 1
        if '19 enumerasi' in bersih(p.text):
            ganti_paragraf(p, '19 enumerasi', '20 enumerasi')
            n += 1
    log(f'Jumlah kelas/enumerasi pada narasi & judul diagram: {n} tempat diperbarui '
        f'(35 -> 37 kelas, 19 -> 20 enumerasi)')


# ------------------------------------------------------------------ 7. riwayat revisi


RIWAYAT = [
    ('Versi', 'Tanggal', 'Ringkasan perubahan'),
    ('1.0', '2026', 'Terbitan awal: perancangan 13 use case, 25 tabel, dan 19 algoritma/kueri.'),
    ('1.1', '21 November 2026',
     '(a) kode dokumen diselaraskan menjadi DPPL-1.1; (b) kamus data dilengkapi entitas kode_otp dan '
     'pencairan_nelayan serta atribut kode_tpi, preferensi, dan jejak pembatalan transaksi; (c) Tabel 2.4 '
     'dan jumlah tabel dikoreksi menjadi dua puluh tujuh sesuai basis data terpasang; (d) enumerasi '
     'TujuanOtp dan StatusPencairan ditambahkan; (e) ALG-01 diperjelas untuk tawaran pertama pada lelang '
     'naik dan Ambil pada lelang turun; (f) perancangan portal nelayan, pemantauan lot, dan pencairan '
     'bertahap dinyatakan sebagai bagian cakupan versi 1.1; (g) Gambar 2.2 dan Gambar 3.27 diganti dengan diagram terbaru dari berkas diagrams/*.drawio sehingga ERD memuat 27 tabel (termasuk pencairan_nelayan) dan diagram kelas memuat 37 kelas.'),
]


def tambah_riwayat(dok):
    if any('RIWAYAT REVISI' in bersih(p.text).upper() for p in dok.paragraphs):
        log('Riwayat revisi: sudah ada')
        return
    jangkar = None
    for el in dok.element.body:
        if el.tag.endswith('}p') and bersih(Paragraph(el, dok).text).strip() == 'DAFTAR ISI':
            jangkar = el
            break
    if jangkar is None:
        log('Riwayat revisi: DAFTAR ISI tidak ditemukan')
        return

    judul = dok.add_paragraph('RIWAYAT REVISI DOKUMEN', style='Heading 1')
    judul.paragraph_format.page_break_before = True
    tabel = dok.add_table(rows=len(RIWAYAT), cols=3)
    tabel.style = next((t.style.name for t in dok.tables if t.style is not None), 'Table Grid')
    for r, isi in enumerate(RIWAYAT):
        for c, nilai in enumerate(isi):
            sel_tulis(tabel.rows[r].cells[c], nilai)
    for lebar, kolom in zip((Cm(2.2), Cm(3.4), Cm(10.9)), tabel.columns):
        kolom.width = lebar
        for sel in kolom.cells:
            sel.width = lebar
    jangkar.addprevious(judul._p)
    jangkar.addprevious(tabel._tbl)

    # entri daftar isi
    for el in list(jangkar.getparent().iterchildren()):
        if el.tag.endswith('}p') and re.match(r'^1\.\s*PENDAHULUAN', bersih(Paragraph(el, dok).text).strip()):
            entri = copy.deepcopy(el)
            el.addprevious(entri)
            par = Paragraph(entri, dok)
            for run in list(par.runs)[1:]:
                run._r.getparent().remove(run._r)
            if par.runs:
                par.runs[0].text = 'RIWAYAT REVISI DOKUMEN\t3'
            break
    log('Riwayat revisi: judul + tabel + entri daftar isi ditambahkan')


# ------------------------------------------------------------------ jalan

def utama():
    dok = Document(MASUK)
    print('DPPL: menyunting...')
    ubah_versi(dok)
    ubah_lingkup(dok)
    ubah_kelompok_tabel(dok)
    ubah_kamus(dok)
    ubah_enumerasi(dok)
    ubah_alg01(dok)
    ubah_diagram(dok)
    ubah_kalimat_kelas(dok)
    # halaman RIWAYAT REVISI DOKUMEN dihapus atas permintaan (v1.1); fungsi tambah_riwayat() dibiarkan
    # tetapi tidak dipanggil lagi.
    os.makedirs('/home/user/dokumen/hasil', exist_ok=True)
    dok.save(KELUAR)
    print(f'\nTersimpan: {KELUAR}  ({len(catatan)} perubahan)')


if __name__ == '__main__':
    utama()
