<?php

namespace App\Services;

use App\Enums\KanalPenawaran;
use App\Enums\StatusLot;
use App\Events\SesiLelangDimulai;
use App\Enums\StatusPenawaran;
use App\Enums\StatusSesi;
use App\Models\DaftarPantauan;
use App\Models\Konfigurasi;
use App\Models\LotLelang;
use App\Models\Penawaran;
use App\Models\Pengguna;
use App\Models\SaldoPembeli;
use App\Models\SesiLelang;
use App\Models\TarifRetribusi;
use App\Models\TransaksiPenjualan;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Kelas kendali LelangService (BiddingEngine + PenutupanLelangService pada DPPL).
 * UC-03 buka sesi, UC-04 penawaran (ALG-01, ALG-02, ALG-03), UC-05 penutupan (ALG-04, ALG-05).
 *
 * Real-time: setiap perubahan penting disiarkan lewat kanal WebSocket (Laravel Reverb) sesuai SKPL
 * Tabel IV.7, sedangkan aplikasi tetap memakai polling 1 detik sebagai cadangan (NFR-10).
 * Sesi yang waktunya habis juga ditutup saat statusnya diminta (penutupan "malas") dan oleh
 * perintah penjadwal lelang:tutup-otomatis, sehingga tetap benar meski penjadwal mati.
 */
class LelangService
{
    public const DURASI_MIN = 30;
    public const DURASI_MAKS = 600;

    /** Penyiaran peristiwa real-time; kegagalan WebSocket tidak boleh membatalkan transaksi. */
    private function siarkan(?int $idTpi, string $nama, array $muatan, ?string $kanal = null): void
    {
        try {
            event(new \App\Events\PeristiwaLelang((int) $idTpi, $nama, $muatan, $kanal));
        } catch (\Throwable $e) {
            Log::warning('Gagal menyiarkan peristiwa ' . $nama . '.', ['muatan' => $muatan, 'galat' => $e->getMessage()]);
        }
    }

    /**
     * Tabel IV.7 LOT_DIPANTAU_DIBUKA: beri tahu pembeli yang memantau lot ini bahwa lelang dimulai.
     * Pengiriman per pembeli pada kanal privat pembeli.{id} agar tidak membocorkan daftar pemantau.
     */
    private function siarkanKePemantau(SesiLelang $sesi): void
    {
        $pemantau = \App\Models\DaftarPantauan::where('id_lot', $sesi->getIdLot())->pluck('id_pembeli')->unique();
        $muatan = [
            'id_lot'   => $sesi->getIdLot(),
            'kode_lot' => $sesi->lot?->getKodeLot(),
            'id_sesi'  => $sesi->getIdSesi(),
            'mode'     => $sesi->getMode()->value,
            'batas_harga_dilewati' => false,
        ];

        foreach ($pemantau as $idPembeli) {
            $this->siarkan(null, \App\Events\PeristiwaLelang::LOT_DIPANTAU_DIBUKA, $muatan, 'pembeli.' . $idPembeli);
        }
    }

    private function idTpiLot(LotLelang $lot): ?int
    {
        $lot->loadMissing('tangkapan.kedatangan');

        return $lot->tangkapan?->kedatangan?->getIdTpi();
    }

    // ================= UC-03 =================

    /** FR-12: antrean lot siap lelang beserta jumlah peserta terdaftar (kode PSN) dan usulan kenaikan. */
    public function antrean(int $idTpi): array
    {
        return LotLelang::cariSiapLelang($idTpi)
            ->map(fn (LotLelang $lot) => $this->ringkasLot($lot) + [
                'jumlah_peserta'     => DaftarPantauan::where('id_lot', $lot->getIdLot())->count(),
                'usulan_kenaikan'    => $this->usulanKenaikan($lot),
            ])
            ->values()
            ->all();
    }

    /** ALG-02: kenaikan minimum bertingkat menurut nilai lot (Rp per kg, dibulatkan ke atas kelipatan 100). */
    public function usulanKenaikan(LotLelang $lot): int
    {
        $harga = (float) $lot->getHargaPembukaan();
        $nilai = $harga * (float) $lot->getBeratAktual();

        $kenaikan = match (true) {
            $nilai <= 1_000_000 => max(500, 0.02 * $harga),
            $nilai <= 5_000_000 => max(1000, 0.015 * $harga),
            default             => max(2500, 0.01 * $harga),
        };

        return (int) (ceil($kenaikan / 100) * 100);
    }

