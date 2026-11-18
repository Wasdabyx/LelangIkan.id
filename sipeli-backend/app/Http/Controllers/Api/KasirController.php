<?php

namespace App\Http\Controllers\Api;

use App\Enums\MetodeBayar;
use App\Http\Controllers\Controller;
use App\Services\PembayaranService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** UC-06 Pembayaran dan Penyelesaian Transaksi - Kasir TPI (A5). */
class KasirController extends Controller
{
    public function __construct(private PembayaranService $layanan)
    {
    }

    /** GET /api/kasir/tagihan  (belum lunas + lunas hari ini) */
    public function daftar(Request $request)
    {
        return response()->json([
            'data'        => $this->layanan->tagihanTerbuka($request->user()),
            'lunas_hari_ini' => $this->layanan->lunasHariIni($request->user()),
        ]);
    }

    /** GET /api/kasir/tagihan/{kode}  (TRX-000001, 1, atau isi QR nota) */
    public function cari(Request $request, string $kode)
    {
        $trx = $this->layanan->cari($request->user(), $kode);

        return response()->json(['data' => $this->layanan->rincian($trx)]);
    }

    /** POST /api/kasir/tagihan/{id}/bayar  { metode, nominal, nomor_referensi? } */
    public function bayar(Request $request, int $id)
    {
        $data = $request->validate([
            'metode'          => ['required', Rule::in(MetodeBayar::nilai())],
            'nominal'         => 'required|integer|min:1|max:99999999999',
            'nomor_referensi' => ['nullable', 'required_if:metode,TRANSFER', 'string', 'regex:/^[A-Za-z0-9-]{6,40}$/'],
        ], [
            'metode.required'             => 'Metode pembayaran wajib dipilih.',
            'metode.in'                   => 'Metode pembayaran tidak dikenal.',
            'nominal.required'            => 'Nominal pembayaran wajib diisi.',
            'nominal.integer'             => 'Nominal harus berupa angka rupiah tanpa titik atau koma.',
            'nominal.min'                 => 'Nominal pembayaran harus lebih dari Rp0.',
            'nomor_referensi.required_if' => 'Nomor referensi transfer wajib diisi.',
            'nomor_referensi.regex'       => 'Nomor referensi 6-40 karakter: huruf, angka, atau tanda strip.',
        ]);

        $trx = $this->layanan->bayar(
            $request->user(), $id, MetodeBayar::from($data['metode']), (float) $data['nominal'],
            isset($data['nomor_referensi']) ? strtoupper($data['nomor_referensi']) : null,
        );

        return response()->json([
            'pesan' => $trx->getStatus()->value === 'LUNAS'
                ? "Pembayaran tersimpan. Transaksi {$trx->getKodeTransaksi()} LUNAS."
                : "Pembayaran sebagian tersimpan. Sisa tagihan Rp" . number_format($trx->sisaTagihan(), 0, ',', '.') . '.',
            'data'  => $this->layanan->rincian($trx),
        ], 201);
    }

    /** GET /api/nota/verifikasi?kode=...  (FR-31: periksa keaslian nota dari QR) */
    public function verifikasiNota(Request $request)
    {
        $data = $request->validate(['kode' => 'required|string|max:100'], ['kode.required' => 'Isi kode QR nota wajib dikirim.']);

        return response()->json(['data' => $this->layanan->verifikasiNota($data['kode'])]);
    }
}
