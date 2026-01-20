<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Wallet;

class WalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default wallet with ID 1 for webhook transactions
        Wallet::firstOrCreate(
            ['email' => 'default@digitpay.test'],
            [
                'id' => 1,
                'owner_name' => 'Default System Wallet',
                'email' => 'default@digitpay.test',
                'balance' => 0,
                'currency' => 'USD',
            ]
        );
    }
}
