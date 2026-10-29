<?php

namespace App\Services;

use App\Enums\StatusKedatangan;
use App\Models\KedatanganKapal;
use App\Models\Konfigurasi;
use App\Models\LotLelang;
use App\Models\Pengguna;
use App\Models\Tangkapan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kelas kendali PenimbanganService (DPPL Tabel 3.7) - UC-02
 * Aturan bisnis (selisih, harga, status, nomor lot) ada di kelas entitas;
 * kelas ini hanya mengatur urutan langkah dan transaksi.
 */
class PenimbanganService
{
    public function __construct(private LotService $lotService)
    {
    }

    /** "KDT-00012", "kdt-12", atau "12" -> 12 */
    public function idDariKode(string $kode): ?int
    {
        return KedatanganKapal::idDariNomor($kode);
    }

    /** Pesan (1)-(2): ambil kedatangan + daftar tangkapannya */
    public function ambilKedatangan(int $idKedatangan): ?array
    {
        $k = KedatanganKapal::with(['kapal', 'tangkapan.jenisIkan', 'tangkapan.grade', 'tangkapan.lot'])
            ->find($idKedatangan);
        if (! $k) {
            return null;
        }

        return [
            'id_kedatangan'    => $k->getIdKedatangan(),
            'nomor_kedatangan' => $k->getNomorKedatangan(),
            'nama_kapal'       => $k->kapal->getNamaKapal(),
            'nomor_registrasi' => $k->kapal->getNomorRegistrasi(),
            'tanggal'          => $k->getTanggal()->format('Y-m-d'),
            'jam'              => $k->getJam(),
            'nama_kapten'      => $k->getNamaKapten(),
            'status'           => $k->getStatus()->value,
            'ambang_selisih'   => (float) Konfigurasi::nilai('ambang_selisih_berat', 15),
            'tangkapan'        => $k->tangkapan->map(function (Tangkapan $t) {
                $lot = $t->lot;
                return [
                    'id_tangkapan'     => $t->getIdTangkapan(),
                    'nama_jenis'       => $t->jenisIkan->getNamaJenis(),
                    'kode_grade'       => $t->grade->getKodeGrade(),
                    'berat_estimasi'   => $t->getBeratEstimasi(),
                    'jumlah_keranjang' => $t->getJumlahKeranjang(),
                    'status'           => $t->getStatus()->value,
                    'usulan_harga'     => ! $t->isSudahDitimbang()
                        ? $this->lotService->usulanHarga($t->getIdJenis(), $t->getIdGrade())
                        : null,
                    'lot' => $lot ? $this->ringkasLot($lot) : null,
                ];
            })->values()->all(),
        ];
    }

