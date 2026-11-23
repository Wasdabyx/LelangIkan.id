<?php

namespace App\Services;

use App\Models\KedatanganKapal;
use App\Models\PembayaranNelayan;
use App\Models\PemilikKapal;
use App\Models\TautanAksesNelayan;
use App\Models\TransaksiPenjualan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kelas kendali PortalNelayanService - UC-13 (A8, portal baca-saja).
 *
 * FR-56 rekap hasil lelang per kapal pada rentang tanggal
 * FR-57 rincian per lot: berat final, harga akhir, subtotal, retribusi, potongan lain, nilai bersih
 * FR-58 status pembayaran (belum dibayar, dibayar sebagian, lunas) beserta tanggal dan nomor bukti
 * FR-59 akses memakai tautan bertoken + verifikasi tambahan, maksimum 72 jam
 * FR-60 bukti pembayaran dapat diunduh sebagai PDF
 */
class PortalNelayanService
{
    public function __construct(private AuditService $audit)
    {
    }

    /** FR-59: terbitkan tautan akses untuk seorang pemilik kapal. */
    public function terbitkanTautan(PemilikKapal $pemilik, $penerbit, ?int $jam = null): array
    {
        [$tautan, $kode] = TautanAksesNelayan::terbitkan($pemilik, $penerbit, $jam);

        $this->audit->catat($penerbit, 'TERBITKAN_TAUTAN_NELAYAN', 'tautan_akses_nelayan',
            $tautan->getIdTautan(), null, [
                'id_pemilik'  => $pemilik->getIdPemilik(),
                'kedaluwarsa' => $tautan->kedaluwarsa->toDateTimeString(),
            ]);

        return [
            'id_tautan'       => $tautan->getIdTautan(),
            'token'           => $tautan->getToken(),
            'kode_verifikasi' => $kode,                 // hanya ditampilkan sekali
            'kedaluwarsa'     => $tautan->kedaluwarsa->format('Y-m-d H:i'),
            'umur_jam'        => $jam ?? TautanAksesNelayan::UMUR_MAKS_JAM,
            'nama_pemilik'    => $pemilik->getNama(),
        ];
    }

    /**
     * FR-59: buka tautan. Token + 4 digit verifikasi wajib benar dan tautan belum kedaluwarsa.
     * Setiap pemakaian dihitung (jumlah_akses) untuk penelusuran.
     */
    public function buka(string $token, string $kodeVerifikasi): TautanAksesNelayan
    {
        $tautan = TautanAksesNelayan::cariDariToken(trim($token));
        if (! $tautan) {
            throw ValidationException::withMessages(['token' => 'Tautan akses tidak dikenali.']);
        }
        if (! $tautan->masihBerlaku()) {
            throw ValidationException::withMessages(['token' => 'Tautan akses sudah kedaluwarsa. Minta kasir menerbitkan tautan baru.']);
        }
        if (! $tautan->verifikasiEmpatDigit($kodeVerifikasi)) {
            throw ValidationException::withMessages(['kode_verifikasi' => 'Kode verifikasi tidak sesuai.']);
        }
        // FR-59: tautan sekali pakai. Kode yang salah tidak menghanguskan tautan,
        // tetapi pembukaan yang berhasil langsung menandainya terpakai.
        if ($tautan->sudahDipakai()) {
            throw ValidationException::withMessages([
                'token' => 'Tautan akses ini sudah dipakai (sekali pakai). Minta kasir menerbitkan tautan baru.',
            ]);
        }

        $tautan->tandaiDipakai();
        $tautan->buatSesi();
        $this->audit->catat(null, 'BUKA_PORTAL_NELAYAN', 'tautan_akses_nelayan', $tautan->getIdTautan(),
            null, [
                'jumlah_akses'    => (int) $tautan->jumlah_akses,
                'dipakai_pada'    => $tautan->dipakai_pada?->toDateTimeString(),
                'sesi_kedaluwarsa' => $tautan->sesi_kedaluwarsa?->toDateTimeString(),
            ]);

        return $tautan;
    }

    /**
     * FR-56 dan FR-57: rekap seluruh lelang per kapal milik nelayan pada rentang tanggal,
     * lengkap dengan rincian per lot dan status pembayaran (FR-58).
     */
    /**
     * FR-59: lanjutkan akses portal memakai sesi (30 menit) yang terbit saat tautan dibuka.
     * Dipakai halaman rekap dan unduhan bukti supaya tautan tetap benar-benar sekali pakai.
     */
    public function bukaSesi(string $sesi): TautanAksesNelayan
    {
        $tautan = TautanAksesNelayan::cariDariSesi($sesi);
        if (! $tautan) {
            throw ValidationException::withMessages([
                'sesi' => 'Sesi portal tidak dikenali. Buka lagi tautan dari kasir.',
            ]);
        }
        if (! $tautan->sesiMasihBerlaku()) {
            throw ValidationException::withMessages([
                'sesi' => 'Sesi portal sudah berakhir (30 menit). Minta kasir menerbitkan tautan baru.',
            ]);
        }

        return $tautan;
    }

