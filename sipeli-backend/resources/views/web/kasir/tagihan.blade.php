@php use App\Support\Tampilan; @endphp
@extends('web.layout')

@section('judul', 'Tagihan Pembeli')
@section('keterangan', 'Transaksi lelang yang belum lunas beserta pelunasan di meja kasir')

@section('isi')
    <div class="rounded-xl border border-slate-500 bg-white">
        <div class="flex items-center justify-between border-b border-slate-500 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Tagihan terbuka</h2>
            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">{{ count($tagihan) }}</span>
        </div>

        @if (count($tagihan) === 0)
            <p class="px-5 py-10 text-center text-sm text-slate-600">Tidak ada tagihan terbuka.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-2.5">Kode transaksi</th>
                            <th class="px-4 py-2.5">Lot</th>
                            <th class="px-4 py-2.5">Pembeli</th>
                            <th class="px-4 py-2.5 text-right">Berat</th>
                            <th class="px-4 py-2.5 text-right">Harga/kg</th>
                            <th class="px-4 py-2.5 text-right">Total tagihan</th>
                            <th class="px-4 py-2.5 text-right">Sisa</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5">Pelunasan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($tagihan as $t)
                            <tr class="align-top hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $t['kode_transaksi'] }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ $t['kode_lot'] }}<br>
                                    <span class="text-xs">{{ $t['nama_jenis'] }} / {{ $t['kode_grade'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $t['nama_pembeli'] }}</td>
                                <td class="px-4 py-3 text-right">{{ Tampilan::kg($t['berat_final']) }}</td>
                                <td class="px-4 py-3 text-right">{{ Tampilan::rupiah($t['harga_akhir_per_kg']) }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ Tampilan::rupiah($t['total_tagihan']) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-rose-700">{{ Tampilan::rupiah($t['sisa_tagihan']) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ Tampilan::warnaStatus($t['status']) }}">{{ $t['status'] }}</span>
                                    <div class="mt-1 text-xs text-slate-600">{{ $t['batas_bayar'] ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('kasir.tagihan.bayar', $t['id_transaksi']) }}" class="flex flex-wrap items-center gap-1.5">
                                        @csrf
                                        <select name="metode" class="rounded-md border border-slate-500 px-1.5 py-1 text-xs">
                                            @foreach ($metode as $m)
                                                <option value="{{ $m }}">{{ $m }}</option>
                                            @endforeach
                                        </select>
                                        <input name="nominal" type="number" min="1" step="100" value="{{ (int) $t['sisa_tagihan'] }}"
                                               class="w-28 rounded-md border border-slate-500 px-1.5 py-1 text-xs" title="Nominal dibayar">
                                        <input name="nomor_referensi" placeholder="Ref." maxlength="40"
                                               class="w-20 rounded-md border border-slate-500 px-1.5 py-1 text-xs">
                                        <button class="rounded-md bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white hover:bg-slate-800">Bayar</button>
                                    </form>
                                    <p class="mt-1 text-xs text-slate-600">Saldo pembeli {{ Tampilan::rupiah($t['saldo_tersedia']) }}</p>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="rounded-xl border border-slate-500 bg-white">
        <div class="flex items-center justify-between border-b border-slate-500 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Lunas hari ini</h2>
            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">{{ count($lunas) }}</span>
        </div>
        @if (count($lunas) === 0)
            <p class="px-5 py-6 text-center text-sm text-slate-600">Belum ada pelunasan hari ini.</p>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
                    <tr>
                        <th class="px-4 py-2.5">Kode</th><th class="px-4 py-2.5">Pembeli</th>
                        <th class="px-4 py-2.5 text-right">Total</th><th class="px-4 py-2.5 text-right">Dibayar</th>
                        <th class="px-4 py-2.5">Waktu transaksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($lunas as $t)
                        <tr>
                            <td class="px-4 py-2.5 font-medium">{{ $t['kode_transaksi'] }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $t['nama_pembeli'] }}</td>
                            <td class="px-4 py-2.5 text-right">{{ Tampilan::rupiah($t['total_tagihan']) }}</td>
                            <td class="px-4 py-2.5 text-right text-emerald-700">{{ Tampilan::rupiah($t['total_dibayar']) }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-600">{{ $t['waktu_transaksi'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
