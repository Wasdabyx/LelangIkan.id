<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KartuNelayan;
use App\Models\PemilikKapal;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * UC-11 FR-66: kasir atau administrator menerbitkan dan memblokir kartu nelayan ber-QR;
 * kartu dipindai saat pendataan kedatangan untuk mengisi data pemilik kapal secara otomatis.
 */
class KartuNelayanController extends Controller
{
    public function daftar(Request $request)
    {
        $cari = trim((string) $request->query('cari', ''));
        if ($cari !== '' && mb_strlen($cari) < 3) {
            throw ValidationException::withMessages(['cari' => 'Kata kunci pencarian minimal 3 karakter.']);
        }

        $data = KartuNelayan::with('pemilik')
            ->when($cari !== '', function ($q) use ($cari) {
                $q->where('nomor_kartu', 'like', "%{$cari}%")
                    ->orWhereHas('pemilik', fn ($p) => $p->where('nama', 'like', "%{$cari}%"));
            })
            ->orderByDesc('id_kartu')
            ->limit(50)
            ->get()
            ->map(fn (KartuNelayan $k) => $k->ringkas())
            ->all();

        return response()->json(['data' => $data]);
    }

    /** Terbitkan kartu untuk pemilik kapal. */
    public function terbitkan(Request $request)
    {
        $data = $request->validate([
            'id_pemilik' => ['required', 'integer', 'exists:pemilik_kapal,id_pemilik'],
        ]);

        $pemilik = PemilikKapal::findOrFail($data['id_pemilik']);
        $aktif = KartuNelayan::where('id_pemilik', $pemilik->getIdPemilik())->where('status', KartuNelayan::AKTIF)->first();
        if ($aktif) {
            throw ValidationException::withMessages([
                'id_pemilik' => 'Pemilik ini masih memiliki kartu aktif (' . $aktif->getNomorKartu() . ').',
            ]);
        }

        $kartu = KartuNelayan::terbitkan($pemilik);
        $kartu->load('pemilik');

        return response()->json([
            'pesan' => 'Kartu nelayan diterbitkan.',
            'data'  => $kartu->ringkas() + ['token_qr' => $kartu->getTokenQr()],
        ], 201);
    }

    public function blokir(Request $request, int $id)
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:200']]);

        $kartu = KartuNelayan::findOrFail($id);
        if ($kartu->getStatus() === KartuNelayan::DIBLOKIR) {
            throw ValidationException::withMessages(['id_kartu' => 'Kartu ini sudah diblokir sebelumnya.']);
        }
        $kartu->blokir($data['alasan']);

        return response()->json(['pesan' => 'Kartu nelayan diblokir.', 'data' => $kartu->fresh('pemilik')->ringkas()]);
    }

    public function aktifkan(int $id)
    {
        $kartu = KartuNelayan::findOrFail($id);
        if (! $kartu->berlaku_sampai->isFuture()) {
            throw ValidationException::withMessages(['id_kartu' => 'Masa berlaku kartu sudah lewat. Terbitkan kartu baru.']);
        }
        $kartu->aktifkan();

        return response()->json(['pesan' => 'Kartu nelayan diaktifkan kembali.', 'data' => $kartu->fresh('pemilik')->ringkas()]);
    }

    /** Pindai QR kartu (dipakai layar pendataan kedatangan). */
    public function pindai(Request $request)
    {
        $data = $request->validate(['token_qr' => ['required', 'string', 'max:64']]);

        $kartu = KartuNelayan::cariDariQr($data['token_qr']);
        if (! $kartu) {
            throw ValidationException::withMessages(['token_qr' => 'Kartu tidak dikenali.']);
        }
        if (! $kartu->masihBerlaku()) {
            throw ValidationException::withMessages(['token_qr' => 'Kartu tidak aktif atau sudah kedaluwarsa.']);
        }

        $pemilik = $kartu->pemilik;

        return response()->json([
            'data' => [
                'kartu'       => $kartu->ringkas(),
                'pemilik'     => [
                    'id_pemilik'   => $pemilik?->getIdPemilik(),
                    'nama'         => $pemilik?->getNama(),
                    'no_telepon'   => $pemilik?->getNoTelepon(),
                ],
                'daftar_kapal' => $pemilik?->daftarKapal()->map(fn ($k) => [
                    'id_kapal'       => $k->getIdKapal(),
                    'nama_kapal'     => $k->getNamaKapal(),
                    'nomor_registrasi' => $k->getNomorRegistrasi(),
                ])->values()->all(),
            ],
        ]);
    }
}
