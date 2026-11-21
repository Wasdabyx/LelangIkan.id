<?php

namespace App\Services;

use App\Enums\StatusTransaksi;
use App\Models\KedatanganKapal;
use App\Models\PembayaranNelayan;
use App\Models\PencairanNelayan;
use App\Models\Pengguna;
use App\Models\TarifRetribusi;
use App\Models\TransaksiPenjualan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kelas kendali SettlementService - UC-08 Perhitungan Retribusi dan Pembayaran kepada Nelayan (A5).
 *
 * ALG-06 (perhitungan settlement):
 *   1. ambil seluruh transaksi LUNAS pada kedatangan;
 *   2. total bruto = jumlah subtotal lot terjual (FR-37);
 *   3. retribusi memakai tarif yang berlaku pada TANGGAL TRANSAKSI, bukan tarif terbaru (FR-38, ALG-05);
 *   4. netto = bruto - retribusi - potongan lain (FR-39);
 *   5. pencairan dicatat per cara bayar beserta nomor referensi (FR-40, FR-67).
 *
 * Pembayaran bertahap didukung: nilai netto tetap, jumlah cair bertambah sampai status LUNAS (FR-58).
 */
class SettlementService
{
    public function __construct(private AuditService $audit)
    {
    }

    public const CARA_CAIR = [
        PembayaranNelayan::TUNAI,
        PembayaranNelayan::TRANSFER,
        PembayaranNelayan::KARTU,
    ];

    /** UC-08 langkah 1: daftar kedatangan yang punya transaksi lunas.  */
    public function daftarKedatangan(Pengguna $kasir, ?string $tanggal = null): array
    {
        $query = KedatanganKapal::query()
            ->where('id_tpi', $kasir->getIdTpi())
            ->when($tanggal, fn ($q) => $q->whereDate('tanggal', $tanggal))
            ->with(['kapal.pemilik'])
            ->orderByDesc('tanggal')
            ->orderByDesc('id_kedatangan')
            ->limit(60);

        $hasil = [];
        foreach ($query->get() as $kedatangan) {
            $ringkas = $this->ringkasKedatangan($kedatangan);
            if ($ringkas['jumlah_lot_lunas'] === 0 && $ringkas['pembayaran'] === null) {
                continue;
            }
            $hasil[] = $ringkas;
        }

        return $hasil;
    }

    /** Ringkasan bruto/retribusi/netto untuk satu kedatangan tanpa menyimpan apa pun. */
    public function ringkasKedatangan(KedatanganKapal $kedatangan, float $potonganLain = 0, ?string $keterangan = null): array
    {
        $transaksi = $this->transaksiLunas($kedatangan->getIdKedatangan());
        $bruto = (float) $transaksi->sum(fn (TransaksiPenjualan $t) => (float) $t->getSubtotal());
        $retribusi = (float) $transaksi->sum(fn (TransaksiPenjualan $t) => (float) $t->getNilaiRetribusi());
        $netto = max(0, $bruto - $retribusi - $potonganLain);
        $bayar = PembayaranNelayan::cariUntukKedatangan($kedatangan->getIdKedatangan());
        $kapal = $kedatangan->kapal;

        return [
            'id_kedatangan'      => $kedatangan->getIdKedatangan(),
            'nomor_kedatangan'   => $kedatangan->getNomorKedatangan(),
            'tanggal'            => $kedatangan->getTanggal()->format('Y-m-d'),
            'nama_kapal'         => $kapal?->getNamaKapal(),
            'nama_pemilik'       => $kapal?->pemilik?->getNama(),
            'no_rekening'        => $kapal?->pemilik?->samarkanRekening(),
            'jumlah_lot_lunas'   => $transaksi->count(),
            'rincian_lot'        => $transaksi->map(fn (TransaksiPenjualan $t) => [
                'id_transaksi'       => $t->getIdTransaksi(),
                'kode_transaksi'     => $t->getKodeTransaksi(),
                'kode_lot'           => $t->lot?->getKodeLot(),
                'nama_jenis'         => $t->lot?->tangkapan?->jenisIkan?->getNamaJenis(),
                'kode_grade'         => $t->lot?->tangkapan?->grade?->getKodeGrade(),
                'berat_final'        => $t->getBeratFinal(),
                'harga_akhir_per_kg' => $t->getHargaAkhirPerKg(),
                'subtotal'           => $t->getSubtotal(),
                'nilai_retribusi'    => $t->getNilaiRetribusi(),
            ])->values()->all(),
            'total_bruto'        => round($bruto, 2),
            'potongan_retribusi' => round($retribusi, 2),
            'potongan_lain'      => round($potonganLain, 2),
            'keterangan_potongan' => $keterangan,
            'total_netto'        => round($netto, 2),
            'pembayaran'         => $bayar ? $this->rincianPembayaran($bayar) : null,
        ];
    }

