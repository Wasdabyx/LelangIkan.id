@extends('web.layout')

@section('judul', 'Konfigurasi Sistem')
@section('keterangan', 'Parameter lelang dan TPI yang dapat diubah administrator (FR-50)')

@section('isi')
    {{-- FR-63 + BT-03: lelang jarak jauh bersifat opsional dan dapat dimatikan administrator.
         Mematikan kanal ini TIDAK menghentikan lelang di tempat (penawaran DI_TEMPAT). --}}
    <div class="rounded-xl border border-slate-500 bg-white p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Kanal lelang jarak jauh</h2>
                <p class="mt-1 text-xs text-slate-600">
                    Menentukan apakah aplikasi mobile boleh mengirim tawaran. Bila dimatikan,
                    pembeli hanya dapat menawar di tempat melalui operator lelang.
                </p>
            </div>
            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $jarakJauhAktif ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                {{ $jarakJauhAktif ? 'DIBUKA' : 'DIMATIKAN' }}
            </span>
        </div>
        <form method="POST" action="{{ route('admin.jarak-jauh') }}" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-600" for="aktif">Keadaan kanal</label>
                <select id="aktif" name="aktif" class="mt-1 rounded-lg border border-slate-500 px-3 py-2 text-sm">
                    <option value="1" @selected($jarakJauhAktif)>Dibuka (aplikasi mobile boleh menawar)</option>
                    <option value="0" @selected(! $jarakJauhAktif)>Dimatikan (hanya lelang di tempat)</option>
                </select>
            </div>
            <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Simpan</button>
        </form>
        <p class="mt-2 text-xs text-slate-600">
            Perubahan tercatat pada jejak audit dengan aksi <span class="font-mono">UBAH_KANAL_JARAK_JAUH</span>.
        </p>
    </div>

    <div class="rounded-xl border border-slate-500 bg-white">
        <div class="border-b border-slate-500 px-5 py-3 text-sm font-semibold text-slate-900">Parameter aktif</div>
        <div class="divide-y divide-slate-100">
            @foreach ($daftar as $k)
                <form method="POST" action="{{ route('admin.konfigurasi.simpan') }}" class="flex flex-wrap items-end gap-3 px-5 py-3">
                    @csrf
                    <input type="hidden" name="kunci" value="{{ $k->getKunci() }}">
                    <div class="min-w-48 flex-1">
                        <div class="text-sm font-medium text-slate-900">{{ $k->getKunci() }}</div>
                        <div class="text-xs text-slate-600">{{ $k->deskripsi }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-600" for="nilai-{{ $k->getKunci() }}">Nilai</label>
                        <input id="nilai-{{ $k->getKunci() }}" name="nilai" value="{{ $k->getNilai() }}" maxlength="100"
                               class="mt-1 w-40 rounded-md border border-slate-500 px-2 py-1 text-sm">
                    </div>
                    <button class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">Simpan</button>
                </form>
            @endforeach
        </div>
        <p class="border-t border-slate-500 px-5 py-3 text-xs text-slate-600">
            Perubahan konfigurasi tercatat pada jejak audit beserta nilai sebelum dan sesudahnya.
            <code>ambang_selisih_berat</code> dipakai penimbangan untuk menandai lot yang perlu verifikasi (FR-09),
            <code>durasi_lelang_bawaan</code> menjadi nilai awal durasi sesi pada dasbor operator.
        </p>
    </div>
@endsection
