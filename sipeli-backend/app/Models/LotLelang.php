<?php

namespace App\Models;

use App\Enums\StatusLot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Kelas entitas LotLelang (diagram kelas, paket Lelang) - UC-02.
 * kode_lot, id_tangkapan, id_petugas_timbang, dan waktu_timbang diisi sekali saat lot dibuat
 * (lewat konstruktor), sehingga tidak memiliki setter. Status hanya berubah lewat pindahStatus()
 * dan hanya mengikuti 10 transisi pada diagram keadaan SKPL Gambar III.16.
 */
class LotLelang extends Model
{
    protected $table = 'lot_lelang';
    protected $primaryKey = 'id_lot';
    public $timestamps = false;

    protected $fillable = [
        'kode_lot', 'id_tangkapan', 'berat_aktual', 'selisih_persen', 'perlu_verifikasi',
        'harga_pembukaan', 'status', 'id_petugas_timbang', 'waktu_timbang',
        'timbang_manual', 'perangkat_timbang', 'alasan', 'jumlah_cetak', 'waktu_cetak_terakhir',
    ];

    // Lot baru selalu dimulai sebagai DRAFT_HARGA
    protected $attributes = [
        'status'           => 'DRAFT_HARGA',
        'perlu_verifikasi' => false,
        'timbang_manual'   => false,
        'jumlah_cetak'     => 0,
    ];

    protected $casts = [
        'berat_aktual'         => 'decimal:2',
        'selisih_persen'       => 'decimal:2',
        'perlu_verifikasi'     => 'boolean',
        'harga_pembukaan'      => 'decimal:2',
        'timbang_manual'       => 'boolean',
        'waktu_timbang'        => 'datetime',
        'waktu_cetak_terakhir' => 'datetime',
    ];

    private const HARGA_MINIMUM = 100;

    /** Diagram keadaan lot (SKPL Gambar III.16): 8 keadaan, 10 transisi. */
    private const TRANSISI = [
        'DRAFT_HARGA'     => ['MENUNGGU_LELANG'],                       // harga pembukaan diisi
        'MENUNGGU_LELANG' => ['DILELANG'],                              // operator membuka sesi
        'DILELANG'        => ['DIPERPANJANG', 'TERJUAL', 'TIDAK_TERJUAL', 'DIBATALKAN'],
        'DIPERPANJANG'    => ['DILELANG'],                              // perpanjangan berjalan
        'TIDAK_TERJUAL'   => ['MENUNGGU_LELANG', 'SELESAI'],            // sesi ulang / tidak dilelang ulang
        'TERJUAL'         => ['SELESAI'],                               // pembayaran lunas dan settlement
        'DIBATALKAN'      => [],
        'SELESAI'         => [],
    ];

    // ---------------- get / set ----------------

    public function getIdLot(): int
    {
        return (int) $this->id_lot;
    }

    public function getKodeLot(): string
    {
        return $this->kode_lot;
    }

    public function getIdTangkapan(): int
    {
        return (int) $this->id_tangkapan;
    }

    public function getBeratAktual(): string
    {
        return $this->berat_aktual;
    }

    public function setBeratAktual(string|float $berat): void
    {
        $this->berat_aktual = (string) $berat;
    }

    /** Tidak ada setter: dihitung oleh hitungSelisih(). */
    public function getSelisihPersen(): ?string
    {
        return $this->selisih_persen;
    }

    public function isPerluVerifikasi(): bool
    {
        return (bool) $this->perlu_verifikasi;
    }

    /** Tidak ada setter: diisi lewat tetapkanHargaPembukaan(). */
    public function getHargaPembukaan(): ?string
    {
        return $this->harga_pembukaan;
    }

    public function getStatus(): StatusLot
    {
        return StatusLot::from($this->status);
    }

    public function getIdPetugasTimbang(): int
    {
        return (int) $this->id_petugas_timbang;
    }

    public function getWaktuTimbang(): ?\DateTimeInterface
    {
        return $this->waktu_timbang;
    }

    public function isTimbangManual(): bool
    {
        return (bool) $this->timbang_manual;
    }

    public function setTimbangManual(bool $manual): void
    {
        $this->timbang_manual = $manual;
    }

    public function getPerangkatTimbang(): ?string
    {
        return $this->perangkat_timbang;
    }

    public function setPerangkatTimbang(?string $nama): void
    {
        $this->perangkat_timbang = $nama;
    }

    public function getAlasan(): ?string
    {
        return $this->alasan;
    }

    public function setAlasan(?string $alasan): void
    {
        $this->alasan = $alasan;
    }

    public function getJumlahCetak(): int
    {
        return (int) $this->jumlah_cetak;
    }

    public function getWaktuCetakTerakhir(): ?\DateTimeInterface
    {
        return $this->waktu_cetak_terakhir;
    }

    // ---------------- relasi ----------------

    public function tangkapan()
    {
        return $this->belongsTo(Tangkapan::class, 'id_tangkapan');
    }

