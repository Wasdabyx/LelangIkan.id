<?php

namespace App\Http\Requests;

use App\Models\Konfigurasi;
use App\Models\Tangkapan;
use App\Services\PenimbanganService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validasi UC-02 (FR-07, FR-09, FR-10) dan DPPL Tabel 3.6
 */
class SimpanLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // hak akses sudah diperiksa middleware "peran"
    }

    public function rules(): array
    {
        return [
            'id_tangkapan'      => ['required', 'integer', 'exists:tangkapan,id_tangkapan'],
            'berat_keranjang'   => ['required', 'array', 'min:1', 'max:200'],
            'berat_keranjang.*' => ['required', 'numeric', 'gt:0', 'max:9999.99'],
            'timbang_manual'    => ['required', 'boolean'],
            'perangkat_timbang' => ['nullable', 'string', 'max:50'],
            'alasan'            => ['nullable', 'string', 'max:200'],
            'harga_pembukaan'   => ['nullable', 'numeric', 'min:100', 'max:99999999'],
        ];
    }

    /** Aturan yang bergantung pada data lain: status tangkapan, alasan manual, selisih > ambang */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['id_tangkapan', 'berat_keranjang', 'berat_keranjang.*'])) {
                    return;
                }
                $t = Tangkapan::find($this->id_tangkapan);
                if ($t->status === 'DITIMBANG') {
                    $validator->errors()->add('id_tangkapan', 'Baris tangkapan ini sudah ditimbang.');
                    return;
                }

                $alasan = trim((string) $this->alasan);

                // Alur A1: input manual wajib alasan
                if ($this->boolean('timbang_manual') && $alasan === '') {
                    $validator->errors()->add('alasan', 'Alasan input manual wajib diisi.');
                    return;
                }

                // Alur A2 / ALG-08: selisih > ambang wajib dikonfirmasi dengan alasan
                $aktual  = array_sum(array_map('floatval', $this->berat_keranjang));
                $selisih = app(PenimbanganService::class)->hitungSelisih($aktual, (float) $t->berat_estimasi);
                $ambang  = (float) Konfigurasi::nilai('ambang_selisih_berat', 15);
                if ($selisih > $ambang && $alasan === '') {
                    $validator->errors()->add(
                        'alasan',
                        "Selisih berat {$selisih}% melebihi {$ambang}%. Timbang ulang atau isi alasan konfirmasi."
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'id_tangkapan.required'      => 'Baris tangkapan wajib dipilih.',
            'id_tangkapan.exists'        => 'Baris tangkapan tidak ditemukan.',
            'berat_keranjang.required'   => 'Timbang minimal satu keranjang.',
            'berat_keranjang.min'        => 'Timbang minimal satu keranjang.',
            'berat_keranjang.max'        => 'Jumlah keranjang terlalu banyak.',
            'berat_keranjang.*.numeric'  => 'Berat keranjang harus berupa angka.',
            'berat_keranjang.*.gt'       => 'Berat keranjang harus lebih dari 0 kg.',
            'berat_keranjang.*.max'      => 'Berat keranjang terlalu besar.',
            'timbang_manual.required'    => 'Sumber berat (timbangan/manual) wajib dikirim.',
            'alasan.max'                 => 'Alasan maksimal 200 karakter.',
            'harga_pembukaan.numeric'    => 'Harga pembukaan harus berupa angka.',
            'harga_pembukaan.min'        => 'Harga pembukaan minimal Rp100 per kg.',
            'harga_pembukaan.max'        => 'Harga pembukaan terlalu besar.',
        ];
    }
}
