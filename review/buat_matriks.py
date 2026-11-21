#!/usr/bin/env python3
"""Membuat matriks kerunutan FR-01..FR-67 x bukti uji (xlsx)."""
from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
from openpyxl.utils import get_column_letter

# (FR, UC, judul ringkas, status, bukti/uji, catatan)
DATA = [
 ("FR-01","UC-01","Formulir kedatangan (tanggal, jam, kapten, ABK, nomor surat)","Selesai","UC01-04, UC01-05 (POST /kedatangan)","Validasi tanggal/jam tidak boleh masa depan diuji (UC01-06)"),
 ("FR-02","UC-01","Pencarian kapal + data pemilik","Selesai","UC01-02, UC01-03","Minimal 3 huruf ditegakkan (422)"),
 ("FR-03","UC-01","Banyak baris tangkapan per kedatangan","Selesai","UC01-04","Jenis, grade, berat estimasi, jumlah keranjang"),
 ("FR-04","UC-01","Validasi jenis ikan & berat > 0","Selesai","UC01-06","Berat 0 dan nama kapten tidak valid ditolak 422"),
 ("FR-05","UC-01","Antrean luring + kirim ulang idempotent","Belum ada","Uji F: kirim ulang payload identik -> 422 'nomor surat sudah dipakai'","Tidak ada kunci idempotensi/antrean lokal; pakai @react-native-community/netinfo tapi belum dipakai. NFR-09 juga belum"),
 ("FR-06","UC-01","Status sinkronisasi per data","Belum ada","Tidak ditemukan di kode","Belum ada penyimpanan lokal maupun indikator"),
 ("FR-07","UC-02","Baca timbangan digital + input manual cadangan","Sebagian","UC02-02, UC02-03; FormTimbang.js: useTimbanganSimulasi (naik-goyang-diam, stabil 1,5 detik, ketelitian 0,05 kg) dan tambahManual (0 < berat <= 9999,99 kg, alasan wajib dicatat)","Antarmuka pembacaan tersedia dan dipakai, tetapi sumbernya tiruan di dalam aplikasi, bukan adaptor serial/Bluetooth ke perangkat. Input manual berjalan penuh sebagai cadangan. Selisih ini disengaja dan dinyatakan pada SKPL Tabel IV.4 sebagai butir disimulasikan"),
 ("FR-08","UC-02","Nomor lot unik + label QR","Selesai","UC02-02, UC02-04, UC02-05; nomor memakai tpi.kode_tpi","Kode TPI kini per unit (kolom tpi.kode_tpi), bukan konfigurasi global"),
 ("FR-09","UC-02","Tandai selisih berat > 15%","Selesai","UC02-02 (selisih_persen, perlu_verifikasi)","Ambang dari konfigurasi; alasan wajib bila lewat ambang"),
 ("FR-10","UC-02","Usulan harga pembukaan (median 7 hari)","Selesai","UC02-01","ALG-06 + faktor_harga_grade + pembulatan 500"),
 ("FR-11","UC-02","Catat petugas, waktu, perangkat timbang","Selesai","UC02-02","perangkat_timbang tersimpan"),
 ("FR-12","UC-03","Daftar lot siap lelang (antrean)","Selesai","UC03-01","QRY-01"),
 ("FR-13","UC-03","Operator set durasi & parameter harga","Selesai","UC03-02","Durasi 30-600 detik divalidasi"),
 ("FR-14","UC-03","Cegah >1 sesi aktif per lot","Selesai","UC03-03","422 saat buka sesi ganda"),
 ("FR-15","UC-03","Siarkan mulai lelang < 1 detik","Selesai","uji_ws.py: LELANG_DIMULAI diterima klien WebSocket; composer dev menjalankan reverb:start","7 peristiwa Tabel IV.7 + 2 peristiwa tambahan tersiar; polling 1 detik tetap cadangan (NFR-10)"),
 ("FR-16","UC-03","Tolak sesi bila harga pembukaan kosong / status salah","Selesai","Kode LelangService baris 77","Pesan 'Harga pembukaan belum diisi'"),
 ("FR-17","UC-04","Tampilkan harga tertinggi, kenaikan, sisa waktu","Selesai","UC04-04, UC03-04","Polling 1 detik (bukan WebSocket)"),
 ("FR-18","UC-04","Tolak tawaran < harga + kenaikan minimum","Selesai","UC04-05, UC04-09","Termasuk batas atas 10x harga pembukaan"),
 ("FR-19","UC-04","Proses atomik (lock baris)","Selesai","Uji B: 3 tawaran 2 pembeli konsisten","lockForUpdate + ALG-01; belum diuji paralel 100 klien (NFR-02)"),
 ("FR-20","UC-04","Idempotensi kunci tawaran","Selesai","Uji B: ulang kunci -> 200 duplikat=true",""),
 ("FR-21","UC-04","Tolak bila saldo/limit tidak cukup","Selesai","UC04-11: pembeli tanpa saldo -> 422 'Daya beli tidak cukup...'; tes PHPUnit dayaBeli()","dayaBeli = saldo + limit kredit, diperiksa pada tawaran NAIK dan Ambil TURUN"),
 ("FR-22","UC-04","Notifikasi outbid ke penawar sebelumnya","Selesai","uji_ws.py: peristiwa OUTBID diterima penawar sebelumnya; baris notifikasi tersimpan","Kanal real-time tpi.{id} + tabel notifikasi untuk pembeli yang tersalip"),
 ("FR-23","UC-05","Perpanjangan otomatis 30 dtk (maks 5x)","Selesai","Uji akhir: sisa 9s -> 30s, perpanjangan 0 -> 1","ALG-03"),
 ("FR-24","UC-05","Tutup otomatis saat waktu habis","Selesai","Uji C: sesi 30s -> DITUTUP","Ditutup saat status dipolling atau via `lelang:tutup-otomatis`"),
 ("FR-25","UC-05","Tetapkan pemenang (naik: tertinggi, turun: pertama)","Selesai","UC05-01, uji Ambil",""),
 ("FR-26","UC-05","Transaksi = berat final x harga akhir","Selesai","UC05-02, TRX-000005",""),
 ("FR-27","UC-05","Lot tidak terjual bila tak ada tawaran sah","Selesai","Uji C: lot -> TIDAK_TERJUAL",""),
 ("FR-28","UC-06","Rincian tagihan (berat, harga, subtotal, retribusi, biaya, total)","Selesai","UC06-01",""),
 ("FR-29","UC-06","Metode tunai, saldo, transfer, QRIS","Selesai","UC06-05 (tunai), bayar-saldo (saldo)","QRIS/TRANSFER diterima sebagai metode (simulasi)"),
 ("FR-30","UC-06","Tolak nominal 0 / melebihi total; catat sebagian","Selesai","UC06-03, UC06-04, UC06-05",""),
 ("FR-31","UC-06","Nota ber-QR verifikasi","Selesai","Uji E: 'LELANGIKAN|TRX-...|tanda tangan' asli=true; diubah -> asli=false",""),
 ("FR-32","UC-06","Pembatalan hanya oleh supervisor + alasan + audit","Selesai","POST /supervisor/tagihan/{id}/batal; PHPUnit: kasir 403, alasan <10 karakter 422, lot -> TIDAK_TERJUAL, audit BATAL_TRANSAKSI","Pembatalan otomatis kedaluwarsa 2 jam tetap berjalan"),
 ("FR-33","UC-07","Tampilkan saldo, limit kredit, mutasi","Selesai","UC07-02",""),
 ("FR-34","UC-07","Catat top up + metode + kasir","Selesai","Uji D: SETOR 3.000.000","Jenis mutasi: SETOR/PENARIKAN/PENYESUAIAN"),
 ("FR-35","UC-07","Tolak penarikan melebihi saldo","Selesai","Uji D: 422 'Penarikan melebihi saldo tersedia'",""),
 ("FR-36","UC-07","Penyesuaian saldo wajib persetujuan supervisor","Selesai","Uji D: status MENUNGGU_PERSETUJUAN -> disetujui pimpinan",""),
 ("FR-37","UC-08","Total bruto per kedatangan dari lot terjual","Selesai","STL-01, STL-03: GET /kasir/settlement + POST /kasir/settlement/{idKedatangan}","SettlementService::hitung menjumlah subtotal transaksi LUNAS per kedatangan"),
 ("FR-38","UC-08","Tarif retribusi berlaku per tanggal transaksi","Selesai","MS-12 tambah tarif baru; ALG-05 dipakai SettlementService & transaksi","Tarif dikunci pada id_tarif transaksi; admin dapat mengelola tarif (FR-48)"),
 ("FR-39","UC-08","Nilai bersih = bruto - retribusi - potongan","Selesai","STL-02, STL-04: netto = bruto - retribusi - potongan lain, keterangan wajib","Nilai disimpan pada pembayaran_nelayan.total_netto"),
 ("FR-40","UC-08","Catat pembayaran ke nelayan + bukti + kasir","Selesai","STL-08..STL-11: pencairan tercatat + BNL-000001 + nama kasir; STL-13 bukti PDF","Tabel pembayaran_nelayan & pencairan_nelayan + audit CAIRKAN_NELAYAN"),
 ("FR-41","UC-09","Papan tampilan real-time (harga, sisa waktu, status)","Selesai","PL-01, PL-03: /papan-lelang memuat harga_tertinggi + sisa_detik; kanal WebSocket tpi.{id}","Endpoint polling menjadi cadangan bila WebSocket terputus"),
 ("FR-42","UC-09","Ringkasan harian (lot, volume, nilai, retribusi)","Selesai","UC09-01, dashboard pimpinan (screenshot)",""),
 ("FR-43","UC-09","Samarkan identitas penawar di papan","Selesai","PL-04: inisial_penawar (mis. DB) di papan; nama hanya di layar operator","PapanLelangService::inisial dipakai pada setiap keluaran papan"),
 ("FR-44","UC-10","Laporan volume/nilai/harga rata-rata per jenis, grade, tanggal","Selesai","LP-harga: GET /laporan/harga?dari=&sampai= (agregasi per jenis & grade)","LaporanService memakai group by (NFR-11)"),
 ("FR-45","UC-10","Tren harga harian + pembanding periode","Selesai","LP-tren: harga_rata_rata, pembanding, perubahan_persen, arah","Periode pembanding otomatis sepanjang periode utama"),
 ("FR-46","UC-10","Rekap retribusi harian/bulanan per TPI","Selesai","LP-retribusi: rekap harian & bulanan + total",""),
 ("FR-47","UC-10","Ekspor CSV & PDF","Selesai","LP-csv, LP-csv2, LP-pdf: ?format=csv dan ?format=pdf (dompdf)","CSV memakai pemisah titik koma + BOM agar rapi di Excel"),
 ("FR-48","UC-11","Kelola master jenis ikan, grade, tarif, kapal, pemilik","Selesai","MS-jenis-ikan..MS-pemilik-kapal, MS-01..MS-03: CRUD /master/{entitas}","Validasi unik + jejak audit TAMBAH/UBAH_MASTER"),
 ("FR-49","UC-11","Kelola pengguna, peran, penugasan TPI","Selesai","MS-04..MS-06: buat pengguna, ubah peran & TPI, kata sandi opsional","Audit TAMBAH_PENGGUNA/UBAH_PENGGUNA"),
 ("FR-50","UC-11","Kelola konfigurasi lelang","Selesai","MS-11: POST /master/konfigurasi {kunci,nilai} + audit UBAH_KONFIGURASI","Durasi, kenaikan, ambang & batas perpanjangan dapat diubah administrator"),
 ("FR-51","UC-11","Nonaktifkan pengguna tanpa hapus jejak","Selesai","MS-07, MS-08, MS-10: nonaktif -> tidak bisa masuk; aktif kembali","Baris pengguna tidak dihapus; token lama dicabut"),
 ("FR-52","UC-12","Jejak audit perubahan data kritikal","Selesai","UC12: tabel audit_log + AuditService; MS-09 membuktikan jejak perubahan pengguna","Mencatat pengguna, aksi, entitas, nilai sebelum/sesudah, IP, waktu"),
 ("FR-53","UC-12","Penyaringan jejak audit","Selesai","GET /audit?id_pengguna=&entitas=&aksi=&dari=&sampai=&batas=",""),
 ("FR-54","UC-12","Larangan ubah/hapus audit","Selesai","PHPUnit: DELETE /audit/1 -> 403; PUT/PATCH/DELETE selalu 403 + audit TOLAK_UBAH_AUDIT",""),
 ("FR-55","UC-12","Ekspor jejak audit","Selesai","GET /audit?format=csv","Kata sandi & token disamarkan pada ekspor"),
 ("FR-56","UC-13","Rekap lelang per kapal + total bruto","Selesai","STL-16, STL-17: /nelayan/rekap?token=&kode=&dari=&sampai=","Rekap per kedatangan/kapal beserta total bruto/netto/dibayar"),
 ("FR-57","UC-13","Rincian per lot (berat, harga, retribusi, bersih)","Selesai","STL-18: rincian_lot per kedatangan",""),
 ("FR-58","UC-13","Status pembayaran nelayan + tanggal/bukti","Selesai","STL-19: status BELUM_DIBAYAR/DIBAYAR_SEBAGIAN/LUNAS + kode bukti + tanggal","Status dihitung ulang dari total pencairan"),
 ("FR-59","UC-13","Tautan sekali pakai bertoken maks 72 jam","Selesai","STL-14, STL-15, STL-29: token + kode 4 digit, umur maks 72 jam, kode salah/ token salah ditolak","Tabel tautan_akses_nelayan + audit TERBITKAN/BUKA_PORTAL_NELAYAN"),
 ("FR-60","UC-13","Unduh bukti pembayaran PDF","Selesai","STL-13, STL-21: bukti PDF dari kasir maupun portal nelayan","Bukti memuat rincian lot, retribusi, potongan, dan nilai bersih"),
 ("FR-61","UC-03","Pilih mode lelang naik/turun","Selesai","UC03-02, uji lelang turun","UI FormBukaSesi punya pemilih mode"),
 ("FR-62","UC-03","Lelang turun otomatis + Ambil pemenang","Selesai","Uji: 50.000 -> 48.000 -> 46.000 tiap 2s; Ambil -> menang, tagihan 46.000/kg, lot TERJUAL","Ambil kedua ditolak 422 (ALG-10 langkah 3)"),
 ("FR-63","UC-04","Penawaran jarak jauh + catat kanal","Sebagian","Kolom penawaran.kanal terisi pada setiap tawaran (LelangService menulis JARAK_JAUH; enum DI_TEMPAT/JARAK_JAUH tersedia)","Nilai kanal dicatat, tetapi belum ditentukan dari lokasi/terminal dan belum ada saklar aktivasi administrator (NFR-12). Selisih ini disengaja dan dinyatakan pada SKPL Tabel III.13 dengan prioritas Sedang"),
 ("FR-64","UC-04","Daftar pantauan + batas harga + notifikasi","Selesai","PN-01..PN-05; uji_ws3: LOT_DIPANTAU_DIBUKA diterima pemantau","Pembeli kini boleh bergabung saat sesi berjalan (revisi FR-64)"),
 ("FR-65","UC-02","Unggah foto lot","Selesai","FR65-01, GET /api/foto-lot/{nama} -> 200","Satu foto per lot (foto lama ditimpa)"),
 ("FR-66","UC-11","Kartu nelayan ber-QR (terbit/blokir/scan)","Selesai","STL-22..STL-27: terbit, tolak kartu ganda, pindai QR, blokir, aktifkan","Tabel kartu_nelayan + token_qr 64 karakter"),
 ("FR-67","UC-08","Pencairan tunai/transfer/kartu + nomor referensi","Selesai","STL-07..STL-09: TUNAI/TRANSFER_REKENING/KARTU_NELAYAN; referensi TRF-/KRT- otomatis","Transfer & kartu disimulasikan (versi 1.1)"),
]