    /**
     * UC-08 langkah 2-5: hitung lalu simpan (atau perbarui) ringkasan settlement.
     * Tarif yang dipakai adalah tarif yang menempel pada transaksi (sudah dikunci saat transaksi dibuat),
     * sehingga perubahan tarif belakangan tidak mengubah nilai historis (FR-38).
     */
    public function hitung(Pengguna $kasir, int $idKedatangan, float $potonganLain = 0, ?string $keterangan = null): PembayaranNelayan
    {
        $kedatangan = $this->kedatanganMilikTpi($kasir, $idKedatangan);
        $transaksi = $this->transaksiLunas($idKedatangan);

        if ($transaksi->isEmpty()) {
            throw ValidationException::withMessages([
                'id_kedatangan' => 'Belum ada transaksi lunas pada kedatangan ini. Settlement belum dapat diproses.',
            ]);
        }
        if ($potonganLain < 0) {
            throw ValidationException::withMessages(['potongan_lain' => 'Potongan lain tidak boleh negatif.']);
        }
        if ($potonganLain > 0 && trim((string) $keterangan) === '') {
            throw ValidationException::withMessages([
                'keterangan_potongan' => 'Keterangan wajib diisi bila ada potongan lain (FR-39).',
            ]);
        }

        $ringkas = $this->ringkasKedatangan($kedatangan, $potonganLain, $keterangan);
        if ($ringkas['total_netto'] <= 0) {
            throw ValidationException::withMessages([
                'potongan_lain' => 'Nilai bersih nol atau negatif. Periksa potongan yang dimasukkan.',
            ]);
        }
        if (TarifRetribusi::cariBerlaku($kedatangan->getTanggal()) === null
            && $transaksi->contains(fn (TransaksiPenjualan $t) => (float) $t->getNilaiRetribusi() <= 0)) {
            throw ValidationException::withMessages([
                'id_tarif' => 'Tarif retribusi untuk tanggal transaksi tidak ditemukan. Hubungi administrator.',
            ]);
        }

        return DB::transaction(function () use ($idKedatangan, $ringkas, $potonganLain, $keterangan) {
            $pembayaran = PembayaranNelayan::cariUntukKedatangan($idKedatangan);
            $baru = $pembayaran === null;

            if ($baru) {
                $pembayaran = new PembayaranNelayan([
                    'id_kedatangan'     => $idKedatangan,
                    'waktu_dibuat'      => now(),
                    'total_dibayar'     => 0,
                    'status'            => PembayaranNelayan::BELUM_DIBAYAR,
                ]);
            } elseif ((float) $pembayaran->total_dibayar > 0) {
                throw ValidationException::withMessages([
                    'id_kedatangan' => 'Settlement sudah dicairkan sebagian. Perubahan perhitungan harus lewat pembatalan resmi.',
                ]);
            }

            $pembayaran->total_bruto = $ringkas['total_bruto'];
            $pembayaran->potongan_retribusi = $ringkas['potongan_retribusi'];
            $pembayaran->potongan_lain = round($potonganLain, 2);
            $pembayaran->keterangan_potongan = $keterangan;
            $pembayaran->total_netto = $ringkas['total_netto'];
            $pembayaran->save();

            $this->audit->catat(null, $baru ? 'HITUNG_SETTLEMENT' : 'PERBARUI_SETTLEMENT', 'pembayaran_nelayan',
                $pembayaran->getIdBayarNelayan(), null, [
                    'id_kedatangan' => $idKedatangan,
                    'total_bruto'   => $pembayaran->total_bruto,
                    'total_netto'   => $pembayaran->total_netto,
                ]);

            return $pembayaran;
        });
    }

