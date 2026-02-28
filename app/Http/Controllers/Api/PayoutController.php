<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\XmlGeneratorService;
use App\Services\Notifications\NotificationDispatcher;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class PayoutController extends Controller
{
    public function store(
        Request $request,
        XmlGeneratorService $generator,
        NotificationDispatcher $notificationDispatcher
    ): Response {
        // Validate request
        $validator = Validator::make($request->all(), [
            'date' => 'required|string',
            'amount' => 'required|numeric',
            'currency' => 'required|string|max:3',
            'notes' => 'nullable|string',
            'paymentType' => 'nullable|integer',
            'chargeDetails' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response('Validation error', 422);
        }

        $data = $request->only(['date', 'amount', 'currency', 'notes', 'paymentType', 'chargeDetails']);

        // Generate XML
        $xml = $generator->generate($data);

        // FR-10: Send notification on successful payout generation
        $this->sendPayoutNotification($notificationDispatcher, (float) $data['amount']);

        // Return XML with proper headers
        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Send notification for successful payout generation.
     * Per NFR-08: Notification failures must not affect the transaction outcome.
     */
    private function sendPayoutNotification(NotificationDispatcher $dispatcher, float $amount): void
    {
        try {
            // Get default wallet owner for notification
            $wallet = Wallet::find(1);

            if ($wallet && $wallet->email) {
                $dispatcher->notifyPayoutGenerated(
                    payoutId: 1, // Generated payout ID (would be from model in production)
                    amount: $amount,
                    channel: 'email',
                    recipient: $wallet->email
                );
            }
        } catch (\Exception $e) {
            // Log but don't throw - notifications are non-blocking per NFR-08
            \Log::warning('Payout notification failed (non-blocking)', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
