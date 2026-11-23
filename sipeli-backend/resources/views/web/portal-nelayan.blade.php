@php use App\Support\Tampilan; @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal Nelayan &middot; LELANGIKAN.ID</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6">
    <header class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-300 pb-4">
        <div>
            <div class="text-lg font-semibold tracking-wide text-slate-900">LELANGIKAN.ID</div>
            <div class="text-xs text-slate-600">Portal Nelayan &mdash; rekap hasil lelang &amp; status pembayaran (baca-saja)</div>
        </div>
        <a href="{{ route('web.masuk') }}" class="rounded-lg border border-slate-400 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-white">Masuk petugas</a>
    </header>

    @if (session('sukses'))
        <div class="mt-4 rounded-xl border border-emerald-600 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('sukses') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-xl border border-rose-600 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <div class="font-semibold">Portal belum dapat dibuka:</div>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Formulir akses: token dari tautan kasir + kode 4 digit yang disampaikan terpisah (FR-59) --}}
    <form method="POST" action="{{ route('web.portal.buka') }}" class="mt-5 rounded-xl border border-slate-300 bg-white p-5">
        @csrf
        <h1 class="text-base font-semibold text-slate-900">Buka rekap</h1>
        <p class="mt-1 text-xs text-slate-600">
            Masukkan token dari tautan yang diberikan kasir TPI, lalu 4 digit kode verifikasi yang disampaikan terpisah.
        </p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <label class="block text-xs text-slate-600 sm:col-span-2">
                Token akses
                <input name="token" value="{{ $token }}" required
                       class="mt-1 w-full rounded-md border border-slate-400 px-3 py-2 font-mono text-xs">
            </label>
            <label class="block text-xs text-slate-600">
                Kode verifikasi (4 digit)
                <input name="kode" value="{{ $kode }}" required inputmode="numeric" maxlength="4" pattern="[0-9]{4}"
                       class="mt-1 w-full rounded-md border border-slate-400 px-3 py-2 text-sm tracking-[0.4em]">
            </label>
            <div class="grid grid-cols-2 gap-2">
                <label class="block text-xs text-slate-600">
                    Dari
                    <input type="date" name="dari" value="{{ $dari }}" class="mt-1 w-full rounded-md border border-slate-400 px-2 py-2 text-xs">
                </label>
                <label class="block text-xs text-slate-600">
                    Sampai
                    <input type="date" name="sampai" value="{{ $sampai }}" class="mt-1 w-full rounded-md border border-slate-400 px-2 py-2 text-xs">
                </label>
            </div>
        </div>
        <button class="mt-4 w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 sm:w-auto">
            Buka rekap
        </button>
        <p class="mt-3 text-xs text-slate-600">
            Kosongkan tanggal untuk melihat 30 hari terakhir. Portal ini hanya menampilkan data; tidak ada yang bisa diubah dari sini.
        </p>
    </form>

    @if ($rekap)
        {{-- Identitas pemilik (FR-56) --}}
        <div class="mt-5 rounded-xl border border-slate-300 bg-white p-5">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <div class="text-xs uppercase tracking-wide text-slate-600">Pemilik kapal</div>
                    <div class="text-lg font-semibold text-slate-900">{{ $rekap['pemilik']['nama'] }}</div>
                    <div class="text-xs text-slate-600">Rekening {{ $rekap['pemilik']['no_rekening'] ?? '-' }}</div>
                </div>
                <div class="text-right text-xs text-slate-600">
                    Periode<br>
                    <span class="font-semibold text-slate-900">{{ Tampilan::tanggal($rekap['periode']['dari']) }} &ndash; {{ Tampilan::tanggal($rekap['periode']['sampai']) }}</span>
                </div>
            </div>

            <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg bg-slate-50 p-3">
                    <dt class="text-xs text-slate-600">Nilai jual (bruto)</dt>
                    <dd class="text-lg font-semibold text-slate-900">{{ Tampilan::rupiah($rekap['ringkasan']['total_bruto']) }}</dd>
                </div>
                <div class="rounded-lg bg-emerald-50 p-3">
                    <dt class="text-xs text-emerald-800">Hak bersih nelayan</dt>
                    <dd class="text-lg font-semibold text-emerald-900">{{ Tampilan::rupiah($rekap['ringkasan']['total_netto']) }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <dt class="text-xs text-slate-600">Sudah dibayarkan</dt>
                    <dd class="text-lg font-semibold text-slate-900">{{ Tampilan::rupiah($rekap['ringkasan']['total_dibayar']) }}</dd>
                    <dd class="text-xs text-rose-700">Belum dibayar {{ Tampilan::rupiah($rekap['ringkasan']['belum_dibayar']) }}</dd>
                </div>
            </dl>
            <p class="mt-3 text-xs text-slate-600">{{ $rekap['ringkasan']['catatan'] }}</p>
        </div>

        {{-- Rincian per kedatangan + unduhan bukti (FR-57, FR-58, FR-60) --}}
        <div class="mt-5 space-y-3">
            @forelse ($rekap['kedatangan'] as $k)
                <div class="rounded-xl border border-slate-300 bg-white p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold text-slate-900">{{ $k['nomor_kedatangan'] }} &middot; {{ $k['nama_kapal'] }}</div>
                            <div class="text-xs text-slate-600">{{ Tampilan::tanggal($k['tanggal']) }} &middot; {{ $k['jumlah_lot'] }} lot terjual</div>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ Tampilan::warnaStatus($k['status_bayar']) }}">{{ $k['status_bayar'] }}</span>
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                        <div><dt class="text-slate-600">Bruto</dt><dd class="font-semibold text-slate-900">{{ Tampilan::rupiah($k['total_bruto']) }}</dd></div>
                        <div><dt class="text-slate-600">Retribusi</dt><dd class="font-semibold text-rose-700">&minus;{{ Tampilan::rupiah($k['potongan_retribusi']) }}</dd></div>
                        <div><dt class="text-slate-600">Potongan lain</dt><dd class="font-semibold text-rose-700">&minus;{{ Tampilan::rupiah($k['potongan_lain']) }}</dd></div>
                        <div><dt class="text-slate-600">Netto</dt><dd class="font-semibold text-emerald-800">{{ Tampilan::rupiah($k['total_netto']) }}</dd></div>
                    </dl>
                    <div class="mt-2 text-xs text-slate-600">
                        Dibayar {{ Tampilan::rupiah($k['total_dibayar']) }} &middot; sisa {{ Tampilan::rupiah($k['sisa']) }}
                        @if ($k['tanggal_bayar'])
                            &middot; dicairkan {{ $k['tanggal_bayar'] }} ({{ $k['cara_pencairan'] ?? '-' }})
                        @endif
                        @if ($k['keterangan_potongan'])
                            <br>Keterangan potongan: {{ $k['keterangan_potongan'] }}
                        @endif
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if ($k['id_bayar_nelayan'])
                            <a href="{{ url('/api/nelayan/bukti/' . $k['id_bayar_nelayan'] . '.pdf?' . http_build_query(['token' => $token, 'kode' => $kode])) }}"
                               class="rounded-md bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800">
                                Unduh bukti PDF ({{ $k['kode_bukti'] }})
                            </a>
                        @else
                            <span class="text-xs text-slate-600">Belum ada bukti pembayaran: settlement belum dihitung kasir.</span>
                        @endif
                        @if (count($k['rincian_lot']))
                            <details class="w-full">
                                <summary class="cursor-pointer text-xs font-medium text-slate-700">Rincian {{ count($k['rincian_lot']) }} lot</summary>
                                <table class="mt-2 w-full text-xs">
                                    <thead class="bg-slate-50 text-left text-slate-600">
                                        <tr>
                                            <th class="px-2 py-1">Lot</th><th class="px-2 py-1">Ikan</th>
                                            <th class="px-2 py-1 text-right">Berat</th><th class="px-2 py-1 text-right">Harga/kg</th>
                                            <th class="px-2 py-1 text-right">Subtotal</th><th class="px-2 py-1">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($k['rincian_lot'] as $l)
                                            <tr class="border-t border-slate-200">
                                                <td class="px-2 py-1 font-mono">{{ $l['kode_lot'] }}</td>
                                                <td class="px-2 py-1">{{ $l['nama_jenis'] }} {{ $l['kode_grade'] }}</td>
                                                <td class="px-2 py-1 text-right">{{ Tampilan::angka($l['berat_final'], 2) }} kg</td>
                                                <td class="px-2 py-1 text-right">{{ Tampilan::rupiah($l['harga_akhir_per_kg']) }}</td>
                                                <td class="px-2 py-1 text-right">{{ Tampilan::rupiah($l['subtotal']) }}</td>
                                                <td class="px-2 py-1">{{ $l['status_transaksi'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </details>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-slate-300 bg-white px-4 py-6 text-center text-sm text-slate-600">
                    Belum ada kedatangan kapal Anda pada periode ini.
                </div>
            @endforelse
        </div>
    @endif

    <footer class="mt-8 border-t border-slate-300 pt-4 text-xs text-slate-600">
        SKPL LELANGIKAN.ID v1.1 &mdash; UC-13 Portal Nelayan. Butuh bantuan? Hubungi kasir TPI tempat kapal Anda mendarat.
    </footer>
</div>
</body>
</html>