    /** FR-13, FR-14, FR-16: buka sesi lelang atas satu lot. */
    public function buka(Pengguna $operator, int $idLot, int $durasi, ?float $kenaikan, string $mode = 'NAIK', ?float $hargaAwal = null, ?float $batasBawah = null, ?float $penurunan = null, ?int $interval = null): SesiLelang
    {
        $modeEnum = \App\Enums\ModeLelang::from($mode);
        $sesi = DB::transaction(function () use ($operator, $idLot, $durasi, $kenaikan, $modeEnum, $hargaAwal, $batasBawah, $penurunan, $interval) {
            $lot = LotLelang::whereKey($idLot)->lockForUpdate()->first();
            $this->pastikanLotDiTpi($lot, $operator);

            if (SesiLelang::aktifUntukLot($lot->getIdLot())) {
                $this->tolak('id_lot', "Lot {$lot->getKodeLot()} sudah memiliki sesi lelang yang aktif.");
            }
            if (! $lot->isSiapDilelang()) {
                $this->tolak('id_lot', $lot->getHargaPembukaan() === null
                    ? 'Harga pembukaan belum diisi. Hubungi petugas timbang.'
                    : "Lot {$lot->getKodeLot()} berstatus {$lot->getStatus()->value}, bukan MENUNGGU_LELANG.");
            }

            if ($modeEnum === \App\Enums\ModeLelang::TURUN && ($hargaAwal === null || $batasBawah === null || $penurunan === null || $interval === null || $batasBawah >= $hargaAwal)) {
                $this->tolak('harga_awal', 'Pengaturan lelang turun tidak valid.');
            }
            if ($modeEnum === \App\Enums\ModeLelang::TURUN) {
                $durasi = (int) ceil(($hargaAwal - $batasBawah) / $penurunan) * $interval + $interval;
            }
            $sesi = SesiLelang::buka(
                $lot, $operator, $durasi, $modeEnum,
                $modeEnum === \App\Enums\ModeLelang::NAIK ? ($kenaikan ?? $this->usulanKenaikan($lot)) : null,
                $hargaAwal, $batasBawah, $penurunan, $interval,
                (int) Konfigurasi::nilai('maks_perpanjangan', 5),
                (int) Konfigurasi::nilai('ambang_perpanjangan_detik', 15),
            );
            $lot->pindahStatus(StatusLot::DILELANG);

            return $sesi;
        });

        app(AuditService::class)->catat($operator, 'BUKA_SESI', 'sesi_lelang', $sesi->getIdSesi(), null, [
            'id_lot' => $sesi->getIdLot(), 'mode' => $sesi->getMode()->value,
            'durasi_detik' => $sesi->getDurasiDetik(), 'kenaikan_minimum' => $sesi->getKenaikanMinimum(),
        ]);

        // Disiarkan setelah commit agar klien tidak menerima sesi yang gagal tersimpan.
        $this->siarkan((int) $operator->getIdTpi(), \App\Events\PeristiwaLelang::DIMULAI, [
            'id_sesi'               => $sesi->getIdSesi(),
            'kode_lot'              => $sesi->lot->getKodeLot(),
            'id_lot'                => $sesi->getIdLot(),
            'mode'                  => $sesi->getMode()->value,
            'harga_pembukaan'       => $sesi->lot->getHargaPembukaan(),
            'kenaikan_minimum'      => $sesi->getKenaikanMinimum(),
            'harga_awal'            => $sesi->getHargaAwal(),
            'harga_batas_bawah'     => $sesi->getHargaBatasBawah(),
            'penurunan_per_langkah' => $sesi->getPenurunanPerLangkah(),
            'interval_detik'        => $sesi->getIntervalDetik(),
            'waktu_mulai'           => $sesi->getWaktuMulai()->format('Y-m-d H:i:s'),
            'waktu_selesai'         => $sesi->getWaktuSelesaiRencana()->format('Y-m-d H:i:s'),
        ]);

        $this->siarkanKePemantau($sesi);

        return $sesi;
    }

    // ================= UC-04 =================

