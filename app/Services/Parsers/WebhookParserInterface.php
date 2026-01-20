<?php

namespace App\Services\Parsers;

interface WebhookParserInterface
{
    /**
     * Parse a raw webhook payload into structured transaction data.
     *
     * @param string $payload Raw webhook payload
     * @return array Array of parsed transaction data
     */
    public function parse(string $payload): array;
}
