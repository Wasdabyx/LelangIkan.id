<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TransaksiPenjualan;
use App\Services\DepositService;
use App\Services\PembayaranService;
use App\Services\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dasbor Kasir TPI (UC-06, UC-07, UC-08): tagihan pembeli, settlement nelayan, dan saldo pembeli.
 * Semua aturan nominal, status, dan audit tetap dijalankan oleh Service yang sama dengan API.
 */
class KasirController extends Controller
{
    public function __construct(
        private PembayaranService $pembayaran,
        private SettlementService $settlement,
        private DepositService $deposit,
    ) {
    }

    /** GET /dasbor/kasir/tagihan */
    public function tagihan(Request $request)
    {
        return view('web.kasir.tagihan', [
            'tagihan' => $this->pembayaran->tagihanTerbuka($request->user()),
            'lunas'   => $this->pembayaran->lunasHariIni($request->user()),
            'metode'  => \App\Enums\MetodeBayar::nilai(),
        ]);
    }

    /** POST /dasbor/kasir/tagihan/{id}/bayar */
    public function bayar(Request $request, int $id)
    {
        $data = $request->validate([
            'metode'          => ['required', Rule::in(\App\Enums\MetodeBayar::nilai())],
            'nominal'         => ['required', 'numeric', 'gt:0'],
            'nomor_referensi' => ['nullable', 'string', 'max:40'],
        ], [
            'metode.required'  => 'Metode pembayaran wajib dipilih.',
            'nominal.required' => 'Nominal pembayaran wajib diisi.',
            'nominal.gt'       => 'Nominal harus lebih dari Rp0.',
        ]);

        $trx = $this->pembayaran->bayar(
            $request->user(),
            $id,
            \App\Enums\MetodeBayar::from($data['metode']),
            (float) $data['nominal'],
            $data['nomor_referensi'] ?? null,
        );

        return back()->with('sukses', 'Pembayaran tagihan ' . $trx->getKodeTransaksi() . ' tercatat. Status: ' . $trx->getStatus()->value . '.');
    }

    /** GET /dasbor/kasir/settlement */
    public function settlement(Request $request)
    {
        $tanggal = $request->query('tanggal');

        return view('web.kasir.settlement', [
            'tanggal'    => $tanggal,
            'kedatangan' => $this->settlement->daftarKedatangan($request->user(), $tanggal),
            'caraCair'   => SettlementService::CARA_CAIR,
        ]);
    }

    /** POST /dasbor/kasir/settlement/{idKedatangan} */
    public function hitungSettlement(Request $request, int $idKedatangan)
    {
        $data = $request->validate([
            'potongan_lain'       => ['nullable', 'numeric', 'min:0'],
            'keterangan_potongan' => ['nullable', 'string', 'max:200'],
        ], [
            'potongan_lain.min' => 'Potongan lain tidak boleh negatif.',
        ]);

        $pembayaran = $this->settlement->hitung(
            $request->user(),
            $idKedatangan,
            (float) ($data['potongan_lain'] ?? 0),
            $data['keterangan_potongan'] ?? null,
        );

        return redirect()
            ->route('kasir.settlement.rincian', $pembayaran->getIdBayarNelayan())
            ->with('sukses', 'Perhitungan settlement tersimpan dengan kode bukti ' . $pembayaran->getKodeBukti() . '.');
    }

    /** GET /dasbor/kasir/settlement/{id} */
    public function rincianSettlement(int $id)
    {
        $pembayaran = \App\Models\PembayaranNelayan::findOrFail($id);

        return view('web.kasir.settlement-rincian', [
            'rincian'  => $this->settlement->rincianPembayaran($pembayaran),
            'caraCair' => SettlementService::CARA_CAIR,
        ]);
    }

    /** POST /dasbor/kasir/settlement/{id}/cairkan */
    public function cairkan(Request $request, int $id)
    {
        $data = $request->validate([
            'cara_pencairan'  => ['required', Rule::in(SettlementService::CARA_CAIR)],
            'nominal'         => ['nullable', 'numeric', 'gt:0'],
            'nomor_referensi' => ['nullable', 'string', 'max:40'],
        ], [
            'cara_pencairan.required' => 'Cara pencairan wajib dipilih.',
        ]);

        $pembayaran = $this->settlement->cairkan(
            $request->user(),
            $id,
            $data['cara_pencairan'],
            isset($data['nominal']) ? (float) $data['nominal'] : null,
            $data['nomor_referensi'] ?? null,
        );

        return redirect()
            ->route('kasir.settlement.rincian', $id)
            ->with('sukses', 'Pencairan tercatat. Kode bukti ' . $pembayaran->getKodeBukti() . ' siap dicetak.');
    }

    /** GET /dasbor/kasir/saldo */
    public function saldo(Request $request)
    {
        $kataKunci = trim((string) $request->query('cari', ''));
        $hasil = null;
        $rincian = null;

        if ($kataKunci !== '') {
            $request->validate(['cari' => ['min:3']], ['cari.min' => 'Kata kunci pencarian minimal 3 karakter.']);
            $hasil = $this->deposit->cariPembeli($request->user(), $kataKunci);
        }
        if ($idPembeli = (int) $request->query('pembeli', 0)) {
            $rincian = $this->deposit->rincian($request->user(), $idPembeli);
        }

        return view('web.kasir.saldo', [
            'kataKunci' => $kataKunci,
            'hasil'     => $hasil,
            'rincian'   => $rincian,
        ]);
    }

    /** POST /dasbor/kasir/saldo/{id}/mutasi */
    public function mutasi(Request $request, int $id)
    {
        $data = $request->validate([
            'jenis'      => ['required', Rule::in(['SETOR', 'PENARIKAN', 'PENYESUAIAN'])],
            'nominal'    => ['required', 'numeric', 'gt:0'],
            'metode'     => ['nullable', Rule::in(['TUNAI', 'TRANSFER', 'QRIS'])],
            'arah'       => ['nullable', Rule::in(['KREDIT', 'DEBIT'])],
            'referensi'  => ['nullable', 'string', 'max:60'],
            'keterangan' => ['nullable', 'string', 'max:200'],
        ], [
            'nominal.gt'       => 'Nominal harus lebih dari Rp0.',
            'jenis.required'   => 'Jenis mutasi wajib dipilih.',
        ]);

        $this->deposit->buatMutasi(
            $request->user(),
            $id,
            $data['jenis'],
            (float) $data['nominal'],
            $data['metode'] ?? null,
            $data['arah'] ?? null,
            $data['referensi'] ?? null,
            $data['keterangan'] ?? null,
        );

        return redirect()
            ->route('kasir.saldo', ['pembeli' => $id, 'cari' => $request->query('cari')])
            ->with('sukses', 'Mutasi saldo tercatat. Transaksi di atas batas kewenangan kasir menunggu persetujuan pimpinan.');
    }
}
