<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Surel berisi kode OTP 6 digit untuk aktivasi akun atau pemesanan lot (Tugas 2). */
class KodeOtpMail extends Mailable
{
    public function __construct(
        public string $namaPenerima,
        public string $tujuan,          // AKTIVASI | PEMESANAN
        public string $kode,
        public string $berlakuSampai,   // HH:MM (WIB)
        public int $berlakuMenit,
        public ?array $lot = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->tujuan === 'AKTIVASI'
                ? "Kode Aktivasi Akun LelangIkan.id: {$this->kode}"
                : "Kode OTP Pemesanan Lot {$this->lot['kode_lot']}: {$this->kode}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'surel.kode-otp');
    }
}
