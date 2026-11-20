<?php

namespace App\Services;

use App\Enums\StatusLot;
use App\Enums\StatusPenawaran;
use App\Enums\StatusSesi;
use App\Models\DaftarPantauan;
use App\Models\Konfigurasi;
use App\Models\LotLelang;
use App\Models\Penawaran;
use App\Models\Pengguna;
use App\Models\SesiLelang;
use App\Models\TarifRetribusi;
use App\Models\TransaksiPenjualan;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kelas kendali LelangService (BiddingEngine + PenutupanLelangService pada DPPL).
 * UC-03 buka sesi, UC-04 penawaran (ALG-01, ALG-02, ALG-03), UC-05 penutupan (ALG-04, ALG-05).
 *
 * Real-time memakai polling: aplikasi meminta status sesi setiap 1 detik. Sesi yang waktunya habis
 * ditutup saat itu juga (penutupan "malas"), sehingga tidak memerlukan penjadwal / server WebSocket.
 */
class LelangService
{
    public const DURASI_MIN = 30;
    public const DURASI_MAKS = 600;

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
    public function buka(Pengguna $operator, int $idLot, int $durasi, ?float $kenaikan): SesiLelang
    {
        return DB::transaction(function () use ($operator, $idLot, $durasi, $kenaikan) {
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

            $sesi = SesiLelang::buka(
                $lot,
                $operator,
                $durasi,
                $kenaikan ?? $this->usulanKenaikan($lot),
                (int) Konfigurasi::nilai('maks_perpanjangan', 5),
                (int) Konfigurasi::nilai('ambang_perpanjangan_detik', 15),
            );
            $lot->pindahStatus(StatusLot::DILELANG);

            return $sesi;
        });
    }

    // ================= UC-04 =================

    /**
     * ALG-01: penawaran atomik. Mengembalikan [penawaran, duplikat?].
     * Hanya pembeli yang punya kode pemesanan (PSN, Tugas 2) pada lot ini yang boleh menawar.
     */
    public function ajukanTawaran(Pengguna $pembeli, int $idSesi, float $hargaPerKg, string $kunci, ?string $ip): array
    {
        // Langkah 2: kunci idempotensi yang sama -> kembalikan hasil tersimpan tanpa memproses ulang (FR-20)
        if ($lama = Penawaran::cariKunci($kunci)) {
            return [$lama, true];
        }

        try {
            $hasil = DB::transaction(function () use ($pembeli, $idSesi, $hargaPerKg, $kunci, $ip) {
                // Langkah 3: kunci baris sesi
                $sesi = SesiLelang::with('lot')->whereKey($idSesi)->lockForUpdate()->first();
                if (! $sesi) {
                    throw new HttpResponseException(response()->json(['message' => 'Sesi lelang tidak ditemukan.'], 404));
                }
                $this->pastikanPeserta($pembeli, $sesi);

                // Langkah 4: sesi masih aktif dan waktunya belum habis
                if (! $sesi->isMasihAktif()) {
                    $this->tolak('harga_per_kg', 'Sesi lelang sudah berakhir. Tawaran tidak dapat diterima.');
                }

                // Pembeli yang sudah tertinggi tidak perlu menaikkan tawarannya sendiri
                $tertinggi = $sesi->tertinggi();
                if ($tertinggi && $tertinggi->getIdPembeli() === $pembeli->getIdPengguna()) {
                    $this->tolak('harga_per_kg', 'Anda sudah menjadi penawar tertinggi.');
                }

                // Langkah 5-6: harga minimum (FR-18) dan batas kewajaran
                $minimum = $sesi->hargaMinimumBerikut();
                if ($hargaPerKg < $minimum) {
                    $this->tolak('harga_per_kg', $sesi->getHargaBerjalan() === null
                        ? 'Tawaran minimal sama dengan harga pembukaan, yaitu ' . $this->rp($minimum) . '/kg.'
                        : 'Tawaran minimal ' . $this->rp($minimum) . '/kg (harga tertinggi '
                          . $this->rp((float) $sesi->getHargaBerjalan()) . ' + kenaikan ' . $this->rp((float) $sesi->getKenaikanMinimum()) . ').');
                }
                $batasAtas = 10 * (float) $sesi->lot->getHargaPembukaan();
                if ($hargaPerKg > $batasAtas) {
                    $this->tolak('harga_per_kg', 'Tawaran lebih dari 10 kali harga pembukaan. Periksa kembali angka yang Anda ketik.');
                }
                $nilaiTawaran = $hargaPerKg * (float) $sesi->lot->getBeratAktual();
                if (! app(DepositService::class)->periksaDayaBeli($pembeli->getIdPengguna(), $nilaiTawaran)) {
                    $this->tolak('harga_per_kg', 'Daya beli tidak mencukupi untuk tawaran ini. Silakan hubungi kasir untuk menambah saldo atau limit.');
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
                    'kanal'             => 'JARAK_JAUH',
                    'kunci_idempotensi' => $kunci,
                    'alamat_terminal'   => $ip,
                ]);
                $sesi->harga_berjalan = (string) $hargaPerKg;
                $sesi->save();

                // Langkah 9: ALG-03 perpanjangan otomatis
                if ($sesi->perpanjangBilaPerlu((int) Konfigurasi::nilai('tambahan_perpanjangan_detik', 30))
                    && $sesi->lot->getStatus() === StatusLot::DILELANG) {
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

        return [$hasil, false];
    }

    // ================= UC-05 =================

    /** Tutup sesi bila waktunya sudah habis (dipanggil setiap kali status sesi diminta). */
    public function tutupBilaWaktuHabis(SesiLelang $sesi): SesiLelang
    {
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

        return $this->tutup($idSesi, $alasan);
    }

    /** ALG-04: seluruh langkah dalam satu transaksi basis data. */
    private function tutup(int $idSesi, ?string $alasan): SesiLelang
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
            'lot'                   => $this->ringkasLot($sesi->lot),
            'harga_pembukaan'       => $sesi->lot->getHargaPembukaan(),
            'harga_tertinggi'       => $sesi->getHargaBerjalan(),
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
            ] : ['terjual' => false, 'menang' => false];
        }

        return $data;
    }

    /** Daftar sesi untuk operator: semua yang AKTIF + yang ditutup hari ini. */
    public function daftarSesiOperator(Pengguna $operator): array
    {
        $idTpi = $this->idTpi($operator);
        $sesi = SesiLelang::with('lot.tangkapan.kedatangan')
            ->whereHas('lot.tangkapan.kedatangan', fn ($q) => $q->where('id_tpi', $idTpi))
            ->where(fn ($q) => $q->where('status', StatusSesi::AKTIF->value)->orWhereDate('waktu_mulai', today()))
            ->orderByDesc('id_sesi')
            ->get();

        return $sesi->map(fn (SesiLelang $s) => $this->ringkasSesi($this->tutupBilaWaktuHabis($s)))->values()->all();
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
