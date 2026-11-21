"""Uji antarmuka web LELANGIKAN.ID (Blade + sesi) terhadap server lokal.

Diuji: pembatasan peran, halaman masuk, setiap halaman dasbor, aksi tulis utama
(pembayaran, settlement, mutasi saldo, konfigurasi master, papan lelang), dan
pencatatan jejak audit untuk aksi web.
"""
import re
import subprocess
import sys
import requests
from bs4 import BeautifulSoup

BASE = "http://127.0.0.1:8000"
SANDI = "sipeli12345"
hasil = []


def siapkan_lot():
    """Pastikan ada minimal satu lot berstatus MENUNGGU_LELANG agar uji dapat diulang."""
    skrip = ("$n = App\\Models\\LotLelang::where('status','MENUNGGU_LELANG')->count();"
             "if ($n < 1) { foreach (App\\Models\\LotLelang::where('status','TIDAK_TERJUAL')->limit(2)->get() as $l)"
             " { $l->pindahStatus(App\\Enums\\StatusLot::MENUNGGU_LELANG); } }"
             "echo App\\Models\\LotLelang::where('status','MENUNGGU_LELANG')->count();")
    out = subprocess.run(["php", "artisan", "tinker", "--execute", skrip],
                         cwd="/home/user/LelangIkan.id/sipeli-backend",
                         capture_output=True, text=True, timeout=120)
    return out.stdout.strip().splitlines()[-1] if out.stdout.strip() else "0"


def cek(nama, kondisi, catatan=""):
    hasil.append((nama, bool(kondisi), catatan))
    print(("LULUS  " if kondisi else "GAGAL  ") + nama + (("  -> " + catatan) if catatan else ""))


def token(html):
    m = re.search(r'name="csrf-token" content="([^"]+)"', html) or re.search(r'name="_token" value="([^"]+)"', html)
    return m.group(1) if m else None


def masuk(pengguna):
    s = requests.Session()
    r = s.get(BASE + "/masuk", timeout=20)
    t = token(r.text)
    r = s.post(BASE + "/masuk", data={"_token": t, "nama_pengguna": pengguna, "kata_sandi": SANDI},
               allow_redirects=False, timeout=20)
    return s, r


def ambil(s, jalur):
    return s.get(BASE + jalur, timeout=30)


def judul(html):
    soup = BeautifulSoup(html, "html.parser")
    h = soup.find("title")
    return h.get_text(strip=True) if h else ""


# ---------- 0. basis data bersih -----------------------------------------
subprocess.run(["php", "artisan", "migrate:fresh", "--seed"],
               cwd="/home/user/LelangIkan.id/sipeli-backend", capture_output=True, text=True, timeout=600)

# ---------- 1. pembatasan peran -------------------------------------------
for peran in ["pendata", "timbang", "pembeli", "nelayan"]:
    s, r = masuk(peran)
    pesan = "Peran" in r.text or "dilayani aplikasi mobile" in r.text
    cek(f"Peran {peran} ditolak di antarmuka web", r.status_code in (302, 422) and (pesan or r.status_code == 302),
        f"status {r.status_code}")

s, r = masuk("operator")
cek("Operator lelang berhasil masuk", r.status_code == 302 and "/dasbor/operator" in r.headers.get("Location", ""),
    r.headers.get("Location", "-"))
operator = s

s, r = masuk("kasir")
cek("Kasir berhasil masuk", r.status_code == 302 and "/dasbor/kasir" in r.headers.get("Location", ""),
    r.headers.get("Location", "-"))
kasir = s

s, r = masuk("pimpinan")
cek("Pimpinan berhasil masuk", r.status_code == 302 and "/dasbor/pimpinan" in r.headers.get("Location", ""),
    r.headers.get("Location", "-"))
pimpinan = s

s, r = masuk("admin")
cek("Administrator berhasil masuk", r.status_code == 302 and "/dasbor/admin" in r.headers.get("Location", ""),
    r.headers.get("Location", "-"))
admin = s

# ---------- 2. halaman dasbor ---------------------------------------------
halaman = [
    (operator, "/dasbor/operator/antrean", "Antrean Lelang"),
    (operator, "/dasbor/operator/sesi", "Sesi Lelang"),
    (kasir, "/dasbor/kasir/tagihan", "Tagihan Pembeli"),
    (kasir, "/dasbor/kasir/settlement", "Settlement Nelayan"),
    (kasir, "/dasbor/kasir/saldo?cari=dedi", "Saldo Pembeli"),
    (pimpinan, "/dasbor/pimpinan/ringkasan", "Ringkasan Harian"),
    (pimpinan, "/dasbor/pimpinan/laporan", "Laporan &amp; Analitik"),
    (pimpinan, "/dasbor/pimpinan/penyesuaian", "Persetujuan Penyesuaian Saldo"),
    (admin, "/dasbor/admin/master", "Data Master"),
    (admin, "/dasbor/admin/pengguna", "Pengguna &amp; Peran"),
    (admin, "/dasbor/admin/konfigurasi", "Konfigurasi Sistem"),
    (admin, "/dasbor/admin/audit", "Jejak Audit"),
]
for sesi, jalur, judul_harap in halaman:
    r = ambil(sesi, jalur)
    cek(f"Halaman {jalur} tampil", r.status_code == 200 and judul_harap in r.text, f"status {r.status_code}")