NFR = [
 ("NFR-01","Kinerja","Waktu tawaran -> peristiwa diterima klien <= 500 ms (p95)","Belum diuji","Uji beban 100 klien belum; polling 1 detik berarti latensi tampilan s.d. 1 detik di klien lain"),
 ("NFR-02","Kinerja","100 klien penawaran serentak tanpa kehilangan peristiwa","Belum diuji","Belum ada skrip beban"),
 ("NFR-03","Ketersediaan","Uptime 99% pada 04.00-12.00","Belum diuji","Butuh pemantauan produksi"),
 ("NFR-04","Keamanan","Hash adaptif + TLS 1.2+","Sebagian","Kata sandi bcrypt (Laravel default); pengembangan lokal HTTP; belum ada konfigurasi TLS/Nginx"),
 ("NFR-05","Keamanan","Hak akses per peran + tercatat pada audit","Sebagian","Uji negatif 403/401 lulus untuk 4 kombinasi; pencatatan audit belum ada"),
 ("NFR-06","Keamanan","Maks 5 percobaan masuk/menit per akun & IP","Sebagian","Per akun: 429 setelah 5 gagal (teruji). Per alamat IP: belum"),
 ("NFR-07","Usabilitas","Satu lot selesai < 20 detik","Belum diuji","Butuh uji ketergunaan 5 pengguna"),
 ("NFR-08","Usabilitas","Bahasa Indonesia + kontras 4,5:1","Sebagian","Seluruh teks Indonesia; kontras belum diukur"),
 ("NFR-09","Keandalan","Data gagal kirim tidak hilang saat aplikasi ditutup","Belum ada","Tidak ada antrean lokal (lihat FR-05)"),
 ("NFR-10","Keandalan","Fallback polling 2 detik saat kanal putus","Sebagian","Aplikasi memang polling 1 detik terus-menerus (tanpa WebSocket)"),
 ("NFR-11","Kinerja","300 penimbangan/hari tanpa penurunan > 20%","Belum diuji","Seed 30 hari (133 lot) tersedia untuk uji volume"),
 ("NFR-12","Portabilitas","Berjalan lokal tanpa internet","Sebagian","Mail=log, DB lokal OK; pendaftaran/OTP butuh surel"),
 ("NFR-13","Pemeliharaan","Lapisan presentasi/kendali/data + cakupan tes >= 70%","Sebagian","Struktur berlapis baik (Enums/Models/Services/Controllers); tes otomatis hanya 6 tes (4 unit LelangService + 2 contoh), cakupan jauh di bawah 70%"),
 ("NFR-14","Auditabilitas","Perubahan data kritikal tercatat & tidak dapat diubah","Belum ada","Lihat FR-52"),
 ("NFR-15","Ketersediaan data","Backup harian, pemulihan < 60 menit","Belum ada","Tidak ada skrip/prosedur backup di repo"),
]

