<?php

namespace App\Services;

use App\Enums\KodePeran;
use App\Models\MutasiSaldo;
use App\Models\Pengguna;
use App\Models\SaldoPembeli;
use App\Models\TransaksiPenjualan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepositService
{
    public function cariPembeli(Pengguna $petugas, string $kataKunci): array
    {
        $pembeli = $this->kueriPembeli($petugas)
            ->where(function ($query) use ($kataKunci) {
                $like = '%' . addcslashes(trim($kataKunci), '%_\\') . '%';
                $query->where('nama_lengkap', 'like', $like)
                    ->orWhere('nama_pengguna', 'like', $like)
                    ->orWhere('nik', 'like', $like)
                    ->orWhere('no_hp', 'like', $like);
            })
            ->orderBy('nama_lengkap')
            ->limit(20)
            ->get();

        return $pembeli->map(fn (Pengguna $p) => [
            'id_pembeli' => $p->getIdPengguna(),
            'nama_lengkap' => $p->getNamaLengkap(),
            'nama_pengguna' => $p->getNamaPengguna(),
            'nik_tersamar' => $this->samar($p->getNik()),
            'no_hp_tersamar' => $this->samar($p->getNoHp()),
            'status_aktif' => $p->isStatusAktif(),
        ])->all();
    }

    public function rincian(Pengguna $petugas, int $idPembeli): array
    {
        $pembeli = $this->kueriPembeli($petugas)->whereKey($idPembeli)->first();
        if (! $pembeli) {
            abort(404, 'Pembeli tidak ditemukan pada TPI Anda.');
        }

        return $this->rangkumanSaldo($pembeli);
    }

    /**
     * FR-33: pembeli memeriksa saldo, limit kredit, daya beli, dan riwayat mutasinya sendiri
     * tanpa harus lewat kasir. Perhitungannya satu sumber dengan tampilan kasir.
     */
    public function rincianSendiri(Pengguna $pembeli): array
    {
        return $this->rangkumanSaldo($pembeli);
    }

    private function rangkumanSaldo(Pengguna $pembeli): array
    {
        $saldo = SaldoPembeli::firstOrCreate(
            ['id_pembeli' => $pembeli->getIdPengguna()],
            ['saldo' => 0, 'limit_kredit' => 0, 'status' => 'AKTIF'],
        );
        $tagihanTerbuka = TransaksiPenjualan::where('id_pembeli', $pembeli->getIdPengguna())
            ->whereIn('status', ['BELUM_DIBAYAR', 'DIBAYAR_SEBAGIAN'])
            ->get()
            ->sum(fn (TransaksiPenjualan $transaksi) => $transaksi->sisaTagihan());
        $mutasi = MutasiSaldo::with(['kasir:id_pengguna,nama_lengkap', 'penyetuju:id_pengguna,nama_lengkap'])
            ->where('id_saldo', $saldo->id_saldo)
            ->orderByDesc('waktu')
            ->limit(10)
            ->get();

        return [
            'pembeli' => [
                'id_pembeli' => $pembeli->getIdPengguna(),
                'nama_lengkap' => $pembeli->getNamaLengkap(),
                'nama_pengguna' => $pembeli->getNamaPengguna(),
                'nik_tersamar' => $this->samar($pembeli->getNik()),
                'no_hp_tersamar' => $this->samar($pembeli->getNoHp()),
                'status_aktif' => $pembeli->isStatusAktif(),
            ],
            'saldo' => (float) $saldo->saldo,
            'limit_kredit' => (float) $saldo->limit_kredit,
            'status_saldo' => $saldo->status,
            'tagihan_terbuka' => round((float) $tagihanTerbuka, 2),
            'daya_beli_efektif' => max(0, round((float) $saldo->saldo + (float) $saldo->limit_kredit - (float) $tagihanTerbuka, 2)),
            'mutasi' => $mutasi->map(fn (MutasiSaldo $m) => $this->formatMutasi($m))->all(),
        ];
    }

    public function buatMutasi(Pengguna $kasir, int $idPembeli, string $jenis, float $nominal, ?string $metode, ?string $arah, ?string $referensi, ?string $keterangan): MutasiSaldo
    {
        if (! in_array($jenis, ['SETOR', 'PENARIKAN', 'PENYESUAIAN'], true)) {
            $this->tolak('jenis', 'Jenis mutasi tidak dikenal.');
        }
        if ($nominal <= 0) {
            $this->tolak('nominal', 'Nominal harus lebih dari Rp0.');
        }
        $arahEfektif = $jenis === 'SETOR' ? 'KREDIT' : ($jenis === 'PENARIKAN' ? 'DEBIT' : $arah);
        if ($jenis === 'PENYESUAIAN' && ! in_array($arahEfektif, ['KREDIT', 'DEBIT'], true)) {
            $this->tolak('arah', 'Pilih penyesuaian tambah atau kurang.');
        }
        if ($jenis !== 'PENYESUAIAN' && ! in_array($metode, ['TUNAI', 'TRANSFER', 'QRIS'], true)) {
            $this->tolak('metode', 'Metode transaksi wajib dipilih.');
        }
        if ($jenis === 'PENYESUAIAN' && mb_strlen(trim((string) $keterangan)) < 5) {
            $this->tolak('keterangan', 'Alasan penyesuaian minimal 5 karakter.');
        }

        return DB::transaction(function () use ($kasir, $idPembeli, $jenis, $nominal, $metode, $arahEfektif, $referensi, $keterangan) {
            $pembeli = $this->kueriPembeli($kasir)->whereKey($idPembeli)->first();
            if (! $pembeli) {
                abort(404, 'Pembeli tidak ditemukan pada TPI Anda.');
            }
            if (! $pembeli->isStatusAktif() && $jenis !== 'PENARIKAN') {
                $this->tolak('id_pembeli', 'Akun nonaktif hanya dapat menarik sisa saldo.');
            }

            $saldo = SaldoPembeli::where('id_pembeli', $idPembeli)->lockForUpdate()->first();
            if (! $saldo) {
                $saldo = SaldoPembeli::create(['id_pembeli' => $idPembeli, 'saldo' => 0, 'limit_kredit' => 0, 'status' => 'AKTIF']);
            }
            if ($saldo->status !== 'AKTIF') {
                $this->tolak('id_pembeli', 'Saldo pembeli sedang diblokir.');
            }
            if ($jenis === 'PENARIKAN' && (float) $saldo->saldo < $nominal) {
                $this->tolak('nominal', 'Penarikan melebihi saldo tersedia Rp' . number_format((float) $saldo->saldo, 0, ',', '.') . '.');
            }
            if ($referensi && MutasiSaldo::where('nomor_referensi', $referensi)->exists()) {
                $this->tolak('nomor_referensi', 'Nomor referensi sudah pernah digunakan.');
            }

            $pending = $jenis === 'PENYESUAIAN';
            $saldoBaru = (float) $saldo->saldo;
            if (! $pending) {
                $saldoBaru += $arahEfektif === 'KREDIT' ? $nominal : -$nominal;
                $saldo->saldo = (string) $saldoBaru;
                $saldo->save();
            }

            return MutasiSaldo::create([
                'id_saldo' => $saldo->id_saldo,
                'jenis' => $jenis,
                'arah' => $arahEfektif,
                'nominal' => (string) $nominal,
                'saldo_akhir' => (string) $saldoBaru,
                'metode' => $metode,
                'status' => $pending ? 'MENUNGGU_PERSETUJUAN' : 'BERHASIL',
                'id_kasir' => $kasir->getIdPengguna(),
                'nomor_referensi' => $referensi,
                'keterangan' => trim((string) $keterangan) ?: null,
            ]);
        });
    }

    public function penyesuaianMenunggu(Pengguna $pimpinan): array
    {
        return MutasiSaldo::with(['saldoPembeli.pembeli', 'kasir:id_pengguna,nama_lengkap'])
            ->where('status', 'MENUNGGU_PERSETUJUAN')
            ->whereHas('saldoPembeli.pembeli', fn ($q) => $q->where('id_tpi', $pimpinan->getIdTpi()))
            ->orderBy('waktu')
            ->get()
            ->map(fn (MutasiSaldo $m) => [
                'id_mutasi' => $m->id_mutasi,
                'id_pembeli' => $m->saldoPembeli->id_pembeli,
                'nama_pembeli' => $m->saldoPembeli->pembeli->getNamaLengkap(),
                'jenis' => $m->jenis,
                'arah' => $m->arah,
                'nominal' => (float) $m->nominal,
                'saldo_sekarang' => (float) $m->saldoPembeli->saldo,
                'keterangan' => $m->keterangan,
                'nama_kasir' => $m->kasir->getNamaLengkap(),
                'waktu' => $m->waktu->format('Y-m-d H:i:s'),
            ])->all();
    }

    public function putuskanPenyesuaian(Pengguna $pimpinan, int $idMutasi, bool $setuju, ?string $catatan): MutasiSaldo
    {
        return DB::transaction(function () use ($pimpinan, $idMutasi, $setuju, $catatan) {
            $mutasi = MutasiSaldo::whereKey($idMutasi)->lockForUpdate()->first();
            if (! $mutasi || $mutasi->status !== 'MENUNGGU_PERSETUJUAN') {
                abort(404, 'Permintaan penyesuaian tidak ditemukan atau sudah diproses.');
            }
            if ($mutasi->id_kasir === $pimpinan->getIdPengguna()) {
                $this->tolak('persetujuan', 'Pengaju tidak boleh menyetujui permintaannya sendiri.');
            }
            $saldo = SaldoPembeli::whereKey($mutasi->id_saldo)->lockForUpdate()->firstOrFail();
            if ($pimpinan->getIdTpi() !== null && $saldo->pembeli()->value('id_tpi') !== $pimpinan->getIdTpi()) {
                abort(404, 'Permintaan penyesuaian tidak ditemukan.');
            }
            if ($setuju) {
                $nilai = (float) $mutasi->nominal;
                if ($mutasi->arah === 'DEBIT' && (float) $saldo->saldo < $nilai) {
                    $this->tolak('persetujuan', 'Saldo saat ini tidak cukup untuk penyesuaian pengurangan.');
                }
                $saldo->saldo = (string) ((float) $saldo->saldo + ($mutasi->arah === 'KREDIT' ? $nilai : -$nilai));
                $saldo->save();
                $mutasi->saldo_akhir = (string) $saldo->saldo;
                $mutasi->status = 'BERHASIL';
            } else {
                $mutasi->status = 'DITOLAK';
            }
            $mutasi->id_penyetuju = $pimpinan->getIdPengguna();
            $mutasi->waktu_persetujuan = now();
            if ($catatan) {
                $mutasi->keterangan = mb_substr(trim((string) $mutasi->keterangan . ' | Keputusan: ' . trim($catatan)), 0, 200);
            }
            $mutasi->save();

            return $mutasi->fresh();
        });
    }

    public function periksaDayaBeli(int $idPembeli, float $nilai): bool
    {
        $saldo = SaldoPembeli::firstOrCreate(
            ['id_pembeli' => $idPembeli],
            ['saldo' => 0, 'limit_kredit' => 0, 'status' => 'AKTIF'],
        );
        $saldo = SaldoPembeli::whereKey($saldo->id_saldo)->lockForUpdate()->first();
        if ($saldo->status !== 'AKTIF') {
            return false;
        }
        $terbuka = TransaksiPenjualan::where('id_pembeli', $idPembeli)
            ->whereIn('status', ['BELUM_DIBAYAR', 'DIBAYAR_SEBAGIAN'])
            ->get()
            ->sum(fn (TransaksiPenjualan $transaksi) => $transaksi->sisaTagihan());

        return (float) $saldo->saldo + (float) $saldo->limit_kredit - (float) $terbuka >= $nilai;
    }

    public function potongSaldo(Pengguna $kasir, int $idPembeli, float $nominal, ?string $keterangan = null): MutasiSaldo
    {
        return DB::transaction(function () use ($kasir, $idPembeli, $nominal, $keterangan) {
            $saldo = SaldoPembeli::where('id_pembeli', $idPembeli)->lockForUpdate()->first();
            if (! $saldo || $saldo->status !== 'AKTIF' || (float) $saldo->saldo < $nominal) {
                $this->tolak('metode', 'Saldo tunai pembeli tidak cukup untuk pembayaran ini.');
            }
            $saldo->saldo = (string) ((float) $saldo->saldo - $nominal);
            $saldo->save();

            return MutasiSaldo::create([
                'id_saldo' => $saldo->id_saldo, 'jenis' => 'PEMBAYARAN', 'arah' => 'DEBIT',
                'nominal' => (string) $nominal, 'saldo_akhir' => (string) $saldo->saldo,
                'status' => 'BERHASIL', 'id_kasir' => $kasir->getIdPengguna(), 'keterangan' => $keterangan,
            ]);
        });
    }

    private function kueriPembeli(Pengguna $petugas)
    {
        $idTpi = $petugas->getIdTpi();
        $query = Pengguna::where('id_peran', KodePeran::PEMBELI->value);
        if ($idTpi !== null) {
            $query->where('id_tpi', $idTpi);
        }

        return $query;
    }

    private function formatMutasi(MutasiSaldo $mutasi): array
    {
        return [
            'id_mutasi' => $mutasi->id_mutasi,
            'jenis' => $mutasi->jenis,
            'arah' => $mutasi->arah,
            'nominal' => (float) $mutasi->nominal,
            'saldo_akhir' => (float) $mutasi->saldo_akhir,
            'metode' => $mutasi->metode,
            'status' => $mutasi->status,
            'keterangan' => $mutasi->keterangan,
            'nama_kasir' => $mutasi->kasir?->getNamaLengkap(),
            'nama_penyetuju' => $mutasi->penyetuju?->getNamaLengkap(),
            'waktu' => $mutasi->waktu?->format('Y-m-d H:i:s'),
        ];
    }

    private function samar(?string $nilai): ?string
    {
        if (! $nilai) {
            return null;
        }

        return mb_substr($nilai, 0, 2) . str_repeat('*', max(3, mb_strlen($nilai) - 4)) . mb_substr($nilai, -2);
    }

    private function tolak(string $kolom, string $pesan): never
    {
        throw \Illuminate\Validation\ValidationException::withMessages([$kolom => $pesan]);
    }
}