# ---------- 3. matriks hak akses lintas peran ------------------------------
silang = [
    (kasir, "/dasbor/operator/antrean", "Kasir tidak boleh membuka dasbor operator"),
    (operator, "/dasbor/kasir/tagihan", "Operator tidak boleh membuka dasbor kasir"),
    (pimpinan, "/dasbor/admin/master", "Pimpinan tidak boleh membuka data master"),
    (admin, "/dasbor/pimpinan/laporan", "Administrator tidak boleh membuka laporan pimpinan"),
]
for sesi, jalur, nama in silang:
    r = sesi.get(BASE + jalur, timeout=20, allow_redirects=False)
    cek(nama, r.status_code == 403, f"status {r.status_code}")

# ---------- 4. papan lelang tanpa masuk -----------------------------------
anon = requests.Session()
r = anon.get(BASE + "/papan-lelang", timeout=20)
cek("Papan lelang dapat dibuka tanpa masuk", r.status_code == 200 and "PAPAN LELANG" in r.text, f"status {r.status_code}")
r = anon.get(BASE + "/papan-lelang/keadaan", timeout=20)
cek("Keadaan papan lelang berbentuk JSON", r.status_code == 200 and "sesi_aktif" in r.text, f"status {r.status_code}")

# ---------- 5. aksi tulis: konfigurasi ------------------------------------
r = ambil(admin, "/dasbor/admin/konfigurasi")
t = token(r.text)
r = admin.post(BASE + "/dasbor/admin/konfigurasi",
               data={"_token": t, "kunci": "ambang_selisih_berat", "nilai": "15"},
               allow_redirects=True, timeout=20)
cek("Administrator mengubah konfigurasi", r.status_code == 200 and "diperbarui" in r.text, f"status {r.status_code}")

# ---------- 6. aksi tulis: data master ------------------------------------
r = ambil(admin, "/dasbor/admin/master")
t = token(r.text)
r = admin.post(BASE + "/dasbor/admin/master/grade-mutu",
               data={"_token": t, "kode_grade": "D", "deskripsi": "Uji web", "faktor_harga": "0.5"},
               allow_redirects=True, timeout=20)
cek("Administrator menambah grade mutu", r.status_code == 200 and "tersimpan" in r.text, f"status {r.status_code}")

# ---------- 7. kasir: tagihan & pembayaran --------------------------------
r = ambil(kasir, "/dasbor/kasir/tagihan")
soup = BeautifulSoup(r.text, "html.parser")
form = soup.find("form", action=re.compile(r"/dasbor/kasir/tagihan/\d+/bayar"))
if form:
    aksi = form["action"]
    t = token(r.text)
    nominal = form.find("input", {"name": "nominal"})["value"]
    r2 = kasir.post(aksi, data={"_token": t, "metode": "TUNAI", "nominal": nominal},
                    allow_redirects=True, timeout=20)
    cek("Kasir mencatat pembayaran tagihan", "tercatat" in r2.text, f"status {r2.status_code}")
else:
    cek("Kasir mencatat pembayaran tagihan", False, "tidak ada tagihan terbuka pada data uji")

# ---------- 8. kasir: settlement -----------------------------------------
r = ambil(kasir, "/dasbor/kasir/settlement")
soup = BeautifulSoup(r.text, "html.parser")
form = soup.find("form", action=re.compile(r"/dasbor/kasir/settlement/\d+$"))
if form:
    t = token(r.text)
    r2 = kasir.post(form["action"], data={"_token": t, "potongan_lain": "0"},
                    allow_redirects=True, timeout=30)
    cek("Kasir menghitung settlement", "settlement tersimpan" in r2.text, f"status {r2.status_code}")
    m = re.search(r"/dasbor/kasir/settlement/rincian/(\d+)", r2.url or r2.text)
    if m:
        r3 = ambil(kasir, "/dasbor/kasir/settlement/rincian/" + m.group(1))
        cek("Bukti settlement tampil", r3.status_code == 200 and "Pembayaran hasil lelang" in r3.text, f"status {r3.status_code}")
        t = token(r3.text)
        r4 = kasir.post(BASE + f"/dasbor/kasir/settlement/{m.group(1)}/cairkan",
                        data={"_token": t, "cara_pencairan": "TUNAI", "nomor_referensi": "UJI-WEB"},
                        allow_redirects=True, timeout=20)
        cek("Kasir mencairkan dana nelayan", "Pencairan tercatat" in r4.text, f"status {r4.status_code}")
else:
    cek("Kasir menghitung settlement", False, "tidak ada kedatangan siap settlement pada data uji")

