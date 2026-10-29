<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi UC-01 (FR-01, FR-03, FR-04) dan DPPL Tabel 3.2
 * Dipakai untuk simpan baru (POST) dan menyunting draf (PUT).
 */
class SimpanKedatanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // hak akses sudah diperiksa middleware "peran"
    }

    public function rules(): array
    {
        $draf = $this->input('status') === 'DRAF';

        return [
            'id_kapal'     => ['required', 'integer', Rule::exists('kapal', 'id_kapal')->where('status', 'AKTIF')],
            'tanggal'      => ['required', 'date_format:Y-m-d'],
            'jam'          => ['required', 'date_format:H:i'],
            'nama_kapten'  => ['required', 'string', 'max:100'],
            'jumlah_abk'   => ['required', 'integer', 'between:0,60'],
            // Saat menyunting draf (PUT /kedatangan/{id}), nomor surat milik draf itu sendiri tidak dihitung ganda
            'nomor_surat'  => ['required', 'string', 'max:50',
                Rule::unique('kedatangan_kapal', 'nomor_surat')->ignore($this->route('id'), 'id_kedatangan')],
            'status'       => ['nullable', 'in:DRAF,TERDAFTAR'],

            // Draf boleh tanpa baris tangkapan; TERDAFTAR minimal 1 baris
            'tangkapan'                    => $draf ? ['nullable', 'array'] : ['required', 'array', 'min:1'],
            'tangkapan.*.id_jenis'         => ['required', 'integer', Rule::exists('jenis_ikan', 'id_jenis')->where('status', 1)],
            'tangkapan.*.id_grade'         => ['required', 'integer', 'exists:grade_mutu,id_grade'],
            'tangkapan.*.berat_estimasi'   => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'tangkapan.*.jumlah_keranjang' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /** Aturan tambahan: tanggal + jam tidak boleh melebihi waktu sekarang */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['tanggal', 'jam'])) {
                    return;
                }
                $waktu = Carbon::createFromFormat('Y-m-d H:i', $this->tanggal . ' ' . $this->jam);
                if ($waktu->greaterThan(now()->addMinutes(5))) {
                    $validator->errors()->add('jam', 'Tanggal dan jam kedatangan tidak boleh melebihi waktu sekarang.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'id_kapal.required'    => 'Kapal wajib dipilih.',
            'id_kapal.exists'      => 'Kapal tidak ditemukan atau tidak aktif.',
            'tanggal.required'     => 'Tanggal wajib diisi.',
            'tanggal.date_format'  => 'Format tanggal harus TTTT-BB-HH.',
            'jam.required'         => 'Jam wajib diisi.',
            'jam.date_format'      => 'Format jam harus JJ:MM.',
            'nama_kapten.required' => 'Nama kapten wajib diisi.',
            'nama_kapten.max'      => 'Nama kapten maksimal 100 karakter.',
            'jumlah_abk.required'  => 'Jumlah ABK wajib diisi.',
            'jumlah_abk.integer'   => 'Jumlah ABK harus bilangan bulat.',
            'jumlah_abk.between'   => 'Jumlah ABK harus antara 0 dan 60.',
            'nomor_surat.required' => 'Nomor surat wajib diisi.',
            'nomor_surat.unique'   => 'Nomor surat ini sudah pernah dipakai.',
            'nomor_surat.max'      => 'Nomor surat maksimal 50 karakter.',
            'tangkapan.required'   => 'Tambahkan minimal satu baris tangkapan.',
            'tangkapan.min'        => 'Tambahkan minimal satu baris tangkapan.',

            'tangkapan.*.id_jenis.required'         => 'Jenis ikan wajib dipilih.',
            'tangkapan.*.id_jenis.exists'           => 'Jenis ikan tidak valid.',
            'tangkapan.*.id_grade.required'         => 'Grade wajib dipilih.',
            'tangkapan.*.id_grade.exists'           => 'Grade tidak valid.',
            'tangkapan.*.berat_estimasi.required'   => 'Berat estimasi wajib diisi.',
            'tangkapan.*.berat_estimasi.numeric'    => 'Berat estimasi harus berupa angka.',
            'tangkapan.*.berat_estimasi.gt'         => 'Berat estimasi harus lebih dari 0 kg.',
            'tangkapan.*.berat_estimasi.max'        => 'Berat estimasi terlalu besar.',
            'tangkapan.*.jumlah_keranjang.required' => 'Jumlah keranjang wajib diisi.',
            'tangkapan.*.jumlah_keranjang.integer'  => 'Jumlah keranjang harus bilangan bulat.',
            'tangkapan.*.jumlah_keranjang.min'      => 'Jumlah keranjang minimal 1.',
            'tangkapan.*.jumlah_keranjang.max'      => 'Jumlah keranjang terlalu besar.',
        ];
    }
}
