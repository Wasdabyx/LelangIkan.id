<?php

namespace App\Services;

use App\Models\Pengguna;
use Illuminate\Support\Facades\DB;

/**
 * Kelas kendali AuditService - UC-12 Audit dan Penelusuran Aktivitas.
 *
 * Menulis satu baris pada tabel audit_log untuk setiap perubahan data kritikal (FR-52).
 * Catatan audit hanya dapat ditambah dan dibaca; tidak ada metode ubah/hapus pada kelas ini
 * maupun di controller mana pun (FR-54). Nilai sebelum/sesudah disimpan sebagai JSON agar
 * dapat dibandingkan saat pemeriksaan.
 */
class AuditService
{
    /** Kolom yang tidak boleh disimpan ke jejak audit (rahasia). */
    private const DISAMARKAN = ['kata_sandi', 'password', 'token', 'kode_hash', 'token_qr'];

    /**
     * @param  array<string, mixed>|null  $sebelum
     * @param  array<string, mixed>|null  $sesudah
     */
    public function catat(
        ?Pengguna $pengguna,
        string $aksi,
        string $entitas,
        int|string|null $idEntitas = null,
        ?array $sebelum = null,
        ?array $sesudah = null,
        ?string $alamatIp = null,
    ): void {
        DB::table('audit_log')->insert([
            'id_pengguna'  => $pengguna?->getIdPengguna(),
            'aksi'         => strtoupper(substr($aksi, 0, 40)),
            'entitas'      => substr($entitas, 0, 40),
            'id_entitas'   => $idEntitas === null ? null : (string) $idEntitas,
            'data_sebelum' => $sebelum === null ? null : json_encode($this->bersihkan($sebelum), JSON_UNESCAPED_UNICODE),
            'data_sesudah' => $sesudah === null ? null : json_encode($this->bersihkan($sesudah), JSON_UNESCAPED_UNICODE),
            'alamat_ip'    => $alamatIp ?? request()?->ip(),
            'waktu'        => now(),
        ]);
    }

    /** FR-53: penyaringan jejak audit berdasarkan pengguna, entitas, aksi, dan rentang waktu. */
    public function cari(array $filter = [], int $batas = 100): array
    {
        $q = DB::table('audit_log as a')
            ->leftJoin('pengguna as p', 'p.id_pengguna', '=', 'a.id_pengguna')
            ->select('a.*', 'p.nama_lengkap', 'p.nama_pengguna')
            ->orderByDesc('a.id_audit');

        if (! empty($filter['id_pengguna'])) {
            $q->where('a.id_pengguna', (int) $filter['id_pengguna']);
        }
        if (! empty($filter['entitas'])) {
            $q->where('a.entitas', $filter['entitas']);
        }
        if (! empty($filter['aksi'])) {
            $q->where('a.aksi', strtoupper($filter['aksi']));
        }
        if (! empty($filter['dari'])) {
            $q->where('a.waktu', '>=', $filter['dari'] . ' 00:00:00');
        }
        if (! empty($filter['sampai'])) {
            $q->where('a.waktu', '<=', $filter['sampai'] . ' 23:59:59');
        }

        return $q->limit($batas)->get()->map(fn ($r) => [
            'id_audit'      => (int) $r->id_audit,
            'waktu'         => $r->waktu,
            'pengguna'      => $r->nama_lengkap ?? 'Sistem',
            'nama_pengguna' => $r->nama_pengguna,
            'aksi'          => $r->aksi,
            'entitas'       => $r->entitas,
            'id_entitas'    => $r->id_entitas,
            'data_sebelum'  => $r->data_sebelum === null ? null : json_decode($r->data_sebelum, true),
            'data_sesudah'  => $r->data_sesudah === null ? null : json_decode($r->data_sesudah, true),
            'alamat_ip'     => $r->alamat_ip,
        ])->all();
    }

    /** FR-55: ekspor jejak audit (CSV) sesuai penyaringan yang sama. */
    public function eksporCsv(array $filter = []): string
    {
        $baris = [['id_audit', 'waktu', 'pengguna', 'aksi', 'entitas', 'id_entitas', 'data_sebelum', 'data_sesudah', 'alamat_ip']];
        foreach ($this->cari($filter, 5000) as $r) {
            $baris[] = [
                $r['id_audit'], $r['waktu'], $r['pengguna'], $r['aksi'], $r['entitas'], $r['id_entitas'],
                json_encode($r['data_sebelum'], JSON_UNESCAPED_UNICODE),
                json_encode($r['data_sesudah'], JSON_UNESCAPED_UNICODE),
                $r['alamat_ip'],
            ];
        }
        $keluaran = fopen('php://temp', 'r+');
        foreach ($baris as $b) {
            fputcsv($keluaran, $b);
        }
        rewind($keluaran);
        $isi = stream_get_contents($keluaran);
        fclose($keluaran);

        return $isi;
    }

    private function bersihkan(array $data): array
    {
        foreach (self::DISAMARKAN as $kunci) {
            if (array_key_exists($kunci, $data)) {
                $data[$kunci] = '***';
            }
        }

        return $data;
    }
}