# FR yang sudah beres di kode tetapi masih berstatus lama di matriks -> diperbarui pada putaran 21 Nov 2026
DIPERBARUI = {
    "FR-63": "Kanal penawaran kini terisi pada setiap tawaran; lihat baris FR-63.",
    "FR-07": "Sumber berat: antarmuka pembacaan ada (simulasi) + input manual cadangan.",
}

# Status batang tubuh SKPL/DPPL v1.1 terhadap kode (putaran 21 November 2026, setelah revisi dokumen)
STATUS_DOKUMEN = [
    ("FR-52..FR-55 audit + NFR-14",
     "AuditService, AuditController, model AuditLog, migrasi audit_log; jejak diuji (MS-09)",
     "Selesai - butir lama 'tidak ada tabel audit_log' sudah tidak berlaku"),
    ("Kartu nelayan (FR-66, kartu_nelayan)",
     "KartuNelayanController, model KartuNelayan, migrasi kartu_dan_tautan_nelayan",
     "Selesai - butir lama 'tidak ada tabel & endpoint' sudah tidak berlaku"),
    ("Portal nelayan UC-13 (tautan_akses_nelayan, pembayaran_nelayan)",
     "PortalNelayanService, SettlementController, migrasi pembayaran_nelayan; uji settlement 29/29",
     "Selesai - butir lama sudah tidak berlaku"),
    ("26/27 entitas pada ERD",
     "27 tabel domain setelah pencairan_nelayan; ERD v1.1 memuat 27 entitas (lihat commit 70de974)",
     "Selesai - butir lama 'database hanya 24 tabel domain' sudah tidak berlaku"),
    ("Endpoint API Tabel IV.6 (rute resmi)",
     "RuteResmiController menyediakan rute resmi persis seperti dokumen; uji_rute_resmi.py 19/19",
     "Selesai - dokumen dan kode sejalan"),
    ("Peristiwa real-time Tabel IV.7 (9 peristiwa)",
     "Server menyiarkan 9 peristiwa (uji_ws 9/9 diterima); klien hanya berlangganan SESI_DIMULAI pada kanal tpi.{id}",
     "Sebagian - klien belum berlangganan OUTBID / LOT_DIPANTAU_DIBUKA / SETTLEMENT_DIBAYAR; perluasan klien dijadwalkan"),
    ("Antrean luring FR-05/FR-06 (AntreanSinkronisasi)",
     "Tidak ditemukan di sipeli-mobile/src; @react-native-community/netinfo ada di package.json tetapi belum dipakai",
     "Belum ada - tetap di luar cakupan versi ini"),
    ("FR-07 timbangan, FR-63 kanal jarak jauh",
     "Lihat baris masing-masing pada Matriks FR",
     "Sebagian (disengaja) - dinyatakan pada SKPL Tabel IV.4 dan Tabel III.13"),
]

