<?php

use Illuminate\Support\Facades\Broadcast;

// Semua pengguna aktif pada TPI yang sama boleh menerima pembaruan sesi lelang.
Broadcast::channel('tpi.{idTpi}', function ($pengguna, int $idTpi) {
    return $pengguna->isStatusAktif()
        && $pengguna->getIdTpi() !== null
        && $pengguna->getIdTpi() === $idTpi;
});
