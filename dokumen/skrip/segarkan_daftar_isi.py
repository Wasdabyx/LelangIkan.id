"""Segarkan nomor halaman DAFTAR ISI / DAFTAR TABEL / DAFTAR GAMBAR.

Dokumen asli memakai daftar isi statis (bukan field Word), sehingga Word/LibreOffice tidak
dapat menyegarkannya sendiri. Skrip ini menjalankan siklus:
  DOCX -> PDF (LibreOffice) -> cari halaman tiap judul -> tulis balik nomornya ke DOCX -> ulangi
 sampai nomornya tidak berubah lagi. Tampilan daftar isi tidak diubah, hanya angka di kanannya.
"""
import os
import re
import subprocess
import sys
import tempfile

import pymupdf
from docx import Document

MAKS_PUTARAN = 4
RE_HALAMAN = re.compile(r'[\t.\u2026\u00b7]*\s*\d+\s*$')


def bersih(t):
    return (t or '').replace('\u200b', '').replace('\u00ad', '')


def ke_pdf(docx, keluaran_dir):
    subprocess.run(['soffice', '--headless', '--convert-to', 'pdf', '--outdir', keluaran_dir, docx],
                   check=True, capture_output=True, timeout=600)
    return os.path.join(keluaran_dir, os.path.splitext(os.path.basename(docx))[0] + '.pdf')


def kunci(teks):
    """Normalkan judul untuk pencocokan: buang nomor halaman, spasi ganda, dan tanda baca tepi."""
    t = RE_HALAMAN.sub('', bersih(teks))
    t = re.sub(r'[\s\t]+', ' ', t).strip().lower()
    return t


def halaman_judul(pdf):
    """Peta kunci(judul) -> nomor halaman cetak (1-based) untuk kemunculan pertama.

    Halaman daftar (daftar isi/tabel/gambar) dilewati supaya yang diambil adalah
    halaman tempat judul benar-benar berada, bukan halaman daftarnya.
    """
    dok = pymupdf.open(pdf)
    peta = {}
    for i in range(dok.page_count):
        teks = dok[i].get_text('text')
        if 'DAFTAR ISI' in teks or 'DAFTAR TABEL' in teks or 'DAFTAR GAMBAR' in teks:
            continue
        for baris in teks.splitlines():
            k = kunci(baris)
            if k and len(k) > 3 and k not in peta:
                peta[k] = i + 1
    dok.close()
    return peta


def segarkan(docx, ulang=True):
    for putaran in range(1, MAKS_PUTARAN + 1):
        with tempfile.TemporaryDirectory() as tmp:
            pdf = ke_pdf(docx, tmp)
            peta = halaman_judul(pdf)
        d = Document(docx)
        berubah = 0
        tanpa_pasangan = []
        for p in d.paragraphs:
            mentah = bersih(p.text)
            m = re.match(r'^(.*?)\t\s*(\d+)\s*$', mentah)
            if not m:
                continue
            judul = m.group(1)
            nomor_lama = int(m.group(2))
            if judul.strip().upper() == 'HALAMAN':
                continue
            nomor_baru = peta.get(kunci(judul))
            if nomor_baru is None:
                # judul pada daftar isi dipotong dengan '…'; cocokkan sebagai awalan
                awalan = kunci(judul).rstrip('.… ')
                if awalan:
                    calon = sorted({v for k, v in peta.items() if k.startswith(awalan)})
                    if len(calon) == 1:
                        nomor_baru = calon[0]
            if nomor_baru is None:
                tanpa_pasangan.append(judul[:60])
                continue
            if nomor_baru == nomor_lama:
                continue
            # ganti hanya angka di ujung, pertahankan tab dan format
            if p.runs and p.runs[-1].text.strip().isdigit():
                p.runs[-1].text = p.runs[-1].text.replace(str(nomor_lama), str(nomor_baru))
            else:
                for run in p.runs:
                    if str(nomor_lama) in run.text:
                        run.text = run.text.replace(str(nomor_lama), str(nomor_baru), 1)
                        break
            berubah += 1
        if berubah:
            d.save(docx)
        print(f'  putaran {putaran}: {berubah} nomor halaman diperbarui'
              + (f', {len(tanpa_pasangan)} judul tidak ditemukan' if tanpa_pasangan else ''))
        if not berubah:
            return True, tanpa_pasangan
    return False, tanpa_pasangan


if __name__ == '__main__':
    berkas = sys.argv[1]
    stabil, hilang = segarkan(berkas)
    print('  stabil' if stabil else '  belum stabil')
    if hilang:
        print('  judul tanpa pasangan:', hilang[:8])
