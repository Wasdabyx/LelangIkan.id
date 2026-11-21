<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan {{ $jenis }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #14251f; }
        h1 { font-size: 15px; margin: 0; color: #0f766e; }
        .muted { color: #5b6b64; font-size: 9px; }
        .kop { border-bottom: 2px solid #0f766e; padding-bottom: 6px; margin-bottom: 12px; }
        h2 { font-size: 11px; margin: 14px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { padding: 3px 4px; border-bottom: 1px solid #e2ecea; }
        thead th { background: #e7f3f0; font-size: 8.5px; text-transform: uppercase; text-align: left; }
        .angka { text-align: right; }
        table.kpi { width: 100%; border-collapse: separate; border-spacing: 4px; margin-bottom: 6px; }
        table.kpi td { border: 1px solid #d6e5e1; padding: 6px; vertical-align: top; }
        .label { font-size: 8px; color: #5b6b64; }
        .nilai { font-size: 12px; font-weight: bold; color: #0f766e; }
        .kaki { margin-top: 12px; font-size: 8px; color: #5b6b64; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>LAPORAN {{ strtoupper($jenis) }} LELANG IKAN</h1>
        <div>TPI Palabuhanratu &middot; LELANGIKAN.ID</div>
        <div class="muted">Periode {{ $dari }} s.d. {{ $sampai }} &middot; dicetak {{ $dicetak->format('d-m-Y H:i') }} oleh {{ $pencetak }}</div>
    </div>

    @if($jenis === 'harga')
        <table class="kpi"><tr><td><div class="label">Volume terjual</div><div class="nilai">{{ number_format($laporan['harga_dan_volume']['total']['volume_kg'], 2, ',', '.') }} kg</div></td><td><div class="label">Nilai transaksi</div><div class="nilai">Rp{{ number_format($laporan['harga_dan_volume']['total']['nilai'], 0, ',', '.') }}</div></td></tr></table>
        <h2>Volume, nilai, dan harga rata-rata per jenis dan grade (FR-44)</h2>
        <table>
            <thead><tr><th>Jenis ikan</th><th>Grade</th><th class="angka">Trx</th><th class="angka">Volume (kg)</th>
                <th class="angka">Nilai</th><th class="angka">Harga rata-rata</th><th class="angka">Min</th><th class="angka">Maks</th></tr></thead>
            <tbody>
            @foreach($laporan['harga_dan_volume']['baris'] as $r)
                <tr><td>{{ $r['nama_jenis'] }}</td><td>{{ $r['kode_grade'] }}</td><td class="angka">{{ $r['jumlah_transaksi'] }}</td>
                    <td class="angka">{{ number_format($r['volume_kg'], 2, ',', '.') }}</td>
                    <td class="angka">{{ number_format($r['nilai'], 0, ',', '.') }}</td>
                    <td class="angka">{{ number_format($r['harga_rata_rata'], 0, ',', '.') }}</td>
                    <td class="angka">{{ number_format($r['harga_terendah'], 0, ',', '.') }}</td>
                    <td class="angka">{{ number_format($r['harga_tertinggi'], 0, ',', '.') }}</td></tr>
            @endforeach
            </tbody>
        </table>

    @elseif($jenis === 'retribusi')
        <table class="kpi"><tr><td><div class="label">Total retribusi periode</div><div class="nilai">Rp{{ number_format($laporan['retribusi']['total_retribusi'], 0, ',', '.') }}</div></td></tr></table>
        <h2>Rekap retribusi harian (FR-46)</h2>
        <table>
            <thead><tr><th>Tanggal</th><th class="angka">Transaksi</th><th class="angka">Nilai transaksi</th><th class="angka">Retribusi</th></tr></thead>
            <tbody>
            @foreach($laporan['retribusi']['harian'] as $r)
                <tr><td>{{ $r['tanggal'] }}</td><td class="angka">{{ $r['jumlah_transaksi'] }}</td>
                    <td class="angka">{{ number_format($r['nilai_transaksi'], 0, ',', '.') }}</td>
                    <td class="angka">{{ number_format($r['retribusi'], 0, ',', '.') }}</td></tr>
            @endforeach
            </tbody>
        </table>
        <h2>Rekap retribusi bulanan</h2>
        <table>
            <thead><tr><th>Bulan</th><th class="angka">Transaksi</th><th class="angka">Nilai transaksi</th><th class="angka">Retribusi</th></tr></thead>
            <tbody>
            @foreach($laporan['retribusi']['bulanan'] as $r)
                <tr><td>{{ $r['bulan'] }}</td><td class="angka">{{ $r['jumlah_transaksi'] }}</td>
                    <td class="angka">{{ number_format($r['nilai_transaksi'], 0, ',', '.') }}</td>
                    <td class="angka">{{ number_format($r['retribusi'], 0, ',', '.') }}</td></tr>
            @endforeach
            </tbody>
        </table>

    @elseif($jenis === 'tren')
        <table class="kpi"><tr><td><div class="label">Harga rata-rata periode ini</div><div class="nilai">Rp{{ number_format($laporan['tren_harga']['harga_rata_rata'], 0, ',', '.') }}</div></td><td><div class="label">Periode pembanding ({{ $laporan['tren_harga']['periode_pembanding']['dari'] }} s.d. {{ $laporan['tren_harga']['periode_pembanding']['sampai'] }})</div>
                <div class="nilai">Rp{{ number_format($laporan['tren_harga']['harga_rata_rata_pembanding'], 0, ',', '.') }}</div></td><td><div class="label">Perubahan</div><div class="nilai">{{ $laporan['tren_harga']['perubahan_persen'] === null ? '-' : $laporan['tren_harga']['perubahan_persen'].' %' }} ({{ $laporan['tren_harga']['arah'] }})</div></td></tr></table>
        <h2>Tren harga harian (FR-45)</h2>
        <table>
            <thead><tr><th>Tanggal</th><th class="angka">Transaksi</th><th class="angka">Volume (kg)</th><th class="angka">Nilai</th><th class="angka">Harga rata-rata</th></tr></thead>
            <tbody>
            @foreach($laporan['tren_harga']['harian'] as $r)
                <tr><td>{{ $r['tanggal'] }}</td><td class="angka">{{ $r['jumlah_transaksi'] }}</td>
                    <td class="angka">{{ number_format($r['volume_kg'], 2, ',', '.') }}</td>
                    <td class="angka">{{ number_format($r['nilai'], 0, ',', '.') }}</td>
                    <td class="angka">{{ number_format($r['harga_rata_rata'], 0, ',', '.') }}</td></tr>
            @endforeach
            </tbody>
        </table>

    @else
        @php $k = $laporan['kinerja_lelang']; @endphp
        <table class="kpi"><tr><td><div class="label">Jumlah sesi</div><div class="nilai">{{ $k['jumlah_sesi'] }}</div></td><td><div class="label">Sesi terjual</div><div class="nilai">{{ $k['sesi_terjual'] }}</div></td><td><div class="label">Tidak terjual</div><div class="nilai">{{ $k['sesi_tidak_terjual'] }}</div></td><td><div class="label">Keberhasilan</div><div class="nilai">{{ $k['tingkat_keberhasilan_persen'] }} %</div></td><td><div class="label">Settlement lunas</div><div class="nilai">{{ $k['kedatangan_lunas'] }} / {{ $k['kedatangan_disettlement'] }}</div></td></tr></table>
        <h2>Kinerja lelang periode {{ $dari }} s.d. {{ $sampai }}</h2>
        <table>
            <thead><tr><th>Indikator</th><th class="angka">Nilai</th></tr></thead>
            <tbody>
                <tr><td>Sesi diperpanjang otomatis</td><td class="angka">{{ $k['sesi_diperpanjang'] }}</td></tr>
                <tr><td>Kedatangan sudah disettlement</td><td class="angka">{{ $k['kedatangan_disettlement'] }}</td></tr>
                <tr><td>Kedatangan dengan pembayaran nelayan lunas</td><td class="angka">{{ $k['kedatangan_lunas'] }}</td></tr>
            </tbody>
        </table>
    @endif

    <div class="kaki">
        Dokumen dihasilkan sistem LELANGIKAN.ID (versi 1.1) untuk keperluan evaluasi UC-10.
        Semua angka diambil dari transaksi tercatat pada basis data TPI.
    </div>
</body>
</html>