    /**
     * FR-40 dan FR-67: catat pencairan dana nelayan.
     * Transfer rekening dan kartu nelayan disimulasikan: nomor referensi dibuat otomatis bila kosong.
     */
    public function cairkan(Pengguna $kasir, int $idBayarNelayan, string $cara, ?float $nominal = null, ?string $referensi = null): PembayaranNelayan
    {
        if (! in_array($cara, self::CARA_CAIR, true)) {
            throw ValidationException::withMessages(['cara_pencairan' => 'Cara pencairan harus TUNAI, TRANSFER_REKENING, atau KARTU_NELAYAN.']);
        }

        return DB::transaction(function () use ($kasir, $idBayarNelayan, $cara, $nominal, $referensi) {
            $pembayaran = PembayaranNelayan::with('kedatangan.kapal.pemilik')->lockForUpdate()->find($idBayarNelayan);
            if (! $pembayaran) {
                throw ValidationException::withMessages(['id_bayar_nelayan' => 'Data pembayaran nelayan tidak ditemukan.']);
            }
            $this->kedatanganMilikTpi($kasir, $pembayaran->getIdKedatangan());

            $sisa = $pembayaran->sisa();
            if ($sisa <= 0.004) {
                throw ValidationException::withMessages(['id_bayar_nelayan' => 'Pembayaran nelayan ini sudah lunas.']);
            }

            $nominal = $nominal === null ? $sisa : round($nominal, 2);
            if ($nominal <= 0) {
                throw ValidationException::withMessages(['nominal' => 'Nominal pencairan harus lebih dari nol.']);
            }
            if ($nominal > $sisa + 0.004) {
                throw ValidationException::withMessages([
                    'nominal' => 'Nominal pencairan melebihi sisa kewajiban (sisa Rp' . number_format($sisa, 0, ',', '.') . ').',
                ]);
            }

            // Simulasi gerbang pencairan: kartu/transfer menghasilkan nomor referensi.
            $pemilik = $pembayaran->kedatangan?->kapal?->pemilik;
            $referensi = $this->buatReferensi($cara, $referensi, $pemilik);

            PencairanNelayan::create([
                'id_bayar_nelayan' => $pembayaran->getIdBayarNelayan(),
                'nominal'          => $nominal,
                'cara_pencairan'   => $cara,
                'nomor_referensi'  => $referensi,
                'waktu'            => now(),
                'id_kasir'         => $kasir->getIdPengguna(),
                'keterangan'       => $cara === PembayaranNelayan::TUNAI ? 'Pencairan tunai di kasir TPI' : 'Pencairan simulasi versi 1.1',
            ]);

            $pembayaran->cara_pencairan = $cara;
            $pembayaran->nomor_referensi_bank = $referensi;
            $pembayaran->waktu_bayar = now();
            $pembayaran->id_kasir = $kasir->getIdPengguna();
            $pembayaran->save();
            $pembayaran->segarkanStatus();

            $this->siarkanSettlementDibayar($pembayaran);

            $this->audit->catat($kasir, 'CAIRKAN_NELAYAN', 'pembayaran_nelayan',
                $pembayaran->getIdBayarNelayan(), null, [
                    'nominal'         => $nominal,
                    'cara_pencairan'  => $cara,
                    'nomor_referensi' => $referensi,
                    'status'          => $pembayaran->getStatus(),
                ]);

            return $pembayaran->fresh(['kedatangan.kapal.pemilik', 'daftarPencairan']);
        });
    }

    /** Rincian satu pembayaran nelayan untuk tampilan/tangkapan API. */
    public function rincianPembayaran(PembayaranNelayan $pembayaran, bool $denganLot = true): array
    {
        $pembayaran->loadMissing(['kedatangan.kapal.pemilik', 'daftarPencairan.kasir', 'kasir']);
        $kedatangan = $pembayaran->kedatangan;

        return [
            'id_bayar_nelayan'    => $pembayaran->getIdBayarNelayan(),
            'kode_bukti'          => $pembayaran->getKodeBukti(),
            'id_kedatangan'       => $pembayaran->getIdKedatangan(),
            'nomor_kedatangan'    => $kedatangan?->getNomorKedatangan(),
            'nama_kapal'          => $kedatangan?->kapal?->getNamaKapal(),
            'nama_pemilik'        => $kedatangan?->kapal?->pemilik?->getNama(),
            'no_rekening'         => $kedatangan?->kapal?->pemilik?->samarkanRekening(),
            'total_bruto'         => $pembayaran->getTotalBruto(),
            'potongan_retribusi'  => $pembayaran->getPotonganRetribusi(),
            'potongan_lain'       => $pembayaran->getPotonganLain(),
            'keterangan_potongan' => $pembayaran->keterangan_potongan,
            'total_netto'         => $pembayaran->getTotalNetto(),
            'total_dibayar'       => number_format((float) $pembayaran->total_dibayar, 2, '.', ''),
            'sisa'                => number_format($pembayaran->sisa(), 2, '.', ''),
            'status'              => $pembayaran->getStatus(),
            'cara_pencairan'      => $pembayaran->getCaraPencairan(),
            'nomor_referensi'     => $pembayaran->nomor_referensi_bank,
            'waktu_bayar'         => $pembayaran->getWaktuBayar()?->format('Y-m-d H:i'),
            'nama_kasir'          => $pembayaran->kasir?->getNamaLengkap(),
            'rincian_lot'         => $denganLot
                ? collect($this->transaksiLunas($pembayaran->getIdKedatangan()))
                    ->map(fn (TransaksiPenjualan $t) => [
                        'kode_lot'           => $t->lot?->getKodeLot(),
                        'berat_final'        => $t->getBeratFinal(),
                        'harga_akhir_per_kg' => $t->getHargaAkhirPerKg(),
                        'subtotal'           => $t->getSubtotal(),
                        'nilai_retribusi'    => $t->getNilaiRetribusi(),
                    ])->values()->all()
                : [],
            'pencairan'           => $pembayaran->daftarPencairan->map(fn (PencairanNelayan $p) => [
                'id_pencairan'    => (int) $p->id_pencairan,
                'nominal'         => (string) $p->nominal,
                'cara_pencairan'  => $p->cara_pencairan,
                'nomor_referensi' => $p->nomor_referensi,
                'waktu'           => $p->waktu?->format('Y-m-d H:i'),
                'nama_kasir'      => $p->kasir?->getNamaLengkap(),
            ])->all(),
        ];
    }

