@php use App\Support\Tampilan; @endphp
@extends('web.layout')

@section('judul', 'Antrean Lelang')
@section('keterangan', 'Lot yang sudah ditimbang dan siap dilelang pada TPI Anda')

@section('isi')
    <div class="rounded-xl border border-slate-500 bg-white">
        <div class="flex items-center justify-between border-b border-slate-500 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Lot siap lelang</h2>
            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                {{ count($antrean) }} lot
            </span>
        </div>

        @if (count($antrean) === 0)
            <p class="px-5 py-10 text-center text-sm text-slate-600">
                Belum ada lot siap lelang. Lot muncul setelah petugas timbang menyimpan penimbangan di aplikasi mobile.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-5 py-2.5">Kode lot</th>
                            <th class="px-5 py-2.5">Jenis / Grade</th>
                            <th class="px-5 py-2.5">Kapal</th>
                            <th class="px-5 py-2.5 text-right">Berat</th>
                            <th class="px-5 py-2.5 text-right">Harga pembukaan</th>
                            <th class="px-5 py-2.5 text-right">Nilai lot</th>
                            <th class="px-5 py-2.5 text-right">Peserta</th>
                            <th class="px-5 py-2.5">Buka sesi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($antrean as $lot)
                            <tr class="align-top hover:bg-slate-50">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $lot['kode_lot'] }}</td>
                                <td class="px-5 py-3">
                                    {{ $lot['nama_jenis'] }}
                                    <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-600">Grade {{ $lot['kode_grade'] }}</span>
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $lot['nama_kapal'] }}</td>
                                <td class="px-5 py-3 text-right">{{ Tampilan::kg($lot['berat_aktual']) }}</td>
                                <td class="px-5 py-3 text-right">{{ Tampilan::rupiah($lot['harga_pembukaan']) }}</td>
                                <td class="px-5 py-3 text-right">{{ Tampilan::rupiah($lot['nilai_lot']) }}</td>
                                <td class="px-5 py-3 text-right">{{ $lot['jumlah_peserta'] }}</td>
                                <td class="px-5 py-3">
                                    <form method="POST" action="{{ route('operator.sesi.buka') }}" class="flex flex-wrap items-center gap-1.5">
                                        @csrf
                                        <input type="hidden" name="id_lot" value="{{ $lot['id_lot'] }}">
                                        <select name="mode" class="rounded-md border border-slate-500 px-1.5 py-1 text-xs">
                                            <option value="NAIK">Naik</option>
                                            <option value="TURUN">Turun</option>
                                        </select>
                                        <input name="durasi_detik" type="number" min="30" max="600" value="{{ $durasiBawaan }}"
                                               class="w-16 rounded-md border border-slate-500 px-1.5 py-1 text-xs" title="Durasi sesi (detik)">
                                        <input name="kenaikan_minimum" type="number" min="100" step="100" value="{{ $lot['usulan_kenaikan'] }}"
                                               class="w-20 rounded-md border border-slate-500 px-1.5 py-1 text-xs" title="Kenaikan minimum per kg">
                                        <button class="rounded-md bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white hover:bg-slate-800">Buka</button>
                                    </form>
                                    <p class="mt-1 text-xs text-slate-600">Usulan kenaikan {{ Tampilan::rupiah($lot['usulan_kenaikan']) }}/kg (ALG-05)</p>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-slate-500 px-5 py-3 text-xs text-slate-600">
                Mode <strong>Turun</strong> memerlukan harga awal, harga batas bawah, penurunan per langkah, dan interval.
                Untuk itu gunakan aplikasi mobile atau isi parameter lelang turun melalui formulir lanjutan.
            </p>
        @endif
    </div>
@endsection
