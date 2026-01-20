<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->string('bank_reference');
            $table->string('bank_provider');
            $table->decimal('amount', 19, 4);
            $table->dateTime('bank_transaction_time');
            $table->json('metadata')->nullable(); // Flexible fields
            $table->timestamps();

            // === IDEMPOTENCY KEY ===
            $table->unique(['bank_provider', 'bank_reference'], 'unique_txn_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
