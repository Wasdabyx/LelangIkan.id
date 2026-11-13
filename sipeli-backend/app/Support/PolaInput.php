<?php

namespace App\Support;

/**
 * Kumpulan pola Regular Expression (RegEx) untuk validasi isian.
 * Pola di sini SAMA dengan file aplikasi mobile src/validasi.js,
 * sehingga aturan di perangkat dan di server selalu sejalan.
 */
final class PolaInput
{
    // Nama orang: diawali huruf, lalu huruf/spasi/titik/koma/petik/strip, total 3-100 karakter
    public const NAMA_ORANG = "/^[A-Za-z][A-Za-z .',-]{2,99}$/";

    // NIK 16 digit: kode provinsi (11-99) + kab/kec (4 digit) + tanggal lahir
    // (01-31, perempuan ditambah 40 menjadi 41-71) + bulan (01-12) + tahun (2 digit) + nomor urut (bukan 0000)
    public const NIK = '/^(1[1-9]|[2-9]\d)\d{4}(0[1-9]|[12]\d|3[01]|4[1-9]|[56]\d|7[01])(0[1-9]|1[0-2])\d{2}(?!0000)\d{4}$/';

    // Nomor ponsel Indonesia: 08 / 628 / +628, lalu 8-11 digit (total 10-13 digit bila diawali 0)
    public const NO_HP = '/^(\+62|62|0)8[1-9]\d{7,10}$/';

    // Surel: bagian-lokal@domain.tld (tld minimal 2 huruf)
    public const SUREL = '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$/';

    // Nama pengguna: huruf kecil di awal, lalu huruf kecil/angka/titik/garis bawah, total 4-30 karakter
    public const NAMA_PENGGUNA = '/^[a-z][a-z0-9._]{3,29}$/';

    // Nama pengguna saat MASUK: pola yang sama tetapi tidak membedakan huruf besar/kecil (flag i)
    public const NAMA_PENGGUNA_MASUK = '/^[a-z][a-z0-9._]{3,29}$/i';

    // Kata sandi kuat: minimal 1 huruf kecil, 1 huruf besar, 1 angka, 1 simbol, tanpa spasi, 8-64 karakter
    public const SANDI_KUAT = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9\s])\S{8,64}$/';

    // Nomor surat izin berlayar: SPB atau SIB / kode pelabuhan 2-5 huruf / TTBBHH / urut 2 digit
    // contoh: SPB/PLR/261014/01
    public const NOMOR_SURAT = '#^(SPB|SIB)/[A-Z]{2,5}/\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])/\d{2}$#';
}