    /**
     * ALG-01: penawaran atomik. Mengembalikan [penawaran, duplikat?].
     * Hanya pembeli yang punya kode pemesanan (PSN, Tugas 2) pada lot ini yang boleh menawar.
     */
    public function ajukanTawaran(
        Pengguna $pembeli,
        int $idSesi,
        float $hargaPerKg,
        string $kunci,
        ?string $ip,
        KanalPenawaran $kanal = KanalPenawaran::JARAK_JAUH,
    ): array {
        // Langkah 2: kunci idempotensi yang sama -> kembalikan hasil tersimpan tanpa memproses ulang (FR-20)
        if ($lama = Penawaran::cariKunci($kunci)) {
            return [$lama, true];
        }

        // BT-03: saat administrator mematikan lelang jarak jauh, hanya penawaran di tempat
        // yang diterima. Pesannya menyebutkan sebabnya agar petugas tidak menduga kerusakan.
        if ($kanal === KanalPenawaran::JARAK_JAUH
            && (string) Konfigurasi::nilai('lelang_jarak_jauh_aktif', '1') !== '1') {
            $this->tolak('harga_per_kg', 'Lelang jarak jauh sedang dimatikan administrator. '
                . 'Penawaran hanya dapat dilakukan di tempat melalui petugas operator.');
        }

        try {
            $hasil = DB::transaction(function () use ($pembeli, $idSesi, $hargaPerKg, $kunci, $ip, $kanal) {
                // Langkah 3: kunci baris sesi
                $sesi = SesiLelang::with('lot')->whereKey($idSesi)->lockForUpdate()->first();
                if (! $sesi) {
                    throw new HttpResponseException(response()->json(['message' => 'Sesi lelang tidak ditemukan.'], 404));
                }
                $this->pastikanPeserta($pembeli, $sesi);
                if ($sesi->getMode() !== \App\Enums\ModeLelang::NAIK) {
                    $this->tolak('harga_per_kg', 'Mode lelang turun memakai tombol Ambil, bukan tawaran nominal.');
                }

                // Langkah 4: sesi masih aktif dan waktunya belum habis
                if (! $sesi->isMasihAktif()) {
                    $this->tolak('harga_per_kg', 'Sesi lelang sudah berakhir. Tawaran tidak dapat diterima.');
                }

                // Pembeli yang sudah tertinggi tidak perlu menaikkan tawarannya sendiri.
                // Hanya kolom yang dipakai yang diambil (bukan SELECT *) — lebih ringan di dalam kunci.
                $tertinggi = Penawaran::where('id_sesi', $sesi->getIdSesi())
                    ->where('status', StatusPenawaran::TERTINGGI->value)
                    ->orderByDesc('harga_per_kg')
                    ->orderBy('id_penawaran')
                    ->first(['id_penawaran', 'id_pembeli']);
                if ($tertinggi && $tertinggi->getIdPembeli() === $pembeli->getIdPengguna()) {
                    $this->tolak('harga_per_kg', 'Anda sudah menjadi penawar tertinggi.');
                }

                // Langkah 5-6: harga minimum (FR-18) dan batas kewajaran
                $minimum = $sesi->hargaMinimumBerikut();
                if ($hargaPerKg < $minimum) {
                    if ($sesi->getMode() === \App\Enums\ModeLelang::TURUN) {
                        $pesan = 'Tawaran minimal ' . $this->rp($minimum) . '/kg (harga berjalan lelang turun).';
                    } elseif ($sesi->getHargaBerjalan() === null) {
                        $pesan = 'Tawaran minimal sama dengan harga pembukaan, yaitu ' . $this->rp($minimum) . '/kg.';
                    } else {
                        $pesan = 'Tawaran minimal ' . $this->rp($minimum) . '/kg (harga tertinggi '
                            . $this->rp((float) $sesi->getHargaBerjalan()) . ' + kenaikan '
                            . $this->rp((float) $sesi->getKenaikanMinimum()) . ').';
                    }
                    $this->tolak('harga_per_kg', $pesan);
                }
                $batasAtas = 10 * (float) $sesi->lot->getHargaPembukaan();
                if ($hargaPerKg > $batasAtas) {
                    $this->tolak('harga_per_kg', 'Tawaran lebih dari 10 kali harga pembukaan. Periksa kembali angka yang Anda ketik.');
                }
                $nilaiTawaran = $hargaPerKg * (float) $sesi->lot->getBeratAktual();

                // FR-21 + QRY-03: daya beli = saldo + limit kredit pembeli harus mencukupi nilai tawaran
                $dayaBeli = SaldoPembeli::dayaBeli($pembeli->getIdPengguna());
                if ($dayaBeli < $nilaiTawaran) {
                    $this->tolak('harga_per_kg', 'Daya beli tidak cukup. Nilai tawaran ' . $this->rp($nilaiTawaran)
                        . ' melebihi saldo + limit kredit Anda (' . $this->rp($dayaBeli)
                        . '). Lakukan top up melalui Kasir TPI.');
                }

                // Langkah 7-8: simpan sebagai TERTINGGI, yang lama menjadi TERTINGGAL
                if ($tertinggi) {
                    $tertinggi->tandaiTertinggal();
                }
                $penawaran = Penawaran::create([
                    'id_sesi'           => $sesi->getIdSesi(),
                    'id_pembeli'        => $pembeli->getIdPengguna(),
                    'harga_per_kg'      => (string) $hargaPerKg,
                    'nominal'           => (string) round($nilaiTawaran),
                    'waktu_penawaran'   => now(),
                    'status'            => StatusPenawaran::TERTINGGI->value,
                    'kanal'             => $kanal->value,
                    'kunci_idempotensi' => $kunci,
                    'alamat_terminal'   => $ip,
                ]);
                // Langkah 9 (ALG-03) dihitung lebih dulu supaya harga berjalan dan perpanjangan
                // bisa ditulis dalam SATU pernyataan. Di dalam lockForUpdate setiap round-trip
                // tambahan memperpanjang antrean tawaran berikutnya (NFR-01: p95 <= 500 ms).
                $tambahan = (int) Konfigurasi::nilai('tambahan_perpanjangan_detik', 30);
                $sisa = $sesi->sisaDetik();
                $perluPerpanjang = $sisa <= (int) $sesi->ambang_perpanjangan_detik
                    && $sesi->getJumlahPerpanjangan() < $sesi->getMaksPerpanjangan();
                $kolom = ['harga_berjalan' => (string) $hargaPerKg];
                if ($perluPerpanjang) {
                    $kolom['waktu_selesai_rencana'] = now()->addSeconds($tambahan);
                    $kolom['jumlah_perpanjangan'] = $sesi->getJumlahPerpanjangan() + 1;
                }
                // Tanpa kejadian model: tidak perlu model event, dan menghindari save() kedua.
                SesiLelang::whereKey($sesi->getIdSesi())->update($kolom);
                $sesi->forceFill($kolom)->syncOriginal();

                if ($perluPerpanjang && $sesi->lot->getStatus() === StatusLot::DILELANG) {
                    $sesi->lot->pindahStatus(StatusLot::DIPERPANJANG);
                }

                return $penawaran;
            });
        } catch (QueryException $e) {
            // Dua permintaan dengan kunci sama tiba bersamaan: yang kedua kalah di indeks unik
            if ($lama = Penawaran::cariKunci($kunci)) {
                return [$lama, true];
            }
            throw $e;
        }

        $this->siarkanPerubahanTawaran($hasil);

        return [$hasil, false];
    }

