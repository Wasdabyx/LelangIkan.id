"""Sunting SKPL LelangIkan.id (DOCX) sesuai spesifikasi revisi v1.1.

Prinsip: tata letak asli dipertahankan. Yang diubah hanya teks/baris yang disebut
spesifikasi (SPESIFIKASI-REVISI-v1.1.md); gaya huruf, tabel, dan gambar tidak disentuh.
Setiap perubahan dicetak ke layar agar dapat diperiksa.
"""
import copy
import re
import sys

from docx import Document
from docx.table import _Row
from docx.shared import Cm

from ganti_gambar import ganti_gambar

# DOCX sumber diunduh ulang dari Google Drive (ekspor publik) agar tidak menetap di ruang kerja.
MASUK = '/tmp/sumber/SKPL-LelangIkan-v1.0-docx.docx'
KELUAR = '/home/user/dokumen/hasil/SKPL-LelangIkan-v1.1.docx'

catatan = []


def bersih(teks):
    """Buang zero-width space / soft hyphen yang dipakai Word untuk pemenggalan."""
    return (teks or '').replace('\u200b', '').replace('\u00ad', '')


def log(pesan):
    catatan.append(pesan)
    print('  ' + pesan)


# ---------------------------------------------------------------- utilitas teks

def cocok_paragraf(dok, pola):
    return [(i, p) for i, p in enumerate(dok.paragraphs) if re.search(pola, bersih(p.text))]


def ganti_dalam_paragraf(p, lama, baru):
    """Ganti teks pada paragraf dengan mempertahankan format run pertama.
    Jika teks terpecah antar-run, seluruh teks paragraf diganti memakai format run pertama."""
    teks = p.text
    if lama in teks:
        # coba ganti di dalam satu run
        for run in p.runs:
            if lama in run.text:
                run.text = run.text.replace(lama, baru)
                return True
        # teks terpecah: sisipkan pada run pertama, kosongkan sisanya
        run_pertama = p.runs[0]
        utuh = teks.replace(lama, baru, 1)
        for run in p.runs[1:]:
            run.text = ''
        run_pertama.text = utuh
        return True
    # teks memuat karakter pemisah (ZWSP) -> bersihkan dulu
    bersih_teks = bersih(teks)
    if lama in bersih_teks:
        run_pertama = p.runs[0]
        utuh = bersih_teks.replace(lama, baru, 1)
        for run in p.runs[1:]:
            run.text = ''
        run_pertama.text = utuh
        return True
    return False


def sisip_paragraf_setelah(p, teks, gaya=None):
    baru = copy.deepcopy(p._p)
    p._p.addnext(baru)
    from docx.text.paragraph import Paragraph
    par = Paragraph(baru, p._parent)
    for r in list(par.runs)[1:]:
        r._r.getparent().remove(r._r)
    if par.runs:
        par.runs[0].text = teks
    else:
        par.add_run(teks)
    if gaya:
        par.style = par.style if par.style.name == gaya else par.style
    return par


def sel_tulis(sel, teks):
    """Tulis teks sel dengan mempertahankan format run pertama."""
    par = sel.paragraphs[0]
    if par.runs:
        for r in par.runs[1:]:
            r.text = ''
        par.runs[0].text = teks
    else:
        par.add_run(teks)
    for ekstra in sel.paragraphs[1:]:
        ekstra._p.getparent().remove(ekstra._p)


def baris_baru(tabel, sebelum_index=-1):
    """Duplikasi baris (untuk mewarisi format) lalu kembalikan objek _Row."""
    acuan = tabel.rows[sebelum_index]._tr
    baru = copy.deepcopy(acuan)
    acuan.addnext(baru)
    return _Row(baru, tabel)


def tabel_punya(tabel, teks):
    return any(teks in bersih(c.text) for r in tabel.rows for c in r.cells)


# ---------------------------------------------------------------- 1. versi dokumen