wb = Workbook()
ws = wb.active
ws.title = "Ringkasan"
ws2 = wb.create_sheet("Matriks FR")
ws3 = wb.create_sheet("Matriks NFR")
ws4 = wb.create_sheet("Dokumen vs Kode")
ws5 = wb.create_sheet("Status Batang Tubuh v1.1")

HEAD = Font(bold=True, color="FFFFFF", size=11)
FILL = PatternFill("solid", fgColor="0F5C6B")
HIJAU = PatternFill("solid", fgColor="D8F0DC")
KUNING = PatternFill("solid", fgColor="FFF2CC")
MERAH = PatternFill("solid", fgColor="F8D7DA")
BIRU = PatternFill("solid", fgColor="DDEBF7")
THIN = Border(*[Side(style="thin", color="BFBFBF")] * 4)

def tulis(sheet, judul_kolom, baris, lebar, warna_status_kolom=None):
    sheet.append(judul_kolom)
    for c in range(1, len(judul_kolom) + 1):
        cell = sheet.cell(row=1, column=c)
        cell.font, cell.fill, cell.border = HEAD, FILL, THIN
        cell.alignment = Alignment(vertical="center", wrap_text=True)
    for r in baris:
        sheet.append(list(r))
    for i, w in enumerate(lebar, start=1):
        sheet.column_dimensions[get_column_letter(i)].width = w
    for row in sheet.iter_rows(min_row=2, max_row=sheet.max_row):
        for cell in row:
            cell.border = THIN
            cell.alignment = Alignment(vertical="top", wrap_text=True)
    if warna_status_kolom:
        for r in range(2, sheet.max_row + 1):
            v = str(sheet.cell(row=r, column=warna_status_kolom).value or "")
            warn = HIJAU if v.startswith("Selesai") else (KUNING if v.startswith(("Sebagian", "Dihapus", "Belum diuji")) else (MERAH if v.startswith("Belum") else None))
            if warn:
                sheet.cell(row=r, column=warna_status_kolom).fill = warn

