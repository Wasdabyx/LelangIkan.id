<?php

namespace Tests\Unit;

use App\Enums\StatusLot;
use App\Models\LotLelang;
use App\Services\LelangService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LelangServiceTest extends TestCase
{
    public function test_minimum_increase_uses_the_correct_value_tier(): void
    {
        $service = new LelangService;

        $this->assertSame(500, $service->usulanKenaikan($this->lot(10_000, 10)));
        $this->assertSame(1_000, $service->usulanKenaikan($this->lot(20_000, 60)));
        $this->assertSame(3_000, $service->usulanKenaikan($this->lot(200_000, 20)));
        $this->assertSame(5_000, $service->usulanKenaikan($this->lot(500_000, 20)));
    }

    public function test_minimum_increase_rounds_up_to_the_next_hundred(): void
    {
        // 2% x Rp26.050 = Rp521, dibulatkan ke atas menjadi Rp600.
        $this->assertSame(600, (new LelangService)->usulanKenaikan($this->lot(26_050, 1)));
    }

    public function test_lot_must_have_an_opening_price_before_auction_and_cannot_be_repriced_while_live(): void
    {
        $lot = LotLelang::make([
            'status' => StatusLot::DRAFT_HARGA->value,
            'berat_aktual' => '12.50',
        ]);

        $this->assertFalse($lot->pindahStatus(StatusLot::DILELANG));

        $lot->tetapkanHargaPembukaan(15_000);
        $this->assertSame(StatusLot::MENUNGGU_LELANG, $lot->getStatus());
        $this->assertTrue($lot->pindahStatus(StatusLot::DILELANG));
        $this->assertSame(StatusLot::DILELANG, $lot->getStatus());

        $this->expectException(ValidationException::class);
        $lot->tetapkanHargaPembukaan(16_000);
    }

    public function test_opening_price_below_the_minimum_is_rejected(): void
    {
        $lot = LotLelang::make(['status' => StatusLot::DRAFT_HARGA->value]);

        $this->expectException(ValidationException::class);
        $lot->tetapkanHargaPembukaan(99);
    }

    private function lot(float $hargaPembukaan, float $beratAktual): LotLelang
    {
        return LotLelang::make([
            'harga_pembukaan' => (string) $hargaPembukaan,
            'berat_aktual' => (string) $beratAktual,
            'status' => StatusLot::MENUNGGU_LELANG->value,
        ]);
    }
}
