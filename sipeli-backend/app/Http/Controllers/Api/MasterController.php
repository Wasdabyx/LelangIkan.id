<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradeMutu;
use App\Models\JenisIkan;
use App\Models\Kapal;
use App\Models\Konfigurasi;
use App\Models\PemilikKapal;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\TarifRetribusi;
use App\Models\Tpi;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * UC-11: pengelolaan data master dan pengguna (Administrator Sistem, A7).
 *
 * FR-48 jenis ikan, grade mutu, tarif retribusi, kapal, pemilik kapal
 * FR-49 pengguna, peran, dan penugasan pengguna pada TPI tertentu
 * FR-50 konfigurasi lelang (durasi bawaan, kenaikan minimum, ambang perpanjangan, batas perpanjangan)
 * FR-51 menonaktifkan pengguna tanpa menghapus jejak transaksi
 *
 * Rute berbentuk POST /api/master/{entitas} sesuai SKPL Tabel IV.6, dilengkapi GET/PUT untuk
 * pembacaan dan penyuntingan. Setiap perubahan dicatat pada jejak audit (FR-52).
 */
class MasterController extends Controller
{
    /** Daftar entitas master yang didukung. */
    private const ENTITAS = [
        'jenis-ikan'  => JenisIkan::class,
        'grade-mutu'  => GradeMutu::class,
        'tarif-retribusi' => TarifRetribusi::class,
        'kapal'       => Kapal::class,
        'pemilik-kapal' => PemilikKapal::class,
        'tpi'         => Tpi::class,
        'peran'       => Peran::class,
    ];

    public function __construct(private AuditService $audit)
    {
    }

    /** GET /api/master/{entitas}: daftar isi tabel master. */
    public function index(Request $request, string $entitas)
    {
        return match ($entitas) {
            'konfigurasi' => response()->json(['data' => $this->daftarKonfigurasi()]),
            'pengguna'    => response()->json(['data' => $this->daftarPengguna($request)]),
            'peran'       => response()->json(['data' => Peran::orderBy('id_peran')->get()]),
            default       => response()->json(['data' => $this->model($entitas)::orderBy($this->kunci($entitas))->limit(300)->get()]),
        };
    }

    /** POST /api/master/{entitas}: tambah baris master baru. */
    public function simpan(Request $request, string $entitas)
    {
        if ($entitas === 'konfigurasi') {
            return $this->ubahKonfigurasi($request);
        }
        if ($entitas === 'pengguna') {
            return $this->simpanPengguna($request);
        }

        $kelas = $this->model($entitas);
        $data = $request->validate($this->aturan($entitas, null));

        $baris = $kelas::create($data);
        $this->audit->catat($request->user(), 'TAMBAH_MASTER_' . strtoupper(str_replace('-', '_', $entitas)),
            str_replace('-', '_', $entitas), $baris->getKey(), null, $data);

        return response()->json(['pesan' => 'Data master tersimpan.', 'data' => $baris], 201);
    }

    /** PUT/PATCH /api/master/{entitas}/{id}: ubah baris master. */
    public function perbarui(Request $request, string $entitas, int $id)
    {
        if ($entitas === 'pengguna') {
            return $this->perbaruiPengguna($request, $id);
        }

        $kelas = $this->model($entitas);
        $baris = $kelas::findOrFail($id);
        $sebelum = $baris->toArray();
        $data = $request->validate($this->aturan($entitas, $id));

        $baris->fill($data)->save();
        $this->audit->catat($request->user(), 'UBAH_MASTER_' . strtoupper(str_replace('-', '_', $entitas)),
            str_replace('-', '_', $entitas), $id, $sebelum, $data);

        return response()->json(['pesan' => 'Data master diperbarui.', 'data' => $baris->fresh()]);
    }