# --- Ringkasan ---
ws.append(["Laporan Hasil Uji & Kesesuaian dengan SKPL/DPPL - LelangIkan.id"])
ws["A1"].font = Font(bold=True, size=15, color="0F5C6B")
ws.append(["Tanggal uji: 21 November 2026 | Dokumen: SKPL/DPPL v1.1 (commit 1d0c6fe); uji kode pada commit 5e11aa8"])
ws.append(["Lingkungan uji: Debian, PHP 8.4, MariaDB 11.8, Node 20, Expo web (Chromium) - bukan lingkungan penulis"])
ws.append([])
ws.append(["Cara uji", "Detail"])
ws.append(["Backend", "composer install, migrate --seed, php artisan test, php artisan serve; 46 endpoint API diuji dengan skrip Python"])
ws.append(["Aplikasi mobile", "npx expo start --web; login 6 peran diuji otomatis + tangkapan layar"])
ws.append(["Ketergantungan MATI", "Laravel Reverb (WebSocket) tidak dijalankan; notifikasi OTP lewat MAIL_MAILER=log"])
ws.append([])
ws.append(["Status FR", "Jumlah", "Arti"])
ringkasan_fr = [
 ("Selesai (teruji jalan)", sum(1 for d in DATA if d[3].startswith("Selesai")), "Sesuai dokumen dan terbukti pada uji API/UI"),
 ("Sebagian", sum(1 for d in DATA if d[3].startswith("Sebagian")), "Ada tetapi belum memenuhi seluruh kalimat kebutuhan"),
 ("Belum ada", sum(1 for d in DATA if d[3].startswith("Belum")), "Endpoint/antarmuka/tabel belum ditemukan"),
 ("Selisih disengaja (FR-07, FR-63)", sum(1 for d in DATA if d[3].startswith("Sebagian") and d[0] in ("FR-07", "FR-63")), "Sudah dinyatakan pada batang tubuh SKPL; rincian pada sheet Status Batang Tubuh v1.1"),
]
for r in ringkasan_fr:
    ws.append(list(r))
