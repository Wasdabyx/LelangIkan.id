<?php

namespace App\Services;

use App\Enums\KanalPenawaran;
use App\Models\DaftarPantauan;
use App\Models\Konfigurasi;
use App\Models\Penawaran;
use App\Models\Pengguna;
use App\Models\SesiLelang;
use App\Support\Tampilan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * UC-04 dari sisi TEMPAT (FR-63): petugas operator mencatat penawaran pembeli yang
 * mengangkat tangan di ruang pelelangan, dan administrator dapat mematikan kanal
 * jarak jauh (BT-03) tanpa mengganggu lelang di tempat.
 *
 * Semua aturan lelang (harga minimum, daya beli, perpanjangan, penyiaran peristiwa)
 * tetap dijalankan oleh LelangService yang sama dengan aplikasi mobile — tidak ada
 * aturan kedua yang bisa menyimpang.
 */
class TerminalLelangService
{
    public function __construct(private LelangService $lelang)
    {
    }

    /** Apakah kanal jarak jauh (aplikasi mobile) sedang dibuka administrator? */
    public function jarakJauhAktif(): bool
    {
        return (string) Konfigurasi::nilai('lelang_jarak_jauh_aktif', '1') === '1';
    }

    /**
     * Peserta sesi: pembeli yang sudah memesan lot ini pada TPI tersebut, beserta
     * status tawarannya. Dipakai operator untuk memilih siapa yang menawar di tempat.
     *
     * @return array<int, array<string, mixed>>
     */
    public function peserta(Pengguna $operator, SesiLelang $sesi): array
    {
        $this->pastikanSesiTpi($operator, $sesi);

        $pantauan = DaftarPantauan::with('pembeli')
            ->where('id_lot', $sesi->getIdLot())
            ->orderBy('id_pantauan')
            ->get();

        $tertinggi = $sesi->tertinggi();

        return $pantauan->map(function (DaftarPantauan $p) use ($sesi, $tertinggi) {
            $tawaranSendiri = Penawaran::where('id_sesi', $sesi->getIdSesi())
                ->where('id_pembeli', $p->getIdPembeli())
                ->orderByDesc('harga_per_kg')
                ->first(['id_penawaran', 'harga_per_kg', 'kanal', 'status']);

            $penawarTertinggi = $tertinggi && $tertinggi->getIdPembeli() === $p->getIdPembeli();

            return [
                'id_pembeli'        => $p->getIdPembeli(),
                'kode_pemesanan'    => $p->getKodePemesanan(),
                'nama_lengkap'      => $p->pembeli?->getNamaLengkap() ?? '-',
                'nama_pengguna'     => $p->pembeli?->getNamaPengguna() ?? '-',
                'harga_terakhir'    => $tawaranSendiri ? (float) $tawaranSendiri->harga_per_kg : null,
                'kanal_terakhir'    => $tawaranSendiri?->kanal,
                'tertinggi'         => $penawarTertinggi,
            ];
        })->all();
    }

    /**
     * Catat penawaran DI_TEMPAT atas nama pembeli yang dipilih operator.
     * Nominal diketik operator dalam RUPIAH PER KG, sama seperti di aplikasi mobile.
     *
     * @param  array<string, mixed>  $data
     */
    public function catatDiTempat(Pengguna $operator, SesiLelang $sesi, array $data, Request $request): array
    {
        $this->pastikanSesiTpi($operator, $sesi);

        $data = validator($data, [
            'id_pembeli'  => ['required', 'integer', Rule::exists('pengguna', 'id_pengguna')],
            'harga_per_kg' => ['required', 'integer', 'between:100,99999999'],
        ], [
            'id_pembeli.required'   => 'Pilih pembeli yang menawar.',
            'harga_per_kg.required' => 'Harga per kg wajib diisi.',
            'harga_per_kg.integer'  => 'Harga per kg harus angka rupiah tanpa titik atau koma.',
            'harga_per_kg.between'  => 'Harga tawaran Rp100 sampai Rp99.999.999 per kg.',
        ])->validate();

        $pembeli = Pengguna::findOrFail($data['id_pembeli']);

        [$penawaran, $duplikat] = $this->lelang->ajukanTawaran(
            $pembeli,
            $sesi->getIdSesi(),
            (float) $data['harga_per_kg'],
            (string) \Illuminate\Support\Str::uuid(),
            $request->ip(),
            KanalPenawaran::DI_TEMPAT,
        );

        return [
            'penawaran' => $penawaran,
            'duplikat'  => $duplikat,
            'pesan'     => 'Tawaran di tempat tercatat untuk ' . $pembeli->getNamaLengkap()
                . ' pada ' . Tampilan::rupiah((float) $penawaran->harga_per_kg) . '/kg. '
                . 'Papan lelang dan aplikasi pembeli lain sudah menerima pembaruannya.',
        ];
    }

    /** Ubah saklar kanal jarak jauh (dipakai dasbor administrator, FR-63 + BT-03). */
    public function ubahJarakJauh(Pengguna $admin, bool $aktif): void
    {
        $baris = Konfigurasi::where('kunci', 'lelang_jarak_jauh_aktif')->first();
        if (! $baris) {
            throw ValidationException::withMessages([
                'lelang_jarak_jauh_aktif' => 'Konfigurasi belum tersedia. Jalankan php artisan migrate.',
            ]);
        }
        $baris->ubahNilai($aktif ? '1' : '0', $admin->getIdPengguna());
    }

    private function pastikanSesiTpi(Pengguna $operator, SesiLelang $sesi): void
    {
        $sesi->loadMissing('lot.tangkapan.kedatangan');
        if ((int) $sesi->lot?->tangkapan?->kedatangan?->getIdTpi() !== $this->lelang->idTpi($operator)) {
            abort(404, 'Sesi lelang tidak ditemukan pada TPI Anda.');
        }
    }
}
