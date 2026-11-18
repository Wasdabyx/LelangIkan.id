@extends('surel.tata-letak')

@section('isi')
  <p style="margin:0 0 12px;">Halo <strong>{{ $namaPenerima }}</strong>,</p>

  @if ($tujuan === 'AKTIVASI')
    <p style="margin:0 0 16px;">Terima kasih telah mendaftar sebagai pembeli di LelangIkan.id. Masukkan kode berikut pada aplikasi untuk mengaktifkan akun Anda:</p>
  @else
    <p style="margin:0 0 16px;">Anda meminta untuk ikut lelang lot berikut. Masukkan kode OTP di bawah pada aplikasi untuk mengonfirmasi pemesanan:</p>
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #E4EBF1;border-radius:8px;margin:0 0 16px;font-size:14px;">
      <tr><td style="padding:8px 12px;color:#486581;">Nomor lot</td><td style="padding:8px 12px;font-weight:bold;text-align:right;">{{ $lot['kode_lot'] }}</td></tr>
      <tr><td style="padding:8px 12px;color:#486581;border-top:1px solid #E4EBF1;">Jenis ikan</td><td style="padding:8px 12px;text-align:right;border-top:1px solid #E4EBF1;">{{ $lot['nama_jenis'] }} (Grade {{ $lot['kode_grade'] }})</td></tr>
      <tr><td style="padding:8px 12px;color:#486581;border-top:1px solid #E4EBF1;">Berat</td><td style="padding:8px 12px;text-align:right;border-top:1px solid #E4EBF1;">{{ number_format((float) $lot['berat_aktual'], 2, ',', '.') }} kg</td></tr>
      <tr><td style="padding:8px 12px;color:#486581;border-top:1px solid #E4EBF1;">Harga pembukaan</td><td style="padding:8px 12px;text-align:right;border-top:1px solid #E4EBF1;">Rp{{ number_format((float) $lot['harga_pembukaan'], 0, ',', '.') }}/kg</td></tr>
    </table>
  @endif

  <div style="text-align:center;margin:8px 0 16px;">
    <div style="display:inline-block;background:#E6F1F6;border:1px solid #C5DCE8;border-radius:10px;padding:14px 22px;font-family:'Courier New',Courier,monospace;font-size:34px;font-weight:bold;letter-spacing:10px;color:#0B4F6C;">{{ $kode }}</div>
  </div>

  <p style="margin:0 0 12px;text-align:center;color:#486581;font-size:14px;">Kode berlaku {{ $berlakuMenit }} menit, sampai pukul <strong>{{ $berlakuSampai }} WIB</strong>, dan hanya dapat dipakai satu kali.</p>

  <p style="margin:16px 0 0;padding:12px;background:#FFFAEB;border:1px solid #F5D9A8;border-radius:8px;font-size:13px;color:#B54708;">
    Jangan berikan kode ini kepada siapa pun, termasuk petugas TPI. Abaikan surel ini bila Anda tidak merasa melakukan permintaan tersebut.
  </p>
@endsection
