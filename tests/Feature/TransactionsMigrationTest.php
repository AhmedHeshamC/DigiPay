<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use App\Models\Transaction;
use App\Models\Wallet;
use Tests\TestCase;

class TransactionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transactions_table_exists()
    {
        $this->assertTrue(Schema::hasTable('transactions'));
    }

    public function test_transactions_table_has_all_required_columns()
    {
        $this->assertTrue(Schema::hasColumn('transactions', 'id'));
        $this->assertTrue(Schema::hasColumn('transactions', 'wallet_id'));
        $this->assertTrue(Schema::hasColumn('transactions', 'type'));
        $this->assertTrue(Schema::hasColumn('transactions', 'bank_reference'));
        $this->assertTrue(Schema::hasColumn('transactions', 'bank_provider'));
        $this->assertTrue(Schema::hasColumn('transactions', 'amount'));
        $this->assertTrue(Schema::hasColumn('transactions', 'bank_transaction_time'));
        $this->assertTrue(Schema::hasColumn('transactions', 'metadata'));
        $this->assertTrue(Schema::hasColumn('transactions', 'created_at'));
        $this->assertTrue(Schema::hasColumn('transactions', 'updated_at'));
    }

    public function test_transactions_has_foreign_key_to_wallets()
    {
        // Create a wallet first
        $wallet = Wallet::create([
            'owner_name' => 'Test Owner',
            'email' => 'test@example.com',
            'balance' => 100,
            'currency' => 'USD',
        ]);

        // Create a transaction
        $transaction = Transaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'bank_reference' => 'REF123',
            'bank_provider' => 'paytech',
            'amount' => 50.00,
            'bank_transaction_time' => now(),
        ]);

        $this->assertEquals($wallet->id, $transaction->wallet->id);
    }

    public function test_idempotency_unique_constraint_on_bank_provider_and_reference()
    {
        // Create a wallet
        $wallet = Wallet::create([
            'owner_name' => 'Test Owner',
            'email' => 'test@example.com',
            'balance' => 100,
            'currency' => 'USD',
        ]);

        // Create first transaction
        Transaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'bank_reference' => 'REF123',
            'bank_provider' => 'paytech',
            'amount' => 50.00,
            'bank_transaction_time' => now(),
        ]);

        // Try to create duplicate with same bank_provider + bank_reference
        $this->expectException(\Illuminate\Database\QueryException::class);
        Transaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'bank_reference' => 'REF123', // Same reference
            'bank_provider' => 'paytech', // Same provider
            'amount' => 50.00,
            'bank_transaction_time' => now(),
        ]);
    }

    public function test_different_providers_can_have_same_reference()
    {
        // Create a wallet
        $wallet = Wallet::create([
            'owner_name' => 'Test Owner',
            'email' => 'test@example.com',
            'balance' => 100,
            'currency' => 'USD',
        ]);

        // Create transaction for paytech
        Transaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'bank_reference' => 'REF123',
            'bank_provider' => 'paytech',
            'amount' => 50.00,
            'bank_transaction_time' => now(),
        ]);

        // Same reference for acme should work (different provider)
        $transaction = Transaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'bank_reference' => 'REF123',
            'bank_provider' => 'acme',
            'amount' => 75.00,
            'bank_transaction_time' => now(),
        ]);

        $this->assertEquals('acme', $transaction->bank_provider);
        $this->assertCount(2, Transaction::all());
    }

    public function test_metadata_can_store_json()
    {
        $wallet = Wallet::create([
            'owner_name' => 'Test Owner',
            'email' => 'test@example.com',
            'balance' => 100,
            'currency' => 'USD',
        ]);

        $metadata = [
            'note' => 'debt payment',
            'internal_reference' => 'A462JE81',
        ];

        $transaction = Transaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'bank_reference' => 'REF123',
            'bank_provider' => 'paytech',
            'amount' => 50.00,
            'bank_transaction_time' => now(),
            'metadata' => $metadata,
        ]);

        $this->assertEquals($metadata, $transaction->metadata);
    }
}
