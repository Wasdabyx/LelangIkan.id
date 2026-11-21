<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LaporanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * UC-10: pelaporan dan analitik harga ikan (Pimpinan/Admin Dinas, A6).
 * GET /api/laporan/{jenis}?dari=&sampai=  dengan jenis: harga, retribusi, kinerja, tren
 * FR-47: format=csv atau format=pdf. Parameter laporan terakhir tersimpan pada sesi pengguna.
 */
class LaporanController extends Controller
{
    private const JENIS = ['harga', 'retribusi', 'kinerja', 'tren'];

    public function __construct(private LaporanService $layanan)
    {
    }

    public function index(Request $request, string $jenis = 'harga')
    {
        abort_unless(in_array($jenis, self::JENIS, true), 404, 'Jenis laporan tidak dikenal.');

        $data = $request->validate([
            'dari'   => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'format' => ['nullable', Rule::in(['json', 'csv', 'pdf'])],
        ]);

        // Parameter laporan terakhir tersimpan untuk penggunaan berikutnya (DPPL UC-10 langkah 1).
        // API stateless: parameter disimpan pada baris pengguna, bukan pada sesi PHP.
        $dari = $data['dari'] ?? $request->user()->preferensi['laporan_dari'] ?? now()->subDays(29)->toDateString();
        $sampai = $data['sampai'] ?? $request->user()->preferensi['laporan_sampai'] ?? now()->toDateString();
        if (isset($data['dari']) || isset($data['sampai'])) {
            $preferensi = $request->user()->preferensi ?? [];
            $preferensi['laporan_dari'] = $dari;
            $preferensi['laporan_sampai'] = $sampai;
            $request->user()->preferensi = $preferensi;
            $request->user()->save();
        }

        $idTpi = (int) $request->user()->getIdTpi();
        $laporan = $this->layanan->semua($idTpi, $dari, $sampai);
        $format = $data['format'] ?? 'json';

        if ($format === 'csv') {
            return $this->unduhCsv($jenis, $laporan, $dari, $sampai);
        }
        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.laporan', [
                'jenis'   => $jenis,
                'laporan' => $laporan,
                'dari'    => $dari,
                'sampai'  => $sampai,
                'dicetak' => now(),
                'pencetak' => $request->user()->getNamaLengkap(),
            ])->setPaper('a4', 'portrait');

            return $pdf->download("laporan-{$jenis}-{$dari}-{$sampai}.pdf");
        }

        return response()->json([
            'data' => ['jenis' => $jenis] + $laporan,
        ]);
    }

    /** FR-47: ekspor CSV tanpa pustaka tambahan (stream langsung). */
    private function unduhCsv(string $jenis, array $laporan, string $dari, string $sampai): StreamedResponse
    {
        [$kepala, $baris] = $this->layanan->sebagaiCsv($jenis, $laporan);
        $nama = "laporan-{$jenis}-{$dari}-{$sampai}.csv";

        return response()->streamDownload(function () use ($kepala, $baris) {
            $keluaran = fopen('php://output', 'w');
            fwrite($keluaran, "\xEF\xBB\xBF");            // BOM agar Excel membaca UTF-8
            fputcsv($keluaran, $kepala, ';');
            foreach ($baris as $b) {
                fputcsv($keluaran, $b, ';');
            }
            fclose($keluaran);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