def ubah_versi(dok):
    n = 0
    for p in dok.paragraphs:
        t = bersih(p.text)
        if 'LELANGIKAN.ID-SKPL-1.0' in t:
            ganti_dalam_paragraf(p, 'LELANGIKAN.ID-SKPL-1.0', 'LELANGIKAN.ID-SKPL-1.1')
            n += 1
        if 'Versi 1.0' in bersih(p.text):
            ganti_dalam_paragraf(p, 'Versi 1.0', 'Versi 1.1')
            n += 1
    # kaki halaman
    for bagian in dok.sections:
        for jenis in ('header', 'footer'):
            wadah = getattr(bagian, jenis)
            for p in wadah.paragraphs:
                if 'SKPL-1.0' in bersih(p.text):
                    ganti_dalam_paragraf(p, 'SKPL-1.0', 'SKPL-1.1')
                    n += 1
        for p in bagian.first_page_header.paragraphs + bagian.first_page_footer.paragraphs:
            if 'SKPL-1.0' in bersih(p.text):
                ganti_dalam_paragraf(p, 'SKPL-1.0', 'SKPL-1.1')
                n += 1
    log(f'Versi dokumen: {n} tempat diubah menjadi 1.1')


# ---------------------------------------------------------------- 2. tabel kebutuhan fungsional

FR_BARU = {
    'FR-21': ('Sistem menolak penawaran apabila saldo ditambah limit kredit pembeli (daya beli) tidak '
              'mencukupi nilai penawaran, yaitu harga tawaran dikalikan berat aktual lot, dan menerapkan '
              'aturan yang sama pada pengambilan harga pada lelang turun. Pesan penolakan menyebutkan nilai '
              'daya beli dan jalan keluar berupa top up melalui Kasir TPI.'),
    'FR-64': ('Sistem mengizinkan pembeli menambahkan lot ke daftar pantauan, menetapkan batas harga pribadi, '
              'dan menerima notifikasi saat sesi lot tersebut dibuka (peristiwa LOT_DIPANTAU_DIBUKA). Pembeli '
              'juga dapat bergabung mengikuti lelang selama sesi masih berjalan, yaitu pada status lot '
              'MENUNGGU_LELANG, DILELANG, atau DIPERPANJANG, dengan konfirmasi kode OTP pemesanan; setelah '
              'sesi ditutup pembeli tidak dapat lagi bergabung.'),
}

FR_PRIORITAS = {'FR-21': 'Tinggi', 'FR-64': 'Sedang'}


def ubah_kebutuhan(dok):
    for tabel in dok.tables:
        if not tabel_punya(tabel, 'Prioritas') or not tabel_punya(tabel, 'Kebutuhan'):
            continue
        for baris in tabel.rows[1:]:
            kode = bersih(baris.cells[0].text).strip()
            if kode in FR_BARU:
                sel_tulis(baris.cells[1], FR_BARU[kode])
                if len(baris.cells) > 2:
                    sel_tulis(baris.cells[2], FR_PRIORITAS[kode])
                log(f'{kode}: deskripsi kebutuhan diperbarui')


# ---------------------------------------------------------------- 3. Tabel IV.6 (REST)

RUTE_TAMBAHAN = [
    ('GET', '/api/sesi', 'A3', 'Daftar sesi lelang hari ini (aktif dan ditutup)', 'Ya'),
    ('GET', '/api/lot/siap-lelang', 'A3', 'Antrean lot siap dilelang', 'Ya'),
    ('GET', '/api/laporan/{jenis}', 'A6', 'Laporan harga, retribusi, tren, atau kinerja; ?format=csv|pdf', 'Ya'),
    ('GET', '/api/kasir/settlement', 'A5', 'Daftar kedatangan yang siap diselesaikan', 'Ya'),
    ('GET', '/api/audit?format=csv', 'A7', 'Ekspor jejak audit untuk pemeriksaan', 'Ya'),
]

KETERANGAN_BARIS = {
    'POST /api/master/{entitas}': ('Pengelolaan data master',
        'Pengelolaan data master (entitas: jenis-ikan, grade-mutu, tarif-retribusi, kapal, '
        'pemilik-kapal, tpi, peran, pengguna, konfigurasi)'),
    'POST /api/settlement/{idKedatangan}': ('Perhitungan dan pembayaran nelayan',
        'Perhitungan settlement nelayan; pencairan melalui POST /api/pembayaran-nelayan/{id}/cairkan'),
    'GET /api/nelayan/rekap?token=': ('Rekap hasil lelang dan status pembayaran',
        'Rekap hasil lelang dan status pembayaran; wajib token + kode verifikasi 4 digit'),
    'POST /api/sesi/{id}/penawaran': ('Pengajuan penawaran atau Ambil pada lelang turun',
        'Pengajuan penawaran (mode naik, muatan nominal) atau Ambil (mode turun), dengan kanal '
        'di tempat atau jarak jauh'),
}