    /**
     * Siarkan PENAWARAN_BARU, OUTBID, dan SESI_DIPERPANJANG untuk satu penawaran yang sudah tersimpan.
     * Dipanggil setelah commit agar klien tidak menerima harga yang gagal tersimpan.
     */
    private function siarkanPerubahanTawaran(Penawaran $penawaran): void
    {
        $sesi = SesiLelang::with('lot.tangkapan.kedatangan')->find($penawaran->getIdSesi());
        if (! $sesi) {
            return;
        }
        $idTpi = $sesi->lot->tangkapan->kedatangan->getIdTpi();
        $harga = (float) $penawaran->getHargaPerKg();
        $berat = (float) $sesi->lot->getBeratAktual();
        $akhir = $penawaran->getWaktuPenawaran()->format('Y-m-d H:i:s');

        $this->siarkan($idTpi, \App\Events\PeristiwaLelang::PENAWARAN_BARU, [
            'id_sesi'            => $sesi->getIdSesi(),
            'id_penawaran'       => $penawaran->getIdPenawaran(),
            'harga_tertinggi'    => $harga,
            'nilai'              => round($harga * $berat),
            'waktu_penawaran'    => $akhir,
            'inisial_penawar'    => $this->inisial($penawaran),
            'jumlah_penawaran'   => $sesi->daftarPenawaran()->count(),
        ]);

        // OUTBID ditujukan ke klien penawar sebelumnya; aplikasi menyaringnya berdasarkan id_pembeli.
        $sebelumnya = Penawaran::where('id_sesi', $sesi->getIdSesi())
            ->where('id_pembeli', '!=', $penawaran->getIdPembeli())
            ->where('status', StatusPenawaran::TERTINGGAL->value)
            ->orderByDesc('id_penawaran')
            ->first();
        $this->siarkan($idTpi, \App\Events\PeristiwaLelang::OUTBID, [
            'id_sesi'         => $sesi->getIdSesi(),
            'harga_tertinggi' => $harga,
            'id_pembeli'      => $sebelumnya?->getIdPembeli(),
            'pesan'           => 'Tawaran Anda tertinggal. Harga tertinggi sekarang ' . $this->rp($harga) . '/kg.',
        ]);

        if ((int) $sesi->getJumlahPerpanjangan() > 0) {
            $this->siarkan($idTpi, \App\Events\PeristiwaLelang::DIPERPANJANG, [
                'id_sesi'              => $sesi->getIdSesi(),
                'waktu_selesai_baru'   => $sesi->getWaktuSelesaiRencana()->format('Y-m-d H:i:s'),
                'jumlah_perpanjangan'  => (int) $sesi->getJumlahPerpanjangan(),
            ]);
        }
    }

    /** Inisial nama pembeli untuk papan lelang umum (FR-43: identitas disamarkan). */
    private function inisial(Penawaran $p): string
    {
        $nama = trim((string) ($p->pembeli?->getNamaLengkap() ?? 'Pembeli'));
        $bagian = preg_split('/\s+/', $nama) ?: [];

        return strtoupper(implode('', array_map(fn ($b) => mb_substr($b, 0, 1), array_slice($bagian, 0, 2))));
    }

