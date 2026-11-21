@php use App\Support\Tampilan; @endphp
@extends('web.layout')

@section('judul', 'Persetujuan Penyesuaian Saldo')
@section('keterangan', 'Mutasi penyesuaian kasir yang menunggu keputusan pimpinan')

@section('isi')
    <div class="rounded-xl border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Menunggu persetujuan</h2>
            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">{{ count($daftar) }}</span>
        </div>

        @if (count($daftar) === 0)
            <p class="px-5 py-10 text-center text-sm text-slate-500">Tidak ada penyesuaian saldo yang menunggu.</p>
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($daftar as $m)
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-medium text-slate-900">{{ $m['nama_pembeli'] }}</div>
                                <div class="text-xs text-slate-500">
                                    Diajukan {{ $m['nama_kasir'] }} &middot; {{ Tampilan::waktu($m['waktu']) }}
                                </div>
                                <div class="mt-1 text-sm text-slate-700">
                                    {{ $m['arah'] === 'KREDIT' ? 'Tambah' : 'Kurangi' }} saldo
                                    <strong>{{ Tampilan::rupiah($m['nominal']) }}</strong>
                                    &mdash; {{ $m['keterangan'] }}
                                </div>
                                <div class="text-xs text-slate-500">Saldo sekarang {{ Tampilan::rupiah($m['saldo_sekarang']) }}</div>
                            </div>
                            <form method="POST" action="{{ route('pimpinan.penyesuaian.putuskan', $m['id_mutasi']) }}" class="flex flex-wrap items-end gap-2">
                                @csrf
                                <div>
                                    <label class="block text-xs text-slate-500">Catatan</label>
                                    <input name="catatan" maxlength="200" placeholder="opsional"
                                           class="mt-1 w-44 rounded-md border border-slate-300 px-2 py-1 text-xs">
                                </div>
                                <button name="keputusan" value="SETUJU"
                                        class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Setujui</button>
                                <button name="keputusan" value="TOLAK"
                                        class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Tolak</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <p class="text-xs text-slate-500">
        Keputusan pimpinan dicatat pada jejak audit bersama nominal, arah, dan alasan pengajuan kasir.
    </p>
@endsection
