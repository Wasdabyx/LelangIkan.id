@php use App\Support\Tampilan; @endphp
@extends('web.layout')

@section('judul', 'Ringkasan Harian')
@section('keterangan', 'Angka operasional TPI untuk ' . Tampilan::tanggal($tanggal))

@section('isi')
    <form method="GET" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-white px-5 py-3">
        <div>
            <label class="block text-xs font-medium text-slate-600" for="tanggal">Tanggal</label>
            <input id="tanggal" type="date" name="tanggal" value="{{ $tanggal }}"
                   class="mt-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
        </div>
        <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Tampilkan</button>
        <a href="{{ route('pimpinan.laporan') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Buka laporan periode</a>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php
            $kartu = [
                ['Sesi aktif', $data['sesi']['aktif'], 'sesi berjalan saat ini'],
                ['Sesi ditutup', $data['sesi']['ditutup'], 'selesai pada tanggal ini'],
                ['Lot terjual', $data['lot']['terjual'], 'lot berpindah ke pembeli'],
                ['Lot tidak terjual', $data['lot']['tidak_terjual'], 'perlu dijadwalkan ulang'],
                ['Nilai penjualan', Tampilan::rupiah($data['penjualan']['subtotal']), 'subtotal transaksi'],
                ['Pembayaran diterima', Tampilan::rupiah($data['pembayaran']['diterima']), 'kas masuk hari ini'],
                ['Tagihan belum lunas', $data['tagihan']['belum_lunas'] . ' transaksi', 'piutang pembeli'],
                ['Sisa tagihan', Tampilan::rupiah($data['tagihan']['sisa']), 'belum dibayar'],
            ];
        @endphp
        @foreach ($kartu as [$judul, $nilai, $catatan])
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">{{ $judul }}</div>
                <div class="mt-1 text-xl font-bold text-slate-900">{{ $nilai }}</div>
                <div class="text-xs text-slate-500">{{ $catatan }}</div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600">
        <h2 class="text-sm font-semibold text-slate-900">Catatan pembacaan angka</h2>
        <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
            <li>Sesi dihitung berdasarkan waktu mulai dan waktu selesai aktual pada tanggal yang dipilih.</li>
            <li>Nilai penjualan memakai subtotal transaksi yang tidak dibatalkan, termasuk transaksi yang belum lunas.</li>
            <li>Pembayaran diterima memakai tanggal pembayaran, sehingga dapat berbeda dari tanggal transaksi.</li>
            <li>Angka retribusi dirinci pada halaman Laporan &amp; Analitik.</li>
        </ul>
    </div>
@endsection
