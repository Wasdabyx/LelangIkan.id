<?php

namespace App\Services;

use App\Enums\MetodeBayar;
use App\Enums\StatusLot;
use App\Enums\StatusTransaksi;
use App\Models\Konfigurasi;
use App\Models\LotLelang;
use App\Models\MutasiSaldo;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\SaldoPembeli;
use App\Models\Tpi;
use App\Models\TransaksiPenjualan;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kelas kendali PembayaranService - UC-06 Pembayaran dan Penyelesaian Transaksi (Kasir TPI, A5).
 * Pembayaran daring masih simulasi dan belum memakai payment gateway.
 */
class PembayaranService
{
    public function __construct(private DepositService $deposit, private AuditService $audit)
    {
    }

    /** Tagihan yang belum lunas di TPI kasir (terbaru dulu). */
    public function tagihanTerbuka(Pengguna $kasir): array
    {
        return $this->kueriTpi($kasir)
            ->whereIn('status', [StatusTransaksi::BELUM_DIBAYAR->value, StatusTransaksi::DIBAYAR_SEBAGIAN->value])
            ->orderByDesc('id_transaksi')
            ->limit(50)
            ->get()
            ->map(fn (TransaksiPenjualan $t) => $this->rincian($t, false))
            ->all();
    }

    /** Transaksi yang lunas hari ini (riwayat kasir). */
    public function lunasHariIni(Pengguna $kasir): array
    {
        return $this->kueriTpi($kasir)
            ->where('status', StatusTransaksi::LUNAS->value)
            ->whereHas('daftarPembayaran', fn ($q) => $q->whereDate('waktu_bayar', today()))
            ->orderByDesc('id_transaksi')
            ->get()
            ->map(fn (TransaksiPenjualan $t) => $this->rincian($t, false))
            ->all();
    }

    /** UC-06 langkah 1: cari dari nomor transaksi (TRX-000001) atau isi QR nota. */
    public function cari(Pengguna $kasir, string $kode): TransaksiPenjualan
    {
        $kode = trim($kode);
        if (str_starts_with($kode, 'LELANGIKAN|')) {
            $kode = explode('|', $kode)[1] ?? '';
        }
        $id = TransaksiPenjualan::idDariKode($kode);
        $trx = $id ? $this->kueriTpi($kasir)->whereKey($id)->first() : null;
        if (! $trx) {
            throw new HttpResponseException(response()->json([
                'message' => "Transaksi {$kode} tidak ditemukan. Periksa kembali nomor transaksi.",
            ], 404));
        }

        return $trx;
    }

    /** UC-06 langkah 3-7 (FR-29, FR-30). */
    public function bayar(Pengguna $kasir, int $idTransaksi, MetodeBayar $metode, float $nominal, ?string $referensi): TransaksiPenjualan
    {
        $this->batalkanKedaluwarsa($idTransaksi);

        return DB::transaction(function () use ($kasir, $idTransaksi, $metode, $nominal, $referensi) {
            $trx = $this->kueriTpi($kasir)->whereKey($idTransaksi)->lockForUpdate()->first();
            if (! $trx) {
                throw new HttpResponseException(response()->json(['message' => 'Transaksi tidak ditemukan.'], 404));
            }
            if ($trx->getStatus() === StatusTransaksi::LUNAS) {
                $this->tolak('nominal', "Transaksi {$trx->getKodeTransaksi()} sudah lunas.");
            }
            if ($trx->getStatus() === StatusTransaksi::DIBATALKAN) {
                $this->tolak('nominal', "Transaksi {$trx->getKodeTransaksi()} sudah dibatalkan.");
            }
            if ($this->pembayaranLewatBatas($trx)) {
                $this->tolak('nominal', 'Batas pembayaran telah lewat. Tagihan akan dibatalkan.');
            }

            // FR-30: nominal > 0 dan tidak melebihi sisa tagihan
            $sisa = $trx->sisaTagihan();
            if (! Pembayaran::validasiNominal($nominal, $sisa)) {
                $this->tolak('nominal', $nominal <= 0
                    ? 'Nominal pembayaran harus lebih dari Rp0.'
                    : 'Nominal melebihi sisa tagihan ' . $this->rp($sisa) . '.');
            }

            if ($metode === MetodeBayar::SALDO) {
                $this->deposit->potongSaldo($kasir, $trx->getIdPembeli(), $nominal, 'Pembayaran ' . $trx->getKodeTransaksi());
                $referensi = null;
            } elseif ($metode === MetodeBayar::QRIS) {
                $referensi = 'QRIS-' . now()->format('ymdHis') . '-' . strtoupper(Str::random(4));   // simulasi
            } elseif ($metode === MetodeBayar::TUNAI) {
                $referensi = null;
            } elseif (Pembayaran::where('nomor_referensi', $referensi)->exists()) {
                $this->tolak('nomor_referensi', 'Nomor referensi transfer ini sudah pernah dicatat.');
            }

            Pembayaran::create([
                'id_transaksi'    => $trx->getIdTransaksi(),
                'metode'          => $metode->value,
                'nominal'         => (string) $nominal,
                'waktu_bayar'     => now(),
                'id_kasir'        => $kasir->getIdPengguna(),
                'nomor_referensi' => $referensi,
            ]);
            $trx->perbaruiStatusBayar();

            $this->audit->catat($kasir, 'BAYAR_TRANSAKSI', 'transaksi_penjualan', $trx->getIdTransaksi(), null, [
                'metode' => $metode->value, 'nominal' => $nominal, 'status' => $trx->status,
                'nomor_referensi' => $referensi,
            ]);

            return $trx->fresh();
        });
    }

