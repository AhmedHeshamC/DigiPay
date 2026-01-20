<?php

namespace App\Services\Parsers;

class AcmeParser implements WebhookParserInterface
{
    /**
     * Parse Acme webhook payload.
     *
     * Format: date//amount//reference[//extra//data]
     * Example: 20250615//99.50//ACME-REF-001
     *
     * @param string $payload Raw webhook payload
     * @return array Array of parsed transaction data
     */
    public function parse(string $payload): array
    {
        if (empty($payload)) {
            return [];
        }

        $transactions = [];

        // Split by newlines for multi-line payloads
        $lines = explode("\n", trim($payload));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $transaction = $this->parseLine($line);
            if ($transaction !== null) {
                $transactions[] = $transaction;
            }
        }

        return $transactions;
    }

    /**
     * Parse a single Acme line.
     *
     * @param string $line Single line from payload
     * @return array|null Parsed transaction or null if invalid
     */
    private function parseLine(string $line): ?array
    {
        // Split by //
        $parts = explode('//', $line);

        if (count($parts) < 3) {
            return null;
        }

        $date = $parts[0];
        $amount = (float) $parts[1];
        $reference = $parts[2];

        // Extra segments (if any) go into metadata
        $metadata = [];
        if (count($parts) > 3) {
            $extraSegments = array_slice($parts, 3);
            $metadata['extra'] = $extraSegments;
        }

        return [
            'date' => $date,
            'amount' => $amount,
            'reference' => $reference,
            'metadata' => $metadata,
        ];
    }
}