    /** Pembeli pertama yang mengambil harga berjalan memenangkan lelang turun secara atomik. */
    public function ambilHargaTurun(
        Pengguna $pembeli,
        int $idSesi,
        string $kunci,
        ?string $ip,
        KanalPenawaran $kanal = KanalPenawaran::JARAK_JAUH,
    ): array {
        if ($kanal === KanalPenawaran::JARAK_JAUH
            && (string) Konfigurasi::nilai('lelang_jarak_jauh_aktif', '1') !== '1') {
            $this->tolak('ambil', 'Lelang jarak jauh sedang dimatikan administrator. '
                . 'Pengambilan harga hanya dapat dilakukan di tempat melalui petugas operator.');
        }

        try {
            $penawaran = DB::transaction(function () use ($pembeli, $idSesi, $kunci, $ip, $kanal) {
                $sesi = SesiLelang::with('lot')->whereKey($idSesi)->lockForUpdate()->first();
                if (! $sesi || $sesi->getMode() !== \App\Enums\ModeLelang::TURUN) {
                    throw new HttpResponseException(response()->json(['message' => 'Sesi lelang turun tidak ditemukan.'], 404));
                }
                $this->pastikanPeserta($pembeli, $sesi);
                $sesi = $this->tutupBilaWaktuHabis($sesi);
                if (! $sesi->isMasihAktif() || $sesi->tertinggi()) {
                    $this->tolak('ambil', 'Harga sudah diambil atau sesi lelang telah berakhir.');
                }
                $harga = $sesi->hargaTurunSaatIni();
                $nominal = $harga * (float) $sesi->lot->getBeratAktual();
                // FR-21: aturan daya beli sama untuk lelang turun (ALG-10 langkah 4)
                $dayaBeli = SaldoPembeli::dayaBeli($pembeli->getIdPengguna());
                if ($dayaBeli < $nominal) {
                    $this->tolak('ambil', 'Daya beli tidak cukup untuk mengambil harga ' . $this->rp($harga)
                        . '/kg (nilai ' . $this->rp($nominal) . ', daya beli ' . $this->rp($dayaBeli) . ').');
                }
                $p = Penawaran::create([
                    'id_sesi' => $sesi->getIdSesi(), 'id_pembeli' => $pembeli->getIdPengguna(),
                    'harga_per_kg' => (string) $harga, 'nominal' => (string) round($nominal),
                    'waktu_penawaran' => now(), 'status' => StatusPenawaran::TERTINGGI->value,
                    'kanal' => $kanal->value, 'kunci_idempotensi' => strtolower($kunci), 'alamat_terminal' => $ip,
                ]);
                $sesi->harga_berjalan = (string) $harga;
                $sesi->waktu_selesai_rencana = now();
                $sesi->save();
                return $p;
            });
        } catch (QueryException $e) {
            if ($lama = Penawaran::cariKunci($kunci)) return [$lama, true];
            throw $e;
        }
        $this->siarkanPerubahanTawaran($penawaran);

        return [$penawaran, false];
    }

    // ================= UC-05 =================

    /** Tutup sesi bila waktunya sudah habis (dipanggil setiap kali status sesi diminta). */
    public function tutupBilaWaktuHabis(SesiLelang $sesi): SesiLelang
    {
        if ($sesi->getMode() === \App\Enums\ModeLelang::TURUN) {
            $hargaLama = (float) $sesi->getHargaBerjalan();
            $sesi->perbaruiHargaTurun();
            if ($sesi->getStatus() === StatusSesi::AKTIF && (float) $sesi->getHargaBerjalan() !== $hargaLama) {
                $this->siarkan($this->idTpiLot($sesi->lot), \App\Events\PeristiwaLelang::HARGA_TURUN, [
                    'id_sesi'                => $sesi->getIdSesi(),
                    'harga_saat_ini'         => (float) $sesi->getHargaBerjalan(),
                    'langkah_ke'             => $sesi->getIntervalDetik() > 0
                        ? (int) floor(max(0, $sesi->getWaktuMulai()->diffInSeconds(now(), false)) / $sesi->getIntervalDetik())
                        : 0,
                    'waktu_langkah_berikut'  => $sesi->getIntervalDetik()
                        ? now()->addSeconds($sesi->getIntervalDetik())->format('Y-m-d H:i:s')
                        : null,
                ]);
            }
            if ($sesi->tertinggi() || $sesi->hargaBatasBawahLewatSatuInterval()) {
                return $this->tutup($sesi->getIdSesi(), null);
            }
            return $sesi;
        }
        if ($sesi->getStatus() === StatusSesi::AKTIF && $sesi->sisaDetik() === 0) {
            return $this->tutup($sesi->getIdSesi(), null);
        }

        return $sesi;
    }

    /** Tutup semua sesi yang waktunya habis (perintah artisan lelang:tutup-otomatis). */
    public function tutupSemuaKedaluwarsa(): int
    {
        $jumlah = 0;
        foreach (SesiLelang::kedaluwarsa() as $sesi) {
            $this->tutup($sesi->getIdSesi(), null);
            $jumlah++;
        }

        return $jumlah;
    }

    /** Penutupan manual oleh operator (alur A3, alasan wajib). */
    public function tutupManual(Pengguna $operator, int $idSesi, string $alasan): SesiLelang
    {
        $sesi = SesiLelang::with('lot.tangkapan.kedatangan')->find($idSesi);
        if (! $sesi) {
            throw new HttpResponseException(response()->json(['message' => 'Sesi lelang tidak ditemukan.'], 404));
        }
        $this->pastikanLotDiTpi($sesi->lot, $operator);
        $sesi = $this->tutupBilaWaktuHabis($sesi);
        if ($sesi->getStatus() !== StatusSesi::AKTIF) {
            $this->tolak('alasan', 'Sesi lelang ini sudah ditutup.');
        }

        return $this->tutup($idSesi, $alasan, $operator);
    }

