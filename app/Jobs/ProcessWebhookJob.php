<?php

namespace App\Jobs;

use App\Models\WebhookCall;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Parsers\WebhookParserFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Exception;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private int $webhookCallId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $webhookCall = WebhookCall::find($this->webhookCallId);

        if (!$webhookCall) {
            return;
        }

        try {
            // Select the appropriate parser strategy
            $parser = WebhookParserFactory::create($webhookCall->bank_provider);

            // Parse the payload
            $parsedTransactions = $parser->parse($webhookCall->payload);

            if (empty($parsedTransactions)) {
                $webhookCall->update([
                    'status' => 'processed',
                    'error_message' => null,
                ]);
                return;
            }

            // Process each parsed transaction
            $successCount = 0;
            $duplicateCount = 0;

            foreach ($parsedTransactions as $parsed) {
                // Check if transaction already exists (idempotency)
                $existing = Transaction::where('bank_provider', $parsed['bankProvider'] ?? $webhookCall->bank_provider)
                    ->where('bank_reference', $parsed['reference'])
                    ->first();

                if ($existing) {
                    $duplicateCount++;
                    continue;
                }

                // Create new transaction
                Transaction::create([
                    'wallet_id' => 1, // Default wallet per FR-06
                    'type' => 'credit',
                    'bank_reference' => $parsed['reference'],
                    'bank_provider' => $parsed['bankProvider'] ?? $webhookCall->bank_provider,
                    'amount' => $parsed['amount'],
                    'bank_transaction_time' => now(),
                    'metadata' => $parsed['metadata'] ?? [],
                ]);

                $successCount++;
            }

            // Update wallet balance for successful credits
            if ($successCount > 0) {
                $this->updateWalletBalance();
            }

            $webhookCall->update([
                'status' => 'processed',
                'error_message' => null,
            ]);

        } catch (Exception $e) {
            $webhookCall->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            // Don't re-throw - mark as failed and continue
        }
    }

    /**
     * Update wallet balance based on all credit transactions.
     */
    private function updateWalletBalance(): void
    {
        $wallet = Wallet::find(1);

        if (!$wallet) {
            return;
        }

        // Calculate total balance from all transactions
        $totalCredits = Transaction::where('wallet_id', 1)
            ->where('type', 'credit')
            ->sum('amount');

        $totalDebits = Transaction::where('wallet_id', 1)
            ->where('type', 'debit')
            ->sum('amount');

        $wallet->update([
            'balance' => $totalCredits - $totalDebits,
        ]);
    }
}