    public function bayarDenganSaldo(Pengguna $pembeli, int $idTransaksi): TransaksiPenjualan
    {
        $this->batalkanKedaluwarsa($idTransaksi);

        return DB::transaction(function () use ($pembeli, $idTransaksi) {
            $trx = TransaksiPenjualan::where('id_pembeli', $pembeli->getIdPengguna())
                ->whereKey($idTransaksi)->lockForUpdate()->first();
            if (! $trx) {
                abort(404, 'Tagihan tidak ditemukan.');
            }
            if ($trx->getStatus() === StatusTransaksi::DIBATALKAN) {
                $this->tolak('pembayaran', 'Batas pembayaran telah lewat. Hubungi petugas TPI.');
            }
            if ($trx->getStatus() === StatusTransaksi::LUNAS) {
                $this->tolak('pembayaran', 'Tagihan ini sudah lunas.');
            }
            if ($this->pembayaranLewatBatas($trx)) {
                $this->tolak('pembayaran', 'Batas pembayaran telah lewat. Tagihan akan dibatalkan.');
            }

            $nominal = $trx->sisaTagihan();
            $saldo = SaldoPembeli::where('id_pembeli', $pembeli->getIdPengguna())->lockForUpdate()->first();
            if (! $saldo || $saldo->status !== 'AKTIF' || (float) $saldo->saldo < $nominal) {
                $this->tolak('pembayaran', 'Saldo aktif tidak cukup untuk melunasi tagihan ini.');
            }

            $saldo->saldo = (string) ((float) $saldo->saldo - $nominal);
            $saldo->save();
            MutasiSaldo::create([
                'id_saldo' => $saldo->id_saldo,
                'jenis' => 'PEMBAYARAN',
                'arah' => 'DEBIT',
                'nominal' => (string) $nominal,
                'saldo_akhir' => (string) $saldo->saldo,
                'status' => 'BERHASIL',
                'id_kasir' => null,
                'keterangan' => 'Pembayaran mandiri ' . $trx->getKodeTransaksi(),
            ]);
            Pembayaran::create([
                'id_transaksi' => $trx->getIdTransaksi(),
                'metode' => MetodeBayar::SALDO->value,
                'nominal' => (string) $nominal,
                'waktu_bayar' => now(),
                'id_kasir' => null,
            ]);
            $trx->perbaruiStatusBayar();

            return $trx->fresh();
        });
    }

    public function jadwalkanPengambilan(Pengguna $pembeli, int $idTransaksi, string $tanggal): TransaksiPenjualan
    {
        $trx = TransaksiPenjualan::where('id_pembeli', $pembeli->getIdPengguna())->find($idTransaksi);
        if (! $trx) {
            abort(404, 'Tagihan tidak ditemukan.');
        }
        if ($trx->getStatus() !== StatusTransaksi::LUNAS) {
            $this->tolak('tanggal_pengambilan', 'Jadwal pengambilan dapat dipilih setelah tagihan lunas.');
        }

        $pilihan = Carbon::createFromFormat('Y-m-d', $tanggal)->startOfDay();
        if ($pilihan->lt(today()) || $pilihan->gt(today()->addDay())) {
            $this->tolak('tanggal_pengambilan', 'Pengambilan hanya dapat dijadwalkan hari ini atau besok.');
        }

        $trx->tanggal_pengambilan = $pilihan->toDateString();
        $trx->save();

        return $trx->fresh();
    }

