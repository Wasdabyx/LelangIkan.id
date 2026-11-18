<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Surel bukti pemesanan (ikut lelang) lot berisi kode pemesanan PSN-YYMMDD-#### (Tugas 2). */
class PemesananBerhasilMail extends Mailable
{
    public function __construct(
        public string $namaPenerima,
        public array $pemesanan,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Pemesanan Lot Berhasil: {$this->pemesanan['kode_pemesanan']}");
    }

    public function content(): Content
    {
        return new Content(view: 'surel.pemesanan-berhasil');
    }
}
