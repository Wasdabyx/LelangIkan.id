@extends('web.layout')

@section('judul', 'Masuk Antarmuka Web')
@section('keterangan', 'Operator lelang, kasir, pimpinan/dinas, dan administrator')

@section('isi')
    <div class="mx-auto w-full max-w-md">
        <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
            <h1 class="text-lg font-semibold text-slate-900">Masuk ke Dasbor TPI</h1>
            <p class="mt-1 text-sm text-slate-500">
                Petugas pendataan, petugas timbang, dan pembeli memakai aplikasi mobile.
                Papan lelang dapat dibuka tanpa masuk.
            </p>

            <form method="POST" action="{{ route('web.masuk') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="nama_pengguna" class="block text-sm font-medium text-slate-700">Nama pengguna</label>
                    <input id="nama_pengguna" name="nama_pengguna" value="{{ old('nama_pengguna') }}" autofocus
                           autocomplete="username" required maxlength="50"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                </div>
                <div>
                    <label for="kata_sandi" class="block text-sm font-medium text-slate-700">Kata sandi</label>
                    <input id="kata_sandi" name="kata_sandi" type="password" required autocomplete="current-password"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                </div>
                <input type="hidden" name="perangkat" value="antarmuka-web">
                <button class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                    Masuk
                </button>
            </form>

            <div class="mt-5 border-t border-slate-200 pt-4 text-xs text-slate-500">
                Akun contoh hasil <code class="rounded bg-slate-100 px-1">migrate:fresh --seed</code>:
                operator, kasir, pimpinan, admin &mdash; kata sandi <code class="rounded bg-slate-100 px-1">sipeli12345</code>.
            </div>
        </div>

        <p class="mt-4 text-center text-xs text-slate-500">
            <a class="underline hover:text-slate-700" href="{{ route('web.papan') }}">Buka papan lelang (baca-saja)</a>
        </p>
    </div>
@endsection
