<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookJob;
use App\Models\WebhookCall;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class WebhookController extends Controller
{
    /**
     * Handle incoming webhook from bank providers.
     *
     * @param Request $request
     * @param string $bankName Bank provider name (paytech, acme)
     * @return Response
     */
    public function store(Request $request, string $bankName): Response
    {
        // Validate bank name
        $validator = Validator::make(['bank' => $bankName], [
            'bank' => 'required|in:paytech,acme',
        ]);

        if ($validator->fails()) {
            return response('Unknown bank provider', 422);
        }

        // Get raw payload content
        $payload = $request->getContent() ?? file_get_contents('php://input');

        // Store in webhook_calls buffer table (FR-03: Resilient Buffering)
        $webhookCall = WebhookCall::create([
            'bank_provider' => $bankName,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        // Dispatch job for async processing
        dispatch(new ProcessWebhookJob($webhookCall->id));

        // Return 202 Accepted immediately
        return response('Accepted', 202);
    }
}
