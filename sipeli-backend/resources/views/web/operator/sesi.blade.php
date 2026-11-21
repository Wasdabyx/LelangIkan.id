@php use App\Support\Tampilan; @endphp
@extends('web.layout')

@section('judul', 'Sesi Lelang')
@section('keterangan', 'Sesi aktif hari ini pada TPI Anda')

@section('isi')
    <div class="rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Sesi aktif dan sesi hari ini</h2>
        </div>
        @if (count($sesi) === 0)
            <p class="px-5 py-10 text-center text-sm text-slate-500">Belum ada sesi lelang hari ini.</p>
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($sesi as $s)
                    <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-slate-900">{{ $s['lot']['kode_lot'] }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ Tampilan::warnaStatus($s['status']) }}">{{ $s['status'] }}</span>
                                <span class="text-xs text-slate-500">mulai {{ Tampilan::waktu($s['waktu_mulai']) }}</span>
                            </div>
                            <div class="text-xs text-slate-500">
                                {{ $s['lot']['nama_jenis'] }} grade {{ $s['lot']['kode_grade'] }} &middot;
                                {{ Tampilan::kg($s['lot']['berat_aktual']) }} &middot; {{ $s['lot']['nama_kapal'] }}
                            </div>
                        </div>
                        <div class="flex items-center gap-5 text-right">
                            <div>
                                <div class="text-xs text-slate-500">Harga berjalan</div>
                                <div class="font-semibold text-slate-900">{{ Tampilan::rupiah($s['harga_tertinggi']) }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Penawaran</div>
                                <div class="font-semibold text-slate-900">{{ $s['jumlah_penawaran'] }}</div>
                            </div>
                            <a href="{{ route('operator.sesi.pantau', $s['id_sesi']) }}"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                Pantau
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