    /** ALG-04: seluruh langkah dalam satu transaksi basis data. */
    private function tutup(int $idSesi, ?string $alasan, ?Pengguna $oleh = null): SesiLelang
    {
        $sebelum = SesiLelang::find($idSesi);
        $sesi = $this->tutupDalamTransaksi($idSesi, $alasan);

        app(AuditService::class)->catat($oleh, 'TUTUP_SESI', 'sesi_lelang', $sesi->getIdSesi(),
            ['status' => $sebelum?->status],
            ['status' => $sesi->status, 'alasan' => $alasan, 'id_penawaran_pemenang' => $sesi->id_penawaran_pemenang,
             'status_lot' => $sesi->lot->getStatus()->value]);

        // Setelah commit: siarkan hasil dan penutupan (Tabel IV.7).
        $idTpi = $this->idTpiLot($sesi->lot);
        $pemenang = $sesi->tertinggi();
        $hargaAkhir = $pemenang ? (float) $pemenang->getHargaPerKg() : null;
        $this->siarkan($idTpi, \App\Events\PeristiwaLelang::HASIL, [
            'id_sesi'      => $sesi->getIdSesi(),
            'kode_lot'     => $sesi->lot->getKodeLot(),
            'pemenang'     => $pemenang ? $this->inisial($pemenang) : null,   // disamarkan (FR-43)
            'harga_akhir'  => $hargaAkhir,
            'status_lot'   => $sesi->lot->getStatus()->value,
        ]);
        $this->siarkan($idTpi, \App\Events\PeristiwaLelang::DITUTUP, [
            'id_sesi'          => $sesi->getIdSesi(),
            'alasan_penutupan' => $alasan,
        ]);

        return $sesi;
    }

    private function tutupDalamTransaksi(int $idSesi, ?string $alasan): SesiLelang
    {
        for ($coba = 1; ; $coba++) {
            try {
                return DB::transaction(function () use ($idSesi, $alasan) {
                    // Langkah 1: kunci baris sesi, pastikan masih AKTIF (mencegah penutupan ganda)
                    $sesi = SesiLelang::with('lot')->whereKey($idSesi)->lockForUpdate()->first();
                    if ($sesi->getStatus() !== StatusSesi::AKTIF) {
                        return $sesi;
                    }

                    // Langkah 2
                    $sesi->status = StatusSesi::DITUTUP->value;
                    $sesi->waktu_selesai_aktual = now();
                    $sesi->alasan_tutup = $alasan;

                    $lot = $sesi->lot;
                    if ($lot->getStatus() === StatusLot::DIPERPANJANG) {
                        $lot->pindahStatus(StatusLot::DILELANG);   // diagram keadaan: DIPERPANJANG -> DILELANG
                    }

                    // Langkah 3-7: ada pemenang -> lot TERJUAL dan transaksi BELUM_DIBAYAR
                    $pemenang = $sesi->tertinggi();
                    if ($pemenang) {
                        $sesi->id_penawaran_pemenang = $pemenang->getIdPenawaran();
                        $sesi->save();
                        $lot->pindahStatus(StatusLot::TERJUAL);

                        $tarif = TarifRetribusi::cariBerlaku(now());
                        if (! $tarif) {
                            throw new HttpResponseException(response()->json([
                                'message' => 'Tarif retribusi yang berlaku belum diatur. Hubungi administrator.',
                            ], 500));
                        }
                        TransaksiPenjualan::dariSesi($sesi, $pemenang, $tarif, (float) Konfigurasi::nilai('biaya_layanan', 0));
                    } else {
                        // Langkah 8: tidak ada penawaran sah
                        $sesi->save();
                        $lot->pindahStatus(StatusLot::TIDAK_TERJUAL);
                    }

                    return $sesi->fresh('lot');
                });
            } catch (QueryException $e) {
                if ($coba >= 3) {
                    throw $e;
                }
                usleep(100_000);   // deadlock: ulangi
            }
        }
    }

    // ================= Data untuk layar =================