    /**
     * Tabel IV.7 SETTLEMENT_DIBAYAR: beri tahu portal nelayan terkait (kanal privat pemilik kapal).
     * Kegagalan WebSocket diabaikan agar pencairan dana tidak pernah gagal karena siaran.
     */
    private function siarkanSettlementDibayar(PembayaranNelayan $pembayaran): void
    {
        $kedatangan = $pembayaran->kedatangan()->with('kapal')->first();
        $idPemilik = $kedatangan?->kapal?->getIdPemilik();
        if (! $idPemilik) {
            return;
        }

        try {
            event(new \App\Events\PeristiwaLelang((int) $kedatangan->getIdTpi(),
                \App\Events\PeristiwaLelang::SETTLEMENT_DIBAYAR,
                [
                    'id_kedatangan'  => $pembayaran->getIdKedatangan(),
                    'id_bayar_nelayan' => $pembayaran->getIdBayarNelayan(),
                    'total_netto'    => (float) $pembayaran->getTotalNetto(),
                    'total_dibayar'  => (float) $pembayaran->total_dibayar,
                    'status'         => $pembayaran->getStatus(),
                    'waktu_bayar'    => now()->format('Y-m-d H:i:s'),
                ],
                'nelayan.' . $idPemilik));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal menyiarkan SETTLEMENT_DIBAYAR.', [
                'id_bayar_nelayan' => $pembayaran->getIdBayarNelayan(),
                'galat'            => $e->getMessage(),
            ]);
        }
    }

    // ---------------- internal ----------------

    private function transaksiLunas(int $idKedatangan)
    {
        return TransaksiPenjualan::query()
            ->with(['lot.tangkapan.jenisIkan', 'lot.tangkapan.grade'])
            ->where('status', StatusTransaksi::LUNAS->value)
            ->whereHas('lot.tangkapan', fn ($q) => $q->where('id_kedatangan', $idKedatangan))
            ->orderBy('id_transaksi')
            ->get();
    }

    private function kedatanganMilikTpi(Pengguna $kasir, int $idKedatangan): KedatanganKapal
    {
        $kedatangan = KedatanganKapal::find($idKedatangan);
        if (! $kedatangan) {
            throw ValidationException::withMessages(['id_kedatangan' => 'Kedatangan kapal tidak ditemukan.']);
        }
        if ($kasir->getIdTpi() && $kedatangan->getIdTpi() !== $kasir->getIdTpi()) {
            throw ValidationException::withMessages(['id_kedatangan' => 'Kedatangan ini bukan milik TPI Anda.']);
        }

        return $kedatangan;
    }

    /** FR-67: nomor referensi pencairan; transfer/kartu disimulasikan. */
    private function buatReferensi(string $cara, ?string $referensi, $pemilik): ?string
    {
        if ($cara === PembayaranNelayan::TUNAI) {
            return $referensi;
        }
        if ($referensi !== null && trim($referensi) !== '') {
            return Str::limit(trim($referensi), 40, '');
        }

        return $cara === PembayaranNelayan::TRANSFER
            ? 'TRF-' . now()->format('ymd') . '-' . strtoupper(Str::random(6))
            : 'KRT-' . now()->format('ymd') . '-' . strtoupper(Str::random(6));
    }
}
