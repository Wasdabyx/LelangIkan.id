@php use App\Support\Tampilan; @endphp
@extends('web.layout')

@section('judul', 'Pengguna & Peran')
@section('keterangan', 'Akun petugas, peran, penugasan TPI, serta aktivasi dan penonaktifan')

@section('isi')
    <form method="GET" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-500 bg-white px-5 py-3">
        <div class="min-w-64 flex-1">
            <label class="block text-xs font-medium text-slate-600" for="cari">Cari nama atau nama pengguna (min. 3 karakter)</label>
            <input id="cari" name="cari" value="{{ $cari }}" class="mt-1 w-full rounded-lg border border-slate-500 px-3 py-2 text-sm">
        </div>
        <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Cari</button>
    </form>

    <div class="rounded-xl border border-slate-500 bg-white">
        <div class="border-b border-slate-500 px-5 py-3 text-sm font-semibold text-slate-900">Daftar pengguna ({{ count($daftar) }})</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
                    <tr>
                        <th class="px-4 py-2">Nama</th><th class="px-4 py-2">Nama pengguna</th><th class="px-4 py-2">Surel</th>
                        <th class="px-4 py-2">Peran</th><th class="px-4 py-2">TPI</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($daftar as $p)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2 font-medium text-slate-900">{{ $p->getNamaLengkap() }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $p->nama_pengguna }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $p->surel }}</td>
                            <td class="px-4 py-2">{{ $p->peran?->nama_peran }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $p->tpi?->getNamaTpi() ?? '-' }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $p->status_aktif ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $p->status_aktif ? 'AKTIF' : 'NONAKTIF' }}
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                <form method="POST" action="{{ route('admin.pengguna.status', $p->getIdPengguna()) }}">
                                    @csrf
                                    @if ($p->status_aktif)
                                        <button name="aksi" value="nonaktifkan"
                                                class="rounded-md border border-rose-600 px-2.5 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">Nonaktifkan</button>
                                    @else
                                        <button name="aksi" value="aktifkan"
                                                class="rounded-md border border-emerald-600 px-2.5 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Aktifkan</button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="border-t border-slate-500 px-5 py-3 text-xs text-slate-600">
            Penonaktifan tidak menghapus data: seluruh transaksi historis tetap utuh dan token perangkat dicabut (FR-51).
        </p>
    </div>

    <div class="rounded-xl border border-slate-500 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Buat pengguna baru</h2>
        <form method="POST" action="{{ route('admin.pengguna.simpan') }}" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            @foreach ([
                ['nama_lengkap', 'Nama lengkap', 'text', true],
                ['nama_pengguna', 'Nama pengguna', 'text', true],
                ['surel', 'Surel', 'email', true],
                ['nik', 'NIK (16 digit, opsional)', 'text', false],
                ['no_hp', 'No. HP (opsional)', 'text', false],
                ['kata_sandi', 'Kata sandi (min. 8)', 'password', true],
            ] as [$nama, $label, $tipe, $wajib])
                <div>
                    <label class="block text-xs font-medium text-slate-600" for="{{ $nama }}">{{ $label }}</label>
                    <input id="{{ $nama }}" name="{{ $nama }}" type="{{ $tipe }}" @if ($wajib) required @endif
                           class="mt-1 w-full rounded-lg border border-slate-500 px-3 py-2 text-sm">
                </div>
            @endforeach
            <div>
                <label class="block text-xs font-medium text-slate-600" for="id_peran">Peran</label>
                <select id="id_peran" name="id_peran" class="mt-1 w-full rounded-lg border border-slate-500 px-3 py-2 text-sm">
                    @foreach (\App\Enums\KodePeran::cases() as $kode)
                        <option value="{{ $kode->value }}">{{ $kode->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600" for="id_tpi">TPI</label>
                <select id="id_tpi" name="id_tpi" class="mt-1 w-full rounded-lg border border-slate-500 px-3 py-2 text-sm">
                    @foreach (\App\Models\Tpi::orderBy('id_tpi')->get() as $tpi)
                        <option value="{{ $tpi->id_tpi }}">{{ $tpi->getNamaTpi() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2 lg:col-span-4">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Buat pengguna</button>
            </div>
        </form>
    </div>
@endsection
