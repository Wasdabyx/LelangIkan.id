@php use App\Support\Tampilan; @endphp
@extends('web.layout')

@section('judul', 'Bukti Settlement')
@section('keterangan', 'Kode bukti ' . $rincian['kode_bukti'])

@section('isi')
    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-5">
            <div class="rounded-xl border border-slate-500 bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Pembayaran hasil lelang</h2>
                        <p class="text-sm text-slate-600">
                            {{ $rincian['nomor_kedatangan'] }} &middot; {{ $rincian['nama_kapal'] }} &middot; {{ $rincian['nama_pemilik'] }}
                        </p>
                        <p class="text-xs text-slate-600">Rekening {{ $rincian['no_rekening'] ?? '-' }} &middot; dicatat oleh {{ $rincian['nama_kasir'] ?? '-' }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ Tampilan::warnaStatus($rincian['status']) }}">{{ $rincian['status'] }}</span>
                </div>

                <dl class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-xs text-slate-600">Total bruto</dt>
                        <dd class="text-lg font-semibold text-slate-900">{{ Tampilan::rupiah($rincian['total_bruto']) }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-xs text-slate-600">Potongan retribusi</dt>
                        <dd class="text-lg font-semibold text-rose-700">&minus;{{ Tampilan::rupiah($rincian['potongan_retribusi']) }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-xs text-slate-600">Potongan lain</dt>
                        <dd class="text-lg font-semibold text-rose-700">&minus;{{ Tampilan::rupiah($rincian['potongan_lain']) }}</dd>
                        @if ($rincian['keterangan_potongan'])
                            <dd class="text-xs text-slate-600">{{ $rincian['keterangan_potongan'] }}</dd>
                        @endif
                    </div>
                </dl>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-emerald-50 px-4 py-3">
                    <div>
                        <div class="text-xs text-emerald-800">Total netto kepada nelayan</div>
                        <div class="text-2xl font-bold text-emerald-900">{{ Tampilan::rupiah($rincian['total_netto']) }}</div>
                    </div>
                    <div class="text-right text-sm">
                        <div class="text-emerald-800">Sudah dicairkan {{ Tampilan::rupiah($rincian['total_dibayar']) }}</div>
                        <div class="font-semibold text-emerald-900">Sisa {{ Tampilan::rupiah($rincian['sisa']) }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-500 bg-white">
                <div class="border-b border-slate-500 px-5 py-3 text-sm font-semibold text-slate-900">Lot yang diperhitungkan</div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-2">Kode lot</th><th class="px-4 py-2 text-right">Berat</th>
                            <th class="px-4 py-2 text-right">Harga/kg</th><th class="px-4 py-2 text-right">Subtotal</th>
                            <th class="px-4 py-2 text-right">Retribusi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rincian['rincian_lot'] as $lot)
                            <tr>
                                <td class="px-4 py-2 font-medium">{{ $lot['kode_lot'] }}</td>
                                <td class="px-4 py-2 text-right">{{ Tampilan::kg($lot['berat_final']) }}</td>
                                <td class="px-4 py-2 text-right">{{ Tampilan::rupiah($lot['harga_akhir_per_kg']) }}</td>
                                <td class="px-4 py-2 text-right">{{ Tampilan::rupiah($lot['subtotal']) }}</td>
                                <td class="px-4 py-2 text-right text-rose-700">{{ Tampilan::rupiah($lot['nilai_retribusi']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="rounded-xl border border-slate-500 bg-white">
                <div class="border-b border-slate-500 px-5 py-3 text-sm font-semibold text-slate-900">Riwayat pencairan</div>
                @if (count($rincian['pencairan']) === 0)
                    <p class="px-5 py-6 text-center text-sm text-slate-600">Belum ada pencairan.</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
                            <tr><th class="px-4 py-2">Waktu</th><th class="px-4 py-2">Cara</th><th class="px-4 py-2 text-right">Nominal</th><th class="px-4 py-2">Referensi</th><th class="px-4 py-2">Kasir</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($rincian['pencairan'] as $p)
                                <tr>
                                    <td class="px-4 py-2 text-xs text-slate-600">{{ $p['waktu'] }}</td>
                                    <td class="px-4 py-2">{{ $p['cara_pencairan'] }}</td>
                                    <td class="px-4 py-2 text-right font-medium">{{ Tampilan::rupiah($p['nominal']) }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ $p['nomor_referensi'] ?? '-' }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ $p['nama_kasir'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="space-y-5">
            @if ((float) $rincian['sisa'] > 0)
                <div class="rounded-xl border border-slate-500 bg-white p-5">
                    <h3 class="text-sm font-semibold text-slate-900">Cairkan dana</h3>
                    <form method="POST" action="{{ route('kasir.settlement.cairkan', $rincian['id_bayar_nelayan']) }}" class="mt-3 space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-600" for="cara">Cara pencairan</label>
                            <select id="cara" name="cara_pencairan" class="mt-1 w-full rounded-lg border border-slate-500 px-3 py-2 text-sm">
                                @foreach ($caraCair as $cara)
                                    <option value="{{ $cara }}">{{ $cara }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600" for="nominal">Nominal (kosongkan = sisa)</label>
                            <input id="nominal" name="nominal" type="number" min="1" step="100" placeholder="{{ (int) $rincian['sisa'] }}"
                                   class="mt-1 w-full rounded-lg border border-slate-500 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600" for="ref">Nomor referensi bank/kartu</label>
                            <input id="ref" name="nomor_referensi" maxlength="40"
                                   class="mt-1 w-full rounded-lg border border-slate-500 px-3 py-2 text-sm">
                        </div>
                        <button class="w-full rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Cairkan</button>
                    </form>
                    <p class="mt-2 text-xs text-slate-600">
                        Pencairan tunai, transfer, dan kartu nelayan dicatat lengkap pada jejak audit (FR-40, FR-67).
                    </p>
                </div>
            @else
                <div class="rounded-xl border border-emerald-600 bg-emerald-50 p-5 text-sm text-emerald-900">
                    Seluruh dana sudah dicairkan. Bukti {{ $rincian['kode_bukti'] }} dapat dicetak dari menu cetak bukti.
                </div>
            @endif

            <a href="{{ route('kasir.settlement') }}" class="block rounded-xl border border-slate-500 bg-white px-4 py-3 text-center text-sm text-slate-700 hover:bg-slate-50">
                &larr; Kembali ke daftar settlement
            </a>
        </div>
    </div>
@endsection