    /** FR-31: isi QR nota = LELANGIKAN|TRX-000001|tanda-tangan (HMAC dengan APP_KEY). */
    public function isiQrNota(TransaksiPenjualan $t): string
    {
        return 'LELANGIKAN|' . $t->getKodeTransaksi() . '|' . $this->tandaTangan($t);
    }

    /** Periksa keaslian nota dari isi QR-nya. */
    public function verifikasiNota(string $isiQr): array
    {
        $bagian = explode('|', trim($isiQr));
        $id = count($bagian) === 3 && $bagian[0] === 'LELANGIKAN' ? TransaksiPenjualan::idDariKode($bagian[1]) : null;
        $trx = $id ? TransaksiPenjualan::find($id) : null;

        if (! $trx || ! hash_equals($this->tandaTangan($trx), $bagian[2] ?? '')) {
            return ['asli' => false, 'pesan' => 'Nota TIDAK DIKENALI. Kode QR bukan nota resmi LelangIkan.id atau sudah diubah.'];
        }

        return ['asli' => true, 'pesan' => 'Nota asli dan tercatat di sistem.'] + $this->rincian($trx, false);
    }

    /** FR-28: rincian tagihan. */
    public function rincian(TransaksiPenjualan $t, bool $lengkap = true): array
    {
        $this->batalkanKedaluwarsa($t->getIdTransaksi());
        $t->refresh();
        $t->loadMissing(['lot.tangkapan.jenisIkan', 'lot.tangkapan.grade', 'lot.tangkapan.kedatangan.kapal', 'pembeli', 'tarif']);
        $tg = $t->lot->tangkapan;
        $dibayar = $t->totalDibayar();

        $data = [
            'id_transaksi'       => $t->getIdTransaksi(),
            'kode_transaksi'     => $t->getKodeTransaksi(),
            'kode_lot'           => $t->lot->getKodeLot(),
            'nama_jenis'         => $tg->jenisIkan->getNamaJenis(),
            'kode_grade'         => $tg->grade->getKodeGrade(),
            'nama_kapal'         => $tg->kedatangan->kapal->getNamaKapal(),
            'nama_pembeli'       => $t->pembeli->getNamaLengkap(),
            'berat_final'        => $t->getBeratFinal(),
            'harga_akhir_per_kg' => $t->getHargaAkhirPerKg(),
            'subtotal'           => $t->getSubtotal(),
            'persen_retribusi'   => $t->tarif->getPersentase(),
            'nilai_retribusi'    => $t->getNilaiRetribusi(),
            'biaya_layanan'      => $t->getBiayaLayanan(),
            'total_tagihan'      => $t->getTotalTagihan(),
            'total_dibayar'      => round($dibayar, 2),
            'sisa_tagihan'       => $t->sisaTagihan(),
            'status'             => $t->getStatus()->value,
            'waktu_transaksi'    => $t->getWaktuTransaksi()->format('Y-m-d H:i'),
            'batas_bayar'        => $t->batas_bayar?->format('Y-m-d H:i:s'),
            'tanggal_pengambilan' => $t->tanggal_pengambilan?->format('Y-m-d'),
            'saldo_tersedia'     => (float) (SaldoPembeli::where('id_pembeli', $t->getIdPembeli())->value('saldo') ?? 0),
        ];

        if ($lengkap) {
            $data['pembayaran'] = $t->daftarPembayaran()->with('kasir')->get()->map(fn (Pembayaran $p) => [
                'metode'          => $p->getMetode()->value,
                'nominal'         => $p->getNominal(),
                'waktu_bayar'     => $p->getWaktuBayar()->format('Y-m-d H:i'),
                'nomor_referensi' => $p->getNomorReferensi(),
                'nama_kasir'      => $p->kasir?->getNamaLengkap() ?? 'Pembayaran mandiri',
            ])->all();
            $data['qr_nota'] = $this->isiQrNota($t);
            $data['nama_tpi'] = Tpi::find($tg->kedatangan->getIdTpi())?->getNamaTpi() ?? Konfigurasi::nilai('kode_tpi', 'PLR');
        }

        return $data;
    }

