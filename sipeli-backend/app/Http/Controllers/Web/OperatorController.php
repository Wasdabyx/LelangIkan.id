<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Konfigurasi;
use App\Services\LelangService;
use Illuminate\Http\Request;

/** Dasbor Operator Lelang (UC-03/UC-05): antrean lot siap lelang dan pembukaan sesi. */
class OperatorController extends Controller
{
    public function __construct(private LelangService $lelang)
    {
    }

    /** GET /dasbor/operator/antrean */
    public function antrean(Request $request)
    {
        $pengguna = $request->user();
        $idTpi = $this->lelang->idTpi($pengguna);

        return view('web.operator.antrean', [
            'antrean' => $this->lelang->antrean($idTpi),
            'durasiBawaan' => (int) Konfigurasi::nilai('durasi_lelang_bawaan', 120),
        ]);
    }

    /** GET /dasbor/operator/sesi */
    public function sesi(Request $request)
    {
        return view('web.operator.sesi', [
            'sesi' => $this->lelang->daftarSesiOperator($request->user(), (int) $request->query('halaman', 1)),
        ]);
    }

    /** POST /dasbor/operator/sesi */
    public function buka(Request $request)
    {
        $data = $request->validate([
            'id_lot'                => ['required', 'integer'],
            'mode'                  => ['required', 'in:NAIK,TURUN'],
            'durasi_detik'          => ['required', 'integer', 'between:' . LelangService::DURASI_MIN . ',' . LelangService::DURASI_MAKS],
            'kenaikan_minimum'      => ['nullable', 'integer', 'between:100,1000000'],
            'harga_awal'            => ['required_if:mode,TURUN', 'nullable', 'integer', 'min:100'],
            'harga_batas_bawah'     => ['required_if:mode,TURUN', 'nullable', 'integer', 'min:100'],
            'penurunan_per_langkah' => ['required_if:mode,TURUN', 'nullable', 'integer', 'min:1'],
            'interval_detik'        => ['required_if:mode,TURUN', 'nullable', 'integer', 'between:1,600'],
        ], [
            'id_lot.required'         => 'Lot wajib dipilih.',
            'durasi_detik.between'    => 'Durasi sesi 30 sampai 600 detik.',
            'kenaikan_minimum.between' => 'Kenaikan minimum Rp100 sampai Rp1.000.000 per kg.',
            'harga_awal.required_if'  => 'Lelang turun memerlukan harga awal.',
            'harga_batas_bawah.required_if' => 'Lelang turun memerlukan harga batas bawah.',
            'penurunan_per_langkah.required_if' => 'Lelang turun memerlukan penurunan per langkah.',
            'interval_detik.required_if' => 'Lelang turun memerlukan interval penurunan.',
        ]);

        $sesi = $this->lelang->buka(
            $request->user(),
            (int) $data['id_lot'],
            (int) $data['durasi_detik'],
            isset($data['kenaikan_minimum']) ? (float) $data['kenaikan_minimum'] : null,
            $data['mode'],
            isset($data['harga_awal']) ? (float) $data['harga_awal'] : null,
            isset($data['harga_batas_bawah']) ? (float) $data['harga_batas_bawah'] : null,
            isset($data['penurunan_per_langkah']) ? (float) $data['penurunan_per_langkah'] : null,
            isset($data['interval_detik']) ? (int) $data['interval_detik'] : null,
        );

        return redirect()
            ->route('operator.sesi.pantau', $sesi->getIdSesi())
            ->with('sukses', 'Sesi lelang dibuka. Papan lelang dan peserta mobile sudah menerima peristiwa LELANG_DIMULAI.');
    }

    /** GET /dasbor/operator/sesi/{id}: layar pemantauan satu detik. */
    public function pantau(Request $request, int $id)
    {
        $pengguna = $request->user();

        return view('web.operator.pantau', [
            'idSesi' => $id,
            'awal'   => $this->lelang->status($id, $pengguna, true),
        ]);
    }

    /** GET /dasbor/operator/sesi/{id}/keadaan (JSON untuk pembaruan tiap detik). */
    public function keadaan(Request $request, int $id)
    {
        return response()->json(['data' => $this->lelang->status($id, $request->user(), true)]);
    }

    /** POST /dasbor/operator/sesi/{id}/tutup */
    public function tutup(Request $request, int $id)
    {
        $data = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'alasan.required' => 'Alasan penutupan wajib diisi.',
            'alasan.min'      => 'Alasan penutupan minimal 5 karakter.',
        ]);

        $this->lelang->tutupManual($request->user(), $id, trim($data['alasan']));

        // Kembali ke layar pemantauan, bukan ke alamat JSON pembaruan tiap detik.
        return redirect()->route('operator.sesi.pantau', $id)->with('sukses', 'Sesi ditutup lebih awal. Pemenang ditetapkan dan peserta menerima peristiwa SESI_DITUTUP.');
    }
}
