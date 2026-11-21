<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DepositService;
use App\Services\LaporanService;
use App\Services\RingkasanPimpinanService;
use Illuminate\Http\Request;

/** Dasbor Pimpinan/Dinas (UC-10, UC-12): ringkasan harian, laporan, dan persetujuan penyesuaian saldo. */
class PimpinanController extends Controller
{
    public function __construct(
        private RingkasanPimpinanService $ringkasan,
        private LaporanService $laporan,
        private DepositService $deposit,
    ) {
    }

    /** GET /dasbor/pimpinan/ringkasan */
    public function ringkasan(Request $request)
    {
        $tanggal = $request->query('tanggal') ?: now()->toDateString();

        return view('web.pimpinan.ringkasan', [
            'tanggal' => $tanggal,
            'data'    => $this->ringkasan->ringkasan((int) $request->user()->getIdTpi(), $tanggal),
        ]);
    }

    /** GET /dasbor/pimpinan/laporan */
    public function laporan(Request $request)
    {
        $data = $request->validate([
            'dari'   => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ], [
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh lebih awal dari tanggal awal.',
        ]);

        // Parameter terakhir disimpan pada baris pengguna (DPPL UC-10 langkah 1).
        $preferensi = $request->user()->preferensi ?? [];
        $dari = $data['dari'] ?? ($preferensi['laporan_dari'] ?? now()->subDays(29)->toDateString());
        $sampai = $data['sampai'] ?? ($preferensi['laporan_sampai'] ?? now()->toDateString());
        if (isset($data['dari']) || isset($data['sampai'])) {
            $preferensi['laporan_dari'] = $dari;
            $preferensi['laporan_sampai'] = $sampai;
            $request->user()->preferensi = $preferensi;
            $request->user()->save();
        }

        return view('web.pimpinan.laporan', [
            'dari'    => $dari,
            'sampai'  => $sampai,
            'laporan' => $this->laporan->semua((int) $request->user()->getIdTpi(), $dari, $sampai),
        ]);
    }

    /** GET /dasbor/pimpinan/laporan/{jenis}/csv */
    public function unduhCsv(Request $request, string $jenis)
    {
        abort_unless(in_array($jenis, ['harga', 'retribusi'], true), 404);

        $dari = $request->query('dari', now()->subDays(29)->toDateString());
        $sampai = $request->query('sampai', now()->toDateString());
        $laporan = $this->laporan->semua((int) $request->user()->getIdTpi(), $dari, $sampai);
        [$kepala, $baris] = $this->laporan->sebagaiCsv($jenis, $laporan);

        return response()->streamDownload(function () use ($kepala, $baris) {
            $keluaran = fopen('php://output', 'w');
            fputcsv($keluaran, $kepala, ';');
            foreach ($baris as $b) {
                fputcsv($keluaran, $b, ';');
            }
            fclose($keluaran);
        }, "laporan-{$jenis}-{$dari}-{$sampai}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** GET /dasbor/pimpinan/penyesuaian */
    public function penyesuaian(Request $request)
    {
        return view('web.pimpinan.penyesuaian', [
            'daftar' => $this->deposit->penyesuaianMenunggu($request->user()),
        ]);
    }

    /** POST /dasbor/pimpinan/penyesuaian/{id}/keputusan */
    public function putuskan(Request $request, int $id)
    {
        $data = $request->validate([
            'keputusan' => ['required', 'in:SETUJU,TOLAK'],
            'catatan'   => ['nullable', 'string', 'max:200'],
        ], [
            'keputusan.required' => 'Keputusan wajib dipilih.',
        ]);

        $this->deposit->putuskanPenyesuaian($request->user(), $id, $data['keputusan'] === 'SETUJU', $data['catatan'] ?? null);

        return back()->with('sukses', 'Keputusan tersimpan: ' . ($data['keputusan'] === 'SETUJU' ? 'penyesuaian disetujui.' : 'penyesuaian ditolak.'));
    }
}
