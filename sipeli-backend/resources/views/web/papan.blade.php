@php use App\Support\Tampilan; @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Papan Lelang &middot; LELANGIKAN.ID</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-950 text-white">
<div class="mx-auto max-w-7xl px-6 py-6">
    <header class="flex flex-wrap items-end justify-between gap-4 border-b border-white/10 pb-4">
        <div>
            <div class="text-2xl font-bold tracking-wide">PAPAN LELANG</div>
            <div class="text-sm text-slate-400" id="nama-tpi">{{ $awal['nama_tpi'] ?? 'TPI' }}</div>
        </div>
        <div class="flex items-center gap-6 text-right">
            <div>
                <div class="text-xs uppercase tracking-widest text-slate-400">Jam</div>
                <div class="font-mono text-3xl font-bold tabular-nums" id="jam-server">{{ substr($awal['waktu_server'], 11, 8) }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-widest text-slate-400">Sesi aktif</div>
                <div class="font-mono text-3xl font-bold tabular-nums" id="jumlah-sesi">{{ count($awal['sesi_aktif']) }}</div>
            </div>
        </div>
    </header>

    <main id="papan" class="mt-6 grid gap-5 lg:grid-cols-2">
        @forelse ($awal['sesi_aktif'] as $s)
            <div class="rounded-2xl bg-slate-900 p-6 ring-1 ring-white/10">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="font-mono text-sm text-slate-400">{{ $s['kode_lot'] }}</div>
                        <div class="text-2xl font-bold">{{ $s['nama_jenis'] }} &middot; Grade {{ $s['kode_grade'] }}</div>
                        <div class="text-sm text-slate-400">{{ $s['nama_kapal'] }} &middot; {{ Tampilan::kg($s['berat_estimasi']) }}</div>
                    </div>
                    <div class="rounded-xl bg-slate-800 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-amber-300">{{ $s['mode'] }}</div>
                </div>
                <div class="mt-5 flex items-end justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-widest text-slate-400">Harga/kg</div>
                        <div class="font-mono text-5xl font-extrabold tabular-nums text-emerald-300">{{ Tampilan::angka($s['harga_tertinggi']) }}</div>
                        <div class="text-sm text-slate-400">
                            Pembukaan {{ Tampilan::rupiah($s['harga_pembukaan']) }} &middot;
                            {{ $s['jumlah_penawaran'] }} penawaran
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs uppercase tracking-widest text-slate-400">Sisa waktu</div>
                        <div class="font-mono text-5xl font-extrabold tabular-nums {{ $s['sisa_detik'] <= 10 ? 'text-rose-400' : 'text-white' }}">{{ Tampilan::detik($s['sisa_detik']) }}</div>
                        @if ($s['inisial_penawar'])
                            <div class="text-sm text-slate-400">Penawar: <span class="font-bold text-amber-300">{{ $s['inisial_penawar'] }}</span></div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-2xl bg-slate-900 p-12 text-center text-lg text-slate-400 ring-1 ring-white/10">
                Belum ada sesi lelang yang berjalan.
            </div>
        @endforelse
    </main>

    <footer class="mt-8 grid gap-3 border-t border-white/10 pt-4 text-sm text-slate-300 sm:grid-cols-4">
        <div><div class="text-xs uppercase tracking-widest text-slate-500">Lot hari ini</div><div class="text-xl font-semibold" id="r-lot">{{ $awal['ringkasan']['jumlah_lot'] }}</div></div>
        <div><div class="text-xs uppercase tracking-widest text-slate-500">Transaksi</div><div class="text-xl font-semibold" id="r-transaksi">{{ $awal['ringkasan']['jumlah_transaksi'] }}</div></div>
        <div><div class="text-xs uppercase tracking-widest text-slate-500">Volume terjual</div><div class="text-xl font-semibold" id="r-volume">{{ Tampilan::kg($awal['ringkasan']['volume_terjual_kg']) }}</div></div>
        <div><div class="text-xs uppercase tracking-widest text-slate-500">Nilai transaksi</div><div class="text-xl font-semibold" id="r-nilai">{{ Tampilan::rupiah($awal['ringkasan']['nilai_transaksi']) }}</div></div>
    </footer>
</div>

<script>
    (function () {
        const url = @json(route('papan.keadaan'));
        const rupiah = (n) => 'Rp' + Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
        const angka = (n) => Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
        const detik = (n) => {
            n = Math.max(0, Number(n || 0));
            return String(Math.floor(n / 60)).padStart(2, '0') + ':' + String(n % 60).padStart(2, '0');
        };
        const kotak = document.getElementById('papan');

        function kartu(s) {
            return '<div class="rounded-2xl bg-slate-900 p-6 ring-1 ring-white/10">'
                + '<div class="flex items-start justify-between"><div>'
                + '<div class="font-mono text-sm text-slate-400">' + s.kode_lot + '</div>'
                + '<div class="text-2xl font-bold">' + (s.nama_jenis || '-') + ' · Grade ' + (s.kode_grade || '-') + '</div>'
                + '<div class="text-sm text-slate-400">' + (s.nama_kapal || '-') + ' · ' + angka(s.berat_estimasi) + ' kg</div>'
                + '</div><div class="rounded-xl bg-slate-800 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-amber-300">' + s.mode + '</div></div>'
                + '<div class="mt-5 flex items-end justify-between gap-4"><div>'
                + '<div class="text-xs uppercase tracking-widest text-slate-400">Harga/kg</div>'
                + '<div class="font-mono text-5xl font-extrabold tabular-nums text-emerald-300">' + angka(s.harga_tertinggi) + '</div>'
                + '<div class="text-sm text-slate-400">Pembukaan ' + rupiah(s.harga_pembukaan) + ' · ' + s.jumlah_penawaran + ' penawaran</div>'
                + '</div><div class="text-right">'
                + '<div class="text-xs uppercase tracking-widest text-slate-400">Sisa waktu</div>'
                + '<div class="font-mono text-5xl font-extrabold tabular-nums ' + (s.sisa_detik <= 10 ? 'text-rose-400' : 'text-white') + '">' + detik(s.sisa_detik) + '</div>'
                + (s.inisial_penawar ? '<div class="text-sm text-slate-400">Penawar: <span class="font-bold text-amber-300">' + s.inisial_penawar + '</span></div>' : '')
                + '</div></div></div>';
        }

        async function tarik() {
            try {
                const r = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!r.ok) { return; }
                const j = await r.json();
                document.getElementById('jam-server').textContent = (j.waktu_server || '').substring(11, 19);
                document.getElementById('jumlah-sesi').textContent = j.sesi_aktif.length;
                document.getElementById('nama-tpi').textContent = j.nama_tpi || 'TPI';
                kotak.innerHTML = j.sesi_aktif.length
                    ? j.sesi_aktif.map(kartu).join('')
                    : '<div class="col-span-full rounded-2xl bg-slate-900 p-12 text-center text-lg text-slate-400 ring-1 ring-white/10">Belum ada sesi lelang yang berjalan.</div>';

                const g = j.ringkasan || {};
                document.getElementById('r-lot').textContent = g.jumlah_lot ?? 0;
                document.getElementById('r-transaksi').textContent = g.jumlah_transaksi ?? 0;
                document.getElementById('r-volume').textContent = angka(g.volume_terjual_kg) + ' kg';
                document.getElementById('r-nilai').textContent = rupiah(g.nilai_transaksi);
            } catch (e) { /* kanal putus: papan tetap menampilkan angka terakhir */ }
        }
        setInterval(tarik, 1000);
    })();
</script>
</body>
</html>
