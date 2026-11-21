@php use App\Support\Tampilan; @endphp
@extends('web.layout')

@section('judul', 'Jejak Audit')
@section('keterangan', 'Pencatatan aksi pengguna beserta entitas dan waktu (FR-52 sampai FR-55)')

@section('isi')
    <form method="GET" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-500 bg-white px-5 py-3">
        <div>
            <label class="block text-xs font-medium text-slate-600" for="entitas">Entitas</label>
            <input id="entitas" name="entitas" value="{{ $filter['entitas'] ?? '' }}" placeholder="mis. transaksi_penjualan"
                   class="mt-1 w-44 rounded-lg border border-slate-500 px-3 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600" for="aksi">Aksi</label>
            <input id="aksi" name="aksi" value="{{ $filter['aksi'] ?? '' }}" placeholder="mis. masuk_web"
                   class="mt-1 w-40 rounded-lg border border-slate-500 px-3 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600" for="dari">Dari</label>
            <input id="dari" type="date" name="dari" value="{{ $filter['dari'] ?? '' }}" class="mt-1 rounded-lg border border-slate-500 px-3 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600" for="sampai">Sampai</label>
            <input id="sampai" type="date" name="sampai" value="{{ $filter['sampai'] ?? '' }}" class="mt-1 rounded-lg border border-slate-500 px-3 py-1.5 text-sm">
        </div>
        <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Saring</button>
        <a href="{{ route('admin.audit') }}" class="rounded-lg border border-slate-500 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset</a>
    </form>

    <div class="rounded-xl border border-slate-500 bg-white">
        <div class="border-b border-slate-500 px-5 py-3 text-sm font-semibold text-slate-900">Catatan audit ({{ count($baris) }}, maksimum 200 terbaru)</div>
        @if (count($baris) === 0)
            <p class="px-5 py-10 text-center text-sm text-slate-600">Tidak ada catatan yang sesuai saringan.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-2">Waktu</th><th class="px-4 py-2">Pengguna</th><th class="px-4 py-2">Aksi</th>
                            <th class="px-4 py-2">Entitas</th><th class="px-4 py-2">ID</th><th class="px-4 py-2">Perubahan</th><th class="px-4 py-2">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($baris as $b)
                            <tr class="align-top hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-2 text-xs text-slate-600">{{ Tampilan::waktu($b['waktu']) }}</td>
                                <td class="px-4 py-2">
                                    <div class="font-medium text-slate-900">{{ $b['pengguna'] }}</div>
                                    <div class="text-xs text-slate-600">{{ $b['nama_pengguna'] ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs">{{ $b['aksi'] }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $b['entitas'] }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $b['id_entitas'] ?? '-' }}</td>
                                <td class="px-4 py-2 text-xs text-slate-600">
                                    @if ($b['data_sesudah'])
                                        <code class="break-all">{{ json_encode($b['data_sesudah'], JSON_UNESCAPED_UNICODE) }}</code>
                                    @elseif ($b['data_sebelum'])
                                        <span class="text-slate-600">sebelum: </span><code class="break-all">{{ json_encode($b['data_sebelum'], JSON_UNESCAPED_UNICODE) }}</code>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs text-slate-600">{{ $b['alamat_ip'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