    /** Pesan (5)-(7): validasi selisih, buat lot, tandai tangkapan DITIMBANG - satu transaksi */
    public function buatLot(array $data, Pengguna $petugas): LotLelang
    {
        return DB::transaction(function () use ($data, $petugas) {
            // 1. Kunci baris tangkapan agar tidak ditimbang dua kali bersamaan
            $t = Tangkapan::kunciUntukTimbang((int) $data['id_tangkapan']);
            if ($t->isSudahDitimbang()) {
                throw ValidationException::withMessages([
                    'id_tangkapan' => 'Baris tangkapan ini sudah ditimbang.',
                ]);
            }

            // 2. Kedatangan DRAF belum boleh ditimbang (diagram keadaan: DRAF -> TERDAFTAR -> DITIMBANG)
            $kedatangan = $t->kedatangan;
            if ($kedatangan->getStatus() === StatusKedatangan::DRAF) {
                throw ValidationException::withMessages([
                    'id_tangkapan' => 'Kedatangan ' . $kedatangan->getNomorKedatangan()
                        . ' masih berstatus DRAF sehingga belum dapat ditimbang. Hubungi petugas pendataan.',
                ]);
            }

            // 3. Lot baru (DRAFT_HARGA) dengan data identitas yang tidak berubah lagi
            $lot = new LotLelang([
                'kode_lot'           => $this->lotService->buatKodeLot(),
                'id_tangkapan'       => $t->getIdTangkapan(),
                'id_petugas_timbang' => $petugas->getIdPengguna(),
                'waktu_timbang'      => now(),
            ]);
            $lot->setBeratAktual(round(array_sum(array_map('floatval', $data['berat_keranjang'])), 2));
            $lot->setTimbangManual((bool) ($data['timbang_manual'] ?? false));
            $lot->setPerangkatTimbang($data['perangkat_timbang'] ?? null);
            $lot->setAlasan($data['alasan'] ?? null);

            // 4. ALG-08 selisih & tanda verifikasi, lalu harga pembukaan (-> MENUNGGU_LELANG)
            $lot->hitungSelisih((float) $t->getBeratEstimasi(), (float) Konfigurasi::nilai('ambang_selisih_berat', 15));
            if (($data['harga_pembukaan'] ?? null) !== null) {
                $lot->tetapkanHargaPembukaan((float) $data['harga_pembukaan']);
            }
            $lot->simpan();

            // 5. Tangkapan DITIMBANG; bila semua baris sudah, kedatangan DITIMBANG
            $t->tandaiDitimbang();
            $kedatangan->perbaruiStatusTimbang();

            return $lot->fresh();
        }, 3); // 3 percobaan: bila dua petugas menimbang bersamaan dan terjadi deadlock, diulang otomatis
    }

    /** Alur A3: catat setiap percobaan cetak label */
    public function catatCetak(LotLelang $lot): LotLelang
    {
        $lot->catatCetak();
        return $lot->fresh();
    }

    /** ALG-08 untuk validasi formulir (sebelum lot ada); rumusnya tetap di LotLelang::hitungSelisih() */
    public function hitungSelisih(float $aktual, float $estimasi): float
    {
        $lot = new LotLelang();
        $lot->setBeratAktual(round($aktual, 2));

        return $lot->hitungSelisih($estimasi, PHP_FLOAT_MAX);
    }

    /** Data lot untuk layar & label QR */
    public function ringkasLot(LotLelang $lot): array
    {
        $lot->loadMissing(['tangkapan.jenisIkan', 'tangkapan.grade', 'tangkapan.kedatangan.kapal', 'petugasTimbang']);
        $t = $lot->tangkapan;
        $harga = $lot->getHargaPembukaan() !== null ? (float) $lot->getHargaPembukaan() : null;

        return [
            'id_lot'            => $lot->getIdLot(),
            'kode_lot'          => $lot->getKodeLot(),
            'nama_jenis'        => $t->jenisIkan->getNamaJenis(),
            'kode_grade'        => $t->grade->getKodeGrade(),
            'berat_estimasi'    => $t->getBeratEstimasi(),
            'berat_aktual'      => $lot->getBeratAktual(),
            'selisih_persen'    => $lot->getSelisihPersen(),
            'perlu_verifikasi'  => $lot->isPerluVerifikasi(),
            'harga_pembukaan'   => $lot->getHargaPembukaan(),
            'nilai_lot'         => $harga !== null ? round($harga * (float) $lot->getBeratAktual()) : null,
            'status'            => $lot->getStatus()->value,
            'timbang_manual'    => $lot->isTimbangManual(),
            'perangkat_timbang' => $lot->getPerangkatTimbang(),
            'alasan'            => $lot->getAlasan(),
            'waktu_timbang'     => $lot->getWaktuTimbang()?->format('Y-m-d H:i'),
            'petugas_timbang'   => $lot->petugasTimbang?->getNamaLengkap(),
            'nomor_kedatangan'  => $t->kedatangan->getNomorKedatangan(),
            'nama_kapal'        => $t->kedatangan->kapal->getNamaKapal(),
            'jumlah_cetak'      => $lot->getJumlahCetak(),
        ];
    }
}