    public function petugasTimbang()
    {
        return $this->belongsTo(Pengguna::class, 'id_petugas_timbang');
    }

    // ---------------- metode domain ----------------

    /**
     * ALG-08: selisih = |aktual - estimasi| / estimasi x 100%.
     * Mengisi selisih_persen dan perlu_verifikasi (selisih > ambang), lalu mengembalikan selisih.
     */
    public function hitungSelisih(float $beratEstimasi, float $ambang): float
    {
        $aktual  = (float) $this->getBeratAktual();
        $selisih = $beratEstimasi <= 0 ? 0.0 : round(abs($aktual - $beratEstimasi) / $beratEstimasi * 100, 2);

        $this->selisih_persen   = (string) $selisih;
        $this->perlu_verifikasi = $selisih > $ambang;

        return $selisih;
    }

    /**
     * Isi harga pembukaan (Rp per kg). Boleh saat DRAFT_HARGA, MENUNGGU_LELANG, atau
     * TIDAK_TERJUAL (sesi ulang dengan harga baru); lot lalu berstatus MENUNGGU_LELANG.
     */
    public function tetapkanHargaPembukaan(float $harga): void
    {
        $boleh = [StatusLot::DRAFT_HARGA, StatusLot::MENUNGGU_LELANG, StatusLot::TIDAK_TERJUAL];
        if (! in_array($this->getStatus(), $boleh, true)) {
            throw ValidationException::withMessages([
                'harga_pembukaan' => 'Harga pembukaan tidak dapat diubah saat lot berstatus ' . $this->getStatus()->value . '.',
            ]);
        }
        if ($harga < self::HARGA_MINIMUM) {
            throw ValidationException::withMessages([
                'harga_pembukaan' => 'Harga pembukaan minimal Rp100 per kg.',
            ]);
        }

        $this->harga_pembukaan = (string) $harga;
        if ($this->getStatus() !== StatusLot::MENUNGGU_LELANG) {
            $this->pindahStatus(StatusLot::MENUNGGU_LELANG);
        } elseif ($this->exists) {
            $this->save();
        }
    }

    /**
     * Pindah ke status baru bila transisinya ada pada diagram keadaan.
     * Mengembalikan false (status tidak berubah) bila transisi tidak sah.
     */
    public function pindahStatus(StatusLot $statusBaru): bool
    {
        if (! $this->transisiSah($this->getStatus(), $statusBaru)) {
            return false;
        }
        // Masuk antrean (-> MENUNGGU_LELANG) dan dibuka (-> DILELANG) hanya bila harga pembukaan sudah ada
        $perluHarga = in_array($statusBaru, [StatusLot::MENUNGGU_LELANG, StatusLot::DILELANG], true);
        if ($perluHarga && $this->getHargaPembukaan() === null) {
            return false;
        }

        $this->status = $statusBaru->value;
        if ($this->exists) {
            $this->save();
        }

        return true;
    }

    /** Alur A3: catat satu percobaan cetak label (jumlah_cetak + 1 dan waktunya). */
    public function catatCetak(): void
    {
        $this->increment('jumlah_cetak', 1, ['waktu_cetak_terakhir' => now()]);
    }

    public function isSiapDilelang(): bool
    {
        return $this->getStatus() === StatusLot::MENUNGGU_LELANG && $this->getHargaPembukaan() !== null;
    }

    private function transisiSah(StatusLot $dari, StatusLot $ke): bool
    {
        return in_array($ke->value, self::TRANSISI[$dari->value], true);
    }

    public function simpan(): void
    {
        $this->save();
    }

    /**
     * FR-08: nomor lot unik [kode TPI]-[YYMMDD]-[urut], contoh PLR-261026-0001.
     * WAJIB dipanggil di dalam DB::transaction agar kunci baris (lockForUpdate) berlaku.
     */
    public static function buatKodeLot(string $kodeTpi, string|\DateTimeInterface $tanggal): string
    {
        $tanggal = is_string($tanggal) ? new \DateTimeImmutable($tanggal) : $tanggal;
        $awalan  = $kodeTpi . '-' . $tanggal->format('ymd') . '-';

        $terakhir = static::where('kode_lot', 'like', $awalan . '%')
            ->lockForUpdate()
            ->orderByDesc('kode_lot')
            ->value('kode_lot');

        $urut = $terakhir ? ((int) substr($terakhir, -4)) + 1 : 1;

        return $awalan . str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    }

    public static function cariKode(string $kode): ?self
    {
        return static::where('kode_lot', strtoupper(trim($kode)))->first();
    }

    /** @return Collection<int, LotLelang> lot MENUNGGU_LELANG berharga di satu TPI, urut nomor lot */
    public static function cariSiapLelang(int $idTpi): Collection
    {
        return static::where('status', StatusLot::MENUNGGU_LELANG->value)
            ->whereNotNull('harga_pembukaan')
            ->whereHas('tangkapan.kedatangan', fn ($q) => $q->where('id_tpi', $idTpi))
            ->orderBy('kode_lot')
            ->get();
    }
}
