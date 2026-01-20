<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use App\Models\Wallet;
use Tests\TestCase;

class WalletMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallets_table_exists()
    {
        // The table should exist after migration
        $this->assertTrue(Schema::hasTable('wallets'));
    }

    public function test_wallets_table_has_all_required_columns()
    {
        // Check all columns exist
        $this->assertTrue(Schema::hasColumn('wallets', 'id'));
        $this->assertTrue(Schema::hasColumn('wallets', 'owner_name'));
        $this->assertTrue(Schema::hasColumn('wallets', 'email'));
        $this->assertTrue(Schema::hasColumn('wallets', 'balance'));
        $this->assertTrue(Schema::hasColumn('wallets', 'currency'));
        $this->assertTrue(Schema::hasColumn('wallets', 'created_at'));
        $this->assertTrue(Schema::hasColumn('wallets', 'updated_at'));
    }

    public function test_wallets_table_email_column_is_unique()
    {
        // Try to insert duplicate emails - should fail
        Wallet::create([
            'owner_name' => 'Test Owner',
            'email' => 'test@example.com',
            'balance' => 100,
            'currency' => 'USD',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Wallet::create([
            'owner_name' => 'Another Owner',
            'email' => 'test@example.com',
            'balance' => 100,
            'currency' => 'USD',
        ]);
    }
}