    public function rekap(PemilikKapal $pemilik, ?string $dari = null, ?string $sampai = null): array
    {
        $dari ??= now()->subDays(30)->toDateString();
        $sampai ??= now()->toDateString();

        $kedatangan = KedatanganKapal::query()
            ->whereIn('id_kapal', $pemilik->daftarKapal()->pluck('id_kapal'))
            ->whereBetween('tanggal', [$dari, $sampai])
            ->with(['kapal'])
            ->orderByDesc('tanggal')
            ->orderByDesc('id_kedatangan')
            ->get();

        $baris = [];
        $totalBruto = 0.0;
        $totalNetto = 0.0;
        $totalDibayar = 0.0;

        foreach ($kedatangan as $k) {
            $lot = $this->lotTerjual($k->getIdKedatangan());
            $bayar = PembayaranNelayan::cariUntukKedatangan($k->getIdKedatangan());

            $bruto = (float) $lot->sum(fn (TransaksiPenjualan $t) => (float) $t->getSubtotal());
            // Settlement hanya menghitung lot yang pembelinya sudah lunas; nilai ini dipakai
            // agar angka bruto pada rekap dapat direkonsiliasi dengan netto yang dibayarkan.
            $brutoLunas = (float) $lot->filter(fn (TransaksiPenjualan $t) => $t->status === 'LUNAS')
                ->sum(fn (TransaksiPenjualan $t) => (float) $t->getSubtotal());
            $retribusi = (float) $lot->sum(fn (TransaksiPenjualan $t) => (float) $t->getNilaiRetribusi());
            $potonganLain = (float) ($bayar?->getPotonganLain() ?? 0);
            $netto = $bayar ? (float) $bayar->getTotalNetto() : max(0, $bruto - $retribusi - $potonganLain);
            $dibayar = (float) ($bayar?->total_dibayar ?? 0);

            if ($lot->isEmpty() && $bayar === null) {
                continue;
            }

            $baris[] = [
                'id_kedatangan'      => $k->getIdKedatangan(),
                'nomor_kedatangan'   => $k->getNomorKedatangan(),
                'tanggal'            => $k->getTanggal()->format('Y-m-d'),
                'nama_kapal'         => $k->kapal?->getNamaKapal(),
                'jumlah_lot'         => $lot->count(),
                'total_bruto'        => round($bruto, 2),
                'total_bruto_lunas'  => round($brutoLunas, 2),
                'potongan_retribusi' => round($retribusi, 2),
                'potongan_lain'      => round($potonganLain, 2),
                'keterangan_potongan' => $bayar?->keterangan_potongan,
                'total_netto'        => round($netto, 2),
                'total_dibayar'      => round($dibayar, 2),
                'sisa'               => round(max(0, $netto - $dibayar), 2),
                'status_bayar'       => $bayar?->getStatus() ?? PembayaranNelayan::BELUM_DIBAYAR,
                'kode_bukti'         => $bayar?->getKodeBukti(),
                'id_bayar_nelayan'   => $bayar?->getIdBayarNelayan(),
                'tanggal_bayar'      => $bayar?->getWaktuBayar()?->format('Y-m-d H:i'),
                'cara_pencairan'     => $bayar?->getCaraPencairan(),
                'nomor_referensi'    => $bayar?->nomor_referensi_bank,
                'rincian_lot'        => $lot->map(fn (TransaksiPenjualan $t) => [
                    'kode_lot'           => $t->lot?->getKodeLot(),
                    'nama_jenis'         => $t->lot?->tangkapan?->jenisIkan?->getNamaJenis(),
                    'kode_grade'         => $t->lot?->tangkapan?->grade?->getKodeGrade(),
                    'berat_final'        => $t->getBeratFinal(),
                    'harga_akhir_per_kg' => $t->getHargaAkhirPerKg(),
                    'subtotal'           => $t->getSubtotal(),
                    'nilai_retribusi'    => $t->getNilaiRetribusi(),
                    'status_transaksi'   => $t->status,
                ])->values()->all(),
            ];

            $totalBruto += $bruto;
            $totalNetto += $netto;
            $totalDibayar += $dibayar;
        }

        return [
            'pemilik'        => [
                'id_pemilik'   => $pemilik->getIdPemilik(),
                'nama'         => $pemilik->getNama(),
                'no_rekening'  => $pemilik->samarkanRekening(),
            ],
            'periode'        => ['dari' => $dari, 'sampai' => $sampai],
            'ringkasan'      => [
                'jumlah_kedatangan' => count($baris),
                'total_bruto'       => round($totalBruto, 2),
                'catatan'           => 'Nilai bersih hanya dihitung dari lot yang pembelinya sudah lunas; sisa tagihan pembeli belum menjadi kewajiban bayar TPI.',
                'total_netto'       => round($totalNetto, 2),
                'total_dibayar'     => round($totalDibayar, 2),
                'belum_dibayar'     => round(max(0, $totalNetto - $totalDibayar), 2),
            ],
            'kedatangan'     => $baris,
        ];
    }

    private function lotTerjual(int $idKedatangan)
    {
        return TransaksiPenjualan::query()
            ->with(['lot.tangkapan.jenisIkan', 'lot.tangkapan.grade'])
            ->whereIn('status', ['LUNAS', 'DIBAYAR_SEBAGIAN', 'BELUM_DIBAYAR'])
            ->whereHas('lot.tangkapan', fn ($q) => $q->where('id_kedatangan', $idKedatangan))
            ->orderBy('id_transaksi')
            ->get();
    }
}
