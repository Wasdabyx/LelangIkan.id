<?php

namespace App\Services;

use App\Enums\StatusSesi;
use App\Models\Konfigurasi;
use App\Models\LotLelang;
use App\Models\Penawaran;
use App\Models\SesiLelang;
use App\Models\TransaksiPenjualan;

/**
 * Kelas kendali PapanLelangService - UC-09 (FR-41, FR-42, FR-43).
 *
 * Papan tampilan memperbarui harga tertinggi, sisa waktu, dan status sesi tanpa interaksi
 * pengguna (FR-41). Endpoint ini menjadi cadangan polling bila WebSocket terputus, dan
 * identitas penawar selalu disamarkan pada tampilan umum (FR-43).
 */
class PapanLelangService
{
    /** Inisial penawar: dua huruf pertama nama (FR-43). */
    public static function inisial(?string $nama): string
    {
        $nama = trim((string) $nama);
        if ($nama === '') {
            return '**';
        }
        $kata = preg_split('/\s+/', $nama) ?: [];

        return strtoupper(mb_substr($kata[0], 0, 1) . mb_substr($kata[1] ?? '', 0, 1));
    }

    /** Keadaan papan untuk satu TPI: sesi yang sedang berjalan + ringkasan hari ini. */
    public function keadaan(int $idTpi): array
    {
        $sesiAktif = SesiLelang::query()
            ->with(['lot.tangkapan.jenisIkan', 'lot.tangkapan.grade', 'lot.tangkapan.kedatangan.kapal'])
            ->where('status', StatusSesi::AKTIF->value)
            ->whereHas('lot.tangkapan.kedatangan', fn ($q) => $q->where('id_tpi', $idTpi))
            ->orderBy('id_sesi')
            ->get();

        $papan = $sesiAktif->map(fn (SesiLelang $sesi) => $this->kartuSesi($sesi))->all();

        return [
            'waktu_server' => now()->toDateTimeString(),
            'nama_tpi'     => optional(\App\Models\Tpi::find($idTpi))->getNamaTpi(),
            'sesi_aktif'   => $papan,
            'ringkasan'    => $this->ringkasanHariIni($idTpi),
        ];
    }

    /** Satu kartu papan untuk sebuah sesi (dipakai juga oleh polling operator). */
    public function kartuSesi(SesiLelang $sesi): array
    {
        $lot = $sesi->lot;
        $tangkapan = $lot?->tangkapan;
        $tertinggi = Penawaran::where('id_sesi', $sesi->getIdSesi())->orderByDesc('harga_per_kg')->first();
        // diffInSeconds() pada Carbon 3 mengembalikan pecahan; papan menampilkan detik bulat.
        $sisaDetik = (int) max(0, ceil($sesi->getWaktuSelesaiRencana()->getTimestamp() - now()->getTimestamp()));

        return [
            'id_sesi'         => $sesi->getIdSesi(),
            'status'          => $sesi->getStatus()->value,
            'mode'            => $sesi->getMode()->value,
            'kode_lot'        => $lot?->getKodeLot(),
            'nama_jenis'      => $tangkapan?->jenisIkan?->getNamaJenis(),
            'kode_grade'      => $tangkapan?->grade?->getKodeGrade(),
            'berat_estimasi'  => $tangkapan?->getBeratEstimasi(),
            'nama_kapal'      => $tangkapan?->kedatangan?->kapal?->getNamaKapal(),
            'harga_pembukaan' => $lot?->getHargaPembukaan(),
            'harga_berjalan'  => $sesi->getHargaBerjalan(),
            // Papan harus selalu menampilkan angka: tawaran tertinggi, harga berjalan, atau harga pembukaan.
            'harga_tertinggi' => $tertinggi?->harga_per_kg ?? $sesi->getHargaBerjalan() ?? $lot?->getHargaPembukaan(),
            'inisial_penawar' => $tertinggi ? self::inisial($tertinggi->pembeli?->getNamaLengkap()) : null, // FR-43
            'jumlah_penawaran' => Penawaran::where('id_sesi', $sesi->getIdSesi())->count(),
            'kenaikan_minimum' => $sesi->getKenaikanMinimum(),
            'parameter_turun' => $sesi->getMode()->value === 'TURUN' ? [
                'harga_awal'            => $sesi->getHargaAwal(),
                'harga_batas_bawah'     => $sesi->getHargaBatasBawah(),
                'penurunan_per_langkah' => $sesi->getPenurunanPerLangkah(),
                'interval_detik'        => $sesi->getIntervalDetik(),
            ] : null,
            'sisa_detik'          => $sisaDetik,
            'waktu_selesai'       => $sesi->getWaktuSelesaiRencana()->format('Y-m-d H:i:s'),
            'jumlah_perpanjangan' => $sesi->getJumlahPerpanjangan(),
            'maks_perpanjangan'   => $sesi->getMaksPerpanjangan(),
        ];
    }

    /** FR-42: ringkasan harian (lot, volume terjual, nilai transaksi, retribusi terkumpul). */
    public function ringkasanHariIni(int $idTpi, ?string $tanggal = null): array
    {
        $tanggal ??= today()->toDateString();

        $trx = TransaksiPenjualan::query()
            ->whereDate('waktu_transaksi', $tanggal)
            ->whereHas('lot.tangkapan.kedatangan', fn ($q) => $q->where('id_tpi', $idTpi))
            ->whereIn('status', ['BELUM_DIBAYAR', 'DIBAYAR_SEBAGIAN', 'LUNAS']);

        $ringkas = (clone $trx)->selectRaw('COUNT(*) as jumlah_transaksi, COALESCE(SUM(berat_final),0) as volume,
                COALESCE(SUM(subtotal),0) as nilai, COALESCE(SUM(nilai_retribusi),0) as retribusi')->first();

        $lotHariIni = LotLelang::query()
            ->whereHas('tangkapan.kedatangan', fn ($q) => $q->where('id_tpi', $idTpi)->whereDate('tanggal', $tanggal))
            ->count();

        return [
            'tanggal'           => $tanggal,
            'jumlah_lot'        => (int) $lotHariIni,
            'jumlah_transaksi'  => (int) ($ringkas->jumlah_transaksi ?? 0),
            'volume_terjual_kg' => round((float) ($ringkas->volume ?? 0), 2),
            'nilai_transaksi'   => round((float) ($ringkas->nilai ?? 0), 2),
            'retribusi'         => round((float) ($ringkas->retribusi ?? 0), 2),
        ];
    }

    /** Harga tertinggi berjalan untuk satu sesi (dipakai papan dan pengujian). */
    public function hargaTertinggi(int $idSesi): float
    {
        return (float) (Penawaran::where('id_sesi', $idSesi)->max('harga_per_kg') ?? 0);
    }

    /** Nama TPI aktif untuk kepala halaman papan. */
    public function tpiAktif(): ?int
    {
        $id = Konfigurasi::nilai('id_tpi_aktif');

        return $id === null ? null : (int) $id;
    }
}
