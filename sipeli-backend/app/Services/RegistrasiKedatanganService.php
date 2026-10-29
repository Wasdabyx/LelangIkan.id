<?php

namespace App\Services;

use App\Enums\StatusKedatangan;
use App\Enums\StatusTangkapan;
use App\Models\GradeMutu;
use App\Models\JenisIkan;
use App\Models\Kapal;
use App\Models\KedatanganKapal;
use App\Models\Konfigurasi;
use App\Models\Pengguna;
use App\Models\Tangkapan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kelas kendali RegistrasiKedatanganService (DPPL Tabel 3.56) - UC-01
 * Aturan bisnis (total estimasi, status, minimal satu baris, hanya DRAF yang boleh disunting)
 * ada di kelas entitas; kelas ini hanya mengatur urutan langkah dan transaksi.
 */
class RegistrasiKedatanganService
{
    /** FR-02: cari kapal berdasarkan nama atau nomor registrasi (min. 3 karakter) */
    public function cariKapal(string $kataKunci): array
    {
        return Kapal::cari($kataKunci)
            ->map(fn (Kapal $kapal) => $this->dataKapal($kapal))
            ->all();
    }

    /** Data pilihan untuk formulir: jenis ikan & grade */
    public function ambilReferensi(): array
    {
        return [
            'jenis_ikan' => JenisIkan::cariSemuaAktif()->map(fn (JenisIkan $j) => [
                'id_jenis'   => $j->getIdJenis(),
                'nama_jenis' => $j->getNamaJenis(),
                'kategori'   => $j->getKategori()->value,
            ])->all(),
            'grade_mutu' => GradeMutu::cariSemua()->map(fn (GradeMutu $g) => [
                'id_grade'   => $g->getIdGrade(),
                'kode_grade' => $g->getKodeGrade(),
                'deskripsi'  => $g->getDeskripsi(),
            ])->all(),
        ];
    }

    /** Alur utama langkah (2): daftar kedatangan pada tanggal tertentu */
    public function daftarKedatangan(string $tanggal, ?int $idTpi): array
    {
        return KedatanganKapal::daftarHarian($tanggal, $idTpi)
            ->map(fn (KedatanganKapal $k) => $this->ringkas($k))
            ->all();
    }

    /** Alur A2: data lengkap satu kedatangan untuk disunting; null bila tidak ada / beda TPI */
    public function ambilDetail(int $idKedatangan, Pengguna $petugas): ?array
    {
        $k = KedatanganKapal::with(['kapal.pemilik', 'tangkapan'])->find($idKedatangan);
        if (! $k || ! $this->bolehDiakses($k, $petugas)) {
            return null;
        }

        return [
            'id_kedatangan'        => $k->getIdKedatangan(),
            'nomor_kedatangan'     => $k->getNomorKedatangan(),
            'status'               => $k->getStatus()->value,
            'dapat_diubah'         => $k->isDapatDiubah(),
            'tanggal'              => $k->getTanggal()->format('Y-m-d'),
            'jam'                  => $k->getJam(),
            'nama_kapten'          => $k->getNamaKapten(),
            'jumlah_abk'           => $k->getJumlahAbk(),
            'nomor_surat'          => $k->getNomorSurat(),
            'total_berat_estimasi' => $k->getTotalBeratEstimasi(),
            'kapal'                => $this->dataKapal($k->kapal, $k->getIdKedatangan()),
            'tangkapan'            => $k->tangkapan->map(fn (Tangkapan $t) => [
                'id_tangkapan'     => $t->getIdTangkapan(),
                'id_jenis'         => $t->getIdJenis(),
                'id_grade'         => $t->getIdGrade(),
                'berat_estimasi'   => $t->getBeratEstimasi(),
                'jumlah_keranjang' => $t->getJumlahKeranjang(),
                'status'           => $t->getStatus()->value,
            ])->values()->all(),
        ];
    }

    /** Alur utama langkah (9): simpan kedatangan + seluruh baris dalam SATU transaksi */
    public function simpanKedatangan(array $data, Pengguna $petugas): KedatanganKapal
    {
        return DB::transaction(function () use ($data, $petugas) {
            // 1. Kepala kedatangan, dimulai sebagai DRAF
            $kedatangan = new KedatanganKapal();
            $kedatangan->setIdTpi($petugas->getIdTpi() ?? (int) Konfigurasi::nilai('id_tpi_aktif', 1));
            $kedatangan->setIdPetugas($petugas->getIdPengguna());
            $this->isiKepala($kedatangan, $data);
            $kedatangan->simpan();

            // 2-4. Baris tangkapan, total, dan status
            return $this->isiBarisDanStatus($kedatangan, $data);
        });
    }

