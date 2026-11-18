<?php

namespace App\Services;

use App\Enums\MetodeBayar;
use App\Enums\StatusTransaksi;
use App\Models\Konfigurasi;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Tpi;
use App\Models\TransaksiPenjualan;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kelas kendali PembayaranService - UC-06 Pembayaran dan Penyelesaian Transaksi (Kasir TPI, A5).
 * Metode: TUNAI, TRANSFER, QRIS (simulasi). SALDO menunggu fitur Deposit Saldo (UC-07).
 * Pembayaran daring bersifat simulasi sesuai SKPL (tidak memakai payment gateway).
 */
class PembayaranService
{
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
        if ($metode === MetodeBayar::SALDO) {
            $this->tolak('metode', 'Metode saldo belum tersedia. Fitur Deposit Saldo (UC-07) belum dibuat.');
        }

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

            // FR-30: nominal > 0 dan tidak melebihi sisa tagihan
            $sisa = $trx->sisaTagihan();
            if (! Pembayaran::validasiNominal($nominal, $sisa)) {
                $this->tolak('nominal', $nominal <= 0
                    ? 'Nominal pembayaran harus lebih dari Rp0.'
                    : 'Nominal melebihi sisa tagihan ' . $this->rp($sisa) . '.');
            }

            if ($metode === MetodeBayar::QRIS) {
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

            return $trx->fresh();
        });
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
        ];

        if ($lengkap) {
            $data['pembayaran'] = $t->daftarPembayaran()->with('kasir')->get()->map(fn (Pembayaran $p) => [
                'metode'          => $p->getMetode()->value,
                'nominal'         => $p->getNominal(),
                'waktu_bayar'     => $p->getWaktuBayar()->format('Y-m-d H:i'),
                'nomor_referensi' => $p->getNomorReferensi(),
                'nama_kasir'      => $p->kasir->getNamaLengkap(),
            ])->all();
            $data['qr_nota'] = $this->isiQrNota($t);
            $data['nama_tpi'] = Tpi::find($tg->kedatangan->getIdTpi())?->getNamaTpi() ?? Konfigurasi::nilai('kode_tpi', 'PLR');
        }

        return $data;
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
