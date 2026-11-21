@php use App\Support\Tampilan; @endphp
@extends('web.layout')

@section('judul', 'Settlement Nelayan')
@section('keterangan', 'Perhitungan retribusi dan pembayaran hasil lelang kepada nelayan/pemilik kapal')

@section('isi')
    <form method="GET" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-white px-5 py-3">
        <div>
            <label class="block text-xs font-medium text-slate-600" for="tanggal">Tanggal kedatangan</label>
            <input id="tanggal" type="date" name="tanggal" value="{{ $tanggal }}"
                   class="mt-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
        </div>
        <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Saring</button>
        <a href="{{ route('kasir.settlement') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Semua</a>
    </form>

    <div class="rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Kedatangan dengan lot lunas</h2>
            <p class="text-xs text-slate-500">Hanya kedatangan yang seluruh atau sebagian lotnya sudah dibayar pembeli yang dapat diselesaikan.</p>
        </div>
        @if (count($kedatangan) === 0)
            <p class="px-5 py-10 text-center text-sm text-slate-500">Belum ada kedatangan dengan lot lunas.</p>
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($kedatangan as $k)
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-slate-900">{{ $k['nomor_kedatangan'] }}</span>
                                    <span class="text-xs text-slate-500">{{ Tampilan::tanggal($k['tanggal']) }}</span>
                                    @if ($k['pembayaran'])
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ Tampilan::warnaStatus($k['pembayaran']['status']) }}">{{ $k['pembayaran']['status'] }}</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">BELUM DIHITUNG</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ $k['nama_kapal'] }} &middot; {{ $k['nama_pemilik'] }} &middot; {{ $k['no_rekening'] ?? 'tanpa rekening' }}
                                    &middot; {{ $k['jumlah_lot_lunas'] }} lot lunas
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-5 text-right text-sm">
                                <div>
                                    <div class="text-xs text-slate-500">Bruto</div>
                                    <div class="font-medium">{{ Tampilan::rupiah($k['total_bruto']) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-slate-500">Retribusi</div>
                                    <div class="font-medium text-rose-700">&minus;{{ Tampilan::rupiah($k['potongan_retribusi']) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-slate-500">Netto ke nelayan</div>
                                    <div class="font-bold text-emerald-700">{{ Tampilan::rupiah($k['total_netto']) }}</div>
                                </div>
                            </div>
                        </div>

                        @if ($k['pembayaran'])
                            <details class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                <summary class="cursor-pointer font-medium text-slate-700">Rincian lot dan status pencairan</summary>
                                <table class="mt-2 w-full">
                                    <thead class="text-left text-slate-500">
                                        <tr><th class="py-1">Lot</th><th class="py-1">Jenis</th><th class="py-1 text-right">Berat</th><th class="py-1 text-right">Harga/kg</th><th class="py-1 text-right">Subtotal</th><th class="py-1 text-right">Retribusi</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($k['rincian_lot'] as $lot)
                                            <tr class="border-t border-slate-200">
                                                <td class="py-1">{{ $lot['kode_lot'] }}</td>
                                                <td class="py-1">{{ $lot['nama_jenis'] }} / {{ $lot['kode_grade'] }}</td>
                                                <td class="py-1 text-right">{{ Tampilan::kg($lot['berat_final']) }}</td>
                                                <td class="py-1 text-right">{{ Tampilan::rupiah($lot['harga_akhir_per_kg']) }}</td>
                                                <td class="py-1 text-right">{{ Tampilan::rupiah($lot['subtotal']) }}</td>
                                                <td class="py-1 text-right">{{ Tampilan::rupiah($lot['nilai_retribusi']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </details>
                        @endif

                        <div class="mt-3 flex flex-wrap items-end gap-2">
                            @if (! $k['pembayaran'])
                                <form method="POST" action="{{ route('kasir.settlement.hitung', $k['id_kedatangan']) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <div>
                                        <label class="block text-xs text-slate-500">Potongan lain (Rp)</label>
                                        <input name="potongan_lain" type="number" min="0" step="100" value="0"
                                               class="mt-1 w-28 rounded-md border border-slate-300 px-2 py-1 text-xs">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-500">Keterangan potongan</label>
                                        <input name="keterangan_potongan" maxlength="200" placeholder="mis. biaya kebersihan"
                                               class="mt-1 w-48 rounded-md border border-slate-300 px-2 py-1 text-xs">
                                    </div>
                                    <button class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">Hitung settlement</button>
                                </form>
                            @else
                                <a href="{{ route('kasir.settlement.rincian', $k['pembayaran']['id_bayar_nelayan']) }}"
                                   class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    Buka bukti {{ $k['pembayaran']['kode_bukti'] }}
                                </a>
                                @if ((float) $k['pembayaran']['sisa'] > 0)
                                    <form method="POST" action="{{ route('kasir.settlement.cairkan', $k['pembayaran']['id_bayar_nelayan']) }}" class="flex flex-wrap items-end gap-2">
                                        @csrf
                                        <div>
                                            <label class="block text-xs text-slate-500">Cara pencairan</label>
                                            <select name="cara_pencairan" class="mt-1 rounded-md border border-slate-300 px-2 py-1 text-xs">
                                                @foreach ($caraCair as $cara)
                                                    <option value="{{ $cara }}">{{ $cara }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-slate-500">Nominal (kosongkan = sisa)</label>
                                            <input name="nominal" type="number" min="1" step="100" placeholder="{{ (int) $k['pembayaran']['sisa'] }}"
                                                   class="mt-1 w-28 rounded-md border border-slate-300 px-2 py-1 text-xs">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-slate-500">No. referensi</label>
                                            <input name="nomor_referensi" maxlength="40"
                                                   class="mt-1 w-32 rounded-md border border-slate-300 px-2 py-1 text-xs">
                                        </div>
                                        <button class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Cairkan</button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
