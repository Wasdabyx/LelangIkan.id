@php use App\Support\Tampilan; @endphp
@extends('web.layout')

@section('judul', 'Saldo Pembeli')
@section('keterangan', 'Deposit, penarikan, dan penyesuaian saldo (UC-07)')

@section('isi')
    <form method="GET" action="{{ route('kasir.saldo') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-white px-5 py-3">
        <div class="flex-1 min-w-64">
            <label class="block text-xs font-medium text-slate-600" for="cari">Cari pembeli (nama, nama pengguna, NIK, atau no. HP &mdash; min. 3 karakter)</label>
            <input id="cari" name="cari" value="{{ $kataKunci }}" minlength="3"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Cari</button>
    </form>

    @if ($hasil !== null)
        <div class="rounded-xl border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Hasil pencarian ({{ count($hasil) }})</div>
            @if (count($hasil) === 0)
                <p class="px-5 py-6 text-center text-sm text-slate-500">Pembeli tidak ditemukan pada TPI Anda.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($hasil as $p)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <div>
                                <div class="font-medium text-slate-900">{{ $p['nama_lengkap'] }}
                                    <span class="ml-1 text-xs text-slate-500">({{ $p['nama_pengguna'] }})</span>
                                    @unless ($p['status_aktif'])
                                        <span class="ml-1 rounded bg-rose-100 px-1.5 py-0.5 text-xs font-semibold text-rose-800">NONAKTIF</span>
                                    @endunless
                                </div>
                                <div class="text-xs text-slate-500">NIK {{ $p['nik_tersamar'] }} &middot; HP {{ $p['no_hp_tersamar'] }}</div>
                            </div>
                            <a href="{{ route('kasir.saldo', ['pembeli' => $p['id_pembeli'], 'cari' => $kataKunci]) }}"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Buka saldo</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if ($rincian)
        <div class="grid gap-5 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-5">
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="text-base font-semibold text-slate-900">{{ $rincian['pembeli']['nama_lengkap'] }}</h2>
                    <p class="text-xs text-slate-500">{{ $rincian['pembeli']['nama_pengguna'] }} &middot; NIK {{ $rincian['pembeli']['nik_tersamar'] }}</p>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-4">
                        <div class="rounded-lg bg-slate-50 p-3">
                            <dt class="text-xs text-slate-500">Saldo</dt>
                            <dd class="text-lg font-semibold text-slate-900">{{ Tampilan::rupiah($rincian['saldo']) }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                            <dt class="text-xs text-slate-500">Limit kredit</dt>
                            <dd class="text-lg font-semibold text-slate-900">{{ Tampilan::rupiah($rincian['limit_kredit']) }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                            <dt class="text-xs text-slate-500">Tagihan terbuka</dt>
                            <dd class="text-lg font-semibold text-rose-700">{{ Tampilan::rupiah($rincian['tagihan_terbuka']) }}</dd>
                        </div>
                        <div class="rounded-lg bg-emerald-50 p-3">
                            <dt class="text-xs text-emerald-800">Daya beli efektif</dt>
                            <dd class="text-lg font-bold text-emerald-900">{{ Tampilan::rupiah($rincian['daya_beli_efektif']) }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Sepuluh mutasi terakhir</div>
                    @if (count($rincian['mutasi']) === 0)
                        <p class="px-5 py-6 text-center text-sm text-slate-500">Belum ada mutasi saldo.</p>
                    @else
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr><th class="px-4 py-2">Waktu</th><th class="px-4 py-2">Jenis</th><th class="px-4 py-2">Arah</th>
                                    <th class="px-4 py-2 text-right">Nominal</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Keterangan</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($rincian['mutasi'] as $m)
                                    <tr>
                                        <td class="px-4 py-2 text-xs text-slate-500">{{ Tampilan::waktu($m['waktu'] ?? null) }}</td>
                                        <td class="px-4 py-2">{{ $m['jenis'] ?? '-' }}</td>
                                        <td class="px-4 py-2">{{ $m['arah'] ?? '-' }}</td>
                                        <td class="px-4 py-2 text-right font-medium">{{ Tampilan::rupiah($m['nominal'] ?? 0) }}</td>
                                        <td class="px-4 py-2">
                                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ Tampilan::warnaStatus($m['status'] ?? '') }}">{{ $m['status'] ?? '-' }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-xs text-slate-600">{{ $m['keterangan'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <h3 class="text-sm font-semibold text-slate-900">Mutasi saldo baru</h3>
                <p class="mt-1 text-xs text-slate-500">Penyesuaian wajib beralasan (min. 5 karakter) dan menunggu persetujuan pimpinan.</p>
                <form method="POST" action="{{ route('kasir.saldo.mutasi', $rincian['pembeli']['id_pembeli']) }}" class="mt-3 space-y-3">
                    @csrf
                    <input type="hidden" name="cari" value="{{ $kataKunci }}">
                    <div>
                        <label class="block text-xs font-medium text-slate-600" for="jenis">Jenis</label>
                        <select id="jenis" name="jenis" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="SETOR">Setor (tambah saldo)</option>
                            <option value="PENARIKAN">Penarikan (kurangi saldo)</option>
                            <option value="PENYESUAIAN">Penyesuaian (perlu persetujuan)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600" for="nominal">Nominal (Rp)</label>
                        <input id="nominal" name="nominal" type="number" min="1" step="100" required
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600" for="metode">Metode (setor/penarikan)</label>
                        <select id="metode" name="metode" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="TUNAI">TUNAI</option>
                            <option value="TRANSFER">TRANSFER</option>
                            <option value="QRIS">QRIS</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600" for="arah">Arah penyesuaian</label>
                        <select id="arah" name="arah" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="KREDIT">KREDIT (tambah)</option>
                            <option value="DEBIT">DEBIT (kurang)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600" for="referensi">Nomor referensi</label>
                        <input id="referensi" name="referensi" maxlength="60"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600" for="keterangan">Keterangan / alasan</label>
                        <input id="keterangan" name="keterangan" maxlength="200"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Simpan mutasi</button>
                </form>
            </div>
        </div>
    @endif
@endsection