def ubah_tabel_rest(dok):
    for tabel in dok.tables:
        if not tabel_punya(tabel, 'Titik Akhir'):
            continue
        for baris in tabel.rows[1:]:
            metode = bersih(baris.cells[0].text).strip()
            titik = bersih(baris.cells[1].text).strip().replace('\u200b', '')
            kunci = f'{metode} {titik}'
            if kunci in KETERANGAN_BARIS:
                lama, baru = KETERANGAN_BARIS[kunci]
                if lama in bersih(baris.cells[3].text):
                    sel_tulis(baris.cells[3], baru)
                    log(f'Tabel IV.6: keterangan {kunci} diperjelas')
        sudah = {bersih(b.cells[1].text).strip() for b in tabel.rows[1:]}
        for metode, titik, aktor, fungsi, token in RUTE_TAMBAHAN:
            if titik in sudah:
                continue
            baris = baris_baru(tabel)
            for i, nilai in enumerate((metode, titik, aktor, fungsi, token)):
                sel_tulis(baris.cells[i], nilai)
            log(f'Tabel IV.6: baris baru {metode} {titik}')
        break


# ---------------------------------------------------------------- 4. Tabel IV.7 (peristiwa)

PERISTIWA_BARU = [
    ('SETTLEMENT_DIBAYAR', 'peladen → klien',
     'id_kedatangan, id_bayar_nelayan, total_netto, total_dibayar, status, waktu_bayar',
     'Portal nelayan terkait (kanal privat nelayan.{id_pemilik})'),
    ('LOT_DIPANTAU_DIBUKA', 'peladen → klien',
     'id_lot, kode_lot, id_sesi, mode, batas_harga_dilewati',
     'Pembeli yang memantau lot tersebut (kanal privat pembeli.{id_pembeli})'),
]

CATATAN_KANAL = (
    'Kanal tpi.{id_tpi} memuat seluruh klien TPI. Kanal nelayan.{id_pemilik} dan pembeli.{id_pembeli} '
    'bersifat privat: hanya pengguna berhak (pemilik kapal terkait, pembeli pemantau) yang dapat '
    'berlangganan. Bila kanal WebSocket terputus, klien memakai cadangan polling 1 detik melalui '
    'GET /api/papan-lelang dan GET /api/sesi/{id} (NFR-10).')


def ubah_tabel_peristiwa(dok):
    for tabel in dok.tables:
        if not tabel_punya(tabel, 'Peristiwa'):
            continue
        sudah = {bersih(r.cells[0].text).strip() for r in tabel.rows[1:]}
        for nama, arah, muatan, penerima in PERISTIWA_BARU:
            if nama in sudah:
                continue
            baris = baris_baru(tabel)
            for i, nilai in enumerate((nama, arah, muatan, penerima)):
                sel_tulis(baris.cells[i], nilai)
            log(f'Tabel IV.7: peristiwa {nama} ditambahkan')
        # keterangan kanal setelah tabel
        if not any('Kanal tpi.{id_tpi} memuat' in bersih(p.text) for p in dok.paragraphs):
            dasar = tabel._tbl.getnext()
            from docx.text.paragraph import Paragraph
            if dasar is not None and dasar.tag.endswith('p'):
                p_dasar = Paragraph(dasar, dok.paragraphs[-1]._parent)
                p_baru = sisip_paragraf_setelah(p_dasar, CATATAN_KANAL)
                # pindahkan keterangan ke SEBELUM paragraf dasar
                p_dasar._p.addprevious(p_baru._p)
                log('Tabel IV.7: keterangan kanal real-time ditambahkan')
        break


# ---------------------------------------------------------------- 5. ALG-01

