<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\XmlGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class PayoutController extends Controller
{
    public function store(Request $request, XmlGeneratorService $generator): Response
    {
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

        // Return XML with proper headers
        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
