<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Peristiwa kanal real-time lelang (SKPL Tabel IV.7).
 *
 * Satu kelas untuk tujuh peristiwa agar muatan dan kanal konsisten: LELANG_DIMULAI,
 * PENAWARAN_BARU, HARGA_TURUN, OUTBID, SESI_DIPERPANJANG, HASIL_LELANG, SESI_DITUTUP.
 * Disiarkan pada kanal privat tpi.{id_tpi}; kegagalan siaran tidak boleh menggagalkan
 * transaksi basis data (lihat LelangService::siarkan()).
 */
class PeristiwaLelang implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const DIMULAI = 'LELANG_DIMULAI';
    public const PENAWARAN_BARU = 'PENAWARAN_BARU';
    public const HARGA_TURUN = 'HARGA_TURUN';
    public const OUTBID = 'OUTBID';
    public const DIPERPANJANG = 'SESI_DIPERPANJANG';
    public const HASIL = 'HASIL_LELANG';
    public const DITUTUP = 'SESI_DITUTUP';
    public const SETTLEMENT_DIBAYAR = 'SETTLEMENT_DIBAYAR';
    public const LOT_DIPANTAU_DIBUKA = 'LOT_DIPANTAU_DIBUKA';

    /**
     * @param  array<string, mixed>  $muatan
     * @param  string|null  $kanal  nama kanal tanpa awalan "private-", mis. "nelayan.7" atau "pembeli.4"
     */
    public function __construct(
        public readonly int $idTpi,
        public readonly string $nama,
        public readonly array $muatan,
        public readonly ?string $kanal = null,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->kanal ?? ('tpi.' . $this->idTpi))];
    }

    public function broadcastAs(): string
    {
        return $this->nama;
    }

    public function broadcastWith(): array
    {
        return $this->muatan;
    }
}
