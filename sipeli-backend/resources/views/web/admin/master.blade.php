@php use App\Support\Tampilan; @endphp
@extends('web.layout')

@section('judul', 'Data Master')
@section('keterangan', 'Jenis ikan, grade mutu, tarif retribusi, kapal, pemilik kapal, dan TPI')

@section('isi')
    @php
        $labelEntitas = [
            'jenis-ikan'      => 'Jenis Ikan',
            'grade-mutu'      => 'Grade Mutu',
            'tarif-retribusi' => 'Tarif Retribusi',
            'kapal'           => 'Kapal',
            'pemilik-kapal'   => 'Pemilik Kapal',
            'tpi'             => 'TPI',
        ];
    @endphp

    <div class="space-y-5">
        @foreach ($entitas as $kunci => $baris)
            <div class="rounded-xl border border-slate-200 bg-white" x-data="{ buka: false }">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">{{ $labelEntitas[$kunci] ?? $kunci }}</h2>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ count($baris) }} baris</span>
                </div>

                @if (count($baris) === 0)
                    <p class="px-5 py-4 text-sm text-slate-500">Belum ada data.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    @foreach (array_keys($baris[0]) as $kolom)
                                        <th class="px-4 py-2">{{ str_replace('_', ' ', $kolom) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($baris as $r)
                                    <tr>
                                        @foreach ($r as $nilai)
                                            <td class="px-4 py-2 text-slate-700">{{ is_numeric($nilai) ? Tampilan::angka($nilai, str_contains((string) $nilai, '.') ? 2 : 0) : ($nilai ?? '-') }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <details class="border-t border-slate-200 px-5 py-3 text-sm">
                    <summary class="cursor-pointer text-xs font-medium text-slate-700">Tambah data {{ $labelEntitas[$kunci] ?? $kunci }}</summary>
                    <form method="POST" action="{{ route('admin.master.simpan', $kunci) }}" class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf
                        @foreach (array_keys($baris[0] ?? []) as $kolom)
                            @continue(in_array($kolom, ['id_jenis', 'id_grade', 'id_tarif', 'id_kapal', 'id_pemilik', 'id_tpi'], true))
                            <div>
                                <label class="block text-xs text-slate-500">{{ str_replace('_', ' ', $kolom) }}</label>
                                <input name="{{ $kolom }}" class="mt-1 w-40 rounded-md border border-slate-300 px-2 py-1 text-xs">
                            </div>
                        @endforeach
                        <button class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">Simpan</button>
                    </form>
                    <p class="mt-2 text-xs text-slate-500">
                        Kolom bertipe tanggal memakai format <code>YYYY-MM-DD</code>; kolom <code>status</code> diisi 1 untuk aktif.
                        Setiap penambahan dicatat pada jejak audit (FR-52).
                    </p>
                </details>
            </div>
        @endforeach
    </div>
@endsection
