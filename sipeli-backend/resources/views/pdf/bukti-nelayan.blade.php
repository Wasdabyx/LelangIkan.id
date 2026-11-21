<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Pembayaran {{ $p['kode_bukti'] }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #14251f; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .muted { color: #5b6b64; }
        .kop { border-bottom: 2px solid #0f766e; padding-bottom: 6px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 3px 4px; text-align: left; }
        thead th { background: #e7f3f0; font-size: 9px; text-transform: uppercase; letter-spacing: .04em; }
        tbody tr:nth-child(even) { background: #f7faf9; }
        .angka { text-align: right; }
        .total td { border-top: 1px solid #b9cfc8; font-weight: bold; }
        .netto { margin-top: 8px; background: #0f766e; color: #fff; padding: 8px; text-align: right; }
        .netto .label { font-size: 9px; opacity: .85; }
        .netto .nilai { font-size: 16px; font-weight: bold; }
        .kaki { margin-top: 10px; font-size: 8px; color: #5b6b64; }
        .tanda { margin-top: 14px; font-size: 9px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>BUKTI PEMBAYARAN HASIL LELANG NELAYAN</h1>
        <div>TPI Palabuhanratu &middot; LELANGIKAN.ID</div>
        <div class="muted">
            Bukti {{ $p['kode_bukti'] }} &middot; Kedatangan {{ $p['nomor_kedatangan'] }} &middot;
            Dicetak {{ $dicetak->format('d-m-Y H:i') }}
        </div>
    </div>

    <table>
        <tr><td style="width:110px">Nama pemilik</td><td>: {{ $p['nama_pemilik'] }}</td></tr>
        <tr><td>Kapal</td><td>: {{ $p['nama_kapal'] }}</td></tr>
        <tr><td>Rekening</td><td>: {{ $p['no_rekening'] ?: '-' }}</td></tr>
        <tr><td>Cara pencairan</td><td>: {{ $p['cara_pencairan'] ?: '-' }}
            @if($p['nomor_referensi']) (ref. {{ $p['nomor_referensi'] }}) @endif</td></tr>
        <tr><td>Status</td><td>: {{ $p['status'] }} &middot; dibayar Rp{{ number_format((float) $p['total_dibayar'], 0, ',', '.') }}</td></tr>
    </table>

    <table style="margin-top:10px">
        <thead>
            <tr>
                <th>Kode lot</th><th class="angka">Berat (kg)</th><th class="angka">Harga/kg</th>
                <th class="angka">Subtotal</th><th class="angka">Retribusi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($p['rincian_lot'] as $lot)
            <tr>
                <td>{{ $lot['kode_lot'] }}</td>
                <td class="angka">{{ $lot['berat_final'] }}</td>
                <td class="angka">{{ number_format((float) $lot['harga_akhir_per_kg'], 0, ',', '.') }}</td>
                <td class="angka">{{ number_format((float) $lot['subtotal'], 0, ',', '.') }}</td>
                <td class="angka">{{ number_format((float) $lot['nilai_retribusi'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="total">
                <td colspan="3">Total bruto</td>
                <td class="angka" colspan="2">Rp{{ number_format((float) $p['total_bruto'], 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td colspan="3">Potongan retribusi</td>
                <td class="angka" colspan="2">- Rp{{ number_format((float) $p['potongan_retribusi'], 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td colspan="3">Potongan lain{{ $p['keterangan_potongan'] ? ' ('.$p['keterangan_potongan'].')' : '' }}</td>
                <td class="angka" colspan="2">- Rp{{ number_format((float) $p['potongan_lain'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="netto">
        <div class="label">NILAI BERSIH DITERIMA NELAYAN</div>
        <div class="nilai">Rp{{ number_format((float) $p['total_netto'], 0, ',', '.') }}</div>
    </div>

    <div class="tanda">
        Kasir TPI: {{ $p['nama_kasir'] ?: '-' }}<br><br>
        Tanda tangan: ______________________
    </div>
    <div class="kaki">
        Dokumen dihasilkan sistem LELANGIKAN.ID (versi 1.1). Transfer dan kartu nelayan masih disimulasikan.
    </div>
</body>
</html>
