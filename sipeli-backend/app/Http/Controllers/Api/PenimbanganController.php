<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanLotRequest;
use App\Models\LotLelang;
use App\Services\PenimbanganService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * UC-02 Penimbangan dan Pembuatan Lot Lelang
 */
class PenimbanganController extends Controller
{
    public function __construct(private PenimbanganService $layanan)
    {
    }

    /** GET /api/penimbangan/{kode}  (kode: KDT-00001 atau 1) */
    public function kedatangan(string $kode)
    {
        $id = $this->layanan->idDariKode($kode);
        $data = $id ? $this->layanan->ambilKedatangan($id) : null;

        if (! $data) {
            return response()->json([
                'message' => "Kedatangan {$kode} tidak ditemukan. Periksa kembali nomor kedatangan.",
            ], 404);
        }

        return response()->json(['data' => $data]);
    }

    /** POST /api/lot */
    public function simpan(SimpanLotRequest $request)
    {
        $lot = $this->layanan->buatLot($request->validated(), $request->user());

        return response()->json([
            'pesan' => $lot->status === 'DRAFT_HARGA'
                ? 'Lot dibuat sebagai DRAFT_HARGA (harga pembukaan belum diisi).'
                : 'Lot berhasil dibuat dan siap dilelang.',
            'data'  => $this->layanan->ringkasLot($lot),
        ], 201);
    }

    /** GET /api/lot/{kode}  (hasil pindai QR label) */
    public function lihatLot(string $kode)
    {
        $lot = LotLelang::where('kode_lot', strtoupper(trim($kode)))->first();
        if (! $lot) {
            return response()->json(['message' => "Lot {$kode} tidak ditemukan."], 404);
        }

        return response()->json(['data' => $this->layanan->ringkasLot($lot)]);
    }

    /** POST /api/lot/{id}/cetak  (alur A3: catat percobaan cetak) */
    public function catatCetak(int $id)
    {
        $lot = LotLelang::find($id);
        if (! $lot) {
            return response()->json(['message' => 'Lot tidak ditemukan.'], 404);
        }

        $lot = $this->layanan->catatCetak($lot);

        return response()->json([
            'pesan'        => 'Percobaan cetak label dicatat.',
            'jumlah_cetak' => $lot->jumlah_cetak,
        ]);
    }

    /** POST /api/lot/{id}/foto  (opsional: foto ikan dipotret Petugas Timbang, menggantikan foto lama) */
    public function unggahFoto(Request $request, int $id)
    {
        $lot = LotLelang::find($id);
        if (! $lot) {
            return response()->json(['message' => 'Lot tidak ditemukan.'], 404);
        }

        if (! in_array($lot->getStatus()->value, ['DRAFT_HARGA', 'MENUNGGU_LELANG'], true)) {
            return response()->json(['message' => 'Foto hanya bisa diambil sebelum lot dilelang.'], 422);
        }

        $request->validate([
            'foto' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'foto.required' => 'Pilih atau ambil foto terlebih dahulu.',
            'foto.mimes'    => 'Foto harus berformat JPG, PNG, atau WEBP.',
            'foto.max'      => 'Ukuran foto maksimal 5 MB.',
            'foto.uploaded' => 'Foto gagal diunggah. Coba lagi dengan foto yang lebih kecil.',
        ]);

        $berkas = $request->file('foto');
        $ekstensi = strtolower($berkas->getClientOriginalExtension() ?: $berkas->extension() ?: 'jpg');
        $ekstensi = $ekstensi === 'jpeg' ? 'jpg' : $ekstensi;
        $nama = Str::uuid() . '.' . $ekstensi;
        Storage::disk('local')->putFileAs('foto-lot', $berkas, $nama);

        if ($lot->getFoto()) {
            Storage::disk('local')->delete('foto-lot/' . $lot->getFoto());
        }
        $lot->foto = $nama;
        $lot->save();

        return response()->json([
            'pesan' => 'Foto lot disimpan.',
            'data'  => $this->layanan->ringkasLot($lot),
        ]);
    }

    /** GET /api/foto-lot/{nama}  (tanpa login; nama berkas acak sehingga tidak bisa ditebak) */
    public function tampilFoto(string $nama)
    {
        $jalur = 'foto-lot/' . $nama;
        if (! Storage::disk('local')->exists($jalur)) {
            return response()->json(['message' => 'Foto tidak ditemukan.'], 404);
        }

        return response()->file(Storage::disk('local')->path($jalur), [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
