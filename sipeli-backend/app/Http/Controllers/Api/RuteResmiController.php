<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LelangService;
use App\Services\PembayaranService;
use Illuminate\Http\Request;

/**
 * Jembatan rute resmi SKPL Tabel IV.6 ke pengendali yang sudah ada.
 *
 * Dokumen SKPL memakai penamaan umum (/api/sesi, /api/sesi/{id}/penawaran, /api/pembayaran,
 * /api/saldo/mutasi), sedangkan aplikasi memakai rute per peran (/api/operator/sesi,
 * /api/pembeli/lelang/{id}/tawar, /api/kasir/...). Kelas ini menyediakan alamat resmi dokumen
 * tanpa menduplikasi logika: validasi dan aturan bisnis tetap dijalankan oleh layanan yang sama.
 * Rute per peran tetap dipertahankan karena lebih jelas bagi klien mobile.
 */
class RuteResmiController extends Controller
{
    public function __construct(
        private LelangService $lelang,
        private PembayaranService $pembayaran,
        private OperatorLelangController $operator,
        private LelangPembeliController $pembeli,
        private KasirController $kasir,
        private DepositController $deposit,
    ) {
    }

    /** GET /api/sesi : daftar sesi hari ini untuk operator. */
    public function daftarSesi(Request $request)
    {
        return $this->operator->daftarSesi($request);
    }

    /** POST /api/sesi : membuka sesi lelang (A3). */
    public function bukaSesi(Request $request)
    {
        return $this->operator->buka($request);
    }

    /** GET /api/sesi/{id} : keadaan sesi untuk operator dan pembeli. */
    public function statusSesi(Request $request, int $id)
    {
        return $request->user()->getIdPeran() === \App\Enums\KodePeran::PEMBELI->value
            ? $this->pembeli->status($request, $id)
            : $this->operator->status($request, $id);
    }

    /**
     * POST /api/sesi/{id}/penawaran : penawaran (mode NAIK) atau Ambil (mode TURUN).
     * Muatan mengikuti Tabel IV.7: { nominal | harga_per_kg, kunci_idempotensi }.
     */
    public function penawaran(Request $request, int $id)
    {
        $sesi = \App\Models\SesiLelang::findOrFail($id);
        $request->merge([
            'harga_per_kg'      => $request->input('harga_per_kg', $request->input('nominal')),
            'kunci_idempotensi' => $request->input('kunci_idempotensi'),
        ]);

        if ($sesi->getMode()->value === 'TURUN' && $request->input('nominal') === null) {
            return $this->pembeli->ambil($request, $id);
        }

        return $this->pembeli->tawar($request, $id);
    }

    /** POST /api/sesi/{id}/tutup : penutupan sesi dan penetapan pemenang (A3). */
    public function tutupSesi(Request $request, int $id)
    {
        return $this->operator->tutup($request, $id);
    }

    /**
     * POST /api/pembayaran : pencatatan pembayaran pembeli (A5).
     * Muatan: { kode_tagihan | id_tagihan, metode, nominal, nomor_referensi? }.
     */
    public function pembayaran(Request $request)
    {
        $data = $request->validate([
            'kode_tagihan' => ['nullable', 'string', 'max:40'],
            'id_tagihan'   => ['nullable', 'integer'],
        ]);
        $id = $data['id_tagihan'] ?? null;

        if ($id === null && ! empty($data['kode_tagihan'])) {
            $id = $this->pembayaran->cari($request->user(), $data['kode_tagihan'])->getIdTransaksi();
        }
        if ($id === null) {
            abort(422, 'Isi kode_tagihan (TRX-000001) atau id_tagihan.');
        }

        return $this->kasir->bayar($request, (int) $id);
    }

    /**
     * POST /api/saldo/mutasi : top-up, penarikan, atau penyesuaian saldo (A5).
     * Muatan: { id_pembeli, jenis, nominal, metode?, arah?, keterangan? }.
     */
    public function mutasiSaldo(Request $request)
    {
        $data = $request->validate(['id_pembeli' => ['required', 'integer']]);

        return $this->deposit->mutasi($request, (int) $data['id_pembeli']);
    }
}