# ALG-01 tidak berada di SKPL (letaknya di DPPL), sehingga SKPL tidak disentuh untuk butir ini.
# Yang ditambahkan pada SKPL adalah penegasan urutan validasi pada alur UC-04 (lihat ubah_uc04).
def ubah_uc04(dok):
    """Tegaskan bahwa tawaran pertama sah pada harga pembukaan (ALG-01) dan urutan pemeriksaan."""
    for i, p in enumerate(dok.paragraphs):
        t = bersih(p.text)
        if t.startswith('6.') and 'harga minimum yang sah' in t:
            lanjut = bersih(dok.paragraphs[i + 1].text) if i + 1 < len(dok.paragraphs) else ''
            if 'Pada tawaran pertama' in lanjut:
                log('UC-04: catatan tawaran pertama sudah ada')
                return
            sisip_paragraf_setelah(
                p,
                'Pada tawaran pertama, harga minimum yang sah sama dengan harga pembukaan lot; pemeriksaan '
                'kenaikan minimum baru berlaku untuk tawaran berikutnya. Tawaran yang lebih dari sepuluh kali '
                'harga pembukaan ditolak sebagai salah ketik.')
            log('UC-04: catatan tawaran pertama pada harga pembukaan ditambahkan')
            return
    log('UC-04: paragraf langkah 6 tidak ditemukan (dilewati)')


# ---------------------------------------------------------------- 6. jumlah entitas

def ubah_jumlah_entitas(dok):
    """SKPL v1.0 menyebut 25 entitas; tabel nyata pada basis data berjumlah 27."""
    n = 0
    for p in dok.paragraphs:
        t = bersih(p.text)
        if 'dua puluh lima entitas' in t:
            ganti_dalam_paragraf(p, 'dua puluh lima entitas', 'dua puluh tujuh entitas')
            n += 1
        if re.search(r'Gambar III\.15', t) and '25 entitas' in t:
            ganti_dalam_paragraf(p, '25 entitas', '27 entitas')
            n += 1
    log(f'Jumlah entitas data: {n} tempat diperbarui (25 -> 27 entitas, sesuai tabel basis data)')


# ---------------------------------------------------------------- 7. diagram terbaru

DIAGRAM = '/tmp/diagram'
GAMBAR_SKPL = [
    (r'Gambar III\.14', f'{DIAGRAM}/usecase-final.png', 14.4, 0),
    (r'Gambar III\.15', f'{DIAGRAM}/erd-final.png', 24.4, 15.0),
    (r'Gambar III\.17', f'{DIAGRAM}/class-final.png', 24.4, 16.2),
]


def ubah_diagram(dok):
    """Gambar hasil render diagrams/*.drawio menggantikan gambar v1.0."""
    ganti_gambar(dok, GAMBAR_SKPL, log)


def ubah_kalimat_kelas(dok):
    """Diagram kelas baru memuat 37 kelas dan 20 enumerasi (sebelumnya 35 kelas, 19 enumerasi)."""
    n = 0
    for p in dok.paragraphs:
        t = bersih(p.text)
        if '35 kelas' in t or 'Diagram memuat 35 kelas' in t:
            ganti_dalam_paragraf(p, 'Diagram memuat 35 kelas', 'Diagram memuat 37 kelas')
            if '35 kelas' in bersih(p.text):
                ganti_dalam_paragraf(p, '35 kelas', '37 kelas')
            n += 1
        # TujuanOtp menambah enumerasi pada diagram -> 19 menjadi 20
        if '19 kelas enumerasi' in bersih(p.text):
            ganti_dalam_paragraf(p, '19 kelas enumerasi', '20 kelas enumerasi')
            n += 1
        if '19 enumerasi' in bersih(p.text):
            ganti_dalam_paragraf(p, '19 enumerasi', '20 enumerasi')
            n += 1
    log(f'Jumlah kelas/enumerasi pada narasi & judul diagram: {n} tempat diperbarui '
        f'(35 -> 37 kelas, 19 -> 20 enumerasi)')


# ---------------------------------------------------------------- 8. ruang lingkup & riwayat revisi


RUANG_LINGKUP = (
    'Pada versi 1.1, seluruh tiga belas use case dilengkapi antarmuka REST dan peristiwa real-time, '
    'termasuk papan lelang, pelaporan dan analitik, pengelolaan data master, jejak audit, serta portal '
    'nelayan berbasis tautan akses.')

