<?php

use Illuminate\Support\Facades\Broadcast;

// Semua pengguna aktif pada TPI yang sama boleh menerima pembaruan sesi lelang.
Broadcast::channel('tpi.{idTpi}', function ($pengguna, int $idTpi) {
    return $pengguna->isStatusAktif()
        && $pengguna->getIdTpi() !== null
        && $pengguna->getIdTpi() === $idTpi;
});

// UC-13 (Tabel IV.7 SETTLEMENT_DIBAYAR): hanya pemilik kapal terkait yang boleh menerima.
Broadcast::channel('nelayan.{idPemilik}', function ($pengguna, int $idPemilik) {
    return $pengguna->isStatusAktif() && $pengguna->getIdPemilik() === $idPemilik;
});

// UC-04 (Tabel IV.7 LOT_DIPANTAU_DIBUKA): pembeli penerima pemberitahuan pantauan.
Broadcast::channel('pembeli.{idPembeli}', function ($pengguna, int $idPembeli) {
    return $pengguna->isStatusAktif()
        && (int) $pengguna->getIdPengguna() === $idPembeli;
});
