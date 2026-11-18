@extends('surel.tata-letak')

@section('isi')
  <p style="margin:0 0 12px;">Halo <strong>{{ $namaPenerima }}</strong>,</p>
  <p style="margin:0 0 16px;">Pemesanan Anda berhasil. Anda terdaftar sebagai peserta lelang untuk lot berikut.</p>

  <div style="text-align:center;margin:8px 0 16px;">
    <div style="font-size:13px;color:#486581;margin-bottom:4px;">KODE PEMESANAN</div>
    <div style="display:inline-block;background:#ECFDF3;border:1px solid #A6E3C3;border-radius:10px;padding:12px 20px;font-family:'Courier New',Courier,monospace;font-size:26px;font-weight:bold;letter-spacing:2px;color:#067647;">{{ $pemesanan['kode_pemesanan'] }}</div>
  </div>

  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #E4EBF1;border-radius:8px;margin:0 0 16px;font-size:14px;">
    <tr><td style="padding:8px 12px;color:#486581;">Nomor lot</td><td style="padding:8px 12px;font-weight:bold;text-align:right;">{{ $pemesanan['kode_lot'] }}</td></tr>
    <tr><td style="padding:8px 12px;color:#486581;border-top:1px solid #E4EBF1;">Jenis ikan</td><td style="padding:8px 12px;text-align:right;border-top:1px solid #E4EBF1;">{{ $pemesanan['nama_jenis'] }} (Grade {{ $pemesanan['kode_grade'] }})</td></tr>
    <tr><td style="padding:8px 12px;color:#486581;border-top:1px solid #E4EBF1;">Berat</td><td style="padding:8px 12px;text-align:right;border-top:1px solid #E4EBF1;">{{ number_format((float) $pemesanan['berat_aktual'], 2, ',', '.') }} kg</td></tr>
    <tr><td style="padding:8px 12px;color:#486581;border-top:1px solid #E4EBF1;">Harga pembukaan</td><td style="padding:8px 12px;text-align:right;border-top:1px solid #E4EBF1;">Rp{{ number_format((float) $pemesanan['harga_pembukaan'], 0, ',', '.') }}/kg</td></tr>
    <tr><td style="padding:8px 12px;color:#486581;border-top:1px solid #E4EBF1;">Waktu pemesanan</td><td style="padding:8px 12px;text-align:right;border-top:1px solid #E4EBF1;">{{ $pemesanan['waktu_pemesanan'] }} WIB</td></tr>
  </table>

  <p style="margin:0;">Tunjukkan kode pemesanan ini kepada petugas TPI saat sesi lelang lot tersebut dibuka. Harga akhir tetap ditentukan melalui lelang.</p>
@endsection