RIWAYAT = [
    ('Versi', 'Tanggal', 'Ringkasan perubahan'),
    ('1.0', '2026', 'Terbitan awal: 13 use case, 67 kebutuhan fungsional, 15 kebutuhan non-fungsional.'),
    ('1.1', '21 November 2026',
     '(a) FR-21 ditegaskan: daya beli = saldo + limit kredit, berlaku pada penawaran lelang naik maupun '
     'pengambilan pada lelang turun; (b) FR-64 diperjelas: pembeli boleh bergabung selama sesi lelang berjalan '
     'dengan konfirmasi kode OTP; (c) Tabel IV.6 diselaraskan dengan rute REST terpasang dan dilengkapi tiga '
     'titik akhir; (d) Tabel IV.7 dilengkapi keterangan kanal privat per pengguna dan cadangan polling; '
     '(e) UC-04 diperjelas: tawaran pertama sah pada harga pembukaan, kenaikan minimum berlaku untuk tawaran '
     'berikutnya; (f) jumlah entitas data dikoreksi dari dua puluh lima menjadi dua puluh tujuh sesuai tabel '
     'basis data terpasang; (g) cakupan versi 1.1 dinyatakan pada Bab I; (h) Gambar III.14, III.15, dan III.17 diganti dengan diagram terbaru dari berkas diagrams/*.drawio — ERD memuat 27 entitas (termasuk pencairan_nelayan) dan diagram kelas memuat 37 kelas; (i) butir enumerasi ditambah TujuanOtp sehingga menjadi 20 kelas enumerasi.'),
]


def tambah_ruang_lingkup(dok):
    for p in dok.paragraphs:
        if bersih(p.text).startswith('Cakupan yang dibangun pada versi 1.1'):
            if 'seluruh tiga belas use case dilengkapi' in bersih(p.text):
                log('Ruang lingkup v1.1: sudah ada')
                return
            sisip_paragraf_setelah(p, RUANG_LINGKUP)
            log('Ruang lingkup: catatan cakupan versi 1.1 ditambahkan')
            return
    log('Ruang lingkup: paragraf acuan tidak ditemukan (dilewati)')


def tambah_riwayat_revisi(dok):
    """Sisipkan bab RIWAYAT REVISI tepat sebelum Bab I (urutan elemen body, bukan urutan paragraf)."""
    from docx.table import Table
    from docx.text.paragraph import Paragraph

    judul_ada = any('RIWAYAT REVISI' in bersih(p.text).upper() for p in dok.paragraphs)
    if judul_ada:
        log('Riwayat revisi: sudah ada')
        return

    # jangkar: paragraf DAFTAR ISI (riwayat diletakkan setelah sampul, sebelum daftar isi)
    jangkar = None
    for el in dok.element.body:
        if el.tag.endswith('}p') and bersih(Paragraph(el, dok).text).strip() == 'DAFTAR ISI':
            jangkar = el
            break
    if jangkar is None:
        log('Riwayat revisi: judul DAFTAR ISI tidak ditemukan (dilewati)')
        return

    judul = dok.add_paragraph('RIWAYAT REVISI DOKUMEN', style='Heading 1')
    tabel = dok.add_table(rows=len(RIWAYAT), cols=3)
    gaya = next((t.style.name for t in dok.tables if t.style is not None), 'Table Grid')
    tabel.style = gaya
    for r, isi in enumerate(RIWAYAT):
        for c, nilai in enumerate(isi):
            sel_tulis(tabel.rows[r].cells[c], nilai)
    tabel.autofit = True
    for lebar, kolom in zip((Cm(2.2), Cm(3.4), Cm(10.9)), tabel.columns):
        kolom.width = lebar
        for sel in kolom.cells:
            sel.width = lebar

    judul.paragraph_format.page_break_before = True   # halaman tersendiri setelah sampul
    jangkar.addprevious(judul._p)      # judul dulu ...
    jangkar.addprevious(tabel._tbl)    # ... lalu tabel

    # catatan penutup di bawah tabel
    catatan = dok.add_paragraph(
        'Nama titik akhir, peristiwa, dan jumlah entitas pada versi ini telah diperiksa ulang terhadap '
        'kode program terpasang dan hasil pengujian. Tanda perubahan pada batang tubuh dokumen memakai '
        'penomoran yang sama dengan tabel di atas.')
    catatan.paragraph_format.space_before = Cm(0.2)
    tabel._tbl.addnext(catatan._p)

    # entri daftar isi
    for el in list(jangkar.getparent().iterchildren()):
        if el.tag.endswith('}p') and re.match(r'^I\.\s*PENDAHULUAN', bersih(Paragraph(el, dok).text).strip()):
            entri = copy.deepcopy(el)
            el.addprevious(entri)
            par = Paragraph(entri, dok)
            for r in list(par.runs)[1:]:
                r._r.getparent().remove(r._r)
            if par.runs:
                par.runs[0].text = 'RIWAYAT REVISI DOKUMEN\t4'
            log('Riwayat revisi: entri daftar isi ditambahkan (nomor halaman dihitung ulang nanti)')
            break
    log('Riwayat revisi: judul + tabel ditambahkan setelah sampul')


