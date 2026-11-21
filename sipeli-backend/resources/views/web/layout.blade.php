@php
    /** Menu web sesuai peran (SKPL I.B): operator lelang, kasir, pimpinan/dinas, administrator. */
    $p = auth()->user();
    $menu = [];
    if (! $p) {
        $menu = [];
    } else {
        if ($p->periksaKewenangan([\App\Enums\KodePeran::OPERATOR_LELANG])) {
            $menu[] = ['operator.antrean', 'Antrean Lelang', 'operator.antrean'];
            $menu[] = ['operator.sesi', 'Sesi Lelang', 'operator.sesi'];
        }
        if ($p->periksaKewenangan([\App\Enums\KodePeran::KASIR])) {
            $menu[] = ['kasir.tagihan', 'Tagihan Pembeli', 'kasir.tagihan'];
            $menu[] = ['kasir.settlement', 'Settlement Nelayan', 'kasir.settlement'];
            $menu[] = ['kasir.saldo', 'Saldo Pembeli', 'kasir.saldo'];
        }
        if ($p->periksaKewenangan([\App\Enums\KodePeran::PIMPINAN])) {
            $menu[] = ['pimpinan.ringkasan', 'Ringkasan Harian', 'pimpinan.ringkasan'];
            $menu[] = ['pimpinan.laporan', 'Laporan & Analitik', 'pimpinan.laporan'];
            $menu[] = ['pimpinan.penyesuaian', 'Persetujuan Saldo', 'pimpinan.penyesuaian'];
        }
        if ($p->periksaKewenangan([\App\Enums\KodePeran::ADMINISTRATOR])) {
            $menu[] = ['admin.master', 'Data Master', 'admin.master'];
            $menu[] = ['admin.pengguna', 'Pengguna & Peran', 'admin.pengguna'];
            $menu[] = ['admin.konfigurasi', 'Konfigurasi', 'admin.konfigurasi'];
            $menu[] = ['admin.audit', 'Jejak Audit', 'admin.audit'];
        }
        $menu[] = ['web.papan', 'Papan Lelang', 'web.papan'];
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'Dasbor') &middot; LELANGIKAN.ID</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
<div class="flex min-h-screen">
    <aside class="hidden w-64 shrink-0 flex-col bg-slate-900 text-slate-200 lg:flex">
        <div class="border-b border-white/10 px-5 py-5">
            <div class="text-lg font-semibold tracking-wide text-white">LELANGIKAN.ID</div>
            <div class="text-xs text-slate-600">Antarmuka Web TPI</div>
        </div>
        <nav class="flex-1 space-y-1 px-3 py-4 text-sm">
            @foreach ($menu as [$rute, $label, $cocok])
                @php $aktif = request()->routeIs($cocok . '*'); @endphp
                <a href="{{ route($rute) }}"
                   class="block rounded-lg px-3 py-2 {{ $aktif ? 'bg-white/15 font-semibold text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
        <div class="border-t border-white/10 px-5 py-4 text-xs text-slate-600">
            SKPL LELANGIKAN.ID v1.1<br>Bagian I.B &mdash; antarmuka web
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex items-center justify-between gap-4 border-b border-slate-500 bg-white px-6 py-3">
            <div>
                <div class="text-sm font-semibold text-slate-900">@yield('judul', 'Dasbor')</div>
                <div class="text-xs text-slate-600">@yield('keterangan', '')</div>
            </div>
            <div class="flex items-center gap-3 text-right">
                <div>
                    <div class="text-sm font-medium text-slate-900">{{ auth()->user()?->getNamaLengkap() }}</div>
                    <div class="text-xs text-slate-600">{{ auth()->user()?->peran?->nama_peran }} &middot; {{ auth()->user()?->tpi?->getNamaTpi() ?? 'tanpa TPI' }}</div>
                </div>
                <form method="POST" action="{{ route('web.keluar') }}">
                    @csrf
                    <button class="rounded-lg border border-slate-500 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Keluar</button>
                </form>
            </div>
        </header>

        <nav class="flex gap-1 overflow-x-auto border-b border-slate-500 bg-white px-4 py-2 text-xs lg:hidden">
            @foreach ($menu as [$rute, $label, $cocok])
                <a href="{{ route($rute) }}" class="whitespace-nowrap rounded-md px-2 py-1 {{ request()->routeIs($cocok . '*') ? 'bg-slate-900 text-white' : 'text-slate-600' }}">{{ $label }}</a>
            @endforeach
        </nav>

        <main class="min-w-0 flex-1 space-y-5 px-4 py-6 sm:px-6">
            @if (session('sukses'))
                <div class="rounded-xl border border-emerald-600 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('sukses') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-rose-600 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                    <div class="font-semibold">Periksa kembali isian berikut:</div>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $pesan)
                            <li>{{ $pesan }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('isi')
        </main>
    </div>
</div>
</body>
</html>
