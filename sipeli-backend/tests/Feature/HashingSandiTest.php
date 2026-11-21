<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NFR-04 (SKPL): kata sandi disimpan memakai algoritma hash adaptif (bcrypt/Argon2id).
 *
 * Uji ini membaca hash yang benar-benar tersimpan, bukan konfigurasi, sehingga ikut
 * menangkap kesalahan seperti menyimpan kata sandi apa adanya atau memakai cost rendah.
 * phpunit.xml menyetel BCRYPT_ROUNDS=4 (hanya pengaman agar tes cepat), karena itu batas
 * bawah di sini 4 dan batas bawah untuk lingkungan sebenarnya diperiksa terpisah:
 * .env dan .env.example memakai BCRYPT_ROUNDS=12.
 */
class HashingSandiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_kata_sandi_tersimpan_sebagai_hash_bcrypt(): void
    {
        $pengguna = Pengguna::where('nama_pengguna', 'operator')->firstOrFail();
        $info = password_get_info($pengguna->kata_sandi);

        $this->assertSame('bcrypt', $info['algoName'], 'Kata sandi harus memakai bcrypt.');
        $this->assertStringStartsWith('$2y$', $pengguna->kata_sandi);
        $this->assertNotSame('sipeli12345', $pengguna->kata_sandi, 'Kata sandi tidak boleh tersimpan apa adanya.');
    }

    public function test_cost_hash_minimal_empat(): void
    {
        $pengguna = Pengguna::where('nama_pengguna', 'kasir')->firstOrFail();
        $cost = password_get_info($pengguna->kata_sandi)['options']['cost'] ?? 0;
        $this->assertGreaterThanOrEqual(4, $cost);
    }

    public function test_lingkungan_produksi_memakai_cost_dua_belas(): void
    {
        // .env.example adalah acuan penerapan di server TPI.
        $contoh = file_get_contents(base_path('.env.example'));
        $this->assertMatchesRegularExpression('/^BCRYPT_ROUNDS=12$/m', $contoh,
            'Acuan .env harus memakai BCRYPT_ROUNDS=12 (bcrypt cost 12).');
    }

    public function test_kata_sandi_diubah_lewat_model_tetap_di_hash(): void
    {
        $pengguna = Pengguna::where('nama_pengguna', 'pembeli')->firstOrFail();
        $pengguna->kata_sandi = 'SandiBaru#2026';
        $pengguna->save();

        $segar = $pengguna->fresh();
        $this->assertNotSame('SandiBaru#2026', $segar->kata_sandi);
        $this->assertTrue(password_verify('SandiBaru#2026', $segar->kata_sandi));
    }

    public function test_hash_berbeda_untuk_sandi_sama_beda_pengguna(): void
    {
        // Salt acak: dua pengguna dengan kata sandi sama tidak boleh menghasilkan hash sama.
        $a = Pengguna::where('nama_pengguna', 'pendata')->firstOrFail()->kata_sandi;
        $b = Pengguna::where('nama_pengguna', 'timbang')->firstOrFail()->kata_sandi;
        $this->assertNotSame($a, $b);
    }
}
