@php use App\Support\Tampilan; @endphp
@extends('web.layout')

@section('judul', 'Laporan & Analitik')
@section('keterangan', 'Periode ' . Tampilan::tanggal($dari) . ' sampai ' . Tampilan::tanggal($sampai))

@section('isi')
    <form method="GET" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-white px-5 py-3">
        <div>
            <label class="block text-xs font-medium text-slate-600" for="dari">Dari</label>
            <input id="dari" type="date" name="dari" value="{{ $dari }}" class="mt-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600" for="sampai">Sampai</label>
            <input id="sampai" type="date" name="sampai" value="{{ $sampai }}" class="mt-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
        </div>
        <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Terapkan</button>
        <a class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
           href="{{ route('pimpinan.laporan.csv', ['jenis' => 'harga', 'dari' => $dari, 'sampai' => $sampai]) }}">Unduh CSV harga</a>
        <a class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
           href="{{ route('pimpinan.laporan.csv', ['jenis' => 'retribusi', 'dari' => $dari, 'sampai' => $sampai]) }}">Unduh CSV retribusi</a>
    </form>

    @php
        $tren = $laporan['tren_harga'];
        $kinerja = $laporan['kinerja_lelang'];
        $retribusi = $laporan['retribusi'];
        $harga = $laporan['harga_dan_volume'];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase tracking-wide text-slate-500">Harga rata-rata</div>
            <div class="mt-1 text-xl font-bold text-slate-900">{{ Tampilan::rupiah($tren['harga_rata_rata']) }}/kg</div>
            <div class="text-xs {{ $tren['arah'] === 'NAIK' ? 'text-emerald-700' : ($tren['arah'] === 'TURUN' ? 'text-rose-700' : 'text-slate-500') }}">
                {{ $tren['perubahan_persen'] === null ? 'belum ada pembanding' : $tren['arah'] }}
                @if ($tren['perubahan_persen'] !== null)
                    ({{ $tren['perubahan_persen'] > 0 ? '+' : '' }}{{ $tren['perubahan_persen'] }}% vs periode sebelumnya)
                @endif
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase tracking-wide text-slate-500">Volume terjual</div>
            <div class="mt-1 text-xl font-bold text-slate-900">{{ Tampilan::kg($harga['total']['volume_kg']) }}</div>
            <div class="text-xs text-slate-500">Nilai {{ Tampilan::rupiah($harga['total']['nilai']) }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase tracking-wide text-slate-500">Total retribusi</div>
            <div class="mt-1 text-xl font-bold text-slate-900">{{ Tampilan::rupiah($retribusi['total_retribusi']) }}</div>
            <div class="text-xs text-slate-500">Pendapatan TPI pada periode ini</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase tracking-wide text-slate-500">Keberhasilan lelang</div>
            <div class="mt-1 text-xl font-bold text-slate-900">{{ $kinerja['tingkat_keberhasilan_persen'] }}%</div>
            <div class="text-xs text-slate-500">{{ $kinerja['sesi_terjual'] }} dari {{ $kinerja['jumlah_sesi'] }} sesi terjual</div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Harga dan volume per jenis &amp; grade</div>
        @if (count($harga['baris']) === 0)
            <p class="px-5 py-8 text-center text-sm text-slate-500">Belum ada transaksi pada periode ini.</p>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Jenis</th><th class="px-4 py-2">Grade</th>
                        <th class="px-4 py-2 text-right">Transaksi</th><th class="px-4 py-2 text-right">Volume</th>
                        <th class="px-4 py-2 text-right">Nilai</th><th class="px-4 py-2 text-right">Rata-rata/kg</th>
                        <th class="px-4 py-2 text-right">Terendah</th><th class="px-4 py-2 text-right">Tertinggi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($harga['baris'] as $b)
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $b['nama_jenis'] }}</td>
                            <td class="px-4 py-2">{{ $b['kode_grade'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $b['jumlah_transaksi'] }}</td>
                            <td class="px-4 py-2 text-right">{{ Tampilan::kg($b['volume_kg']) }}</td>
                            <td class="px-4 py-2 text-right">{{ Tampilan::rupiah($b['nilai']) }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ Tampilan::rupiah($b['harga_rata_rata']) }}</td>
                            <td class="px-4 py-2 text-right text-slate-600">{{ Tampilan::rupiah($b['harga_terendah']) }}</td>
                            <td class="px-4 py-2 text-right text-slate-600">{{ Tampilan::rupiah($b['harga_tertinggi']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Rekap retribusi harian</div>
            @if (count($retribusi['harian']) === 0)
                <p class="px-5 py-8 text-center text-sm text-slate-500">Belum ada retribusi pada periode ini.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-4 py-2">Tanggal</th><th class="px-4 py-2 text-right">Transaksi</th>
                            <th class="px-4 py-2 text-right">Nilai</th><th class="px-4 py-2 text-right">Retribusi</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($retribusi['harian'] as $r)
                            <tr>
                                <td class="px-4 py-2">{{ Tampilan::tanggal($r['tanggal']) }}</td>
                                <td class="px-4 py-2 text-right">{{ $r['jumlah_transaksi'] }}</td>
                                <td class="px-4 py-2 text-right">{{ Tampilan::rupiah($r['nilai_transaksi']) }}</td>
                                <td class="px-4 py-2 text-right font-medium">{{ Tampilan::rupiah($r['retribusi']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="space-y-5">
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <h3 class="text-sm font-semibold text-slate-900">Kinerja lelang</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    @foreach ([
                        'Jumlah sesi' => $kinerja['jumlah_sesi'],
                        'Sesi terjual' => $kinerja['sesi_terjual'],
                        'Sesi tidak terjual' => $kinerja['sesi_tidak_terjual'],
                        'Sesi diperpanjang (anti-sniping)' => $kinerja['sesi_diperpanjang'],
                        'Kedatangan disettlement' => $kinerja['kedatangan_disettlement'],
                        'Kedatangan lunas' => $kinerja['kedatangan_lunas'],
                    ] as $label => $nilai)
                        <div class="flex items-center justify-between border-b border-slate-100 pb-1">
                            <dt class="text-slate-600">{{ $label }}</dt>
                            <dd class="font-semibold text-slate-900">{{ $nilai }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Tren harga harian</div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-4 py-2">Tanggal</th><th class="px-4 py-2 text-right">Transaksi</th>
                            <th class="px-4 py-2 text-right">Volume</th><th class="px-4 py-2 text-right">Rata-rata/kg</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse (array_slice($tren['harian'], -10) as $t)
                            <tr>
                                <td class="px-4 py-2">{{ Tampilan::tanggal($t['tanggal']) }}</td>
                                <td class="px-4 py-2 text-right">{{ $t['jumlah_transaksi'] }}</td>
                                <td class="px-4 py-2 text-right">{{ Tampilan::kg($t['volume_kg']) }}</td>
                                <td class="px-4 py-2 text-right font-medium">{{ Tampilan::rupiah($t['harga_rata_rata']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <p class="border-t border-slate-200 px-5 py-2 text-xs text-slate-500">Menampilkan 10 hari terakhir dari periode.</p>
            </div>
        </div>
    </div>
@endsection