    /** Status lengkap sebuah sesi untuk polling. Nama penawar disamarkan untuk pembeli. */
    public function status(int $idSesi, Pengguna $pengguna, bool $sebagaiOperator): array
    {
        $sesi = SesiLelang::with('lot.tangkapan.kedatangan')->find($idSesi);
        if (! $sesi) {
            throw new HttpResponseException(response()->json(['message' => 'Sesi lelang tidak ditemukan.'], 404));
        }
        if ($sebagaiOperator) {
            $this->pastikanLotDiTpi($sesi->lot, $pengguna);
        } else {
            $this->pastikanPeserta($pengguna, $sesi);
        }
        $sesi = $this->tutupBilaWaktuHabis($sesi);

        $semua = $sesi->daftarPenawaran()->with('pembeli')->orderByDesc('id_penawaran')->get();
        // Nomor peserta menurut urutan tawaran pertama: Peserta 1, Peserta 2, ...
        $nomor = $semua->sortBy('id_penawaran')->pluck('id_pembeli')->unique()->values()->flip();
        $idSaya = $pengguna->getIdPengguna();
        $tertinggi = $semua->firstWhere('status', StatusPenawaran::TERTINGGI->value);

        $label = function (Penawaran $p) use ($sebagaiOperator, $nomor, $idSaya) {
            if ($sebagaiOperator) {
                return $p->pembeli->getNamaLengkap();
            }

            return $p->getIdPembeli() === $idSaya ? 'Anda' : 'Peserta ' . ($nomor[$p->getIdPembeli()] + 1);
        };

        $data = [
            'id_sesi'               => $sesi->getIdSesi(),
            'status'                => $sesi->getStatus()->value,
            'mode'                  => $sesi->getMode()->value,
            'lot'                   => $this->ringkasLot($sesi->lot),
            'harga_pembukaan'       => $sesi->lot->getHargaPembukaan(),
            'harga_tertinggi'       => $sesi->getMode() === \App\Enums\ModeLelang::TURUN ? $sesi->hargaTurunSaatIni() : $sesi->getHargaBerjalan(),
            'harga_awal'            => $sesi->getHargaAwal(),
            'harga_batas_bawah'     => $sesi->getHargaBatasBawah(),
            'penurunan_per_langkah' => $sesi->getPenurunanPerLangkah(),
            'interval_detik'        => $sesi->getIntervalDetik(),
            'kenaikan_minimum'      => $sesi->getKenaikanMinimum(),
            'harga_minimum_berikut' => $sesi->hargaMinimumBerikut(),
            'sisa_detik'            => $sesi->getStatus() === StatusSesi::AKTIF ? $sesi->sisaDetik() : 0,
            'durasi_detik'          => $sesi->getDurasiDetik(),
            'jumlah_perpanjangan'   => $sesi->getJumlahPerpanjangan(),
            'maks_perpanjangan'     => $sesi->getMaksPerpanjangan(),
            'jumlah_penawaran'      => $semua->count(),
            'jumlah_penawar'        => $nomor->count(),
            'jumlah_peserta'        => DaftarPantauan::where('id_lot', $sesi->getIdLot())->count(),
            'waktu_mulai'           => $sesi->getWaktuMulai()->format('Y-m-d H:i:s'),
            'riwayat'               => $semua->take(10)->map(fn (Penawaran $p) => [
                'penawar'      => $label($p),
                'harga_per_kg' => $p->getHargaPerKg(),
                'waktu'        => $p->getWaktuPenawaran()->format('H:i:s'),
                'tertinggi'    => $p->status === StatusPenawaran::TERTINGGI->value,
            ])->values()->all(),
        ];

        if ($sebagaiOperator) {
            $data['penawar_tertinggi'] = $tertinggi?->pembeli->getNamaLengkap();
            $data['alasan_tutup'] = $sesi->getAlasanTutup();
        } else {
            $sayaTerakhir = $semua->firstWhere('id_pembeli', $idSaya);
            $data['posisi'] = ! $sayaTerakhir ? 'BELUM_MENAWAR'
                : ($tertinggi && $tertinggi->getIdPembeli() === $idSaya ? 'TERTINGGI' : 'TERTINGGAL');
            $data['tawaran_saya'] = $sayaTerakhir?->getHargaPerKg();
            $data['kode_pemesanan'] = DaftarPantauan::cari($idSaya, $sesi->getIdLot())?->getKodePemesanan();
        }

        if ($sesi->getStatus() === StatusSesi::DITUTUP) {
            $trx = $sesi->transaksi;
            $data['hasil'] = $trx ? [
                'terjual'            => true,
                'menang'             => $trx->getIdPembeli() === $idSaya,
                'pemenang'           => $sebagaiOperator ? $trx->pembeli->getNamaLengkap() : null,
                'harga_akhir_per_kg' => $trx->getHargaAkhirPerKg(),
                'id_transaksi'       => ($sebagaiOperator || $trx->getIdPembeli() === $idSaya) ? $trx->getIdTransaksi() : null,
                'kode_transaksi'     => ($sebagaiOperator || $trx->getIdPembeli() === $idSaya) ? $trx->getKodeTransaksi() : null,
                'total_tagihan'      => ($sebagaiOperator || $trx->getIdPembeli() === $idSaya) ? $trx->getTotalTagihan() : null,
                'batas_bayar'        => ($sebagaiOperator || $trx->getIdPembeli() === $idSaya) ? $trx->batas_bayar?->format('Y-m-d H:i:s') : null,
            ] : ['terjual' => false, 'menang' => false];
        }

        return $data;
    }

    /** Batas jumlah baris per halaman daftar sesi operator (NFR-11). */
    public const SESI_PER_HALAMAN = 50;

    /** Batas jumlah halaman yang boleh diminta (50 x 20 = 1.000 sesi). */
    public const SESI_MAKS_HALAMAN = 20;

    /**
     * Daftar sesi untuk operator: yang AKTIF + yang ditutup hari ini, TERPAGINASI.
     *
     * Sebelumnya seluruh baris diambil tanpa batas; pada basis data setahun
     * (100.003 sesi) responsnya 1,4-1,6 detik dan tumbuh terus (NFR-11).
     * Kini dibatasi 50 baris per halaman dengan `?halaman=` (bawaan 1).
     */
    public function daftarSesiOperator(Pengguna $operator, int $halaman = 1): array
    {
        $idTpi = $this->idTpi($operator);
        $halaman = max(1, min($halaman, self::SESI_MAKS_HALAMAN));

        $kueri = SesiLelang::with('lot.tangkapan.kedatangan')
            ->whereHas('lot.tangkapan.kedatangan', fn ($q) => $q->where('id_tpi', $idTpi))
            ->where(fn ($q) => $q->where('status', StatusSesi::AKTIF->value)->orWhereDate('waktu_mulai', today()))
            ->orderByDesc('id_sesi');

        $total = (clone $kueri)->count();
        $sesi = $kueri->forPage($halaman, self::SESI_PER_HALAMAN)->get();

        return [
            'data'     => $sesi->map(fn (SesiLelang $s) => $this->ringkasSesi($this->tutupBilaWaktuHabis($s)))->values()->all(),
            'meta'     => [
                'halaman'      => $halaman,
                'per_halaman'  => self::SESI_PER_HALAMAN,
                'total'        => $total,
                'total_halaman' => (int) ceil($total / self::SESI_PER_HALAMAN),
                'maks_halaman' => self::SESI_MAKS_HALAMAN,
            ],
        ];
    }

