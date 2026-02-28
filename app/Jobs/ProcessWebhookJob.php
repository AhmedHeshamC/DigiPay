<?php

namespace App\Jobs;

use App\Models\WebhookCall;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Parsers\WebhookParserFactory;
use App\Services\Notifications\NotificationDispatcher;
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
    public function handle(NotificationDispatcher $notificationDispatcher): void
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
                $transaction = Transaction::create([
                    'wallet_id' => 1, // Default wallet per FR-06
                    'type' => 'credit',
                    'bank_reference' => $parsed['reference'],
                    'bank_provider' => $parsed['bankProvider'] ?? $webhookCall->bank_provider,
                    'amount' => $parsed['amount'],
                    'bank_transaction_time' => now(),
                    'metadata' => $parsed['metadata'] ?? [],
                ]);

                // FR-10: Send notification on successful credit
                $this->sendSuccessNotification($notificationDispatcher, $transaction);

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

            // FR-10: Send notification on transaction failure
            $this->sendFailureNotification($notificationDispatcher, $webhookCall, $e);

            // Don't re-throw - mark as failed and continue
        }
    }

    /**
     * Send success notification for a credited transaction.
     * Per NFR-08: Notification failures must not affect the transaction outcome.
     */
    private function sendSuccessNotification(NotificationDispatcher $dispatcher, Transaction $transaction): void
    {
        try {
            // Get wallet owner's email for notification
            $wallet = Wallet::find($transaction->wallet_id);

            if ($wallet && $wallet->email) {
                $dispatcher->notifyTransactionSuccess(
                    $transaction,
                    'email', // Default to email channel
                    $wallet->email
                );
            }
        } catch (\Exception $e) {
            // Log but don't throw - notifications are non-blocking per NFR-08
            \Log::warning('Transaction success notification failed (non-blocking)', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send failure notification for a failed transaction.
     * Per NFR-08: Notification failures must not affect the transaction outcome.
     */
    private function sendFailureNotification(NotificationDispatcher $dispatcher, WebhookCall $webhookCall, Exception $e): void
    {
        try {
            $wallet = Wallet::find(1); // Default wallet

            if ($wallet && $wallet->email) {
                $dispatcher->notifyTransactionFailure(
                    $webhookCall->bank_reference ?? 'UNKNOWN',
                    $webhookCall->bank_provider,
                    'email',
                    $wallet->email,
                    $e->getMessage()
                );
            }
        } catch (\Exception $notificationError) {
            // Log but don't throw - notifications are non-blocking per NFR-08
            \Log::warning('Transaction failure notification failed (non-blocking)', [
                'webhook_call_id' => $webhookCall->id,
                'error' => $notificationError->getMessage(),
            ]);
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
