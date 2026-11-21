# Dokumen hasil (v1.1)

| Berkas | Isi | Halaman |
|---|---|---|
| `SKPL-LelangIkan-v1.1.pdf` | Dokumen Standar Kebutuhan Perangkat Lunak, edisi 1.1 (`LELANGIKAN.ID-SKPL-1.1`) | 55 |
| `DPPL-LelangIkan-v1.1.pdf` | Dokumen Perancangan Perangkat Lunak, edisi 1.1 (`LELANGIKAN.ID-DPPL-1.1`) | 86 |

Kedua PDF dihasilkan dari DOCX asli (tata letak dipertahankan) dengan rangkaian skrip di `dokumen/skrip`:

1. `ambil_sumber.sh` — unduh DOCX sumber
2. `render_diagram.sh` — render `diagrams/*.drawio` → PDF vektor + PNG
3. `sunting_skpl.py` / `sunting_dppl.py` — terapkan revisi v1.1 (teks, tabel, gambar)
4. `segarkan_daftar_isi.py` — hitung ulang nomor halaman daftar isi/tabel/gambar
5. LibreOffice `--convert-to pdf`
6. `sisip_vektor.py` — ganti gambar diagram dengan vektor agar tajam saat di-zoom

Rincian setiap perubahan: `dokumen/SPESIFIKASI-REVISI-v1.1.md` (bagian A–H).

Catatan: PDF tidak dirender ulang oleh GitHub. Bila DOCX sumber atau skrip diubah, jalankan ulang
rangkaian di atas lalu perbarui kedua berkas ini.
