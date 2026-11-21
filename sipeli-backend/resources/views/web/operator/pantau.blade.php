@php use App\Support\Tampilan; @endphp
@extends('web.layout')

@section('judul', 'Pemantauan Sesi Lelang')
@section('keterangan', 'Diperbarui otomatis setiap 1 detik dari GET /dasbor/operator/sesi/{id}/keadaan')

@section('isi')
    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-5">
            <div class="rounded-xl border border-slate-500 bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-slate-900" id="kode-lot">{{ $awal['lot']['kode_lot'] }}</h2>
                            <span id="lencana-status" class="rounded-full px-2 py-0.5 text-xs font-semibold {{ Tampilan::warnaStatus($awal['status']) }}">{{ $awal['status'] }}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600" id="lencana-mode">{{ $awal['mode'] }}</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-600" id="ringkas-lot">
                            {{ $awal['lot']['nama_jenis'] }} grade {{ $awal['lot']['kode_grade'] }} &middot; {{ Tampilan::kg($awal['lot']['berat_aktual']) }} &middot; {{ $awal['lot']['nama_kapal'] }}
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-xs uppercase tracking-wide text-slate-600">Sisa waktu</div>
                        <div class="font-mono text-3xl font-bold tabular-nums text-slate-900" id="sisa-waktu">{{ Tampilan::detik($awal['sisa_detik']) }}</div>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <div class="text-xs text-slate-600">Harga berjalan / tertinggi</div>
                        <div class="text-xl font-bold text-slate-900" id="harga-berjalan">{{ Tampilan::rupiah($awal['harga_tertinggi'] > 0 ? $awal['harga_tertinggi'] : $awal['harga_pembukaan']) }}</div>
                        <div class="text-xs text-slate-600" id="harga-minimum">Minimum berikutnya {{ Tampilan::rupiah($awal['harga_minimum_berikut']) }}</div>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <div class="text-xs text-slate-600">Harga pembukaan</div>
                        <div class="text-lg font-semibold text-slate-900">{{ Tampilan::rupiah($awal['harga_pembukaan']) }}</div>
                        <div class="text-xs text-slate-600">Kenaikan minimum {{ Tampilan::rupiah($awal['kenaikan_minimum']) }}</div>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <div class="text-xs text-slate-600">Penawar / penawaran</div>
                        <div class="text-lg font-semibold text-slate-900"><span id="jumlah-penawar">{{ $awal['jumlah_penawar'] }}</span> / <span id="jumlah-penawaran">{{ $awal['jumlah_penawaran'] }}</span></div>
                        <div class="text-xs text-slate-600">Peserta terdaftar: {{ $awal['jumlah_peserta'] }}</div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    <span>Penawar tertinggi: <strong id="penawar-tertinggi">{{ $awal['penawar_tertinggi'] ?? 'belum ada' }}</strong></span>
                    <span>Perpanjangan: <span id="perpanjangan">{{ $awal['jumlah_perpanjangan'] }}</span>/{{ $awal['maks_perpanjangan'] }}</span>
                    <span>Mulai: {{ Tampilan::waktu($awal['waktu_mulai']) }}</span>
                </div>
            </div>

            <div class="rounded-xl border border-slate-500 bg-white">
                <div class="border-b border-slate-500 px-5 py-3 text-sm font-semibold text-slate-900">Sepuluh penawaran terakhir</div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
                        <tr><th class="px-5 py-2">Waktu</th><th class="px-5 py-2">Penawar</th><th class="px-5 py-2 text-right">Harga/kg</th><th class="px-5 py-2">Status</th></tr>
                    </thead>
                    <tbody id="riwayat" class="divide-y divide-slate-100">
                        @forelse ($awal['riwayat'] as $r)
                            <tr>
                                <td class="px-5 py-2 font-mono text-xs text-slate-600">{{ $r['waktu'] }}</td>
                                <td class="px-5 py-2">{{ $r['penawar'] }}</td>
                                <td class="px-5 py-2 text-right font-medium">{{ Tampilan::rupiah($r['harga_per_kg']) }}</td>
                                <td class="px-5 py-2 text-xs">{{ $r['tertinggi'] ? 'Tertinggi' : 'Tersalip' }}</td>
                            </tr>
                        @empty
                            <tr id="riwayat-kosong"><td colspan="4" class="px-5 py-6 text-center text-sm text-slate-600">Belum ada penawaran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-5">
            <div class="rounded-xl border border-slate-500 bg-white p-5" id="kotak-tutup" @if ($awal['status'] !== 'AKTIF') hidden @endif>
                <h3 class="text-sm font-semibold text-slate-900">Tutup sesi lebih awal</h3>
                <p class="mt-1 text-xs text-slate-600">Penutupan manual menetapkan pemenang saat itu juga dan tercatat pada jejak audit.</p>
                <form method="POST" action="{{ route('operator.sesi.tutup', $idSesi) }}" class="mt-3 space-y-2"
                      onsubmit="return confirm('Tutup sesi ini sekarang dan tetapkan pemenang?')">
                    @csrf
                    <input name="alasan" required minlength="5" maxlength="255" placeholder="Alasan penutupan (min. 5 karakter)"
                           class="w-full rounded-lg border border-slate-500 px-3 py-2 text-sm">
                    <button class="w-full rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700">Tutup sesi</button>
                </form>
            </div>

            <div class="rounded-xl border border-slate-500 bg-white p-5 text-sm" id="kotak-hasil" @if ($awal['status'] === 'AKTIF') hidden @endif>
                <h3 class="text-sm font-semibold text-slate-900">Hasil lelang</h3>
                <dl class="mt-2 space-y-1 text-xs text-slate-600">
                    <div class="flex justify-between"><dt>Terjual</dt><dd id="hasil-terjual">-</dd></div>
                    <div class="flex justify-between"><dt>Pemenang</dt><dd id="hasil-pemenang">-</dd></div>
                    <div class="flex justify-between"><dt>Harga akhir/kg</dt><dd id="hasil-harga">-</dd></div>
                    <div class="flex justify-between"><dt>Total tagihan</dt><dd id="hasil-tagihan">-</dd></div>
                    <div class="flex justify-between"><dt>Batas bayar</dt><dd id="hasil-batas">-</dd></div>
                </dl>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const url = @json(route('operator.sesi.keadaan', $idSesi));
            const rupiah = (n) => 'Rp' + Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
            const detik = (n) => {
                n = Math.max(0, Number(n || 0));
                return String(Math.floor(n / 60)).padStart(2, '0') + ':' + String(n % 60).padStart(2, '0');
            };
            const el = (id) => document.getElementById(id);
            const hargaTampil = (d) => (Number(d.harga_tertinggi) > 0 ? d.harga_tertinggi : d.harga_pembukaan);

            function terap(data) {
                el('lencana-status').textContent = data.status;
                el('lencana-mode').textContent = data.mode;
                el('sisa-waktu').textContent = detik(data.sisa_detik);
                el('harga-berjalan').textContent = rupiah(hargaTampil(data));
                // Sesi yang sudah ditutup tidak menampilkan formulir penutupan lagi.
                el('kotak-tutup').hidden = data.status !== 'AKTIF';
                el('kotak-hasil').hidden = data.status === 'AKTIF';
                el('harga-minimum').textContent = 'Minimum berikutnya ' + rupiah(data.harga_minimum_berikut);
                el('jumlah-penawar').textContent = data.jumlah_penawar;
                el('jumlah-penawaran').textContent = data.jumlah_penawaran;
                el('penawar-tertinggi').textContent = data.penawar_tertinggi || 'belum ada';
                el('perpanjangan').textContent = data.jumlah_perpanjangan;
                el('kode-lot').textContent = data.lot.kode_lot;
                el('ringkas-lot').textContent = data.lot.nama_jenis + ' grade ' + data.lot.kode_grade
                    + ' · ' + Number(data.lot.berat_aktual).toLocaleString('id-ID') + ' kg · ' + data.lot.nama_kapal;

                const badan = el('riwayat');
                badan.innerHTML = data.riwayat.length
                    ? data.riwayat.map((r) => '<tr>'
                        + '<td class="px-5 py-2 font-mono text-xs text-slate-600">' + r.waktu + '</td>'
                        + '<td class="px-5 py-2">' + r.penawar + '</td>'
                        + '<td class="px-5 py-2 text-right font-medium">' + rupiah(r.harga_per_kg) + '</td>'
                        + '<td class="px-5 py-2 text-xs">' + (r.tertinggi ? 'Tertinggi' : 'Tersalip') + '</td>'
                        + '</tr>').join('')
                    : '<tr><td colspan="4" class="px-5 py-6 text-center text-sm text-slate-600">Belum ada penawaran.</td></tr>';

                if (data.hasil) {
                    el('kotak-hasil').hidden = false;
                    el('hasil-terjual').textContent = data.hasil.terjual ? 'Ya' : 'Tidak';
                    el('hasil-pemenang').textContent = data.hasil.pemenang || '-';
                    el('hasil-harga').textContent = data.hasil.harga_akhir_per_kg ? rupiah(data.hasil.harga_akhir_per_kg) : '-';
                    el('hasil-tagihan').textContent = data.hasil.total_tagihan ? rupiah(data.hasil.total_tagihan) : '-';
                    el('hasil-batas').textContent = data.hasil.batas_bayar || '-';
                }
            }

            let jeda = null;
            async function tarik() {
                try {
                    const r = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    if (!r.ok) { return; }
                    const j = await r.json();
                    terap(j.data);
                    // Sesi yang sudah ditutup tidak perlu dipantau lagi.
                    if (j.data.status !== 'AKTIF' && jeda) { clearInterval(jeda); jeda = null; }
                } catch (e) { /* jaringan putus: percobaan berikutnya tetap berjalan */ }
            }
            jeda = setInterval(tarik, 1000);
        })();
    </script>
@endsection