    /**
     * FR-51: nonaktifkan pengguna tanpa menghapus data. Transaksi historis tetap utuh karena
     * baris pengguna tidak pernah dihapus; hanya status_aktif yang berubah.
     */
    public function nonaktifkan(Request $request, int $id)
    {
        $pengguna = Pengguna::findOrFail($id);
        if ($pengguna->getIdPengguna() === $request->user()->getIdPengguna()) {
            throw ValidationException::withMessages(['id_pengguna' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }

        $sebelum = ['status_aktif' => (bool) $pengguna->status_aktif];
        $pengguna->status_aktif = false;
        $pengguna->save();
        $pengguna->tokens()->delete();                    // sesi lama tidak dapat dipakai lagi

        $this->audit->catat($request->user(), 'NONAKTIFKAN_PENGGUNA', 'pengguna', $id, $sebelum,
            ['status_aktif' => false]);

        return response()->json(['pesan' => 'Pengguna dinonaktifkan. Riwayat transaksinya tetap tersimpan.']);
    }

    public function aktifkan(Request $request, int $id)
    {
        $pengguna = Pengguna::findOrFail($id);
        $sebelum = ['status_aktif' => (bool) $pengguna->status_aktif];
        $pengguna->status_aktif = true;
        $pengguna->save();
        $this->audit->catat($request->user(), 'AKTIFKAN_PENGGUNA', 'pengguna', $id, $sebelum, ['status_aktif' => true]);

        return response()->json(['pesan' => 'Pengguna diaktifkan kembali.']);
    }

    /** FR-50: konfigurasi lelang hanya dapat diubah administrator dan tercatat pada audit. */
    public function ubahKonfigurasi(Request $request)
    {
        $data = $request->validate([
            'kunci' => ['required', 'string', 'max:60', 'exists:konfigurasi,kunci'],
            'nilai' => ['required', 'string', 'max:100'],
        ]);

        $baris = Konfigurasi::where('kunci', $data['kunci'])->firstOrFail();
        $sebelum = ['nilai' => $baris->getNilai()];
        $baris->ubahNilai($data['nilai'], $request->user()->getIdPengguna());
        $this->audit->catat($request->user(), 'UBAH_KONFIGURASI', 'konfigurasi', $baris->getIdKonfigurasi(),
            $sebelum, ['nilai' => $data['nilai']]);

        return response()->json(['pesan' => 'Konfigurasi diperbarui.', 'data' => [
            'kunci' => $baris->getKunci(), 'nilai' => $baris->getNilai(), 'deskripsi' => $baris->getDeskripsi(),
        ]]);
    }

    // ---------------- internal ----------------

    private function model(string $entitas): string
    {
        abort_unless(isset(self::ENTITAS[$entitas]), 404, 'Entitas master tidak dikenal.');

        return self::ENTITAS[$entitas];
    }

    private function kunci(string $entitas): string
    {
        return match ($entitas) {
            'jenis-ikan'      => 'id_jenis',
            'grade-mutu'      => 'id_grade',
            'tarif-retribusi' => 'id_tarif',
            'kapal'           => 'id_kapal',
            'pemilik-kapal'   => 'id_pemilik',
            'tpi'             => 'id_tpi',
            'peran'           => 'id_peran',
            default           => 'id',
        };
    }

    private function aturan(string $entitas, ?int $id): array
    {
        $unik = fn (string $kolom) => Rule::unique($this->tabel($entitas), $kolom)->ignore($id, $this->kunci($entitas));

        return match ($entitas) {
            'jenis-ikan' => [
                'nama_jenis'   => ['required', 'string', 'max:80', $unik('nama_jenis')],
                'nama_ilmiah'  => ['nullable', 'string', 'max:120'],
                'kategori'     => ['required', 'string', 'max:30'],
                'satuan'       => ['required', 'string', 'max:20'],
            ],
            'grade-mutu' => [
                'kode_grade'    => ['required', 'string', 'max:2', $unik('kode_grade')],
                'deskripsi'     => ['nullable', 'string', 'max:200'],
                'faktor_harga'  => ['required', 'numeric', 'min:0.1', 'max:5'],
            ],
            'tarif-retribusi' => [
                'nama'            => ['required', 'string', 'max:80'],
                'persentase'      => ['required', 'numeric', 'min:0', 'max:100'],
                'nominal_minimum' => ['required', 'numeric', 'min:0'],
                'berlaku_mulai'   => ['required', 'date_format:Y-m-d'],
                'berlaku_sampai'  => ['nullable', 'date_format:Y-m-d', 'after_or_equal:berlaku_mulai'],
                'status'          => ['required', 'boolean'],
            ],
            'kapal' => [
                'nama_kapal'       => ['required', 'string', 'max:100'],
                'nomor_registrasi' => ['required', 'string', 'max:40', $unik('nomor_registrasi')],
                'gt'               => ['nullable', 'numeric', 'min:0'],
                'id_pemilik'       => ['required', 'integer', 'exists:pemilik_kapal,id_pemilik'],
                'status'           => ['nullable', 'string', 'max:20'],
            ],
            'pemilik-kapal' => [
                'nama'        => ['required', 'string', 'max:100'],
                'nik'         => ['required', 'string', 'size:16', $unik('nik')],
                'no_telepon'  => ['nullable', 'string', 'max:20'],
                'alamat'      => ['nullable', 'string', 'max:200'],
                'no_rekening' => ['nullable', 'string', 'max:30'],
                'nama_bank'   => ['nullable', 'string', 'max:50'],
            ],
            'tpi' => [
                'kode_tpi'      => ['required', 'string', 'max:5', $unik('kode_tpi')],
                'nama_tpi'      => ['required', 'string', 'max:100'],
                'alamat'        => ['nullable', 'string', 'max:200'],
                'kabupaten'     => ['nullable', 'string', 'max:80'],
                'kapasitas_ton' => ['nullable', 'numeric', 'min:0'],
                'jam_operasi'   => ['nullable', 'string', 'max:40'],
            ],
            'peran' => [
                'nama_peran' => ['required', 'string', 'max:40', $unik('nama_peran')],
                'deskripsi'  => ['nullable', 'string', 'max:200'],
            ],
            default => [],
        };
    }

    private function tabel(string $entitas): string
    {
        return match ($entitas) {
            'jenis-ikan'      => 'jenis_ikan',
            'grade-mutu'      => 'grade_mutu',
            'tarif-retribusi' => 'tarif_retribusi',
            'kapal'           => 'kapal',
            'pemilik-kapal'   => 'pemilik_kapal',
            'tpi'             => 'tpi',
            'peran'           => 'peran',
            default           => $entitas,
        };
    }

    private function daftarKonfigurasi(): array
    {
        return Konfigurasi::orderBy('kunci')->get()->map(fn (Konfigurasi $k) => [
            'kunci'     => $k->getKunci(),
            'nilai'     => $k->getNilai(),
            'deskripsi' => $k->getDeskripsi(),
        ])->all();
    }

    private function daftarPengguna(Request $request): array
    {
        $cari = trim((string) $request->query('cari', ''));
        if ($cari !== '' && mb_strlen($cari) < 3) {
            throw ValidationException::withMessages(['cari' => 'Kata kunci pencarian minimal 3 karakter.']);
        }

        return Pengguna::with(['peran', 'tpi'])
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w->where('nama_lengkap', 'like', "%{$cari}%")
                ->orWhere('nama_pengguna', 'like', "%{$cari}%")))
            ->orderBy('id_pengguna')
            ->limit(200)
            ->get()
            ->map(fn (Pengguna $p) => [
                'id_pengguna'  => $p->getIdPengguna(),
                'nama_lengkap' => $p->getNamaLengkap(),
                'nama_pengguna' => $p->nama_pengguna,
                'surel'        => $p->surel,
                'id_peran'     => $p->getIdPeran(),
                'nama_peran'   => $p->peran?->nama_peran,
                'id_tpi'       => $p->getIdTpi(),
                'nama_tpi'     => $p->tpi?->getNamaTpi(),
                'status_aktif' => (bool) $p->status_aktif,
            ])->all();
    }