# ---------------------------------------------------------------- 9. kejelasan v1.1 (audit C)

def sisip_catatan_setelah(dok, judul_tabel, teks):
    """Sisipkan paragraf catatan tepat setelah tabel berjudul `judul_tabel`."""
    from docx.table import Table
    from docx.text.paragraph import Paragraph
    els = list(dok.element.body)
    for i, el in enumerate(els):
        if el.tag.endswith('}p') and bersih(Paragraph(el, dok).text).startswith(judul_tabel):
            if i + 1 < len(els) and els[i + 1].tag.endswith('}tbl'):
                tabel = Table(els[i + 1], dok)
                contoh = next(p for p in dok.paragraphs if bersih(p.text).strip() == teks)
                dok.element.body.remove(contoh._p)
                tabel._tbl.addnext(contoh._p)
                return True
    return False


def tambah_kejelasan_v11(dok):
    """C-1: alias rute laporan; C-2: FR-05/FR-06 di luar cakupan; C-3: FR-07 timbangan simulasi."""
    # C-1 — catatan di bawah Tabel IV.6
    catatan_alias = dok.add_paragraph(
        'Baris "/api/laporan/harga" dan "/api/laporan/retribusi" pada tabel di atas dilayani oleh satu rute '
        '"/api/laporan/{jenis}" dengan nilai {jenis} berturut-turut "harga" dan "retribusi"; keduanya juga '
        'menerima parameter ?format=csv atau ?format=pdf.')
    if sisip_catatan_setelah(dok, 'Tabel IV.6', bersih(catatan_alias.text)):
        log('Tabel IV.6: catatan alias /api/laporan/{jenis} ditambahkan')
    else:
        log('Tabel IV.6: catatan alias GAGAL disisipkan')

    # C-2 — FR-05 dan FR-06: cakupan versi 1.1
    CATATAN_FR = {
        'FR-05': ' Pada versi 1.1 antrean luring belum diimplementasikan; ketentuan ini berlaku untuk versi berikutnya.',
        'FR-06': ' Pada versi 1.1 status sinkronisasi belum diimplementasikan; ketentuan ini berlaku untuk versi berikutnya.',
    }
    for tabel in dok.tables:
        for baris in tabel.rows:
            kode = bersih(baris.cells[0].text).strip()
            if kode in CATATAN_FR:
                sel = baris.cells[1]
                par = sel.paragraphs[-1]
                run = par.add_run(CATATAN_FR[kode])
                run.italic = True
                log(f'{kode}: catatan cakupan versi 1.1 ditambahkan')

    # C-3 — FR-07: sumber berat pada v1.1 adalah timbangan simulasi
    for tabel in dok.tables:
        for baris in tabel.rows:
            if bersih(baris.cells[0].text).strip() == 'FR-07':
                sel = baris.cells[1]
                par = sel.paragraphs[-1]
                run = par.add_run(' Pada versi 1.1 pembacaan berat berasal dari timbangan simulasi di dalam '
                                  'aplikasi; adaptor perangkat nyata (Tabel IV.4) masuk rencana versi berikutnya.')
                run.italic = True
                log('FR-07: catatan timbangan simulasi ditambahkan')


# ---------------------------------------------------------------- jalan

def utama():
    dok = Document(MASUK)
    print('SKPL: menyunting...')
    ubah_versi(dok)
    ubah_kebutuhan(dok)
    ubah_tabel_rest(dok)
    ubah_tabel_peristiwa(dok)
    ubah_uc04(dok)
    ubah_jumlah_entitas(dok)
    ubah_diagram(dok)
    ubah_kalimat_kelas(dok)
    tambah_kejelasan_v11(dok)
    tambah_ruang_lingkup(dok)
    # halaman RIWAYAT REVISI DOKUMEN dihapus atas permintaan (v1.1); fungsi tambah_riwayat_revisi() dibiarkan
    # tetapi tidak dipanggil lagi.
    import os
    os.makedirs('/home/user/dokumen/hasil', exist_ok=True)
    dok.save(KELUAR)
    print(f'\nTersimpan: {KELUAR}  ({len(catatan)} perubahan)')


if __name__ == '__main__':
    utama()