ws.append([])
ws.append(["Total kebutuhan fungsional", 67, "FR-01 s.d. FR-67 (SKPL Tabel III.1-III.13)"])
ws.append([])
ws.append(["Kesimpulan", "Pondasi teknis kuat untuk UC-01..UC-07 (teruji jalan). Yang belum sesuai dokumen: UC-08 s.d. UC-13, audit, portal nelayan, data master, papan lelang, antrean luring. Bila dokumen dipakai apa adanya, progres belum sesuai; bila tahap implementasi dinyatakan lebih dulu, dokumen yang harus ditambah."])
for r in ws.iter_rows(min_row=1, max_row=ws.max_row):
    for c in r:
        c.alignment = Alignment(vertical="top", wrap_text=True)
ws.column_dimensions["A"].width = 34
ws.column_dimensions["B"].width = 62
ws.column_dimensions["C"].width = 62
for r in range(6, 9):
    ws.cell(row=r, column=1).font = Font(bold=True)
ws["A5"].font = Font(bold=True)
ws["A10"].font = Font(bold=True)
ws["A16"].font = Font(bold=True)
ws["A18"].font = Font(bold=True)

tulis(ws2, ["FR", "UC", "Kebutuhan (ringkas)", "Status", "Bukti uji", "Catatan"], DATA, [8, 8, 46, 18, 44, 46], 4)
tulis(ws3, ["NFR", "Kategori", "Kebutuhan", "Status", "Catatan"], NFR, [9, 14, 46, 14, 56], 4)

