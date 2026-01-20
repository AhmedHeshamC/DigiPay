<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Wallet;
use Tests\TestCase;

class WalletSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_wallet_exists_after_seeding()
    {
        $this->seed();
        $this->assertDatabaseHas('wallets', [
            'id' => 1,
            'email' => 'default@digitpay.test',
        ]);
    }

    public function test_default_wallet_has_zero_balance()
    {
        $this->seed();

        $wallet = Wallet::find(1);
        $this->assertEquals(0, $wallet->balance);
        $this->assertEquals('USD', $wallet->currency);
    }
}