    /**
     * Alur A2: lengkapi / sunting kedatangan DRAF. Kepala dan semua baris diganti dengan isian baru,
     * lalu tetap DRAF atau langsung didaftarkan (TERDAFTAR). Null bila tidak ada / beda TPI.
     */
    public function perbaruiDraf(int $idKedatangan, array $data, Pengguna $petugas): ?KedatanganKapal
    {
        return DB::transaction(function () use ($idKedatangan, $data, $petugas) {
            // Kunci baris agar tidak disunting dua petugas bersamaan
            $kedatangan = KedatanganKapal::whereKey($idKedatangan)->lockForUpdate()->first();
            if (! $kedatangan || ! $this->bolehDiakses($kedatangan, $petugas)) {
                return null;
            }
            if (! $kedatangan->isDapatDiubah()) {
                throw ValidationException::withMessages([
                    'status' => 'Kedatangan ' . $kedatangan->getNomorKedatangan() . ' sudah berstatus '
                        . $kedatangan->getStatus()->value . ' sehingga tidak dapat diubah lagi.',
                ]);
            }

            $this->isiKepala($kedatangan, $data);
            $kedatangan->simpan();
            $kedatangan->hapusSemuaBarisTangkapan();

            return $this->isiBarisDanStatus($kedatangan, $data);
        });
    }

    /** Bentuk ringkas satu kedatangan untuk dikirim ke aplikasi */
    public function ringkas(KedatanganKapal $k): array
    {
        return [
            'id_kedatangan'        => $k->getIdKedatangan(),
            'nomor_kedatangan'     => $k->getNomorKedatangan(),
            'nama_kapal'           => $k->kapal->getNamaKapal(),
            'nomor_registrasi'     => $k->kapal->getNomorRegistrasi(),
            'tanggal'              => $k->getTanggal()->format('Y-m-d'),
            'jam'                  => $k->getJam(),
            'nama_kapten'          => $k->getNamaKapten(),
            'total_berat_estimasi' => $k->getTotalBeratEstimasi(),
            'jumlah_baris'         => $k->tangkapan_count,
            'jumlah_belum_timbang' => $k->belum_timbang_count ?? 0,
            'status'               => $k->getStatus()->value,
        ];
    }

    // ---------------- bantu (private) ----------------

    private function isiKepala(KedatanganKapal $kedatangan, array $data): void
    {
        $kedatangan->setIdKapal((int) $data['id_kapal']);
        $kedatangan->setTanggal($data['tanggal']);
        $kedatangan->setJam($data['jam']);
        $kedatangan->setNamaKapten($data['nama_kapten']);
        $kedatangan->setJumlahAbk((int) $data['jumlah_abk']);
        $kedatangan->setNomorSurat($data['nomor_surat']);
    }

    private function isiBarisDanStatus(KedatanganKapal $kedatangan, array $data): KedatanganKapal
    {
        // 2. Baris tangkapan
        foreach ($data['tangkapan'] ?? [] as $b) {
            $kedatangan->tambahBarisTangkapan(
                (int) $b['id_jenis'],
                (int) $b['id_grade'],
                (float) $b['berat_estimasi'],
                (int) $b['jumlah_keranjang'],
            );
        }

        // 3. Total estimasi dihitung oleh kelas entitas
        $kedatangan->hitungTotalEstimasi();
        $kedatangan->simpan();

        // 4. Bila tidak diminta sebagai draf: DRAF -> TERDAFTAR
        if (($data['status'] ?? StatusKedatangan::TERDAFTAR->value) === StatusKedatangan::TERDAFTAR->value) {
            $kedatangan->daftarkan();
        }

        return $kedatangan->load('kapal')->loadCount([
            'tangkapan',
            'tangkapan as belum_timbang_count' => fn ($q) => $q->where('status', StatusTangkapan::BELUM_DITIMBANG->value),
        ]);
    }

    /** Petugas yang terikat TPI hanya boleh membuka kedatangan di TPI-nya sendiri */
    private function bolehDiakses(KedatanganKapal $k, Pengguna $petugas): bool
    {
        return $petugas->getIdTpi() === null || $petugas->getIdTpi() === $k->getIdTpi();
    }

    /** Data kapal + pemilik + kedatangan terakhir (selain kedatangan yang sedang disunting) */
    private function dataKapal(Kapal $kapal, ?int $kecualiKedatangan = null): array
    {
        $pemilik  = $kapal->pemilik;
        $terakhir = $kapal->kedatangan()
            ->when($kecualiKedatangan, fn ($q) => $q->where('id_kedatangan', '!=', $kecualiKedatangan))
            ->orderByDesc('tanggal')->orderByDesc('jam')
            ->first();

        return [
            'id_kapal'         => $kapal->getIdKapal(),
            'nama_kapal'       => $kapal->getNamaKapal(),
            'nomor_registrasi' => $kapal->getNomorRegistrasi(),
            'gt'               => $kapal->getGt(),
            'pemilik' => [
                'nama'        => $pemilik->getNama(),
                'no_telepon'  => $pemilik->getNoTelepon(),
                'no_rekening' => $pemilik->getNoRekening(),
                'nama_bank'   => $pemilik->getNamaBank(),
            ],
            'kedatangan_terakhir' => $terakhir ? [
                'tanggal'              => $terakhir->getTanggal()->format('Y-m-d'),
                'nama_kapten'          => $terakhir->getNamaKapten(),
                'total_berat_estimasi' => $terakhir->getTotalBeratEstimasi(),
            ] : null,
        ];
    }
}
