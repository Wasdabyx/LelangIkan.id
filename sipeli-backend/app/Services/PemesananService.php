<?php

namespace App\Services;

use App\Enums\TujuanOtp;
use App\Mail\PemesananBerhasilMail;
use App\Models\DaftarPantauan;
use App\Models\Konfigurasi;
use App\Models\LotLelang;
use App\Models\Pengguna;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Kelas kendali PemesananService (Tugas 2).
 * Pembeli memilih lot yang MENUNGGU_LELANG, meminta OTP ke surel, lalu memasukkan OTP.
 * Setelah OTP benar, pembeli tercatat di daftar_pantauan dengan kode pemesanan PSN-YYMMDD-####.
 * Status lot TIDAK berubah: harga akhir tetap ditentukan lewat lelang (UC-03 s.d. UC-05).
 */
class PemesananService
{
    public function __construct(private OtpService $otp)
    {
    }

    /** Daftar lot yang siap dilelang di TPI pembeli, beserta kode pemesanan milik pembeli (bila ada). */
    public function lotTersedia(Pengguna $pembeli): array
    {
        $milikSaya = DaftarPantauan::where('id_pembeli', $pembeli->getIdPengguna())->pluck('kode_pemesanan', 'id_lot');

        return LotLelang::cariBolehDiikuti($this->idTpi($pembeli))
            ->map(fn (LotLelang $lot) => $this->ringkasLot($lot) + [
                'kode_pemesanan' => $milikSaya[$lot->getIdLot()] ?? null,
            ])
            ->values()
            ->all();
    }

    public function milikPembeli(Pengguna $pembeli): array
    {
        return DaftarPantauan::milikPembeli($pembeli->getIdPengguna())
            ->map(fn (DaftarPantauan $d) => $this->ringkas($d))
            ->all();
    }

    /** Langkah 1: kirim OTP pemesanan ke surel pembeli. */
    public function kirimOtp(Pengguna $pembeli, int $idLot): array
    {
        $lot = $this->lotBolehDipesan($pembeli, $idLot);

        return $this->otp->kirim($pembeli, TujuanOtp::PEMESANAN, $lot, $this->ringkasLot($lot)) + [
            'lot' => $this->ringkasLot($lot),
        ];
    }

    /** Langkah 2: periksa OTP lalu catat pemesanan. */
    public function konfirmasi(Pengguna $pembeli, int $idLot, string $kodeOtp): DaftarPantauan
    {
        $lot = $this->lotBolehDipesan($pembeli, $idLot);
        $this->otp->verifikasi($pembeli, TujuanOtp::PEMESANAN, $kodeOtp, $lot->getIdLot());

        // Kode pemesanan dibuat dengan kunci baris; ulangi bila terjadi deadlock
        $pemesanan = null;
        for ($coba = 1; $coba <= 3 && ! $pemesanan; $coba++) {
            try {
                $pemesanan = DB::transaction(fn () => DaftarPantauan::tambah($pembeli, $lot));
            } catch (QueryException $e) {
                if (DaftarPantauan::cari($pembeli->getIdPengguna(), $lot->getIdLot())) {
                    $this->tolak('Anda sudah terdaftar pada lot ini.');
                }
                if ($coba === 3) {
                    throw $e;
                }
            }
        }

        // Surel bukti pemesanan (bila gagal, pemesanan tetap tersimpan)
        try {
            Mail::to($pembeli->getSurel(), $pembeli->getNamaLengkap())
                ->send(new PemesananBerhasilMail($pembeli->getNamaLengkap(), $this->ringkas($pemesanan)));
        } catch (\Throwable $e) {
            report($e);
        }

        return $pemesanan;
    }

    public function ringkas(DaftarPantauan $d): array
    {
        $d->loadMissing('lot');

        return [
            'id_pantauan'     => $d->getIdPantauan(),
            'kode_pemesanan'  => $d->getKodePemesanan(),
            'waktu_pemesanan' => $d->getWaktuDitambah()?->format('Y-m-d H:i'),
        ] + $this->ringkasLot($d->lot);
    }

    public function ringkasLot(LotLelang $lot): array
    {
        $lot->loadMissing(['tangkapan.jenisIkan', 'tangkapan.grade', 'tangkapan.kedatangan.kapal']);
        $t = $lot->tangkapan;
        $harga = (float) $lot->getHargaPembukaan();

        return [
            'id_lot'          => $lot->getIdLot(),
            'kode_lot'        => $lot->getKodeLot(),
            'nama_jenis'      => $t->jenisIkan->getNamaJenis(),
            'kode_grade'      => $t->grade->getKodeGrade(),
            'berat_aktual'    => $lot->getBeratAktual(),
            'harga_pembukaan' => $lot->getHargaPembukaan(),
            'nilai_lot'       => round($harga * (float) $lot->getBeratAktual()),
            'status_lot'      => $lot->getStatus()->value,
            'nama_kapal'      => $t->kedatangan->kapal->getNamaKapal(),
            'foto'            => $lot->foto()->first()?->urlPublik(),
        ];
    }

    /**
     * Lot harus ada di TPI pembeli, belum pernah dipesan pembeli ini, dan sesinya masih terbuka:
     * MENUNGGU_LELANG (sebelum operator membuka) atau DILELANG/DIPERPANJANG (sesi sedang aktif).
     * Sejak revisi ini pembeli boleh bergabung di tengah sesi yang masih berjalan.
     */
    private function lotBolehDipesan(Pengguna $pembeli, int $idLot): LotLelang
    {
        $lot = LotLelang::with('tangkapan.kedatangan')->find($idLot);

        if (! $lot || $lot->tangkapan->kedatangan->getIdTpi() !== $this->idTpi($pembeli)) {
            throw new HttpResponseException(response()->json(['message' => 'Lot tidak ditemukan.'], 404));
        }
        if (! $lot->bolehDiikutiLelang()) {
            $this->tolak("Lot {$lot->getKodeLot()} berstatus {$lot->getStatus()->value} sehingga tidak dapat diikuti lagi.");
        }
        $ada = DaftarPantauan::cari($pembeli->getIdPengguna(), $lot->getIdLot());
        if ($ada) {
            $this->tolak("Anda sudah terdaftar pada lot ini dengan kode {$ada->getKodePemesanan()}.");
        }

        return $lot;
    }

    private function idTpi(Pengguna $pembeli): int
    {
        return $pembeli->getIdTpi() ?? (int) Konfigurasi::nilai('id_tpi_aktif', 1);
    }

    private function tolak(string $pesan): never
    {
        throw ValidationException::withMessages(['id_lot' => $pesan]);
    }
}