    /** FR-49: administrator membuat pengguna baru beserta peran dan penugasan TPI. */
    private function simpanPengguna(Request $request)
    {
        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:100'],
            'nama_pengguna' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:pengguna,nama_pengguna'],
            'surel'        => ['required', 'email', 'max:120', 'unique:pengguna,surel'],
            'nik'          => ['nullable', 'string', 'size:16', 'unique:pengguna,nik'],
            'no_hp'        => ['nullable', 'string', 'max:15', 'unique:pengguna,no_hp'],
            'kata_sandi'   => ['required', 'string', 'min:8'],
            'id_peran'     => ['required', 'integer', 'exists:peran,id_peran'],
            'id_tpi'       => ['required', 'integer', 'exists:tpi,id_tpi'],
        ]);

        $pengguna = Pengguna::create([
            'nama_lengkap' => $data['nama_lengkap'],
            'nama_pengguna' => $data['nama_pengguna'],
            'surel'        => $data['surel'],
            'nik'          => $data['nik'] ?? null,
            'no_hp'        => $data['no_hp'] ?? null,
            'kata_sandi'   => Hash::make($data['kata_sandi']),
            'id_peran'     => $data['id_peran'],
            'id_tpi'       => $data['id_tpi'],
            'status_aktif' => true,
        ]);

        $this->audit->catat($request->user(), 'TAMBAH_PENGGUNA', 'pengguna', $pengguna->getIdPengguna(), null,
            collect($data)->except('kata_sandi')->all());

        return response()->json([
            'pesan' => 'Pengguna baru dibuat.',
            'data'  => ['id_pengguna' => $pengguna->getIdPengguna(), 'nama_pengguna' => $pengguna->nama_pengguna],
        ], 201);
    }

    /** FR-49: ubah peran/penugasan TPI; kata sandi hanya diubah bila diisi. */
    private function perbaruiPengguna(Request $request, int $id)
    {
        $pengguna = Pengguna::findOrFail($id);
        $data = $request->validate([
            'nama_lengkap' => ['sometimes', 'string', 'max:100'],
            'id_peran'     => ['sometimes', 'integer', 'exists:peran,id_peran'],
            'id_tpi'       => ['sometimes', 'integer', 'exists:tpi,id_tpi'],
            'kata_sandi'   => ['sometimes', 'string', 'min:8'],
        ]);

        $sebelum = ['id_peran' => $pengguna->getIdPeran(), 'id_tpi' => $pengguna->getIdTpi()];
        if (isset($data['nama_lengkap'])) {
            $pengguna->nama_lengkap = $data['nama_lengkap'];
        }
        if (isset($data['id_peran'])) {
            $pengguna->id_peran = $data['id_peran'];
        }
        if (isset($data['id_tpi'])) {
            $pengguna->id_tpi = $data['id_tpi'];
        }
        if (isset($data['kata_sandi'])) {
            $pengguna->kata_sandi = Hash::make($data['kata_sandi']);
            $pengguna->tokens()->delete();
        }
        $pengguna->save();

        $this->audit->catat($request->user(), 'UBAH_PENGGUNA', 'pengguna', $id, $sebelum,
            ['id_peran' => $pengguna->getIdPeran(), 'id_tpi' => $pengguna->getIdTpi()]);

        return response()->json(['pesan' => 'Data pengguna diperbarui.']);
    }
}