# ---------- 9. kasir: mutasi saldo ---------------------------------------
r = ambil(kasir, "/dasbor/kasir/saldo?cari=dedi")
soup = BeautifulSoup(r.text, "html.parser")
tautan = soup.find("a", href=re.compile(r"/dasbor/kasir/saldo\?pembeli=\d+"))
if tautan:
    href = tautan["href"].replace("&amp;", "&")
    r = kasir.get(href if href.startswith("http") else BASE + href, timeout=20)
    t = token(r.text)
    m = re.search(r"/dasbor/kasir/saldo/(\d+)/mutasi", r.text)
    r2 = kasir.post(BASE + m.group(0), data={"_token": t, "jenis": "SETOR", "nominal": "50000", "metode": "TUNAI"},
                    allow_redirects=True, timeout=20)
    cek("Kasir mencatat setor saldo", "Mutasi saldo tercatat" in r2.text, f"status {r2.status_code}")
    r3 = kasir.post(BASE + m.group(0), data={"_token": token(r2.text), "jenis": "PENYESUAIAN",
                                             "nominal": "10000", "arah": "KREDIT",
                                             "keterangan": "uji web penyesuaian"},
                    allow_redirects=True, timeout=20)
    cek("Kasir mengajukan penyesuaian saldo", "Mutasi saldo tercatat" in r3.text, f"status {r3.status_code}")
else:
    cek("Kasir mencatat setor saldo", False, "pembeli uji tidak ditemukan")

# ---------- 10. pimpinan: persetujuan & CSV ------------------------------
r = ambil(pimpinan, "/dasbor/pimpinan/penyesuaian")
m = re.search(r"/dasbor/pimpinan/penyesuaian/(\d+)/keputusan", r.text)
if m:
    r2 = pimpinan.post(BASE + m.group(0), data={"_token": token(r.text), "keputusan": "SETUJU"},
                       allow_redirects=True, timeout=20)
    cek("Pimpinan menyetujui penyesuaian", "Keputusan tersimpan" in r2.text, f"status {r2.status_code}")
else:
    cek("Pimpinan menyetujui penyesuaian", "menunggu persetujuan" in r.text and "Tidak ada" not in r.text,
        "tidak ada pengajuan menunggu")

r = pimpinan.get(BASE + "/dasbor/pimpinan/laporan/harga/csv", timeout=20)
cek("Pimpinan mengunduh CSV laporan", r.status_code == 200 and "text/csv" in r.headers.get("Content-Type", ""),
    r.headers.get("Content-Type", "-"))

# ---------- 11. operator: buka & tutup sesi ------------------------------
siapkan_lot()
r = ambil(operator, "/dasbor/operator/antrean")
soup = BeautifulSoup(r.text, "html.parser")
form = soup.find("form", action=re.compile(r"/dasbor/operator/sesi$"))
if form:
    id_lot = form.find("input", {"name": "id_lot"})["value"]
    r2 = operator.post(BASE + "/dasbor/operator/sesi",
                       data={"_token": token(r.text), "id_lot": id_lot, "mode": "NAIK",
                             "durasi_detik": "60", "kenaikan_minimum": "500"},
                       allow_redirects=False, timeout=20)
    lokasi = r2.headers.get("Location", "")
    cek("Operator membuka sesi lelang", r2.status_code == 302 and "/sesi/" in lokasi, lokasi or f"status {r2.status_code}")
    m = re.search(r"/sesi/(\d+)", lokasi)
    if m:
        r3 = ambil(operator, f"/dasbor/operator/sesi/{m.group(1)}")
        cek("Layar pantau sesi tampil", r3.status_code == 200 and "Sisa waktu" in r3.text, f"status {r3.status_code}")
        r4 = operator.get(BASE + f"/dasbor/operator/sesi/{m.group(1)}/keadaan", timeout=20)
        cek("Keadaan sesi berbentuk JSON 1 detik", r4.status_code == 200 and "sisa_detik" in r4.text, f"status {r4.status_code}")
        r5 = operator.post(BASE + f"/dasbor/operator/sesi/{m.group(1)}/tutup",
                           data={"_token": token(r3.text), "alasan": "uji antarmuka web"},
                           allow_redirects=True, timeout=20)
        cek("Operator menutup sesi lebih awal", "Sesi ditutup" in r5.text, f"status {r5.status_code} url {r5.url}")
else:
    cek("Operator membuka sesi lelang", False, "tidak ada lot siap lelang pada data uji")

# ---------- 12. jejak audit memuat aksi web ------------------------------
r = ambil(admin, "/dasbor/admin/audit")
cek("Jejak audit memuat MASUK_WEB", "MASUK_WEB" in r.text, "")

# ---------- 13. keluar ---------------------------------------------------
r = operator.post(BASE + "/keluar", data={"_token": token(ambil(operator, "/dasbor/operator/antrean").text)},
                  allow_redirects=False, timeout=20)
r2 = operator.get(BASE + "/dasbor/operator/antrean", allow_redirects=False, timeout=20)
cek("Setelah keluar, dasbor tertutup", r2.status_code == 302 and "/masuk" in r2.headers.get("Location", ""),
    f"status {r2.status_code}")

lulus = sum(1 for _, ok, _ in hasil if ok)
print(f"\n{lulus}/{len(hasil)} pemeriksaan lulus")
sys.exit(0 if lulus == len(hasil) else 1)
