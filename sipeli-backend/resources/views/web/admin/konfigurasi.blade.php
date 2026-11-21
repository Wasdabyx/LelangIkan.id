@extends('web.layout')

@section('judul', 'Konfigurasi Sistem')
@section('keterangan', 'Parameter lelang dan TPI yang dapat diubah administrator (FR-50)')

@section('isi')
    <div class="rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Parameter aktif</div>
        <div class="divide-y divide-slate-100">
            @foreach ($daftar as $k)
                <form method="POST" action="{{ route('admin.konfigurasi.simpan') }}" class="flex flex-wrap items-end gap-3 px-5 py-3">
                    @csrf
                    <input type="hidden" name="kunci" value="{{ $k->getKunci() }}">
                    <div class="min-w-48 flex-1">
                        <div class="text-sm font-medium text-slate-900">{{ $k->getKunci() }}</div>
                        <div class="text-xs text-slate-500">{{ $k->deskripsi }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500" for="nilai-{{ $k->getKunci() }}">Nilai</label>
                        <input id="nilai-{{ $k->getKunci() }}" name="nilai" value="{{ $k->getNilai() }}" maxlength="100"
                               class="mt-1 w-40 rounded-md border border-slate-300 px-2 py-1 text-sm">
                    </div>
                    <button class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">Simpan</button>
                </form>
            @endforeach
        </div>
        <p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">
            Perubahan konfigurasi tercatat pada jejak audit beserta nilai sebelum dan sesudahnya.
            <code>ambang_selisih_berat</code> dipakai penimbangan untuk menandai lot yang perlu verifikasi (FR-09),
            <code>durasi_lelang_bawaan</code> menjadi nilai awal durasi sesi pada dasbor operator.
        </p>
    </div>
@endsection
