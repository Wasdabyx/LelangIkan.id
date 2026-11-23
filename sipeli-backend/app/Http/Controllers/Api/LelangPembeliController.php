<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransaksiPenjualan;
use App\Services\DepositService;
use App\Services\LelangService;
use App\Services\PembayaranService;
use Illuminate\Http\Request;

/** UC-04 Penawaran real-time dan tagihan milik Pembeli (A4). */
class LelangPembeliController extends Controller
{
    public function __construct(
        private LelangService $lelang,
        private PembayaranService $pembayaran,
        private DepositService $deposit,
    ) {
    }

    /**
     * GET /api/pembeli/saldo  (FR-33)
     * Pembeli melihat saldo, limit kredit, daya beli efektif, tagihan terbuka, dan 10 mutasi terakhir.
     */
    public function saldo(Request $request)
    {
        return response()->json(['data' => $this->deposit->rincianSendiri($request->user())]);
    }

    /** GET /api/pembeli/lelang */
    public function daftar(Request $request)
    {
        return response()->json(['data' => $this->lelang->daftarSesiPembeli($request->user())]);
    }

    /** GET /api/pembeli/lelang/{id}  (dipanggil tiap 1 detik) */
    public function status(Request $request, int $id)
    {
        return response()->json(['data' => $this->lelang->status($id, $request->user(), false)]);
    }

    /** POST /api/pembeli/lelang/{id}/tawar  { harga_per_kg, kunci_idempotensi } */
    public function tawar(Request $request, int $id)
    {
        $data = $request->validate([
            'harga_per_kg'      => 'required|integer|between:100,99999999',
            'kunci_idempotensi' => 'required|uuid',
        ], [
            'harga_per_kg.required'      => 'Harga tawaran wajib diisi.',
            'harga_per_kg.integer'       => 'Harga tawaran harus berupa angka rupiah tanpa titik atau koma.',
            'harga_per_kg.between'       => 'Harga tawaran Rp100 sampai Rp99.999.999 per kg.',
            'kunci_idempotensi.required' => 'Kunci idempotensi wajib dikirim.',
            'kunci_idempotensi.uuid'     => 'Kunci idempotensi tidak valid.',
        ]);

        [$penawaran, $duplikat] = $this->lelang->ajukanTawaran(
            $request->user(), $id, (float) $data['harga_per_kg'], strtolower($data['kunci_idempotensi']), $request->ip(),
        );

        return response()->json([
            'pesan'    => $duplikat ? 'Tawaran ini sudah diterima sebelumnya.' : 'Tawaran diterima. Anda penawar tertinggi.',
            'duplikat' => $duplikat,
            'data'     => $this->lelang->status($penawaran->getIdSesi(), $request->user(), false),
        ], $duplikat ? 200 : 201);
    }

    /** POST /api/pembeli/lelang/{id}/ambil: klaim harga berjalan lelang turun. */
    public function ambil(Request $request, int $id)
    {
        $data = $request->validate(['kunci_idempotensi' => 'required|uuid']);
        [$penawaran, $duplikat] = $this->lelang->ambilHargaTurun(
            $request->user(), $id, strtolower($data['kunci_idempotensi']), $request->ip(),
        );
        return response()->json([
            'pesan' => $duplikat ? 'Pengambilan ini sudah diproses.' : 'Harga berhasil diambil. Anda pemenang lelang.',
            'duplikat' => $duplikat,
            'data' => $this->lelang->status($id, $request->user(), false),
        ], $duplikat ? 200 : 201);
    }

    /** GET /api/pembeli/tagihan */
    public function tagihan(Request $request)
    {
        $data = TransaksiPenjualan::where('id_pembeli', $request->user()->getIdPengguna())
            ->orderByDesc('id_transaksi')
            ->get()
            ->map(fn ($t) => $this->pembayaran->rincian($t, false));

        return response()->json(['data' => $data]);
    }

    /** GET /api/pembeli/tagihan/{id} */
    public function detailTagihan(Request $request, int $id)
    {
        $t = TransaksiPenjualan::where('id_pembeli', $request->user()->getIdPengguna())->find($id);
        if (! $t) {
            return response()->json(['message' => 'Tagihan tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $this->pembayaran->rincian($t)]);
    }

    public function bayarSaldo(Request $request, int $id)
    {
        $trx = $this->pembayaran->bayarDenganSaldo($request->user(), $id);

        return response()->json([
            'pesan' => 'Tagihan lunas menggunakan saldo.',
            'data' => $this->pembayaran->rincian($trx),
        ]);
    }

    public function jadwalkanPengambilan(Request $request, int $id)
    {
        $data = $request->validate([
            'tanggal_pengambilan' => ['required', 'date_format:Y-m-d'],
        ]);
        $trx = $this->pembayaran->jadwalkanPengambilan($request->user(), $id, $data['tanggal_pengambilan']);

        return response()->json([
            'pesan' => 'Jadwal pengambilan tersimpan.',
            'data' => $this->pembayaran->rincian($trx),
        ]);
    }
}
