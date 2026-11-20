<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** FR-15: pemberitahuan sesi lelang baru ke seluruh klien terautentikasi pada TPI yang sama. */
class SesiLelangDimulai implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $idTpi,
        public readonly int $idSesi,
        public readonly int $idLot,
        public readonly string $mode,
        public readonly string $waktuMulai,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tpi.' . $this->idTpi)];
    }

    public function broadcastAs(): string
    {
        return 'SESI_DIMULAI';
    }

    public function broadcastWith(): array
    {
        return [
            'id_sesi' => $this->idSesi,
            'id_lot' => $this->idLot,
            'mode' => $this->mode,
            'waktu_mulai' => $this->waktuMulai,
        ];
    }
}
