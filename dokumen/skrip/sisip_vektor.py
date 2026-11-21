"""Ganti gambar raster pada PDF hasil LibreOffice dengan diagram vektor.

DOCX memuat PNG (aman dibuka Word). Di PDF final, gambar diagram pada halaman yang
memuat keterangan tertentu diganti dengan jalur vektor dari ekspor PDF drawio, sehingga
tetap tajam saat diperbesar berapa pun. Keterangan gambar (teks) tidak disentuh.
"""
import re
import sys

import pymupdf

# (pola keterangan, berkas vektor)
DIAGRAM = '/tmp/diagram'
PETA = {
    'SKPL': [
        (r'Gambar III\.14', f'{DIAGRAM}/usecase-potong.pdf'),
        (r'Gambar III\.15', f'{DIAGRAM}/erd-potong.pdf'),
        (r'Gambar III\.17', f'{DIAGRAM}/class-potong.pdf'),
    ],
    'DPPL': [
        (r'Gambar 2\.2', f'{DIAGRAM}/erd-potong.pdf'),
        (r'Gambar 3\.27', f'{DIAGRAM}/class-potong.pdf'),
    ],
}


def halaman_keterangan(dok, pola):
    """Halaman yang memuat keterangan sekaligus gambarnya (bukan halaman daftar gambar)."""
    for i in range(dok.page_count):
        halaman = dok[i]
        if not re.search(pola, halaman.get_text()):
            continue
        if kotak_gambar(halaman) is not None:
            return i
    return None


def kotak_gambar(halaman):
    """Kotak gambar terbesar pada halaman (abaikan ornamen kecil)."""
    kandidat = [pymupdf.Rect(i['bbox']) for i in halaman.get_image_info()]
    kandidat = [r for r in kandidat if r.width > 120 and r.height > 80]
    if not kandidat:
        return None
    return max(kandidat, key=lambda r: r.width * r.height)


def ganti(pdf_path, daftar):
    dok = pymupdf.open(pdf_path)
    for pola, vektor in daftar:
        i = halaman_keterangan(dok, pola)
        if i is None:
            print(f'  PERINGATAN: keterangan {pola} tidak ditemukan')
            continue
        halaman = dok[i]
        kotak = kotak_gambar(halaman)
        if kotak is None:
            print(f'  PERINGATAN: gambar pada halaman {i + 1} tidak ditemukan')
            continue
        halaman.add_redact_annot(kotak)
        halaman.apply_redactions(images=pymupdf.PDF_REDACT_IMAGE_REMOVE)
        sumber = pymupdf.open(vektor)
        halaman.show_pdf_page(kotak, sumber, 0)
        print(f'  hal {i + 1}: {pola} -> vektor {vektor.split("/")[-1]} '
              f'({kotak.width:.0f} x {kotak.height:.0f} pt)')
    dok.saveIncr()
    return dok


def utama():
    for nama in ('SKPL', 'DPPL'):
        path = f'/home/user/dokumen/hasil/{nama}-LelangIkan-v1.1.pdf'
        print(f'{nama}: menyisipkan diagram vektor...')
        ganti(path, PETA[nama])


if __name__ == '__main__':
    utama()
