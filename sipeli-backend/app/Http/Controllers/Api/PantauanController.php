<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DaftarPantauan;
use App\Models\LotLelang;
use App\Models\SesiLelang;
use App\Services\PapanLelangService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * UC-04 / FR-64: daftar pantauan pembeli.
 * Pembeli menambahkan lot ke daftar pantauan, menetapkan batas harga, dan menerima
 * pemberitahuan bila lot mulai dilelang. Pemesanan (PSN) tetap menjadi syarat menawar;
 * daftar pantauan hanya alat pemantauan.
 */
class PantauanController extends Controller
{
    public function __construct(private PapanLelangService $papan)
    {
    }

    /** GET /api/pantauan: daftar pantauan milik pembeli beserta keadaan terkini lot. */
    public function index(Request $request)
    {
        $idPembeli = $request->user()->getIdPengguna();

        $data = DaftarPantauan::with(['lot.tangkapan.jenisIkan', 'lot.tangkapan.grade'])
            ->where('id_pembeli', $idPembeli)
            ->orderByDesc('id_pantauan')
            ->get()
            ->map(function (DaftarPantauan $p) {
                $lot = $p->lot;
                $sesi = SesiLelang::where('id_lot', $p->getIdLot())
                    ->where('status', 'AKTIF')
                    ->orderByDesc('id_sesi')
                    ->first();

                return [
                    'id_pantauan'      => $p->getIdPantauan(),
                    'kode_pemesanan'   => $p->getKodePemesanan(),
                    'id_lot'           => $p->getIdLot(),
                    'kode_lot'         => $lot?->getKodeLot(),
                    'nama_jenis'       => $lot?->tangkapan?->jenisIkan?->getNamaJenis(),
                    'kode_grade'       => $lot?->tangkapan?->grade?->getKodeGrade(),
                    'berat_estimasi'   => $lot?->tangkapan?->getBeratEstimasi(),
                    'harga_pembukaan'  => $lot?->getHargaPembukaan(),
                    'status_lot'       => $lot?->status,
                    'batas_harga'      => $p->getHargaMaksimum(),
                    'notifikasi_aktif' => (bool) $p->notifikasi_aktif,
                    'lelang_berjalan'  => $sesi !== null,
                    'id_sesi'          => $sesi?->getIdSesi(),
                    'harga_tertinggi'  => $sesi ? $this->papan->hargaTertinggi($sesi->getIdSesi()) : null,
                    'sisa_detik'       => $sesi ? max(0, now()->diffInSeconds($sesi->getWaktuSelesaiRencana(), false)) : null,
                    'waktu_ditambah'   => $p->waktu_ditambah?->format('Y-m-d H:i'),
                ];
            })->all();

        return response()->json(['data' => $data]);
    }

    /** POST /api/pantauan: tambah lot ke daftar pantauan beserta batas harga. */
    public function simpan(Request $request)
    {
        $data = $request->validate([
            'id_lot'          => ['required', 'integer', 'exists:lot_lelang,id_lot'],
            'batas_harga'     => ['nullable', 'numeric', 'min:1'],
            'notifikasi_aktif' => ['nullable', 'boolean'],
        ]);

        $lot = LotLelang::findOrFail($data['id_lot']);
        if (! $lot->bolehDiikutiLelang()) {
            throw ValidationException::withMessages([
                'id_lot' => 'Lot ini sudah selesai dilelang sehingga tidak dapat dipantau.',
            ]);
        }
        if (isset($data['batas_harga'])
            && (float) $data['batas_harga'] < (float) $lot->getHargaPembukaan()) {
            throw ValidationException::withMessages([
                'batas_harga' => 'Batas harga di bawah harga pembukaan lot, pemberitahuan tidak akan pernah terpicu.',
            ]);
        }

        $pantauan = DaftarPantauan::firstOrCreate(
            ['id_pembeli' => $request->user()->getIdPengguna(), 'id_lot' => $lot->getIdLot()],
            [
                'kode_pemesanan'   => 'PNT-' . now()->format('ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
                'harga_maksimum'   => $data['batas_harga'] ?? null,
                'notifikasi_aktif' => $data['notifikasi_aktif'] ?? true,
            ],
        );

        if (! $pantauan->wasRecentlyCreated) {
            $pantauan->harga_maksimum = $data['batas_harga'] ?? $pantauan->harga_maksimum;
            $pantauan->notifikasi_aktif = $data['notifikasi_aktif'] ?? $pantauan->notifikasi_aktif;
            $pantauan->save();
        }

        return response()->json([
            'pesan' => $pantauan->wasRecentlyCreated ? 'Lot ditambahkan ke daftar pantauan.' : 'Batas harga pantauan diperbarui.',
            'data'  => [
                'id_pantauan'    => $pantauan->getIdPantauan(),
                'kode_pemesanan' => $pantauan->getKodePemesanan(),
                'id_lot'         => $pantauan->getIdLot(),
                'batas_harga'    => $pantauan->getHargaMaksimum(),
            ],
        ], $pantauan->wasRecentlyCreated ? 201 : 200);
    }

    /** DELETE /api/pantauan/{id}: hapus lot dari daftar pantauan. */
    public function hapus(Request $request, int $id)
    {
        $pantauan = DaftarPantauan::where('id_pembeli', $request->user()->getIdPengguna())->find($id);
        abort_if($pantauan === null, 404, 'Pantauan tidak ditemukan pada daftar Anda.');

        $pantauan->delete();

        return response()->json(['pesan' => 'Lot dikeluarkan dari daftar pantauan.']);
    }
}
