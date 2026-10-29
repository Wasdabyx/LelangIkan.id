<?php

namespace App\Services;

use App\Models\GradeMutu;
use App\Models\Konfigurasi;
use App\Models\LotLelang;

/**
 * Kelas kendali LotService (DPPL Tabel 3.7): nomor lot (FR-08) dan usulan harga (ALG-06)
 */
class LotService
{
    /**
     * FR-08: nomor lot untuk hari ini memakai kode TPI dari konfigurasi.
     * Aturan penomoran ada di LotLelang::buatKodeLot(). WAJIB dipanggil di dalam DB::transaction.
     */
    public function buatKodeLot(): string
    {
        return LotLelang::buatKodeLot((string) Konfigurasi::nilai('kode_tpi', 'TPI'), now());
    }

    /**
     * ALG-06 Rekomendasi harga pembukaan (Rp per kg).
     * 1. Ambil transaksi lot terjual jenis & grade sama, 7 hari terakhir.
     * 2. Bila data < 3, pakai harga pembukaan terakhir jenis & grade tsb.
     * 3. harga_usulan = median(harga_akhir_per_kg) x faktor_harga_grade.
     * 4. Bulatkan ke atas kelipatan Rp500.
     */
    public function usulanHarga(int $idJenis, int $idGrade): array
    {
        // Langkah 1 & 3
        $hargaTerjual = $this->hargaTerjualTujuhHari($idJenis, $idGrade);
        if (count($hargaTerjual) >= 3) {
            $faktor = (float) (GradeMutu::find($idGrade)?->getFaktorHarga() ?? 1);
            return [
                'harga'      => $this->bulatkan500($this->median($hargaTerjual) * $faktor),
                'metode'     => 'MEDIAN_7_HARI',
                'keterangan' => 'Median harga terjual 7 hari terakhir (' . count($hargaTerjual) . ' transaksi) x faktor grade.',
            ];
        }

        // Langkah 2
        $terakhir = LotLelang::whereNotNull('harga_pembukaan')
            ->whereHas('tangkapan', fn ($q) => $q->where('id_jenis', $idJenis)->where('id_grade', $idGrade))
            ->orderByDesc('waktu_timbang')
            ->first();

        if ($terakhir) {
            return [
                'harga'      => $this->bulatkan500((float) $terakhir->getHargaPembukaan()),
                'metode'     => 'HARGA_TERAKHIR',
                'keterangan' => 'Harga pembukaan terakhir jenis & grade ini (' . $terakhir->getWaktuTimbang()->format('d-m-Y') . ').',
            ];
        }

        return [
            'harga'      => null,
            'metode'     => null,
            'keterangan' => 'Belum ada riwayat harga untuk jenis dan grade ini.',
        ];
    }

    /**
     * Harga akhir per kg lot TERJUAL 7 hari terakhir.
     * Tabel transaksi baru dibuat pada UC-04/UC-05, jadi untuk tahap ini masih kosong
     * dan ALG-06 otomatis memakai langkah 2 (harga pembukaan terakhir).
     */
    private function hargaTerjualTujuhHari(int $idJenis, int $idGrade): array
    {
        return [];
    }

    private function median(array $nilai): float
    {
        sort($nilai);
        $n = count($nilai);
        $tengah = intdiv($n, 2);
        return $n % 2 ? (float) $nilai[$tengah] : ($nilai[$tengah - 1] + $nilai[$tengah]) / 2;
    }

    private function bulatkan500(float $harga): float
    {
        return ceil($harga / 500) * 500;
    }
}
