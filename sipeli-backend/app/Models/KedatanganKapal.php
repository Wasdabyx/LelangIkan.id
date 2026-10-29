<?php

namespace App\Models;

use App\Enums\StatusKedatangan;
use App\Enums\StatusTangkapan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Kelas entitas KedatanganKapal (diagram kelas, paket Kedatangan dan tangkapan) - UC-01.
 * Status hanya berubah lewat metode domain: DRAF -> daftarkan() -> TERDAFTAR
 * -> perbaruiStatusTimbang() -> DITIMBANG. Selama DRAF, data boleh disunting (isDapatDiubah()).
 */
class KedatanganKapal extends Model
{
    protected $table = 'kedatangan_kapal';
    protected $primaryKey = 'id_kedatangan';

    const CREATED_AT = 'waktu_dibuat';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_kapal', 'id_tpi', 'tanggal', 'jam', 'nama_kapten', 'jumlah_abk',
        'nomor_surat', 'total_berat_estimasi', 'status', 'id_petugas',
    ];

    // Kedatangan baru selalu dimulai sebagai DRAF dengan total 0
    protected $attributes = [
        'status'               => 'DRAF',
        'total_berat_estimasi' => 0,
    ];

    protected $casts = [
        'tanggal'              => 'date:Y-m-d',
        'total_berat_estimasi' => 'decimal:2',
        'waktu_dibuat'         => 'datetime',
    ];

    /** Atribut turunan nomor_kedatangan, contoh: KDT-00012 */
    public function getNomorKedatanganAttribute(): string
    {
        return 'KDT-' . str_pad((string) $this->id_kedatangan, 5, '0', STR_PAD_LEFT);
    }

    // ---------------- get / set ----------------

    public function getIdKedatangan(): int
    {
        return (int) $this->id_kedatangan;
    }

    public function getNomorKedatangan(): string
    {
        return $this->nomor_kedatangan;
    }

    public function getIdKapal(): int
    {
        return (int) $this->id_kapal;
    }

    public function setIdKapal(int $idKapal): void
    {
        $this->id_kapal = $idKapal;
    }

    public function getIdTpi(): int
    {
        return (int) $this->id_tpi;
    }

    public function setIdTpi(int $idTpi): void
    {
        $this->id_tpi = $idTpi;
    }

    public function getTanggal(): \DateTimeInterface
    {
        return $this->tanggal;
    }

    public function setTanggal(string|\DateTimeInterface $tanggal): void
    {
        $this->tanggal = $tanggal;
    }

    /** Jam dalam format HH:MM */
    public function getJam(): string
    {
        return substr((string) $this->jam, 0, 5);
    }

    public function setJam(string $jam): void
    {
        $this->jam = $jam;
    }

    public function getNamaKapten(): string
    {
        return $this->nama_kapten;
    }

    public function setNamaKapten(string $nama): void
    {
        $this->nama_kapten = $nama;
    }

    public function getJumlahAbk(): int
    {
        return (int) $this->jumlah_abk;
    }

    public function setJumlahAbk(int $jumlah): void
    {
        $this->jumlah_abk = $jumlah;
    }

    public function getNomorSurat(): string
    {
        return $this->nomor_surat;
    }

    public function setNomorSurat(string $nomor): void
    {
        $this->nomor_surat = $nomor;
    }

    /** Tidak ada setTotalBeratEstimasi(): nilainya dihitung oleh hitungTotalEstimasi(). */
    public function getTotalBeratEstimasi(): string
    {
        return $this->total_berat_estimasi;
    }

    /** Tidak ada setStatus(): status berubah lewat daftarkan() dan perbaruiStatusTimbang(). */
    public function getStatus(): StatusKedatangan
    {
        return StatusKedatangan::from($this->status);
    }

    public function getIdPetugas(): int
    {
        return (int) $this->id_petugas;
    }

    public function setIdPetugas(int $idPetugas): void
    {
        $this->id_petugas = $idPetugas;
    }

    public function getWaktuDibuat(): ?\DateTimeInterface
    {
        return $this->waktu_dibuat;
    }

    // ---------------- relasi ----------------

    public function kapal()
    {
        return $this->belongsTo(Kapal::class, 'id_kapal');
    }

    public function tpi()
    {
        return $this->belongsTo(Tpi::class, 'id_tpi');
    }

    public function petugas()
    {
        return $this->belongsTo(Pengguna::class, 'id_petugas');
    }

    public function tangkapan()
    {
        return $this->hasMany(Tangkapan::class, 'id_kedatangan');
    }

    // ---------------- metode domain ----------------

    /** Tambah satu baris tangkapan (kedatangan harus sudah tersimpan). */
    public function tambahBarisTangkapan(int $idJenis, int $idGrade, float $beratEstimasi, int $jumlahKeranjang): Tangkapan
    {
        $baris = new Tangkapan();
        if (! $baris->validasiBerat($beratEstimasi)) {
            throw ValidationException::withMessages([
                'tangkapan' => 'Berat estimasi harus lebih dari 0 dan maksimal 999.999,99 kg.',
            ]);
        }

        $baris->setIdJenis($idJenis);
        $baris->setIdGrade($idGrade);
        $baris->setBeratEstimasi($beratEstimasi);
        $baris->setJumlahKeranjang($jumlahKeranjang);
        $this->tangkapan()->save($baris);   // id_kedatangan diisi otomatis

        return $baris;
    }

    /** Jumlahkan berat estimasi semua baris, simpan ke total_berat_estimasi. */
    public function hitungTotalEstimasi(): float
    {
        $total = round((float) $this->tangkapan()->sum('berat_estimasi'), 2);
        $this->total_berat_estimasi = (string) $total;

        return $total;
    }

    /** DRAF -> TERDAFTAR. Ditolak bila belum ada baris tangkapan. */
    public function daftarkan(): void
    {
        if ($this->getStatus() !== StatusKedatangan::DRAF) {
            throw ValidationException::withMessages([
                'status' => 'Hanya kedatangan berstatus DRAF yang dapat didaftarkan.',
            ]);
        }
        if (! $this->tangkapan()->exists()) {
            throw ValidationException::withMessages([
                'tangkapan' => 'Tambahkan minimal satu baris tangkapan.',
            ]);
        }

        $this->status = StatusKedatangan::TERDAFTAR->value;
        $this->simpan();
    }

    /** Hanya kedatangan DRAF yang boleh disunting (SKPL UC-01 alur A2). */
    public function isDapatDiubah(): bool
    {
        return $this->getStatus() === StatusKedatangan::DRAF;
    }

    /** Hapus semua baris tangkapan sebelum diisi ulang; hanya untuk DRAF yang belum ada baris ditimbang. */
    public function hapusSemuaBarisTangkapan(): void
    {
        if (! $this->isDapatDiubah()) {
            throw ValidationException::withMessages([
                'status' => 'Kedatangan ' . $this->getNomorKedatangan() . ' sudah berstatus '
                    . $this->getStatus()->value . ' sehingga tidak dapat diubah lagi.',
            ]);
        }
        if ($this->tangkapan()->where('status', StatusTangkapan::DITIMBANG->value)->exists()) {
            throw ValidationException::withMessages([
                'tangkapan' => 'Ada baris tangkapan yang sudah ditimbang sehingga tidak dapat dihapus.',
            ]);
        }

        $this->tangkapan()->delete();
    }

    /** Benar bila ada baris tangkapan dan semuanya sudah ditimbang. */
    public function semuaDitimbang(): bool
    {
        return $this->tangkapan()->exists()
            && ! $this->tangkapan()->where('status', StatusTangkapan::BELUM_DITIMBANG->value)->exists();
    }

    /** TERDAFTAR -> DITIMBANG bila semua baris sudah ditimbang. */
    public function perbaruiStatusTimbang(): void
    {
        if ($this->getStatus() === StatusKedatangan::TERDAFTAR && $this->semuaDitimbang()) {
            $this->status = StatusKedatangan::DITIMBANG->value;
            $this->simpan();
        }
    }

    public function simpan(): void
    {
        $this->save();
    }

    public static function cari(int $id): ?self
    {
        return static::find($id);
    }

    /** "KDT-00012", "kdt-12", atau "12" -> 12; null bila format salah. */
    public static function idDariNomor(string $nomor): ?int
    {
        $nomor = strtoupper(trim($nomor));
        if (preg_match('/^(KDT-?)?0*(\d{1,9})$/', $nomor, $m)) {
            return (int) $m[2];
        }

        return null;
    }

    /**
     * Kedatangan pada satu tanggal (opsional per TPI), terbaru dulu,
     * lengkap dengan jumlah baris dan jumlah baris yang belum ditimbang.
     * @return Collection<int, KedatanganKapal>
     */
    public static function daftarHarian(string $tanggal, ?int $idTpi): Collection
    {
        return static::with('kapal')
            ->withCount([
                'tangkapan',
                'tangkapan as belum_timbang_count' => fn ($q) => $q->where('status', StatusTangkapan::BELUM_DITIMBANG->value),
            ])
            ->whereDate('tanggal', $tanggal)
            ->when($idTpi, fn ($q) => $q->where('id_tpi', $idTpi))
            ->orderByDesc('jam')
            ->get();
    }
}