    /**
     * FR-32: pembatalan transaksi oleh supervisor (Pimpinan/Kasir senior) dengan alasan wajib.
     * Lot dikembalikan ke TIDAK_TERJUAL, pembayaran yang sudah masuk tetap tercatat sebagai riwayat.
     */
    public function batalkan(Pengguna $supervisor, int $idTransaksi, string $alasan): TransaksiPenjualan
    {
        $this->batalkanKedaluwarsa($idTransaksi);

        return DB::transaction(function () use ($supervisor, $idTransaksi, $alasan) {
            $trx = $this->kueriTpi($supervisor)->whereKey($idTransaksi)->lockForUpdate()->first();
            if (! $trx) {
                throw new HttpResponseException(response()->json(['message' => 'Transaksi tidak ditemukan.'], 404));
            }
            if ($trx->getStatus() === StatusTransaksi::DIBATALKAN) {
                $this->tolak('alasan', "Transaksi {$trx->getKodeTransaksi()} sudah dibatalkan.");
            }

            $trx->status = StatusTransaksi::DIBATALKAN->value;
            $trx->id_pembatal = $supervisor->getIdPengguna();
            $trx->alasan_batal = trim($alasan);
            $trx->waktu_batal = now();
            $trx->save();

            $lot = LotLelang::whereKey($trx->getIdLot())->lockForUpdate()->first();
            if ($lot && in_array($lot->getStatus()->value, [StatusLot::TERJUAL->value], true)) {
                $lot->pindahStatus(StatusLot::TIDAK_TERJUAL);
            }

            $this->audit->catat(
                $supervisor, 'BATAL_TRANSAKSI', 'transaksi_penjualan', $trx->getIdTransaksi(),
                ['status' => StatusTransaksi::LUNAS->value, 'total_tagihan' => $trx->getTotalTagihan()],
                ['status' => $trx->status, 'alasan' => $trx->alasan_batal],
            );

            return $trx->fresh();
        });
    }

    private function pembayaranLewatBatas(TransaksiPenjualan $trx): bool
    {
        return $trx->getStatus() === StatusTransaksi::BELUM_DIBAYAR
            && $trx->batas_bayar !== null
            && ! $trx->batas_bayar->isFuture()
            && $trx->totalDibayar() === 0.0;
    }

    private function batalkanKedaluwarsa(int $idTransaksi): void
    {
        $cek = TransaksiPenjualan::find($idTransaksi);
        if (! $cek || $cek->getStatus() !== StatusTransaksi::BELUM_DIBAYAR
            || ! $cek->batas_bayar || $cek->batas_bayar->isFuture() || $cek->totalDibayar() > 0) {
            return;
        }

        DB::transaction(function () use ($idTransaksi) {
            $trx = TransaksiPenjualan::whereKey($idTransaksi)->lockForUpdate()->first();
            if (! $trx || $trx->getStatus() !== StatusTransaksi::BELUM_DIBAYAR
                || ! $trx->batas_bayar || $trx->batas_bayar->isFuture() || $trx->totalDibayar() > 0) {
                return;
            }

            $trx->status = StatusTransaksi::DIBATALKAN->value;
            $trx->save();
            $lot = LotLelang::whereKey($trx->getIdLot())->lockForUpdate()->first();
            if ($lot?->getStatus() === StatusLot::TERJUAL) {
                $lot->pindahStatus(StatusLot::TIDAK_TERJUAL);
            }
        });
    }

    private function kueriTpi(Pengguna $pengguna)
    {
        $idTpi = $pengguna->getIdTpi() ?? (int) Konfigurasi::nilai('id_tpi_aktif', 1);

        return TransaksiPenjualan::whereHas('lot.tangkapan.kedatangan', fn ($q) => $q->where('id_tpi', $idTpi));
    }

    private function tandaTangan(TransaksiPenjualan $t): string
    {
        $data = $t->getIdTransaksi() . '|' . $t->getIdPembeli() . '|' . $t->getTotalTagihan() . '|' . $t->getWaktuTransaksi()->format('YmdHis');

        return strtoupper(substr(hash_hmac('sha256', $data, (string) config('app.key')), 0, 12));
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
