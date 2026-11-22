<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Konfigurasi;
use App\Models\Pengguna;
use App\Services\AuditService;
use App\Services\TerminalLelangService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Dasbor Administrator Sistem (UC-11): data master, pengguna & peran, konfigurasi, dan jejak audit.
 * Penulisan data master memakai validasi yang sama dengan MasterController API agar aturan
 * tidak berbeda antara mobile dan web.
 */
class AdminController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private TerminalLelangService $terminal,
    ) {
    }

    /** GET /dasbor/admin/master */
    public function master()
    {
        return view('web.admin.master', [
            'entitas' => [
                'jenis-ikan'      => $this->daftar('jenis_ikan', 'id_jenis', ['nama_jenis', 'nama_ilmiah', 'kategori', 'satuan']),
                'grade-mutu'      => $this->daftar('grade_mutu', 'id_grade', ['kode_grade', 'deskripsi', 'faktor_harga']),
                'tarif-retribusi' => $this->daftar('tarif_retribusi', 'id_tarif', ['nama', 'persentase', 'nominal_minimum', 'berlaku_mulai', 'status']),
                'kapal'           => $this->daftar('kapal', 'id_kapal', ['nama_kapal', 'nomor_registrasi', 'gt', 'id_pemilik']),
                'pemilik-kapal'   => $this->daftar('pemilik_kapal', 'id_pemilik', ['nama', 'nik', 'no_telepon', 'nama_bank']),
                'tpi'             => $this->daftar('tpi', 'id_tpi', ['kode_tpi', 'nama_tpi', 'kabupaten', 'kapasitas_ton']),
            ],
        ]);
    }

    /** POST /dasbor/admin/master/{entitas} */
    public function simpanMaster(Request $request, string $entitas)
    {
        $aturan = $this->aturan($entitas);
        $data = $request->validate($aturan, $this->pesanValidasi($entitas));

        $tabel = $this->tabel($entitas);
        // Kolom status bertipe boolean pada MySQL menolak nilai kosong dari formulir.
        if (array_key_exists('status', $data) && $data['status'] === null) {
            unset($data['status']);
        }
        $id = DB::table($tabel)->insertGetId($data);

        $this->audit->catat($request->user(), 'TAMBAH_MASTER_' . strtoupper(str_replace('-', '_', $entitas)),
            str_replace('-', '_', $entitas), $id, null, $data);

        return back()->with('sukses', 'Data master ' . $entitas . ' tersimpan.');
    }

    /** GET /dasbor/admin/pengguna */
    public function pengguna(Request $request)
    {
        $cari = trim((string) $request->query('cari', ''));

        return view('web.admin.pengguna', [
            'cari' => $cari,
            'daftar' => Pengguna::with(['peran', 'tpi'])
                ->when(mb_strlen($cari) >= 3, fn ($q) => $q->where(fn ($w) => $w
                    ->where('nama_lengkap', 'like', "%{$cari}%")
                    ->orWhere('nama_pengguna', 'like', "%{$cari}%")))
                ->orderBy('id_pengguna')
                ->limit(200)
                ->get(),
    ]);
    }

    /** POST /dasbor/admin/pengguna */
    public function simpanPengguna(Request $request)
    {
        $data = $request->validate([
            'nama_lengkap'  => ['required', 'string', 'max:100'],
            'nama_pengguna' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:pengguna,nama_pengguna'],
            'surel'         => ['required', 'email', 'max:120', 'unique:pengguna,surel'],
            'nik'           => ['nullable', 'string', 'size:16', 'unique:pengguna,nik'],
            'no_hp'         => ['nullable', 'string', 'max:15', 'unique:pengguna,no_hp'],
            'kata_sandi'    => ['required', 'string', 'min:8'],
            'id_peran'      => ['required', 'integer', 'exists:peran,id_peran'],
            'id_tpi'        => ['required', 'integer', 'exists:tpi,id_tpi'],
        ], [
            'nama_pengguna.alpha_dash' => 'Nama pengguna hanya boleh huruf, angka, garis bawah, dan tanda hubung.',
            'kata_sandi.min'           => 'Kata sandi minimal 8 karakter.',
            'nik.size'                 => 'NIK harus 16 digit.',
        ]);

        $pengguna = Pengguna::create([
            'nama_lengkap'  => $data['nama_lengkap'],
            'nama_pengguna' => $data['nama_pengguna'],
            'surel'         => $data['surel'],
            'nik'           => $data['nik'] ?? null,
            'no_hp'         => $data['no_hp'] ?? null,
            'kata_sandi'    => Hash::make($data['kata_sandi']),
            'id_peran'      => $data['id_peran'],
            'id_tpi'        => $data['id_tpi'],
            'status_aktif'  => true,
        ]);

        $this->audit->catat($request->user(), 'TAMBAH_PENGGUNA', 'pengguna', $pengguna->getIdPengguna(), null,
            collect($data)->except('kata_sandi')->all());

        return back()->with('sukses', 'Pengguna ' . $pengguna->getNamaLengkap() . ' dibuat.');
    }

    /** POST /dasbor/admin/pengguna/{id}/status */
    public function statusPengguna(Request $request, int $id)
    {
        $data = $request->validate(['aksi' => ['required', 'in:aktifkan,nonaktifkan']]);
        $pengguna = Pengguna::findOrFail($id);

        if ($pengguna->getIdPengguna() === $request->user()->getIdPengguna()) {
            return back()->withErrors(['aksi' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }

        $sebelum = ['status_aktif' => (bool) $pengguna->status_aktif];
        $pengguna->status_aktif = $data['aksi'] === 'aktifkan';
        $pengguna->save();
        if (! $pengguna->status_aktif) {
            $pengguna->tokens()->delete();
        }

        $this->audit->catat($request->user(), strtoupper($data['aksi']) . '_PENGGUNA', 'pengguna', $id,
            $sebelum, ['status_aktif' => $pengguna->status_aktif]);

        return back()->with('sukses', 'Status pengguna diperbarui. Riwayat transaksinya tetap tersimpan (FR-51).');
    }

    /** GET /dasbor/admin/konfigurasi */
    public function konfigurasi()
    {
        return view('web.admin.konfigurasi', [
            'daftar'        => Konfigurasi::orderBy('kunci')->get(),
            'jarakJauhAktif' => $this->terminal->jarakJauhAktif(),
        ]);
    }

    /**
     * POST /dasbor/admin/lelang-jarak-jauh
     * FR-63 + BT-03: mematikan kanal jarak jauh tidak mengganggu lelang di tempat,
     * karena penawaran DI_TEMPAT dari dasbor operator tidak melewati saklar ini.
     */
    public function saklarJarakJauh(Request $request)
    {
        $data = $request->validate([
            'aktif' => ['required', Rule::in(['0', '1'])],
        ], [
            'aktif.required' => 'Pilih keadaan kanal jarak jauh.',
        ]);

        $sebelum = $this->terminal->jarakJauhAktif();
        $this->terminal->ubahJarakJauh($request->user(), $data['aktif'] === '1');
        $this->audit->catat($request->user(), 'UBAH_KANAL_JARAK_JAUH', 'konfigurasi', null,
            ['aktif' => $sebelum], ['aktif' => $data['aktif'] === '1']);

        $pesan = $data['aktif'] === '1'
            ? 'Kanal jarak jauh dibuka. Aplikasi mobile dapat menawar lagi.'
            : 'Kanal jarak jauh dimatikan. Lelang di tempat tetap berjalan dari dasbor operator.';

        return back()->with('sukses', $pesan);
    }

    /** POST /dasbor/admin/konfigurasi */
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

        return back()->with('sukses', 'Konfigurasi ' . $data['kunci'] . ' diperbarui dan tercatat pada jejak audit.');
    }

    /** GET /dasbor/admin/audit */
    public function audit(Request $request)
    {
        $filter = $request->validate([
            'entitas' => ['nullable', 'string', 'max:60'],
            'aksi'    => ['nullable', 'string', 'max:60'],
            'dari'    => ['nullable', 'date_format:Y-m-d'],
            'sampai'  => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ]);

        return view('web.admin.audit', [
            'filter' => $filter,
            'baris'  => $this->audit->cari(array_filter($filter), 200),
        ]);
    }

    // ---------------- internal ----------------

    /** @return array<int, array<string, mixed>> */
    private function daftar(string $tabel, string $kunci, array $kolom): array
    {
        return DB::table($tabel)->orderBy($kunci)->limit(200)
            ->get($kolom)->map(fn ($r) => (array) $r)->all();
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
            default           => abort(404, 'Entitas master tidak dikenal.'),
        };
    }

    private function aturan(string $entitas): array
    {
        return match ($entitas) {
            'jenis-ikan' => [
                'nama_jenis'  => ['required', 'string', 'max:80', Rule::unique('jenis_ikan', 'nama_jenis')],
                'nama_ilmiah' => ['nullable', 'string', 'max:120'],
                'kategori'    => ['required', 'string', 'max:30'],
                'satuan'      => ['required', 'string', 'max:20'],
                'status'      => ['nullable', 'boolean'],
            ],
            'grade-mutu' => [
                'kode_grade'   => ['required', 'string', 'max:2', Rule::unique('grade_mutu', 'kode_grade')],
                'deskripsi'    => ['nullable', 'string', 'max:200'],
                'faktor_harga' => ['required', 'numeric', 'min:0.1', 'max:5'],
            ],
            'tarif-retribusi' => [
                'nama'            => ['required', 'string', 'max:80'],
                'persentase'      => ['required', 'numeric', 'min:0', 'max:100'],
                'nominal_minimum' => ['required', 'numeric', 'min:0'],
                'berlaku_mulai'   => ['required', 'date_format:Y-m-d'],
                'berlaku_sampai'  => ['nullable', 'date_format:Y-m-d', 'after_or_equal:berlaku_mulai'],
                'status'          => ['nullable', 'boolean'],
            ],
            'kapal' => [
                'nama_kapal'       => ['required', 'string', 'max:100'],
                'nomor_registrasi' => ['required', 'string', 'max:40', Rule::unique('kapal', 'nomor_registrasi')],
                'gt'               => ['nullable', 'numeric', 'min:0'],
                'id_pemilik'       => ['required', 'integer', 'exists:pemilik_kapal,id_pemilik'],
                'status'           => ['nullable', 'string', 'max:20'],
            ],
            'pemilik-kapal' => [
                'nama'        => ['required', 'string', 'max:100'],
                'nik'         => ['required', 'string', 'size:16', Rule::unique('pemilik_kapal', 'nik')],
                'no_telepon'  => ['nullable', 'string', 'max:20'],
                'alamat'      => ['nullable', 'string', 'max:200'],
                'no_rekening' => ['nullable', 'string', 'max:30'],
                'nama_bank'   => ['nullable', 'string', 'max:50'],
            ],
            'tpi' => [
                'kode_tpi'      => ['required', 'string', 'max:5', Rule::unique('tpi', 'kode_tpi')],
                'nama_tpi'      => ['required', 'string', 'max:100'],
                'alamat'        => ['nullable', 'string', 'max:200'],
                'kabupaten'     => ['nullable', 'string', 'max:80'],
                'kapasitas_ton' => ['nullable', 'numeric', 'min:0'],
                'jam_operasi'   => ['nullable', 'string', 'max:40'],
            ],
            default => [],
        };
    }

    private function pesanValidasi(string $entitas): array
    {
        return $entitas === 'pemilik-kapal'
            ? ['nik.size' => 'NIK pemilik kapal harus 16 digit.']
            : [];
    }
}