DOK = [
 ("Skenario uji TC-01 s.d. TC-xx (Tabel 4.1 DPPL)", "Belum ada dokumen/skenario uji terpisah di repo; tes otomatis baru 6", "Tambahkan dokumen Skenario Pengujian atau perluas tests/ agar matriks kerunutan punya bukti TC"),
 ("Endpoint API di SKPL Tabel IV.6", "Nama/jalur berbeda dari implementasi (/api/sesi vs /api/operator/sesi, /api/pembayaran vs /api/kasir/tagihan/{id}/bayar, dll.)", "Perbarui Tabel IV.6 agar sama dengan routes/api.php (46 rute)"),
 ("Peristiwa WebSocket Tabel IV.7 (7 peristiwa)", "Baru SESI_DIMULAI; polling 1 detik dipakai sebagai pengganti", "Putuskan: jalankan Reverb + 7 event, atau ubah dokumen menjadi 'polling' beserta konsekuensi NFR-01/NFR-10"),
 ("Lelang turun ALG-10 & FR-62", "Sudah diimplementasi (teruji) tetapi UC-03 di tabel use case belum menyebut tombol Ambil", "Perbarui skenario UC-03/UC-04 di SKPL"),
 ("Kartu nelayan (FR-66), tabel kartu_nelayan", "Tidak ada tabel & endpoint", "Keluarkan dari cakupan versi ini atau jadwalkan"),
 ("Audit log (FR-52..55, NFR-14), tabel audit_log", "Tidak ada tabel audit_log", "Wajib ada sebelum demo penilaian karena DPPL menjanjikan jejak audit"),
 ("Portal nelayan UC-13 (tautan_akses_nelayan, pembayaran_nelayan)", "Tidak ada tabel & endpoint", "Keluarkan dari cakupan atau jadwalkan"),
 ("Tabel notifikasi", "Ada", "Dibuat pada migrasi 2026_11_21_000005 untuk FR-22"),
 ("26 tabel pada ERD (SKPL III.15)", "Database hanya 24 tabel domain (tanpa audit_log, notifikasi, pembayaran_nelayan, tautan_akses_nelayan, kartu_nelayan; ada kode_otp, foto_lot, daftar_pantauan)", "Sinkronkan ERD + SKPL Tabel III.16 dengan migrasi yang ada"),
 ("36 kelas + 3 interface (Gambar III.17)", "21 model + 10 service + 9 controller; belum ada interface Timbangan/Pencetak, BiddingEngine, PenutupanLelangService, PenyiaranLelangService, NotifikasiService, PortalNelayanService, AuditService", "Perbarui class diagram atau tambahkan kelas tersebut"),
 ("Aplikasi web untuk operator/kasir/pimpinan/admin (Ruang Lingkup SKPL butir 4)", "Semua peran dilayani satu aplikasi Expo (mobile/web); tidak ada aplikasi web terpisah", "Ubah pernyataan ruang lingkup atau bangun web terpisah"),
 ("Hardware: timbangan serial 9600-8-N-1 & printer ESC/POS (Tabel IV.4, DP-03/DP-04)", "Timbangan disimulasikan; cetak label via expo-print (bukan ESC/POS)", "Turunkan status menjadi 'disimulasikan' pada Tabel IV.4 + DPPL"),
 ("NFR-13 cakupan tes >= 70%", "Belum ada laporan cakupan; tes 6", "Tambahkan phpunit --coverage + target realistis per tahap"),
 ("NFR-07 uji ketergunaan 5 pengguna, NFR-08 kontras 4,5:1", "Belum ada bukti", "Tambahkan bagian 'Hasil Uji Usabilitas' di dokumen/dokumen terpisah"),
 ("Antrean luring FR-05/FR-06 (AntreanSinkronisasi)", "Kelas disebut di DPPL, tidak ada di kode", "Implementasi atau keluarkan dari cakupan tahap ini"),
 ("README belum mencantumkan langkah Reverb & lelang:tutup-otomatis", "Broadcast gagal di log; penutupan otomatis hanya saat dipolling", "Tambahkan langkah operasional di README (reverb:start, schedule:work)"),
 ("Dokumen lain yang lazim pada IMPAL", "Belum ada: Matriks Kerunutan terpisah, Dokumen Skenario Pengujian, Laporan Hasil Uji, manual pengguna", "Tambahkan; bahan mentahnya sudah tersedia di laporan ini"),
]
tulis(ws4, ["Butir dokumen", "Kondisi di kode", "Tindakan yang disarankan"], DOK, [54, 62, 58])
tulis(ws5, ["Butir pada SKPL/DPPL v1.1", "Kondisi di kode (21 Nov 2026)", "Status"], STATUS_DOKUMEN, [46, 76, 56], 3)

wb.save("/home/user/LelangIkan-review/Matriks-Kesesuaian-SKPL-DPPL-LelangIkan.xlsx")
print("xlsx tersimpan")
