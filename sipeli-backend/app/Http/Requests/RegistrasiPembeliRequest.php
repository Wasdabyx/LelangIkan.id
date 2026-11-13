<?php

namespace App\Http\Requests;

use App\Support\PolaInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi formulir Registrasi Pembeli (Tugas Week 9, UC-11, AS-03).
 * Setiap kolom diperiksa dengan Regular Expression dari App\Support\PolaInput.
 * Aturan memakai bentuk array karena beberapa pola mengandung karakter "|".
 */
class RegistrasiPembeliRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // terbuka untuk tamu (belum punya akun)
    }

    /** Rapikan isian sebelum divalidasi. */
    protected function prepareForValidation(): void
    {
        $rapikan = fn ($v) => is_string($v) ? trim(preg_replace('/\s+/', ' ', $v)) : $v;

        $this->merge([
            'nama_lengkap'  => $rapikan($this->input('nama_lengkap')),
            'nik'           => is_string($this->input('nik')) ? trim($this->input('nik')) : $this->input('nik'),
            'no_hp'         => is_string($this->input('no_hp')) ? self::rapikanHp($this->input('no_hp')) : $this->input('no_hp'),
            'surel'         => is_string($this->input('surel')) ? strtolower(trim($this->input('surel'))) : $this->input('surel'),
            'nama_pengguna' => is_string($this->input('nama_pengguna')) ? strtolower(trim($this->input('nama_pengguna'))) : $this->input('nama_pengguna'),
        ]);
    }

    /**
     * Spasi dan strip dibuang, awalan +62 / 62 diganti 0 agar satu nomor hanya punya satu bentuk.
     * "+62 812-3456-7890" -> "081234567890"
     */
    public static function rapikanHp(string $hp): string
    {
        $hp = preg_replace('/[\s-]/', '', $hp);

        return preg_replace('/^(\+62|62)(?=8)/', '0', $hp);
    }

    public function rules(): array
    {
        return [
            'nama_lengkap'     => ['required', 'string', 'max:100', 'regex:' . PolaInput::NAMA_ORANG],
            'nik'              => ['required', 'string', 'regex:' . PolaInput::NIK, 'unique:pengguna,nik'],
            'no_hp'            => ['required', 'string', 'regex:' . PolaInput::NO_HP, 'unique:pengguna,no_hp'],
            'surel'            => ['required', 'string', 'max:120', 'regex:' . PolaInput::SUREL, 'unique:pengguna,surel'],
            'nama_pengguna'    => ['required', 'string', 'regex:' . PolaInput::NAMA_PENGGUNA, 'unique:pengguna,nama_pengguna'],
            'kata_sandi'       => ['required', 'string', 'regex:' . PolaInput::SANDI_KUAT],
            'konfirmasi_sandi' => ['required', 'same:kata_sandi'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_lengkap.required'     => 'Nama lengkap wajib diisi.',
            'nama_lengkap.max'          => 'Nama lengkap maksimal 100 karakter.',
            'nama_lengkap.regex'        => 'Nama lengkap hanya boleh huruf, spasi, titik, koma, petik, dan strip (minimal 3 karakter).',
            'nik.required'              => 'NIK wajib diisi.',
            'nik.regex'                 => 'NIK harus 16 digit angka dengan kode wilayah dan tanggal lahir yang valid.',
            'nik.unique'                => 'NIK ini sudah terdaftar.',
            'no_hp.required'            => 'Nomor HP wajib diisi.',
            'no_hp.regex'               => 'Nomor HP harus diawali 08, 628, atau +628 dan berisi 10-13 digit.',
            'no_hp.unique'              => 'Nomor HP ini sudah terdaftar.',
            'surel.required'            => 'Surel wajib diisi.',
            'surel.max'                 => 'Surel maksimal 120 karakter.',
            'surel.regex'               => 'Format surel tidak valid (contoh: nama@contoh.com).',
            'surel.unique'              => 'Surel ini sudah terdaftar.',
            'nama_pengguna.required'    => 'Nama pengguna wajib diisi.',
            'nama_pengguna.regex'       => 'Nama pengguna 4-30 karakter: diawali huruf kecil, lalu huruf kecil, angka, titik, atau garis bawah.',
            'nama_pengguna.unique'      => 'Nama pengguna ini sudah dipakai.',
            'kata_sandi.required'       => 'Kata sandi wajib diisi.',
            'kata_sandi.regex'          => 'Kata sandi 8-64 karakter, wajib ada huruf besar, huruf kecil, angka, dan simbol, tanpa spasi.',
            'konfirmasi_sandi.required' => 'Konfirmasi kata sandi wajib diisi.',
            'konfirmasi_sandi.same'     => 'Konfirmasi kata sandi tidak sama.',
        ];
    }
}
