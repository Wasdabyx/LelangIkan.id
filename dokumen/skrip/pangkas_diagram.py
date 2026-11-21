"""Pangkas kanvas kosong pada PDF diagram, lalu buat PNG untuk DOCX.

Masukan : /tmp/diagram/{usecase,erd,class}.pdf   (ekspor drawio, vektor)
Keluaran: /tmp/diagram/{nama}-potong.pdf          (dipakai sisip_vektor.py)
          /tmp/diagram/{nama}-final.png           (dipakai ganti_gambar.py)

PNG diturunkan dari PDF terpangkas yang sama, sehingga ukuran di DOCX dan letak
gambar vektor di PDF final selalu sama.
"""
import os
import sys

import pymupdf
from PIL import Image

LEBAR_PNG = {'usecase': 2200, 'erd': 3800, 'class': 4200}


def kotak_isi(halaman):
    kotak = None

    def tambah(rect):
        nonlocal kotak
        if rect.width > halaman.rect.width * 0.9 and rect.height > halaman.rect.height * 0.9:
            return  # latar penuh, bukan isi
        kotak = rect if kotak is None else kotak | rect

    for blok in halaman.get_text('blocks'):
        tambah(pymupdf.Rect(blok[:4]))
    for gambar in halaman.get_drawings():
        tambah(gambar['rect'])
    return kotak


def utama(folder='/tmp/diagram'):
    for nama, lebar in LEBAR_PNG.items():
        sumber = pymupdf.open(os.path.join(folder, f'{nama}.pdf'))
        halaman = sumber[0]
        kotak = kotak_isi(halaman)
        potong = pymupdf.open()
        baru = potong.new_page(width=kotak.width, height=kotak.height)
        baru.show_pdf_page(pymupdf.Rect(0, 0, kotak.width, kotak.height), sumber, 0, clip=kotak)
        jalur_pdf = os.path.join(folder, f'{nama}-potong.pdf')
        potong.save(jalur_pdf)

        skala = lebar / kotak.width
        pix = baru.get_pixmap(matrix=pymupdf.Matrix(skala, skala))
        jalur_png = os.path.join(folder, f'{nama}-raw.png')
        pix.save(jalur_png)
        im = Image.open(jalur_png)
        if im.mode in ('RGBA', 'LA', 'P'):
            im = im.convert('RGBA')
            im = Image.alpha_composite(Image.new('RGBA', im.size, (255, 255, 255, 255)), im)
        im = im.convert('RGB').quantize(colors=64, method=Image.MEDIANCUT, dither=Image.NONE)
        jalur_final = os.path.join(folder, f'{nama}-final.png')
        im.save(jalur_final, optimize=True)
        print(f'{nama}: vektor {jalur_pdf} ({kotak.width:.0f} x {kotak.height:.0f} pt), '
              f'PNG {Image.open(jalur_final).size} {os.path.getsize(jalur_final) // 1024} KB')


if __name__ == '__main__':
    utama(sys.argv[1] if len(sys.argv) > 1 else '/tmp/diagram')