    /** Daftar sesi pada lot-lot yang dipesan pembeli (aktif dulu, lalu yang sudah selesai). */
    public function daftarSesiPembeli(Pengguna $pembeli): array
    {
        $idLot = DaftarPantauan::where('id_pembeli', $pembeli->getIdPengguna())->pluck('id_lot');
        $sesi = SesiLelang::with('lot.tangkapan.kedatangan')
            ->whereIn('id_lot', $idLot)
            ->orderByRaw("status = 'AKTIF' DESC")
            ->orderByDesc('id_sesi')
            ->limit(30)
            ->get();

        return $sesi->map(function (SesiLelang $s) use ($pembeli) {
            $s = $this->tutupBilaWaktuHabis($s);
            $r = $this->ringkasSesi($s);
            $tertinggi = $s->tertinggi();
            $sayaMenawar = $s->daftarPenawaran()->where('id_pembeli', $pembeli->getIdPengguna())->exists();
            $r['posisi'] = ! $sayaMenawar ? 'BELUM_MENAWAR'
                : ($tertinggi && $tertinggi->getIdPembeli() === $pembeli->getIdPengguna() ? 'TERTINGGI' : 'TERTINGGAL');
            $r['menang'] = $s->transaksi && $s->transaksi->getIdPembeli() === $pembeli->getIdPengguna();

            return $r;
        })->values()->all();
    }

    private function ringkasSesi(SesiLelang $s): array
    {
        return [
            'id_sesi'          => $s->getIdSesi(),
            'status'           => $s->getStatus()->value,
            'sisa_detik'       => $s->getStatus() === StatusSesi::AKTIF ? $s->sisaDetik() : 0,
            'harga_tertinggi'  => $s->getHargaBerjalan(),
            'jumlah_penawaran' => $s->daftarPenawaran()->count(),
            'waktu_mulai'      => $s->getWaktuMulai()->format('Y-m-d H:i'),
            'status_lot'       => $s->lot->getStatus()->value,
            'lot'              => $this->ringkasLot($s->lot),
        ];
    }

    public function ringkasLot(LotLelang $lot): array
    {
        $lot->loadMissing(['tangkapan.jenisIkan', 'tangkapan.grade', 'tangkapan.kedatangan.kapal']);
        $t = $lot->tangkapan;

        return [
            'id_lot'          => $lot->getIdLot(),
            'kode_lot'        => $lot->getKodeLot(),
            'nama_jenis'      => $t->jenisIkan->getNamaJenis(),
            'kode_grade'      => $t->grade->getKodeGrade(),
            'berat_aktual'    => $lot->getBeratAktual(),
            'harga_pembukaan' => $lot->getHargaPembukaan(),
            'nilai_lot'       => round((float) $lot->getHargaPembukaan() * (float) $lot->getBeratAktual()),
            'nama_kapal'      => $t->kedatangan->kapal->getNamaKapal(),
            'waktu_timbang'   => $lot->getWaktuTimbang()?->format('H:i'),
            'foto'            => $lot->foto()->first()?->urlPublik(),
        ];
    }

    // ================= bantuan =================

    private function pastikanPeserta(Pengguna $pembeli, SesiLelang $sesi): void
    {
        if (! DaftarPantauan::cari($pembeli->getIdPengguna(), $sesi->getIdLot())) {
            throw new HttpResponseException(response()->json([
                'message' => 'Anda belum terdaftar pada lot ini. Tekan "Ikut Lelang Lot Ini" sebelum sesi dibuka.',
            ], 403));
        }
    }

    private function pastikanLotDiTpi(?LotLelang $lot, Pengguna $pengguna): void
    {
        if (! $lot || $lot->tangkapan->kedatangan->getIdTpi() !== $this->idTpi($pengguna)) {
            throw new HttpResponseException(response()->json(['message' => 'Lot tidak ditemukan.'], 404));
        }
    }

    public function idTpi(Pengguna $pengguna): int
    {
        return $pengguna->getIdTpi() ?? (int) Konfigurasi::nilai('id_tpi_aktif', 1);
    }

    private function rp(float $n): string
    {
        return 'Rp' . number_format($n, 0, ',', '.');
    }

    private function tolak(string $kolom, string $pesan): never
    {
        throw ValidationException::withMessages([$kolom => $pesan]);
    }
}
