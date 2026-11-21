"""Ganti gambar diagram di dalam DOCX tanpa mengubah tata letak lain.

Dipakai oleh sunting_skpl.py dan sunting_dppl.py. Gambar dicari berdasarkan teks
keterangan (caption) yang mengikutinya, bukan nomor berkas media, supaya tahan
terhadap perubahan urutan gambar. Berkas pengganti berupa PNG hasil render
`LelangIkan.id/diagrams/*.drawio` (lihat render_diagram.sh).

Ukuran gambar dihitung dari rasio asli PNG lalu dibatasi oleh lebar area teks
dan tinggi kotak gambar aslinya, sehingga pemenggalan halaman tidak bergeser.
"""
import io
import os
import re

from PIL import Image
from docx.oxml.ns import qn

EMU_PER_CM = 360000
R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'

# Batas lebar area teks (cm) untuk bagian portrait: 21,0 - 1,98 - 1,59 = 17,4 cm
# teoretis, tetapi dokumen asli memakai 14,5 cm; nilai itu dipertahankan.
LEBAR_MAKS_PORTRAIT = 14.4
LEBAR_MAKS_LANDSCAPE = 24.4


def _bersih(teks):
    return (teks or '').replace('\u200b', '').replace('\u00ad', '').strip()


def _gambar_berikut(dokumen):
    """Hasilkan (paragraf_gambar, teks_keterangan, drawing, blip) berurutan."""
    paras = dokumen.paragraphs
    for i, p in enumerate(paras):
        gambar = p._p.findall('.//' + qn('w:drawing'))
        if not gambar:
            continue
        keterangan = ''
        for lanjut in paras[i + 1:i + 3]:
            if _bersih(lanjut.text):
                keterangan = _bersih(lanjut.text)
                break
        for dr in gambar:
            blip = dr.find('.//' + qn('a:blip'))
            if blip is not None:
                yield p, keterangan, dr, blip


def _ukuran_cm(drawing):
    ext = drawing.find('.//' + qn('wp:extent'))
    if ext is None:
        return None
    return int(ext.get('cx')) / EMU_PER_CM, int(ext.get('cy')) / EMU_PER_CM


def _set_ukuran(drawing, lebar_cm, tinggi_cm):
    cx, cy = int(round(lebar_cm * EMU_PER_CM)), int(round(tinggi_cm * EMU_PER_CM))
    ext = drawing.find('.//' + qn('wp:extent'))
    ext.set('cx', str(cx))
    ext.set('cy', str(cy))
    for a_ext in drawing.findall('.//' + qn('a:ext')):
        # a:ext ada dua: pada a:xfrm gambar dan pada a:srcRect/extLst; ubah yang xfrm.
        if a_ext.getparent().tag == qn('a:xfrm'):
            a_ext.set('cx', str(cx))
            a_ext.set('cy', str(cy))


def ganti_gambar(dok, daftar, log=print):
    """daftar: [(pola_keterangan, path_png, lebar_maks_cm, tinggi_maks_cm), ...]"""
    sisa = [(d + (None,) * (4 - len(d))) for d in daftar]
    for p, keterangan, drawing, blip in _gambar_berikut(dok):
        for pola, path_png, lebar_maks, tinggi_maks in list(sisa):
            if not re.search(pola, keterangan):
                continue
            rid = blip.get('{%s}embed' % R_NS)
            bagian = dok.part.related_parts[rid]
            blob = open(path_png, 'rb').read()
            if path_png.lower().endswith('.pdf'):
                # PDF vektor: LibreOffice menanamkannya sebagai metafile, Word
                # menampilkannya sebagai halaman PDF. Rasio diambil dari kotak halaman.
                import pymupdf
                halaman = pymupdf.open(path_png)[0].rect
                lebar_px, tinggi_px = halaman.width, halaman.height
            else:
                import io as _io
                from PIL import Image as _Image
                lebar_px, tinggi_px = _Image.open(_io.BytesIO(blob)).size
            lama = _ukuran_cm(drawing)
            rasio = lebar_px / tinggi_px
            batas_lebar = lebar_maks or LEBAR_MAKS_PORTRAIT
            batas_tinggi = tinggi_maks or 18.0
            if lama and not tinggi_maks:
                # tanpa batas khusus, jangan melebihi tinggi kotak asli
                batas_lebar = min(batas_lebar, lama[1] * rasio)
                batas_tinggi = lama[1]
            lebar = batas_lebar
            tinggi = lebar / rasio
            if tinggi > batas_tinggi:
                tinggi = batas_tinggi
                lebar = tinggi * rasio

            bagian._blob = blob
            bagian._image = None
            _set_ukuran(drawing, lebar, tinggi)
            log(f'Diagram diganti: "{keterangan[:60]}" -> {os.path.basename(path_png)} '
                f'({lebar_px}x{tinggi_px} px, {lebar:.2f} x {tinggi:.2f} cm)')
            sisa.remove((pola, path_png, lebar_maks, tinggi_maks))
            break
    for pola, path_png, _, _ in sisa:
        log(f'PERINGATAN: keterangan "{pola}" tidak ditemukan, {os.path.basename(path_png)} tidak dipakai')
    return len(daftar) - len(sisa)
